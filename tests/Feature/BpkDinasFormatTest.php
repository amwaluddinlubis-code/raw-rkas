<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BpkDinasFormatTest extends TestCase
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

        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $school = School::query()->create(['npsn' => '10208183', 'school_code' => 'SCH-BPK', 'name' => 'SD Negeri 318 Bangun Saroha', 'address' => 'Jl. Tes', 'district' => 'Ranto Baek', 'regency' => 'Mandailing Natal', 'province' => 'Sumatera Utara']);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Wilda, S.Pd.',
            'principal_nip' => '198111072005022004',
            'treasurer_name' => 'Ali Sakti, S.Pd.',
            'treasurer_nip' => '197012032012121001',
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

    private function seedMirrorRow(string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::connection('school')->table('arkas_mirror_kas_umum')->insert([
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
        $this->seedMirrorRow('SALDO-B', ['ID_KAS_UMUM' => 'SALDO-B', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Bank', 'TANGGAL_TRANSAKSI' => '2026-01-01', 'URAIAN' => 'Saldo Bank Des 2025', 'JUMLAH' => 1000000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('SALDO-T', ['ID_KAS_UMUM' => 'SALDO-T', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Tunai', 'TANGGAL_TRANSAKSI' => '2026-01-01', 'URAIAN' => 'Saldo Tunai Des 2025', 'JUMLAH' => 500000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('TRM-1', ['ID_KAS_UMUM' => 'TRM-1', 'KATEGORI_BKU' => 'PENERIMAAN', 'REK_BKU' => 'Terima Dana BOS', 'NO_BUKTI' => 'BBU01', 'TANGGAL_TRANSAKSI' => '2026-02-21', 'URAIAN' => 'Terima Dana BOSP', 'JUMLAH' => 58050000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BLJ-1', ['ID_KAS_UMUM' => 'BLJ-1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU01', 'TANGGAL_TRANSAKSI' => '2026-03-06', 'URAIAN' => 'Belanja ATK', 'KODE_REKENING' => '5.1.02.01.01.0024', 'JUMLAH' => 2000000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BLJ-2', ['ID_KAS_UMUM' => 'BLJ-2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU02', 'TANGGAL_TRANSAKSI' => '2026-03-06', 'URAIAN' => 'Honor', 'KODE_REKENING' => '5.1.02.02.01.0031', 'JUMLAH' => 4500000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BLJ-3', ['ID_KAS_UMUM' => 'BLJ-3', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar Non Tunai', 'NO_BUKTI' => 'BPU03', 'TANGGAL_TRANSAKSI' => '2026-03-07', 'URAIAN' => 'Kursi', 'KODE_REKENING' => '5.2.02.05.02.0001', 'JUMLAH' => 3200000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('SLD-NEXT-B', ['ID_KAS_UMUM' => 'SLD-NEXT-B', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Bank', 'TANGGAL_TRANSAKSI' => '2026-04-01', 'URAIAN' => 'Saldo Bank Mar 2026', 'JUMLAH' => 48000000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('SLD-NEXT-T', ['ID_KAS_UMUM' => 'SLD-NEXT-T', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Tunai', 'TANGGAL_TRANSAKSI' => '2026-04-01', 'URAIAN' => 'Saldo Tunai Mar 2026', 'JUMLAH' => 800000, 'ID_REF_SUMBER_DANA' => 1]);
    }

    public function test_bpk_dinas_sections_follow_official_arithmetic(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'bpk_bos', 1);

        $this->assertNotNull($payload);
        $this->assertSame('bpk_bos', $payload['presentation']);

        $data = $payload['bpkData'];
        $this->assertSame(1000000.0, $data['openingBank']);
        $this->assertSame(500000.0, $data['openingCash']);
        $this->assertSame('01-01-2026', $data['openingDate']);
        $this->assertSame(58050000.0, $data['terimaTotal']);
        $this->assertSame(58050000.0, $data['terimaReguler']);
        $this->assertSame(2000000.0, $data['op']['persediaan']);
        $this->assertSame(4500000.0, $data['op']['jasa']);
        $this->assertSame(3200000.0, $data['modal']['B']);
        $this->assertSame(6500000.0, $data['opTotal']);
        $this->assertSame(3200000.0, $data['modalTotal']);
        $this->assertSame(58050000.0, $data['masukTotal']);
        $this->assertSame(9700000.0, $data['keluarTotal']);
        $this->assertSame(49850000.0, $data['closing']);
        $this->assertSame(48000000.0, $data['closingBank']);
        $this->assertSame(800000.0, $data['closingCash']);
        $this->assertSame('REKAP REALISASI PENGGUNAAN DANA BOS TRIWULAN I TAHUN 2026', $payload['bpkTitle']['title']);
    }

    public function test_bpk_print_route_renders_dinas_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/bpk_bos/cetak?periode_laporan=1');

        $response->assertOk();
        $response->assertSee('DINAS PENDIDIKAN DAN KEBUDAYAAN', false);
        $response->assertSee('DATA REALISASI KEUANGAN', false);
        $response->assertSee('Belanja Persediaan Barang Pakai Habis', false);
        $response->assertSee('KIB B (PERALATAN DAN MESIN)', false);
        $response->assertSee('Dibuat Oleh', false);
        $response->assertSee('pengembalian sisa uang', false);
    }
}
