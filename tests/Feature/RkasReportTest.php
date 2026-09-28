<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\BackgroundOperation;
use App\Models\School;
use App\Models\User;
use App\Services\RkasReportExcelService;
use App\Services\RkasReportService;
use App\Services\ArkasMirrorFreshnessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class RkasReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP Reguler']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->withViewErrors([]);

        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        // Pengesahan 1: ATK 600rb (TW2) + Buku 5jt (TW3).
        $this->seedRapbs('R-ATK', ['ID_RAPBS' => 'R-ATK', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '03.01.01.', 'KODE_REKENING' => '5.1.02.01.01.0024', 'URAIAN' => 'Alat Tulis Kantor', 'VOLUME_TOTAL' => 10, 'SATUAN' => 'paket', 'HARGA_SATUAN' => 60000, 'JUMLAH' => 600000]);
        $this->seedRapbs('R-BUKU', ['ID_RAPBS' => 'R-BUKU', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '05.02.03.', 'KODE_REKENING' => '5.2.05.01.01.0004', 'URAIAN' => 'Buku Teks', 'VOLUME_TOTAL' => 5, 'SATUAN' => 'eksemplar', 'HARGA_SATUAN' => 1000000, 'JUMLAH' => 5000000]);
        $this->seedRapbsPeriode('P-ATK-2', ['ID_RAPBS_PERIODE' => 'P-ATK-2', 'ID_RAPBS' => 'R-ATK', 'ID_PERIODE' => '84', 'JUMLAH' => 600000]);
        $this->seedRapbsPeriode('P-BUKU-3', ['ID_RAPBS_PERIODE' => 'P-BUKU-3', 'ID_RAPBS' => 'R-BUKU', 'ID_PERIODE' => '87', 'JUMLAH' => 5000000]);
        // Pengesahan 2: ATK naik menjadi 800rb (TW2).
        $this->seedRapbs('R-ATK-2', ['ID_RAPBS' => 'R-ATK-2', 'ID_ANGGARAN' => 'ANG-2', 'KODE_KEGIATAN' => '03.01.01.', 'KODE_REKENING' => '5.1.02.01.01.0024', 'URAIAN' => 'Alat Tulis Kantor', 'JUMLAH' => 800000]);
        $this->seedRapbs('R-BUKU-2', ['ID_RAPBS' => 'R-BUKU-2', 'ID_ANGGARAN' => 'ANG-2', 'KODE_KEGIATAN' => '05.02.03.', 'KODE_REKENING' => '5.2.05.01.01.0004', 'URAIAN' => 'Buku Teks', 'JUMLAH' => 5000000]);
        $this->seedRapbsPeriode('P-ATK2-2', ['ID_RAPBS_PERIODE' => 'P-ATK2-2', 'ID_RAPBS' => 'R-ATK-2', 'ID_PERIODE' => '84', 'JUMLAH' => 800000]);
        $this->seedRapbsPeriode('P-BUKU2-3', ['ID_RAPBS_PERIODE' => 'P-BUKU2-3', 'ID_RAPBS' => 'R-BUKU-2', 'ID_PERIODE' => '87', 'JUMLAH' => 5000000]);

        $school = School::query()->create(['npsn' => '10260756', 'name' => 'SMP Negeri 2 Ranto Baek', 'address' => 'Ranto Baek', 'district' => 'Kec. Ranto Baek', 'regency' => 'Kab. Mandailing Natal', 'province' => 'Prov. Sumatera Utara']);
        session()->put(['active_school_id' => $school->id, 'active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    /** @return array{school_id:int,fiscal_year_id:int,fund_source_id:int} */
    private function reportOptions(array $extra = []): array
    {
        return array_merge([
            'school_id' => (int) session('active_school_id'),
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
        ], $extra);
    }

    public function test_tahunan_uses_selected_old_revision(): void
    {
        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions(['revision' => 'ANG-1']));

        $this->assertNotNull($payload);
        $this->assertSame('Pengesahan ke-1', $payload['revision_label']);
        $this->assertSame(5600000.0, $payload['totals']['jumlah']);
        $this->assertSame(600000.0, $payload['totals']['operasi']);
        $this->assertSame(5000000.0, $payload['totals']['modal']);
        $this->assertSame(0, $payload['fund_column']);
        $this->assertSame(5600000.0, collect($payload['penerimaan'])->firstWhere('code', '4.3.1.01.')['amount']);
        $this->assertStringContainsString('31 Mei 2026', $payload['place_date']);

        // Subtotal header tidak boleh melipatgandakan item turunannya, dan
        // tidak ada baris header rekening (sesuai ARKAS).
        $byLevel = collect($payload['lines'])->groupBy('level');
        $this->assertFalse($byLevel->has('account'));
        $this->assertSame(5600000.0, $byLevel['program']->sum('jumlah'));
        $this->assertSame(5600000.0, $byLevel['subprogram']->sum('jumlah'));
        $this->assertSame(5600000.0, $byLevel['activity']->sum('jumlah'));
        $this->assertSame(5600000.0, $byLevel['item']->sum('jumlah'));
    }

    public function test_latest_revision_is_default_and_differs_from_old(): void
    {
        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions());

        $this->assertSame('Pengesahan ke-2', $payload['revision_label']);
        $this->assertSame(5800000.0, $payload['totals']['jumlah']);
    }

    public function test_triwulan_and_tahap_split_tw_amounts(): void
    {
        $payload = app(RkasReportService::class)->build('triwulan', $this->reportOptions(['revision' => 'ANG-1']));

        $this->assertSame([1 => 0.0, 2 => 600000.0, 3 => 5000000.0, 4 => 0.0], $payload['totals']['tw']);

        $tahap = app(RkasReportService::class)->build('tahap', $this->reportOptions(['revision' => 'ANG-1']));
        $this->assertSame([600000.0, 5000000.0], $tahap['totals']['tahap']);

        $quarterly = app(RkasReportService::class)->build('triwulan-bulanan', $this->reportOptions(['revision' => 'ANG-1', 'quarter' => 3]));
        $this->assertSame('Triwulan III', $quarterly['scope_label']);
        $this->assertSame([7, 8, 9], $quarterly['quarter_months']);
        $this->assertSame(5000000.0, $quarterly['totals']['scoped']);
        $this->assertStringContainsString('30 September 2026', $quarterly['place_date']);
    }

    public function test_bulanan_filters_single_month_and_rejects_invalid(): void
    {
        $payload = app(RkasReportService::class)->build('bulanan', $this->reportOptions(['revision' => 'ANG-1', 'month' => 7]));

        $this->assertNotNull($payload);
        $this->assertSame(5000000.0, $payload['totals']['scoped']);
        $this->assertStringContainsString('Juli', $payload['scope_label']);
        $this->assertStringContainsString('31 Juli 2026', $payload['place_date']);

        $this->assertNull(app(RkasReportService::class)->build('bulanan', $this->reportOptions(['month' => 0])));
    }

    public function test_excel_export_returns_readable_file(): void
    {
        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions(['revision' => 'ANG-1']));
        $path = app(RkasReportExcelService::class)->export($payload);

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.xlsx', $path);
        $this->assertFileDoesNotExist(substr($path, 0, -5));
        $this->assertGreaterThan(0, filesize($path));
        @unlink($path);
    }

    public function test_excel_export_keeps_source_text_that_looks_like_a_formula_literal(): void
    {
        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions(['revision' => 'ANG-1']));
        $formulaLikeText = '=HYPERLINK("https://example.invalid/","klik")';
        foreach ($payload['lines'] as &$line) {
            if ($line['level'] === 'item') {
                $line['name'] = $formulaLikeText;
                break;
            }
        }
        unset($line);

        $path = app(RkasReportExcelService::class)->export($payload);
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $formulaCell = null;
            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $cell = $sheet->getCell('D'.$row);
                if ($cell->getValue() === $formulaLikeText) {
                    $formulaCell = $cell;
                    break;
                }
            }

            $this->assertNotNull($formulaCell);
            $this->assertSame(DataType::TYPE_STRING, $formulaCell->getDataType());
        } finally {
            @unlink($path);
        }
    }

    public function test_excel_column_structure_matches_each_report_scope(): void
    {
        foreach ([
            'tahunan' => ['month' => null, 'last_column' => 'Q'],
            'tahap' => ['month' => null, 'last_column' => 'J'],
            'triwulan' => ['month' => null, 'last_column' => 'L'],
            'triwulan-bulanan' => ['month' => null, 'quarter' => 3, 'last_column' => 'K'],
            'bulanan' => ['month' => 7, 'last_column' => 'H'],
        ] as $scope => $expectation) {
            $options = $this->reportOptions(['revision' => 'ANG-1']);
            if ($expectation['month'] !== null) {
                $options['month'] = $expectation['month'];
            }
            if (($expectation['quarter'] ?? null) !== null) {
                $options['quarter'] = $expectation['quarter'];
            }
            $path = app(RkasReportExcelService::class)->export(
                app(RkasReportService::class)->build($scope, $options)
            );
            $sheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame($expectation['last_column'], $sheet->getHighestColumn(), $scope);
            @unlink($path);
        }
    }

    public function test_pdf_renders_for_all_scopes(): void
    {
        foreach (['tahunan', 'tahap', 'triwulan', 'triwulan-bulanan'] as $scope) {
            $payload = app(RkasReportService::class)->build($scope, $this->reportOptions(['revision' => 'ANG-1']));
            if ($scope === 'triwulan-bulanan') {
                $payload = app(RkasReportService::class)->build($scope, $this->reportOptions(['revision' => 'ANG-1', 'quarter' => 3]));
            }
            $output = Pdf::loadView('rkas-reports.pdf', $payload)->setPaper('a4', 'landscape')->output();
            $this->assertStringStartsWith('%PDF', $output, "scope {$scope}");
        }

        $payload = app(RkasReportService::class)->build('bulanan', $this->reportOptions(['revision' => 'ANG-1', 'month' => 7]));
        $output = Pdf::loadView('rkas-reports.pdf', $payload)->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF', $output, 'scope bulanan');
    }

    public function test_report_routes_stream_pdf_and_excel(): void
    {
        $this->withoutMiddleware();

        $this->get(route('rkas-reports.pdf', ['scope' => 'tahunan', 'revisi' => 'ANG-1']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('rkas-reports.excel', ['scope' => 'triwulan', 'revisi' => 'ANG-1']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_revision_comparison_page_renders_selected_snapshots(): void
    {
        $this->withoutMiddleware();

        $this->get(route('rkas-budget.revisions.compare', ['from' => 'ANG-1', 'to' => 'ANG-2']))
            ->assertOk()
            ->assertSee('Perbandingan Revisi RKAS')
            ->assertSee('Pagu revisi awal')
            ->assertSee('Pagu revisi tujuan');

        // The workspace sends the active revision as the destination only;
        // the controller should choose its predecessor as the source.
        $this->get(route('rkas-budget.revisions.compare', ['to' => 'ANG-2']))
            ->assertOk()
            ->assertSee('value="ANG-1" selected', false)
            ->assertSee('value="ANG-2" selected', false);

        $this->get(route('rkas-budget.revisions.compare', ['from' => 'ANG-1', 'to' => 'ANG-1']))
            ->assertStatus(422);
    }

    public function test_report_package_download_contains_selected_scopes_and_revision_context(): void
    {
        $this->withoutMiddleware();
        $response = $this->get(route('rkas-reports.package', [
            'scopes' => ['tahunan', 'tahap'],
            'formats' => ['pdf', 'xlsx'],
            'revisi' => 'ANG-1',
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path) === true);
        try {
            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entries[] = $archive->getNameIndex($index);
            }
            $this->assertContains('RKAS-TAHUNAN-2026-BOSP-REGULER-PENGESAHAN-1.pdf', $entries);
            $this->assertContains('RKAS-TAHAP-2026-BOSP-REGULER-PENGESAHAN-1.pdf', $entries);
            $this->assertContains('RKAS-TAHUNAN-2026-BOSP-REGULER-PENGESAHAN-1.xlsx', $entries);
            $this->assertContains('RKAS-TAHAP-2026-BOSP-REGULER-PENGESAHAN-1.xlsx', $entries);
            $this->assertStringContainsString('Pengesahan ke-1', (string) $archive->getFromName('INFO-PAKET.txt'));
        } finally {
            $archive->close();
        }
    }

    public function test_report_package_requires_a_scope_and_period_for_selected_month_reports(): void
    {
        $this->withoutMiddleware();

        $this->getJson(route('rkas-reports.package', ['formats' => ['pdf']]))
            ->assertUnprocessable();
        $this->get(route('rkas-reports.package', ['scopes' => ['bulanan'], 'formats' => ['pdf']]))
            ->assertStatus(422);
    }

    public function test_rkas_page_freshness_uses_completed_mirror_runs_and_reports_counts(): void
    {
        $schoolId = (int) session('active_school_id');
        BackgroundOperation::query()->create([
            'school_id' => $schoolId,
            'fiscal_year_id' => 1,
            'type' => 'ARKAS_FIXED_MIRROR_REFS',
            'status' => 'COMPLETED',
            'result' => ['read' => 40, 'written' => 12],
            'finished_at' => now(),
        ]);
        BackgroundOperation::query()->create([
            'school_id' => $schoolId,
            'fiscal_year_id' => 1,
            'type' => 'ARKAS_FIXED_MIRROR_SCHOOL',
            'status' => 'COMPLETED',
            'result' => ['read' => 100, 'written' => 25],
            'finished_at' => now(),
        ]);

        $freshness = app(ArkasMirrorFreshnessService::class)->summarize($schoolId, 1);

        $this->assertSame('fresh', $freshness['level']);
        $this->assertSame(40, $freshness['lanes']['referensi']['records_read']);
        $this->assertSame(25, $freshness['lanes']['sekolah']['records_written']);
        $this->assertGreaterThan(0, $freshness['counts']['RKAS']);
    }

    public function test_rkas_page_freshness_flags_an_old_school_mirror_run(): void
    {
        $schoolId = (int) session('active_school_id');
        foreach ([
            ['type' => 'ARKAS_FIXED_MIRROR_REFS', 'finished_at' => now()],
            ['type' => 'ARKAS_FIXED_MIRROR_SCHOOL', 'finished_at' => now()->subHours(25)],
        ] as $run) {
            BackgroundOperation::query()->create([
                'school_id' => $schoolId,
                'fiscal_year_id' => 1,
                'type' => $run['type'],
                'status' => 'COMPLETED',
                'result' => ['read' => 10, 'written' => 10],
                'finished_at' => $run['finished_at'],
            ]);
        }

        $freshness = app(ArkasMirrorFreshnessService::class)->summarize($schoolId, 1);

        $this->assertSame('stale', $freshness['level']);
        $this->assertSame('stale', $freshness['lanes']['sekolah']['level']);
    }

    public function test_rkas_page_freshness_flags_a_missing_required_mirror_table(): void
    {
        DB::connection('school')->getSchemaBuilder()->drop('arkas_mirror_rapbs_periode');
        $schoolId = (int) session('active_school_id');
        foreach (['ARKAS_FIXED_MIRROR_REFS', 'ARKAS_FIXED_MIRROR_SCHOOL'] as $type) {
            BackgroundOperation::query()->create([
                'school_id' => $schoolId,
                'fiscal_year_id' => 1,
                'type' => $type,
                'status' => 'COMPLETED',
                'result' => ['read' => 10, 'written' => 10],
                'finished_at' => now(),
            ]);
        }

        $freshness = app(ArkasMirrorFreshnessService::class)->summarize($schoolId, 1);

        $this->assertSame('incomplete', $freshness['level']);
        $this->assertContains('Periode RKAS', $freshness['missing']);
    }

    public function test_report_preview_uses_html_report_view_and_committee_profile(): void
    {
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => 1,
            'committee_name' => 'Siti Komite',
            'principal_name' => 'Budi Kepala',
            'treasurer_name' => 'Ani Bendahara',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions(['revision' => 'ANG-1']));

        $this->assertSame('Siti Komite', $payload['signatories']['committee_name']);
        $this->assertSame('A. PENERIMAAN', $payload['penerimaan_heading']);

        $this->withoutMiddleware();
        $this->get(route('rkas-reports.preview', ['scope' => 'tahunan', 'revisi' => 'ANG-1']))
            ->assertOk()
            ->assertSee('KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH');
    }

    public function test_report_routes_reject_unknown_scope_and_missing_month(): void
    {
        $this->withoutMiddleware();

        $this->get(route('rkas-reports.pdf', ['scope' => 'semester']))->assertNotFound();
        $this->get(route('rkas-reports.pdf', ['scope' => 'bulanan']))->assertStatus(422);
    }

    private function seedAnggaran(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_anggaran')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedRapbs(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedRapbsPeriode(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs_periode')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
