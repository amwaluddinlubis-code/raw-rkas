<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjPeriodicReportExcelService;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class K7bK7cReportTest extends TestCase
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
            'npsn' => '10208183',
            'school_code' => 'SCH-K7',
            'name' => 'SDN 318 Bangun Saroha',
            'address' => 'Bangun Saroha',
            'desa' => 'Bangun Saroha',
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

        $this->seedJanuaryMirror();
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

    private function seedJanuaryMirror(): void
    {
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-SAB', [
            'ID_KAS_UMUM' => 'K7-SAB', 'KATEGORI_BKU' => 'SALDO', 'REK_BKU' => 'Saldo Awal Bank',
            'NO_BUKTI' => '', 'TANGGAL_TRANSAKSI' => '2026-01-01', 'URAIAN' => 'Saldo awal bank',
            'KODE_REKENING' => '', 'JUMLAH' => 9000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-SAT', [
            'ID_KAS_UMUM' => 'K7-SAT', 'KATEGORI_BKU' => 'SALDO', 'REK_BKU' => 'Saldo Awal Tunai',
            'NO_BUKTI' => '', 'TANGGAL_TRANSAKSI' => '2026-01-01', 'URAIAN' => 'Saldo awal tunai',
            'KODE_REKENING' => '', 'JUMLAH' => 500000,
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-T1', [
            'ID_KAS_UMUM' => 'K7-T1', 'KATEGORI_BKU' => 'PENERIMAAN', 'REK_BKU' => 'Terima Dana BOS',
            'NO_BUKTI' => 'TRS01', 'TANGGAL_TRANSAKSI' => '2026-01-05', 'URAIAN' => 'Terima BOS',
            'KODE_REKENING' => '', 'JUMLAH' => 5000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-K1', [
            'ID_KAS_UMUM' => 'K7-K1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU01', 'TANGGAL_TRANSAKSI' => '2026-01-10', 'URAIAN' => 'Belanja tunai',
            'KODE_REKENING' => '5.1.02.01.01.0026', 'JUMLAH' => 400000,
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-K2', [
            'ID_KAS_UMUM' => 'K7-K2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar Non Tunai',
            'NO_BUKTI' => 'BPU02', 'TANGGAL_TRANSAKSI' => '2026-01-12', 'URAIAN' => 'Belanja transfer',
            'KODE_REKENING' => '5.1.02.01.01.0026', 'JUMLAH' => 1000000,
        ]);

        foreach (['K7-SAB', 'K7-SAT', 'K7-T1', 'K7-K1', 'K7-K2'] as $kasId) {
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

    public function test_k7b_computes_official_totals_from_ledger(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'k7b', 1);

        $this->assertSame('k7b', $payload['presentation']);
        $this->assertSame('portrait', $payload['orientation']);
        $this->assertSame('a4', $payload['paper']);

        $k7b = $payload['k7b'];
        $this->assertSame(14500000.0, $k7b['total_in']);
        $this->assertSame(1400000.0, $k7b['total_out']);
        $this->assertSame(13100000.0, $k7b['book']);
        $this->assertSame(13000000.0, $k7b['bank']);
        $this->assertSame(100000.0, $k7b['cash']);
        $this->assertSame(0.0, $k7b['diff']);
        $this->assertCount(7, $k7b['paper']);
        $this->assertCount(4, $k7b['coins']);
    }

    public function test_k7c_follows_k7b_balances(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'k7c', 1);

        $this->assertSame('k7c', $payload['presentation']);

        $k7c = $payload['k7c'];
        $this->assertSame(100000.0, $k7c['cash']);
        $this->assertSame(13000000.0, $k7c['bank']);
        $this->assertSame(13100000.0, $k7c['total']);
        $this->assertSame(13100000.0, $k7c['book']);
        $this->assertSame(0.0, $k7c['diff']);
    }

    public function test_k7b_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/k7b/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('REGISTER PENUTUPAN KAS', $html);
        $this->assertStringContainsString('Formulir BOS-K7B', $html);
        $this->assertStringContainsString('Tanggal Penutupan Kas', $html);
        $this->assertStringContainsString('Nama Penutup Kas (Pemegang Kas)', $html);
        $this->assertStringContainsString('Jumlah Total Penerimaan (D)', $html);
        $this->assertStringContainsString('Saldo Buku (A = D - K)', $html);
        $this->assertStringContainsString('Lembaran uang kertas Rp 100.000', $html);
        $this->assertStringContainsString('Keping uang logam Rp 1.000', $html);
        $this->assertStringContainsString('Perbedaan (A-B)', $html);
        $this->assertStringContainsString('Yang diperiksa', $html);
        $this->assertStringContainsString('Yang Memeriksa, Kepala Sekolah', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_k7c_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/k7c/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('BERITA ACARA PEMERIKSAAN KAS', $html);
        $this->assertStringContainsString('Formulir BOS-K7C', $html);
        $this->assertStringContainsString('melakukan pemeriksaan kas kepada', $html);
        $this->assertStringContainsString('Uang kertas bank, uang logam', $html);
        $this->assertStringContainsString('Saldo uang menurut Buku Kas Umum', $html);
        $this->assertStringContainsString('Perbedaan antara saldo kas dan saldo buku', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_k7_excel_matches_totals(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'k7b', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertStringContainsString('K7B', (string) $sheet->getCell('A1')->getValue());
        $this->assertEquals(14500000, $sheet->getCell('B5')->getValue());
        $this->assertEquals(1400000, $sheet->getCell('B6')->getValue());
        $book->disconnectWorksheets();

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'k7c', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertStringContainsString('K7C', (string) $sheet->getCell('A1')->getValue());
        $this->assertEquals(13100000, $sheet->getCell('B7')->getValue());
        $book->disconnectWorksheets();
    }
}
