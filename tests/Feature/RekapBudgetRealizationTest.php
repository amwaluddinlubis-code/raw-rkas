<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BospRekapStandardMapper;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RekapBudgetRealizationTest extends TestCase
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
        $school = School::query()->create(['npsn' => '10260756', 'school_code' => 'SCH-REKAP', 'name' => 'SMPN 2 Ranto Baek', 'address' => 'Ranto Baek']);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Kepala Uji',
            'treasurer_name' => 'Bendahara Uji',
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

        $this->seedAprilMirror();
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

    private function seedAprilMirror(): void
    {
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'TRM-1', [
            'ID_KAS_UMUM' => 'TRM-1', 'KATEGORI_BKU' => 'PENERIMAAN', 'REK_BKU' => 'Terima Dana BOS',
            'NO_BUKTI' => 'BBU01', 'TANGGAL_TRANSAKSI' => '2026-04-07', 'URAIAN' => 'Terima Dana BOSP',
            'JUMLAH' => 10000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'BLJ-1', [
            'ID_KAS_UMUM' => 'BLJ-1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU01', 'TANGGAL_TRANSAKSI' => '2026-04-07', 'URAIAN' => 'Belanja Ekstra',
            'KODE_REKENING' => '5.1.02.01.01.0005', 'JUMLAH' => 1000000, 'ID_RAPBS' => 'RA-1',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'BLJ-2', [
            'ID_KAS_UMUM' => 'BLJ-2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU02', 'TANGGAL_TRANSAKSI' => '2026-04-08', 'URAIAN' => 'Honor Operator',
            'KODE_REKENING' => '5.1.02.02.01.0013', 'JUMLAH' => 2000000, 'ID_RAPBS' => 'RB-1',
        ]);
        // RA-1 membawa kode kegiatan langsung; RB-1 lewat tabel legacy.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'RA-1', [
            'id_rapbs' => 'RA-1', 'id_anggaran' => 'ANG-1', 'KODE_KEGIATAN' => '03.03.14',
            'kode_rekening' => '5.1.02.01.01.0005', 'uraian' => 'Belanja Ekstra', 'jumlah' => 1000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'RB-1', [
            'id_rapbs' => 'RB-1', 'id_anggaran' => 'ANG-1',
            'kode_rekening' => '5.1.02.02.01.0013', 'uraian' => 'Honor Operator', 'jumlah' => 2000000,
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RB-1',
            'activity_code' => '07.12.02.',
            'activity_name' => 'Honor Tendik',
            'account_code' => '5.1.02.02.01',
            'description' => 'Honor',
            'amount' => 2000000,
            'payload' => json_encode(['source_rapbs_id' => 'RB-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'BLJ-1',
            'transaction_date' => '2026-04-07',
            'payment_description' => 'Pembayaran Ekstra April',
            'spj_category' => 'BARANG',
        ]);
    }

    public function test_mapper_resolves_official_cells(): void
    {
        $this->assertSame(['row' => 2, 'col' => 3], BospRekapStandardMapper::cellForActivity('03.03.14'));
        $this->assertSame(['row' => 6, 'col' => 12], BospRekapStandardMapper::cellForActivity('07.12.02.'));
        $this->assertSame(['row' => 1, 'col' => 1], BospRekapStandardMapper::cellForActivity('01.01.01'));
        $this->assertNull(BospRekapStandardMapper::cellForActivity('03.17.01'));
        $this->assertNull(BospRekapStandardMapper::cellForActivity(null));
        $this->assertCount(7, BospRekapStandardMapper::standardRows());
        $this->assertCount(12, BospRekapStandardMapper::subPrograms());
    }

    public function test_rekap_grid_and_saldo_follow_official_shape(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'rekapitulasi_pengeluaran_dana_bos', 4);

        $this->assertNotNull($payload);
        $this->assertSame('rekap_bosp', $payload['presentation']);

        $rekap = $payload['rekapBosp'];
        $this->assertSame(1000000.0, $rekap['grid'][2][3]);
        $this->assertSame(2000000.0, $rekap['grid'][6][12]);
        $this->assertSame(1000000.0, $rekap['rowTotals'][2]);
        $this->assertSame(2000000.0, $rekap['rowTotals'][6]);
        $this->assertSame(1000000.0, $rekap['colTotals'][3]);
        $this->assertSame(2000000.0, $rekap['colTotals'][12]);
        $this->assertSame(3000000.0, $rekap['grand']);
        $this->assertSame(0.0, $rekap['prev']);
        $this->assertSame(10000000.0, $rekap['received']);
        $this->assertSame(3000000.0, $rekap['used']);
        $this->assertSame(7000000.0, $rekap['closing']);

        $this->assertSame('APRIL TAHUN 2026', $payload['rekapTitle']['phase']);
    }

    public function test_rekap_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/rekapitulasi_pengeluaran_dana_bos/cetak?periode_laporan=4');

        $response->assertOk();
        $response->assertSee('REKAPITULASI REALISASI PENGGUNAAN DANA BOSP', false);
        $response->assertSee('SUB PROGRAM', false);
        $response->assertSee('Standar Nasional', false);
        $response->assertSee('Saldo periode sebelumnya', false);
        $response->assertSee('Bendahara /', false);
    }

    public function test_bpk_report_aggregates_cash_mirror_into_dinas_sections(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bpk_bos', 4);

        $this->assertNotNull($payload);
        $this->assertSame('bpk_bos', $payload['presentation']);
        $this->assertSame(10000000.0, $payload['bpkData']['terimaTotal']);
        $this->assertSame(1000000.0, $payload['bpkData']['op']['persediaan']);
        $this->assertSame(2000000.0, $payload['bpkData']['op']['jasa']);
        $this->assertSame(3000000.0, $payload['bpkData']['opTotal']);
        $this->assertSame(0.0, $payload['bpkData']['modalTotal']);
        $this->assertSame(7000000.0, $payload['bpkData']['closing']);

        $response = $this->get('/laporan-periode/bulan/bpk_bos/cetak?periode_laporan=4');

        $response->assertOk();
        $response->assertSee('DINAS PENDIDIKAN DAN KEBUDAYAAN', false);
        $response->assertSee('DATA REALISASI KEUANGAN', false);
        $response->assertSee('Belanja Persediaan Barang Pakai Habis', false);
        $response->assertSee('SALDO AKHIR', false);

        $excel = $this->get('/laporan-periode/bulan/bpk_bos/excel?periode_laporan=4');
        $excel->assertOk();
        $excel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $download = tempnam(sys_get_temp_dir(), 'bpk-export-test-').'.xlsx';
        file_put_contents($download, $excel->streamedContent());
        $workbook = IOFactory::load($download);
        $this->assertSame(['Ringkasan', 'Laporan'], $workbook->getSheetNames());
        $this->assertContains('Saldo akhir', array_column($workbook->getSheetByName('Laporan')->toArray(null, true, true, true), 'A'));
        unlink($download);
    }
}
