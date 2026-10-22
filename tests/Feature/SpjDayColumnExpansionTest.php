<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjMaintenance;
use App\Models\SpjPackage;
use App\Services\PreviewAlignedSpjTemplateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjDayColumnExpansionTest extends TestCase
{
    use RefreshDatabase, SeedsArkasMirror;

    private School $school;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['code' => 'BOSP', 'name' => 'BOSP']);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Sekolah Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->school = School::query()->create([
            'npsn' => '10208246',
            'school_code' => 'SCH-DAY',
            'name' => 'SDN Uji Kolom Hari',
            'address' => 'Jl. Uji No. 3',
        ]);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        DB::purge('school');
        parent::tearDown();
    }

    public function test_six_day_period_expands_into_six_date_columns(): void
    {
        $package = $this->maintenancePackage('2026-09-07', '2026-09-12', [6, 5]);
        $rendered = $this->render($package);

        try {
            $sheet = $rendered->getSheet(0);

            foreach (['E' => '7', 'F' => '8', 'G' => '9', 'H' => '10', 'I' => '11', 'J' => '12'] as $column => $date) {
                $this->assertSame($date, (string) $sheet->getCell($column.'14')->getValue(), "Tanggal kolom {$column}");
            }
            $this->assertSame(Carbon::parse('2026-09-07')->translatedFormat('D'), (string) $sheet->getCell('E15')->getValue());
            $this->assertSame(Carbon::parse('2026-09-12')->translatedFormat('D'), (string) $sheet->getCell('J15')->getValue());
            $this->assertContains('E13:J13', $sheet->getMergeCells());

            // Kolom sesudah grid bergeser: JUMLAH HARI kini di K, TANDA TANGAN di L.
            $this->assertSame('JUMLAH HARI', (string) $sheet->getCell('K13')->getValue());
            $this->assertSame('1', (string) $sheet->getCell('B16')->getValue());
            $this->assertSame('Pekerja Satu', (string) $sheet->getCell('C16')->getValue());
            $this->assertSame('6', (string) $sheet->getCell('K16')->getValue());
            $this->assertSame('2', (string) $sheet->getCell('B17')->getValue());
            $this->assertSame('Pekerja Dua', (string) $sheet->getCell('C17')->getValue());
            $this->assertSame('5', (string) $sheet->getCell('K17')->getValue());

            // Sel paraf pada kolom hasil ekspansi ikut bergaya (border) dan terkopi ke baris pekerja kedua.
            $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('H16')->getBorders()->getTop()->getBorderStyle());
            $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('H17')->getBorders()->getTop()->getBorderStyle());

            // Area cetak melebar mengikuti kolom baru (kolom akhir L);
            // baris akhir ikut bergeser karena baris pekerja kedua disisipkan.
            $this->assertStringStartsWith('A1:L', (string) $sheet->getPageSetup()->getPrintArea());
        } finally {
            $rendered->disconnectWorksheets();
        }
    }

    public function test_single_day_period_keeps_single_date_column(): void
    {
        $package = $this->maintenancePackage('2026-09-07', '2026-09-07', [1]);
        $rendered = $this->render($package);

        try {
            $sheet = $rendered->getSheet(0);

            $this->assertSame('7', (string) $sheet->getCell('E14')->getValue());
            $this->assertSame(Carbon::parse('2026-09-07')->translatedFormat('D'), (string) $sheet->getCell('E15')->getValue());
            $this->assertSame('JUMLAH HARI', (string) $sheet->getCell('F13')->getValue());
            $this->assertSame('1', (string) $sheet->getCell('F16')->getValue());
            $this->assertNotContains('E13:J13', $sheet->getMergeCells());
            $this->assertSame('A1:G25', $sheet->getPageSetup()->getPrintArea());
        } finally {
            $rendered->disconnectWorksheets();
        }
    }

    public function test_missing_work_dates_clear_day_markers_without_expansion(): void
    {
        $package = $this->maintenancePackage(null, null, [3]);
        $rendered = $this->render($package);

        try {
            $sheet = $rendered->getSheet(0);

            $this->assertSame('', (string) $sheet->getCell('E14')->getValue());
            $this->assertSame('', (string) $sheet->getCell('E15')->getValue());
            $this->assertSame('JUMLAH HARI', (string) $sheet->getCell('F13')->getValue());
            $this->assertSame('Pekerja Satu', (string) $sheet->getCell('C16')->getValue());
            $this->assertSame('3', (string) $sheet->getCell('F16')->getValue());
        } finally {
            $rendered->disconnectWorksheets();
        }
    }

    /** @param array<int, int> $workerDays */
    private function maintenancePackage(?string $startedAt, ?string $completedAt, array $workerDays): SpjPackage
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => hash('crc32b', 'DAY-'.($startedAt ?? 'none').'-'.count($workerDays)),
            'no_bukti' => 'BPU-DAY-'.($startedAt ?? 'NONE'),
            'transaction_date' => '2026-09-15',
            'rkas_date' => '2026-01-01',
            'description' => 'Pemeliharaan uji kolom hari',
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Uji',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Uji',
            'recipient_name' => 'Penerima BKU Uji',
            'gross_amount' => 100000,
            'tax_total' => 0,
            'net_amount' => 100000,
            'spj_category' => 'PEMELIHARAAN',
            'payment_method' => 'tunai',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
            'source_key' => hash('sha256', 'day-columns-'.($startedAt ?? 'none').'-'.count($workerDays)),
        ]);
        $this->mirrorItem($transaction, [
            'source_item_id' => 'ITEM-DAY-'.($startedAt ?? 'none'),
            'description' => 'Upah pemeliharaan',
            'item_description' => 'Upah pemeliharaan',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);
        $workOrder = $transaction->workOrder()->create([
            'maintenance_id' => SpjMaintenance::query()->create([
                'fiscal_year_id' => $this->year->id,
                'name' => 'Pemeliharaan uji kolom hari',
            ])->id,
            'spk_date' => '2026-09-01',
            'rab_date' => '2026-08-30',
            'expense_type' => 'Belanja Pemeliharaan',
            'work_description' => 'Perbaikan atap uji',
            'work_location' => 'Sekolah uji',
            'work_started_at' => $startedAt,
            'work_completed_at' => $completedAt,
        ]);
        foreach ($workerDays as $index => $days) {
            $workOrder->workers()->create([
                'name' => 'Pekerja '.($index === 0 ? 'Satu' : 'Dua'),
                'job_description' => 'Tukang',
                'work_days' => $days,
                'daily_rate' => 100000,
            ]);
        }
        $package = $transaction->spjPackage()->create([
            'document_number' => 'SPJ-DAY-'.($startedAt ?? 'NONE'),
            'quarter_code' => 'TW3',
            'semester_code' => 'S2',
            'status' => 'READY',
        ]);
        $package->setRelation('transaction', $transaction->fresh());

        return $package;
    }

    private function render(SpjPackage $package): Spreadsheet
    {
        $service = new class extends PreviewAlignedSpjTemplateService
        {
            public function renderCanonical(DocumentTemplate $template, SpjPackage $package, School $school): Spreadsheet
            {
                return $this->canonicalSpreadsheet($template, $package, $school);
            }
        };

        return $service->renderCanonical($this->createAttendanceTemplate(), $package, $this->school);
    }

    private function createAttendanceTemplate(): DocumentTemplate
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TPL_DAFTAR_HADIR_PEMELIHARAAN');
        $thin = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']];
        $bordered = ['borders' => ['allBorders' => $thin]];

        $sheet->setCellValue('B13', 'NO');
        $sheet->setCellValue('C13', 'NAMA PEKERJA');
        $sheet->setCellValue('D13', 'PEKERJAAN / TUGAS');
        $sheet->setCellValue('E13', 'TANGGAL (PARAF KEHADIRAN)');
        $sheet->setCellValue('F13', 'JUMLAH HARI');
        $sheet->setCellValue('G13', 'TANDA TANGAN');
        $sheet->setCellValue('E14', '{{HARI_TANGGAL}}');
        $sheet->setCellValue('E15', '{{HARI_NAMA}}');
        $sheet->getStyle('B13:G15')->applyFromArray($bordered);
        foreach (['B', 'C', 'D', 'F', 'G'] as $column) {
            $sheet->mergeCells($column.'13:'.$column.'15');
        }
        $sheet->setCellValue('B16', '{{UPAH_NO}}');
        $sheet->setCellValue('C16', '{{UPAH_NAMA}}');
        $sheet->setCellValue('D16', '{{UPAH_PEKERJAAN}}');
        $sheet->setCellValue('F16', '{{UPAH_HARI}}');
        $sheet->setCellValue('G16', ' {{UPAH_NO}}.');
        $sheet->getStyle('B16:G16')->applyFromArray($bordered);
        $sheet->getColumnDimension('E')->setWidth(4.1);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getPageSetup()->setPrintArea('A1:G25');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/day-column-attendance.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return (new DocumentTemplate)->forceFill([
            'document_type' => 'DAFTAR_HADIR_PEMELIHARAAN',
            'format' => 'xlsx',
            'file_path' => $relativePath,
        ]);
    }
}
