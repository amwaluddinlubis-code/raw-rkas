<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanBhpBmaTest extends TestCase
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
        $school = School::query()->create(['npsn' => '10208246', 'school_code' => 'SCH-BHP', 'name' => 'SD NEGERI 316 RANTO PANJANG', 'address' => 'Jl. Tes', 'desa' => 'Rantopanjang', 'district' => 'Ranto Baek', 'regency' => 'Kab. Mandailing Natal', 'province' => 'Prov. Sumatera Utara']);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Ubaidah Nasution, S.Pd.',
            'principal_nip' => '197002012000032007',
            'treasurer_name' => 'Tarmini, S.Pd.',
            'treasurer_nip' => '198603312022212019',
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

        $this->seedMirror();
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

    private function seedMirror(): void
    {
        // Rantai kegiatan untuk BHP-1/BHP-2: kas PER-1 -> rapbs RAPBS-1 ->
        // snapshot RKAS membawa kode kegiatan 03.03.14; periode membawa
        // satuan barang.
        $periodeJson = json_encode(['id_rapbs' => 'RAPBS-1', 'satuan' => 'Buah'], JSON_THROW_ON_ERROR);
        DB::connection('school')->table('arkas_mirror_rapbs_periode')->insert([
            'source_key' => 'PER-1',
            'payload' => $periodeJson,
            'source_hash' => hash('sha256', $periodeJson),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-1',
            'activity_code' => '03.03.14.',
            'activity_name' => 'Pengembangan Konten Digital',
            'account_code' => '5.1.02.01.01.0024',
            'description' => 'Belanja bahan habis pakai',
            'amount' => 2710000,
            'payload' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedMirrorRow('BHP-1', ['ID_KAS_UMUM' => 'BHP-1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU09', 'TANGGAL_TRANSAKSI' => '2026-03-07', 'URAIAN' => 'Buku Ramadhan', 'KODE_REKENING' => '5.1.02.01.01.0024', 'VOLUME' => 118, 'JUMLAH' => 2360000, 'ID_RAPBS_PERIODE' => 'PER-1', 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BHP-2', ['ID_KAS_UMUM' => 'BHP-2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU12', 'TANGGAL_TRANSAKSI' => '2026-03-07', 'URAIAN' => 'Kertas HVS A4', 'KODE_REKENING' => '5.1.02.01.01.0024', 'VOLUME' => 7, 'JUMLAH' => 350000, 'ID_RAPBS_PERIODE' => 'PER-1', 'ID_REF_SUMBER_DANA' => 1]);
        // Konsumsi (makanan/minuman 0052/0053/0055/0058): satu kelompok
        // rekening dengan BHP tetapi bukan barang persediaan — tidak boleh tampil.
        $this->seedMirrorRow('BHP-3', ['ID_KAS_UMUM' => 'BHP-3', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU13', 'TANGGAL_TRANSAKSI' => '2026-03-08', 'URAIAN' => 'Konsumsi Rapat', 'KODE_REKENING' => '5.1.02.01.01.0052', 'VOLUME' => 10, 'JUMLAH' => 500000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('KONSUMSI-2', ['ID_KAS_UMUM' => 'KONSUMSI-2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU14', 'TANGGAL_TRANSAKSI' => '2026-03-08', 'URAIAN' => 'Jamuan Tamu', 'KODE_REKENING' => '5.1.02.01.01.0053', 'VOLUME' => 10, 'JUMLAH' => 300000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('KONSUMSI-3', ['ID_KAS_UMUM' => 'KONSUMSI-3', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU17', 'TANGGAL_TRANSAKSI' => '2026-03-09', 'URAIAN' => 'Konsumsi Kegiatan Lapangan', 'KODE_REKENING' => '5.1.02.01.01.0058', 'VOLUME' => 20, 'JUMLAH' => 400000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('KONSUMSI-4', ['ID_KAS_UMUM' => 'KONSUMSI-4', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU18', 'TANGGAL_TRANSAKSI' => '2026-03-10', 'URAIAN' => 'Nasi Box Lomba Siswa', 'KODE_REKENING' => '5.1.02.01.01.0055', 'VOLUME' => 20, 'JUMLAH' => 500000, 'ID_REF_SUMBER_DANA' => 1]);
        // Anak pajak dari BHP-1: bukan barang, tidak boleh tampil.
        $this->seedMirrorRow('BHP-TAX', ['ID_KAS_UMUM' => 'BHP-TAX', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU09', 'TANGGAL_TRANSAKSI' => '2026-03-07', 'URAIAN' => 'PPh 21 atas Buku Ramadhan', 'KODE_REKENING' => '5.1.02.01.01.0024', 'JUMLAH' => 50000, 'PARENT_ID_KAS_UMUM' => 'BHP-1', 'IS_PPH21' => 1, 'ID_REF_SUMBER_DANA' => 1]);
        // Belanja jasa: bukan BHP maupun BMA.
        $this->seedMirrorRow('JASA-1', ['ID_KAS_UMUM' => 'JASA-1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU20', 'TANGGAL_TRANSAKSI' => '2026-03-09', 'URAIAN' => 'Jasa Narasumber', 'KODE_REKENING' => '5.1.02.02.01.0003', 'JUMLAH' => 1000000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BMA-1', ['ID_KAS_UMUM' => 'BMA-1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar Non Tunai', 'NO_BUKTI' => 'BPU15', 'TANGGAL_TRANSAKSI' => '2026-03-07', 'URAIAN' => 'Buku Al-Hira Kelas IV', 'KODE_REKENING' => '5.2.05.01.01.0001', 'VOLUME' => 46, 'JUMLAH' => 2070000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BMA-2', ['ID_KAS_UMUM' => 'BMA-2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar Non Tunai', 'NO_BUKTI' => 'BPU16', 'TANGGAL_TRANSAKSI' => '2026-06-11', 'URAIAN' => 'Laptop Pembelajaran', 'KODE_REKENING' => '5.2.02.19.01.0001', 'VOLUME' => 2, 'JUMLAH' => 13000000, 'ID_REF_SUMBER_DANA' => 1]);
        $this->seedMirrorRow('BHP-FEB', ['ID_KAS_UMUM' => 'BHP-FEB', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'NO_BUKTI' => 'BPU05', 'TANGGAL_TRANSAKSI' => '2026-02-10', 'URAIAN' => 'Bola Kasti', 'KODE_REKENING' => '5.1.02.01.01.0005', 'VOLUME' => 12, 'JUMLAH' => 300000, 'ID_REF_SUMBER_DANA' => 1]);

        // Overlay operator untuk BHP-1: deskripsi item manual harus menang
        // atas URAIAN mirror pada kolom Uraian laporan.
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'BHP-1',
            'payment_description' => 'Pembayaran buku Ramadhan',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create([
            'source_item_id' => 'BHP-1',
            'item_description' => 'Buku Ramadhan Edisi Operator',
        ]);
    }

    public function test_bhp_monthly_rows_follow_account_classification(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'laporan_bhp', 3);

        $this->assertNotNull($payload);
        $this->assertSame('item_recap', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('a4', $payload['paper']);

        $labels = array_column($payload['columns'], 'label');
        $this->assertSame('Satuan', $labels[array_search('Jumlah Barang', $labels, true) + 1]);

        $rows = $payload['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame('07-03-2026', $rows[0]['date']);
        $this->assertSame('03.03.14.', $rows[0]['activity']);
        $this->assertSame('5.1.02.01.01.0024', $rows[0]['account']);
        $this->assertSame('BPU09', $rows[0]['evidence']);
        // Uraian mengikuti deskripsi item overlay operator, bukan mirror.
        $this->assertSame('Buku Ramadhan Edisi Operator', $rows[0]['description']);
        $this->assertSame('118', $rows[0]['quantity']);
        $this->assertSame('Buah', $rows[0]['unit']);
        $this->assertSame(20000.0, $rows[0]['unit_price']);
        $this->assertSame(2360000.0, $rows[0]['amount']);
        // Tanpa overlay: uraian mirror; satuan tetap dari periode yang sama.
        $this->assertSame('Kertas HVS A4', $rows[1]['description']);
        $this->assertSame('Buah', $rows[1]['unit']);
        $this->assertSame(50000.0, $rows[1]['unit_price']);

        $recap = $payload['itemRecap'];
        $this->assertSame('REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG HABIS PAKAI )', $recap['title']);
        $this->assertSame('BHP', $recap['tag']);
        $this->assertSame('BULAN : MARET TAHUN : 2026', $recap['periodLine']);
        $this->assertSame('Rantopanjang, Kec. Ranto Baek', $recap['villageDistrict']);
        $this->assertSame(2710000.0, $recap['total']);
        $this->assertSame('Kec. Ranto Baek', $recap['signedPlace']);
        $this->assertSame('31 Maret 2026', $recap['signedDate']);
    }

    public function test_bma_monthly_contains_only_capital_accounts(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'laporan_bma', 3);

        $this->assertNotNull($payload);
        $this->assertCount(1, $payload['rows']);
        $this->assertSame('-', $payload['rows'][0]['activity']);
        $this->assertSame('-', $payload['rows'][0]['unit']);
        $this->assertSame('Buku Al-Hira Kelas IV', $payload['rows'][0]['description']);
        $this->assertSame(45000.0, $payload['rows'][0]['unit_price']);
        $this->assertSame('REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG MODAL / ASET )', $payload['itemRecap']['title']);
        $this->assertSame('BMA', $payload['itemRecap']['tag']);
        $this->assertSame(2070000.0, $payload['itemRecap']['total']);
    }

    public function test_quarter_semester_and_annual_scopes_aggregate_months(): void
    {
        $service = app(SpjPeriodicReportPrintService::class);

        $triwulan = $service->build('triwulan', 'laporan_bhp', 1);
        $this->assertNotNull($triwulan);
        $this->assertCount(3, $triwulan['rows']);
        $this->assertSame(3010000.0, $triwulan['itemRecap']['total']);
        $this->assertSame('TRIWULAN : I TAHUN : 2026', $triwulan['itemRecap']['periodLine']);

        $triwulanDua = $service->build('triwulan', 'laporan_bma', 2);
        $this->assertNotNull($triwulanDua);
        $this->assertCount(1, $triwulanDua['rows']);
        $this->assertSame(13000000.0, $triwulanDua['itemRecap']['total']);

        $semester = $service->build('semester', 'laporan_bhp', 1);
        $this->assertNotNull($semester);
        $this->assertCount(3, $semester['rows']);
        $this->assertSame('SEMESTER : I TAHUN : 2026', $semester['itemRecap']['periodLine']);

        $semesterDua = $service->build('semester', 'laporan_bma', 2);
        $this->assertNotNull($semesterDua);
        $this->assertCount(0, $semesterDua['rows']);
        $this->assertSame(0.0, $semesterDua['itemRecap']['total']);

        $tahunan = $service->build('tahunan', 'laporan_bma', null);
        $this->assertNotNull($tahunan);
        $this->assertCount(2, $tahunan['rows']);
        $this->assertSame(15070000.0, $tahunan['itemRecap']['total']);
        $this->assertSame('TAHUN : 2026', $tahunan['itemRecap']['periodLine']);
    }

    public function test_bhp_print_route_renders_arkas_style_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/laporan_bhp/cetak?periode_laporan=3');

        $response->assertOk();
        $response->assertSee('REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG HABIS PAKAI )', false);
        $response->assertSee('BULAN : MARET TAHUN : 2026', false);
        $response->assertSee('SD NEGERI 316 RANTO PANJANG', false);
        $response->assertSee('Buku Ramadhan Edisi Operator', false);
        $response->assertSee('Buah', false);
        $response->assertSee('Rp. 2.710.000', false);
        $response->assertSee('Menyetujui,', false);
        $response->assertDontSee('Konsumsi Rapat');
        $response->assertDontSee('Jamuan Tamu');
        $response->assertDontSee('Konsumsi Kegiatan Lapangan');
        $response->assertDontSee('Nasi Box Lomba Siswa');
        $response->assertDontSee('Jasa Narasumber');
        $response->assertDontSee('PPh 21 atas Buku Ramadhan');
        // Blok tanda tangan hanya dari partial BHP/BMA, tidak ganda dengan blok generik.
        $this->assertSame(1, substr_count((string) $response->getContent(), '<table class="signature-table'));
    }

    public function test_bma_print_and_excel_routes_respond(): void
    {
        $print = $this->get('/laporan-periode/bulan/laporan_bma/cetak?periode_laporan=3');
        $print->assertOk();
        $print->assertSee('REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG MODAL / ASET )', false);
        $print->assertSee('Buku Al-Hira Kelas IV', false);
        $print->assertSee('Rp. 2.070.000', false);

        $excel = $this->get('/laporan-periode/bulan/laporan_bhp/excel?periode_laporan=3');
        $excel->assertOk();

        $pdf = $this->get('/laporan-periode/bulan/laporan_bma/pdf?periode_laporan=3');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }
}
