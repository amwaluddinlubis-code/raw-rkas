<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BospRekapStandardMapper;
use App\Services\SpjPeriodicReportExcelService;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BosA1ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP Reguler']);
        $year = FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $school = School::query()->create([
            'npsn' => '10260756',
            'school_code' => 'SCH-A1',
            'name' => 'SMPN 2 Ranto Baek',
            'address' => 'Ranto Baek',
            'desa' => 'Muara Bangko',
            'district' => 'Ranto Baek',
            'regency' => 'Mandailing Natal',
            'province' => 'Sumatera Utara',
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Kepala Uji',
            'principal_nip' => '197901222008011002',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '199303052019032002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN', 'school_id' => $school->id]));
        $this->withoutMiddleware();
        session()->put([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        $this->seedQuarterMirror();
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedMirrorRow(string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::connection('school')->table($table)->insert([
            'source_key' => $key,
            'payload' => $json,
            'source_hash' => hash('sha256', $json),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedQuarterMirror(): void
    {
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'A1-B1', [
            'ID_KAS_UMUM' => 'A1-B1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU01', 'TANGGAL_TRANSAKSI' => '2026-01-15', 'URAIAN' => 'Spanduk',
            'KODE_REKENING' => '5.1.02.01.01.0026', 'JUMLAH' => 1000000, 'ID_RAPBS' => 'A1-RA',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'A1-B2', [
            'ID_KAS_UMUM' => 'A1-B2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU02', 'TANGGAL_TRANSAKSI' => '2026-02-10', 'URAIAN' => 'Honor Operator',
            'KODE_REKENING' => '5.1.02.02.01.0013', 'JUMLAH' => 2000000, 'ID_RAPBS' => 'A1-RB',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'A1-B3', [
            'ID_KAS_UMUM' => 'A1-B3', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU03', 'TANGGAL_TRANSAKSI' => '2026-03-05', 'URAIAN' => 'Belanja tak terkode',
            'KODE_REKENING' => '5.1.02.01.01.0005', 'JUMLAH' => 500000, 'ID_RAPBS' => 'A1-RC',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'A1-B4', [
            'ID_KAS_UMUM' => 'A1-B4', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU04', 'TANGGAL_TRANSAKSI' => '2026-04-10', 'URAIAN' => 'Belanja April',
            'KODE_REKENING' => '5.1.02.01.01.0005', 'JUMLAH' => 700000, 'ID_RAPBS' => 'A1-RA',
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'A1-RA', [
            'id_rapbs' => 'A1-RA', 'id_anggaran' => 'ANG-1', 'KODE_KEGIATAN' => '03.03.21',
            'kode_rekening' => '5.1.02.01.01.0026', 'uraian' => 'Spanduk', 'jumlah' => 1000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'A1-RB', [
            'id_rapbs' => 'A1-RB', 'id_anggaran' => 'ANG-1', 'KODE_KEGIATAN' => '07.12.02',
            'kode_rekening' => '5.1.02.02.01.0013', 'uraian' => 'Honor Operator', 'jumlah' => 2000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'A1-RC', [
            'id_rapbs' => 'A1-RC', 'id_anggaran' => 'ANG-1', 'KODE_KEGIATAN' => '09.99',
            'kode_rekening' => '5.1.02.01.01.0005', 'uraian' => 'Tak terkode', 'jumlah' => 500000,
        ]);

        foreach (['A1-B1', 'A1-B2', 'A1-B3', 'A1-B4'] as $kasId) {
            $transaction = Transaction::query()->create([
                'fiscal_year_id' => 1,
                'fund_source_id' => 1,
                'id_kas_umum' => $kasId,
            ]);
            $transaction->items()->create([
                'source_item_id' => $kasId,
                'item_description' => 'Item '.$kasId,
            ]);
        }
    }

    public function test_mapper_follows_official_a1_taxonomy(): void
    {
        $this->assertCount(8, BospRekapStandardMapper::a1Programs());
        $this->assertSame(3, BospRekapStandardMapper::a1RowForActivity('03.03.21.'));
        $this->assertSame(7, BospRekapStandardMapper::a1RowForActivity('07.12.02'));
        $this->assertNull(BospRekapStandardMapper::a1RowForActivity('09.99'));
        $this->assertNull(BospRekapStandardMapper::a1RowForActivity(null));
        $this->assertTrue(BospRekapStandardMapper::a1IsHonor('07.12.04'));
        $this->assertFalse(BospRekapStandardMapper::a1IsHonor('05.08.04'));
        $this->assertFalse(BospRekapStandardMapper::a1IsHonor(null));
    }

    public function test_bos_a1_groups_by_program_and_splits_pegawai(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'bos_a1', 1);

        $this->assertSame('bos_a1', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);

        $a1 = $payload['bosA1'];
        $this->assertSame(0.0, $a1['rows'][3]['pegawai']);
        $this->assertSame(1000000.0, $a1['rows'][3]['barang_jasa']);
        $this->assertSame(1000000.0, $a1['rows'][3]['total']);
        $this->assertSame(2000000.0, $a1['rows'][7]['pegawai']);
        $this->assertSame(0.0, $a1['rows'][7]['barang_jasa']);
        $this->assertSame(2000000.0, $a1['rows'][7]['total']);
        $this->assertSame(2000000.0, $a1['totals']['pegawai']);
        $this->assertSame(1000000.0, $a1['totals']['barang_jasa']);
        $this->assertSame(3000000.0, $a1['totals']['grand']);
        $this->assertSame(500000.0, $a1['unmapped']);
        $this->assertSame(1, $a1['unmappedCount']);

        $this->assertSame('01 JANUARI - 31 MARET 2026', $payload['bosA1Title']['range']);
    }

    public function test_bos_a1_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/bos_a1/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('REKAPITULASI REALISASI PENGGUNAAN DANA BOS REGULER', $html);
        $this->assertStringContainsString('Format BOS A-1', $html);
        $this->assertStringContainsString('BELANJA PEGAWAI', $html);
        $this->assertStringContainsString('ASET TETAP LAINNYA BOS', $html);
        $this->assertStringContainsString('Pengembangan Standar Proses', $html);
        $this->assertStringContainsString('Pemegang Kas Sekolah', $html);
        $this->assertStringContainsString('Kepala Uji', $html);
        $this->assertStringContainsString('Bendahara Uji', $html);
        $this->assertStringContainsString('Rp -', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_bos_a1_excel_matches_grid(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'bos_a1', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('No', $sheet->getCell('A1')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('B10')->getValue());
        $this->assertEquals(1000000, $sheet->getCell('D4')->getValue());
        $this->assertEquals(2000000, $sheet->getCell('C8')->getValue());
        $this->assertEquals(3000000, $sheet->getCell('H10')->getValue());
        $book->disconnectWorksheets();
    }
}
