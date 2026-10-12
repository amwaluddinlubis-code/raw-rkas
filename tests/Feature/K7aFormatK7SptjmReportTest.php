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

class K7aFormatK7SptjmReportTest extends TestCase
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
            'school_code' => 'SCH-K7A',
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
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7A-T1', [
            'ID_KAS_UMUM' => 'K7A-T1', 'KATEGORI_BKU' => 'PENERIMAAN', 'REK_BKU' => 'Terima Dana BOS',
            'NO_BUKTI' => 'TRS01', 'TANGGAL_TRANSAKSI' => '2026-01-05', 'URAIAN' => 'Terima BOS',
            'KODE_REKENING' => '', 'JUMLAH' => 10000000,
        ]);

        $belanja = [
            ['K7A-B1', '2026-01-06', 'BPU01', 'Pelaksanaan Pendaftaran Murid Baru (PMB)', '5.1.02.01.01.0001', 500000, '01.01.01'],
            ['K7A-B2', '2026-01-07', 'BPU02', 'Pengadaan buku pengayaan dan referensi', '5.1.02.01.01.0005', 1000000, '02.02.01'],
            ['K7A-B3', '2026-01-08', 'BPU03', 'Pembayaran honor Tenaga Kependidikan (selain pendidik)', '5.1.02.02.01.0013', 2000000, '07.12.02'],
            ['K7A-B4', '2026-01-09', 'BPU04', 'Pembayaran jasa internet', '5.1.02.02.01.0062', 300000, '05.08.01'],
            ['K7A-B5', '2026-01-10', 'BPU05', 'Pemeliharaan Prasarana Lahan, Bangunan dan Ruang', '5.1.02.03.02.0001', 700000, '06.05.01'],
            ['K7A-B6', '2026-01-11', 'BPU06', 'Pengadaan perlengkapan sekolah diluar komponen multimedia', '5.1.02.01.01.0026', 400000, '03.03.21'],
            ['K7A-B7', '2026-01-12', 'BPU07', 'Pengadaan komputer untuk laboratorium', '5.2.02.05.0001', 800000, '05.08.02'],
            ['K7A-B8', '2026-01-13', 'BPU08', 'Belanja pegawai bulanan', '5.1.01.01.01.0001', 1500000, '07.07.01'],
        ];

        foreach ($belanja as [$kasId, $date, $bukti, $nama, $rekening, $jumlah, $kode]) {
            $this->seedMirrorRow('arkas_mirror_kas_umum', $kasId, [
                'ID_KAS_UMUM' => $kasId, 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
                'NO_BUKTI' => $bukti, 'TANGGAL_TRANSAKSI' => $date, 'URAIAN' => $nama,
                'KODE_REKENING' => $rekening, 'JUMLAH' => $jumlah, 'ID_RAPBS' => $kasId.'-RA',
            ]);
            $this->seedMirrorRow('arkas_mirror_rapbs', $kasId.'-RA', [
                'id_rapbs' => $kasId.'-RA', 'KODE_KEGIATAN' => $kode, 'NAMA_KEGIATAN' => $nama,
                'kode_rekening' => $rekening, 'uraian' => $nama, 'jumlah' => $jumlah,
            ]);
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

    public function test_mapper_follows_keyword_priority_and_denies_guard(): void
    {
        $this->assertCount(12, BospRekapStandardMapper::k7aComponents());
        $this->assertSame(1, BospRekapStandardMapper::k7aComponentForActivity('Pelaksanaan Pendaftaran Murid Baru (PMB)'));
        $this->assertSame(2, BospRekapStandardMapper::k7aComponentForActivity('Pengadaan buku pengayaan dan referensi'));
        // Honor didahulukan atas "Tenaga Kependidikan" (komponen 6).
        $this->assertSame(12, BospRekapStandardMapper::k7aComponentForActivity('Pembayaran honor Tenaga Kependidikan (selain pendidik)'));
        // Administrasi didahulukan atas "pembelajaran" pada kegiatan campuran.
        $this->assertSame(5, BospRekapStandardMapper::k7aComponentForActivity('Pembelian Bahan Habis Pakai untuk mendukung pembelajaran dan administrasi sekolah (termasuk ATK)'));
        // Penafian eksplisit menonaktifkan kata kunci multimedia.
        $this->assertNull(BospRekapStandardMapper::k7aComponentForActivity('Pengadaan perlengkapan sekolah diluar komponen multimedia'));
        $this->assertSame(9, BospRekapStandardMapper::k7aComponentForActivity('Pengadaan komputer untuk laboratorium'));
        $this->assertNull(BospRekapStandardMapper::k7aComponentForActivity(null));
        $this->assertNull(BospRekapStandardMapper::k7aComponentForActivity(''));
    }

    public function test_k7a_groups_belanja_by_component_with_reconciliation(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bos_k7a', 1);

        $this->assertSame('k7a', $payload['presentation']);
        $this->assertSame('portrait', $payload['orientation']);

        $k7a = $payload['k7a'];
        $this->assertSame(500000.0, $k7a['rows'][1]['amount']);
        $this->assertSame(1000000.0, $k7a['rows'][2]['amount']);
        $this->assertSame(0.0, $k7a['rows'][3]['amount']);
        $this->assertSame(300000.0, $k7a['rows'][7]['amount']);
        $this->assertSame(700000.0, $k7a['rows'][8]['amount']);
        $this->assertSame(800000.0, $k7a['rows'][9]['amount']);
        $this->assertSame(2000000.0, $k7a['rows'][12]['amount']);
        $this->assertSame(5300000.0, $k7a['total']);
        $this->assertSame(1900000.0, $k7a['unmapped']);
        $this->assertSame(2, $k7a['unmappedCount']);
        $this->assertSame(7200000.0, $k7a['belanja']);
        $this->assertSame($k7a['belanja'], $k7a['total'] + $k7a['unmapped']);
    }

    public function test_k7a_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/bos_k7a/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('REKAPITULASI REALISASI PENGGUNAAN DANA BOS', $html);
        $this->assertStringContainsString('BOS K7A', $html);
        $this->assertStringContainsString('Penerimaan Peserta Didik Baru (PPDB)', $html);
        $this->assertStringContainsString('Pembayaran Honor', $html);
        $this->assertStringContainsString('JUMLAH', $html);
        $this->assertStringContainsString('Komite Sekolah', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_format_k7_follows_official_form_structure(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k7', 1);

        $this->assertSame('k7', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);

        $k7 = $payload['k7'];
        $this->assertSame('Realisasi Penggunaan Dana Tiap Jenis Anggaran', $k7['title']['heading']);
        $this->assertSame('Tahun Anggaran 2026', $k7['title']['year_line']);
        $this->assertStringContainsString('Triwulan I', $k7['title']['range_line']);
        // BOSP Reguler (APBN) -> kolom Pusat.
        $this->assertSame('pusat', $k7['fund_column']);

        $rows = $k7['rows'];
        $byLabel = [];
        foreach ($rows as $row) {
            $byLabel[$row['label']] = $row;
        }

        // A. Penerimaan: hanya Terima Dana BOS 10jt pada kolom Pusat.
        $this->assertSame(10000000.0, $byLabel['Penerimaan Terdiri Dari']['amounts']['jumlah']);
        $this->assertSame(10000000.0, $byLabel['Penerimaan Terdiri Dari']['amounts']['pusat']);
        $this->assertSame(10000000.0, $byLabel['Bantuan Operasional Sekolah']['amounts']['jumlah']);
        $this->assertSame(0.0, $byLabel['Saldo Awal']['amounts']['jumlah']);

        // Hierarki 8 program selalu tampil; program tanpa data bernilai nol.
        $this->assertSame(500000.0, $byLabel['Pengembangan Kompetensi Lulusan']['amounts']['jumlah']);
        $this->assertSame(1000000.0, $byLabel['Pengembangan Standar Isi']['amounts']['jumlah']);
        $this->assertSame(400000.0, $byLabel['Pengembangan Standar Proses']['amounts']['jumlah']);
        $this->assertSame(0.0, $byLabel['Pengembangan pendidik dan tenaga kependidikan']['amounts']['jumlah']);
        $this->assertSame(1100000.0, $byLabel['Pengembangan sarana dan prasarana sekolah']['amounts']['jumlah']);
        $this->assertSame(700000.0, $byLabel['Pengembangan standar pengelolaan']['amounts']['jumlah']);
        $this->assertSame(3500000.0, $byLabel['Pengembangan standar pembiayaan']['amounts']['jumlah']);
        $this->assertSame(0.0, $byLabel['Pengembangan dan implementasi sistem penilaian']['amounts']['jumlah']);

        // Sub-program dan kegiatan dari kode kegiatan transaksi.
        $this->assertSame(500000.0, $byLabel['Penerimaan Murid baru']['amounts']['jumlah']);
        $this->assertSame(500000.0, $byLabel['Pelaksanaan Pendaftaran Murid Baru (PMB)']['amounts']['jumlah']);
        $this->assertSame(400000.0, $byLabel['Pelaksanaan Kegiatan Pembelajaran dan Ekstrakurikuler']['amounts']['jumlah']);

        // II total penggunaan, C sisa = penerimaan - penggunaan.
        $this->assertSame(7200000.0, $byLabel['Total Penggunaan Dana']['amounts']['jumlah']);
        $this->assertSame(7200000.0, $byLabel['Total Penggunaan Dana']['amounts']['pusat']);
        $this->assertSame(2800000.0, $byLabel['Sisa Dana = I - II (Pengembalian)']['amounts']['jumlah']);
        $this->assertSame(2800000.0, $byLabel['Sisa Dana = I - II (Pengembalian)']['amounts']['pusat']);

        $this->assertSame(0.0, $k7['unmapped']);
        $this->assertSame(0, $k7['unmappedCount']);
    }

    public function test_format_k7_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/format_k7/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Realisasi Penggunaan Dana Tiap Jenis Anggaran', $html);
        $this->assertStringContainsString('Tahun Anggaran 2026', $html);
        $this->assertStringContainsString('Triwulan I', $html);
        $this->assertStringContainsString('Formulir Bos-K7', $html);
        $this->assertStringContainsString('Diisi Oleh Sekolah', $html);
        $this->assertStringContainsString('Penggunaan Dana Per Sumber Dana', $html);
        $this->assertStringContainsString('Bantuan Operasional Sekolah (BOS)', $html);
        $this->assertStringContainsString('Sisa Dana = I - II (Pengembalian)', $html);
        $this->assertStringContainsString('2.800.000', $html);
        $this->assertStringContainsString('Komite Sekolah', $html);
        $this->assertStringContainsString('Mengetahui', $html);
        $this->assertStringContainsString('Bendahara', $html);
        $this->assertStringContainsString('Kepala Uji', $html);
        $this->assertStringContainsString('Bendahara Uji', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_sptjm_uses_official_wording_with_period_nominal(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'sptjm', 1);

        $this->assertSame('sptjm_doc', $payload['presentation']);

        $sptjm = $payload['sptjm'];
        $this->assertSame(2026, $sptjm['year']);
        $this->assertSame(10000000.0, $sptjm['received']);
        $this->assertSame(7200000.0, $sptjm['used']);
    }

    public function test_sptjm_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/sptjm/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK', $html);
        $this->assertStringContainsString('bertanggung jawab penuh atas penggunaan dana', $html);
        $this->assertStringContainsString('kerugian negara', $html);
        $this->assertStringContainsString('aparat pengawas fungsional', $html);
        $this->assertStringContainsString('Materai 10.000', $html);
        $this->assertStringContainsString('Kepala Uji', $html);
        $this->assertSame(1, substr_count($html, '<table class="signature-table'));
    }

    public function test_k7a_k7_sptjm_excel_exports_match_data(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bos_k7a', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('No', $sheet->getCell('A1')->getValue());
        $this->assertSame('JUMLAH', $sheet->getCell('B14')->getValue());
        $this->assertEquals(5300000, $sheet->getCell('C14')->getValue());
        $book->disconnectWorksheets();

        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k7', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('Realisasi Penggunaan Dana Tiap Jenis Anggaran', $sheet->getCell('A1')->getValue());
        $this->assertSame('No. Kode', $sheet->getCell('A11')->getValue());
        $this->assertSame('Penggunaan Dana Per Sumber Dana', $sheet->getCell('D11')->getValue());
        $this->assertSame('Pusat', $sheet->getCell('E13')->getValue());
        $this->assertEquals(10, $sheet->getCell('H14')->getValue());
        // Baris A: penerimaan 10jt pada kolom Jumlah (C) dan Pusat (E).
        $this->assertSame('Penerimaan Terdiri Dari', $sheet->getCell('B15')->getValue());
        $this->assertEquals(10000000, $sheet->getCell('C15')->getValue());
        $this->assertEquals(10000000, $sheet->getCell('E15')->getValue());
        // Baris C: sisa 2,8jt.
        $this->assertSame('Sisa Dana = I - II (Pengembalian)', $sheet->getCell('B48')->getValue());
        $this->assertEquals(2800000, $sheet->getCell('C48')->getValue());
        $book->disconnectWorksheets();

        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'sptjm', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertStringContainsString('TANGGUNG JAWAB MUTLAK', (string) $sheet->getCell('A1')->getValue());
        $this->assertEquals(10000000, $sheet->getCell('B5')->getValue());
        $book->disconnectWorksheets();
    }
}
