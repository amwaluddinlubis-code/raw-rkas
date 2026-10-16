<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\BospRekapStandardMapper;
use App\Services\SpjPeriodicReportExcelService;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RpdBosReportTest extends TestCase
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
            'npsn' => '10208246',
            'school_code' => 'SCH-RPD',
            'name' => 'SD NEGERI 316 RANTO PANJANG',
            'address' => 'Ranto Panjang',
            'desa' => 'Ranto Panjang',
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
            'committee_name' => 'Komite Uji',
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

        $this->seedData();
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

    private function seedData(): void
    {
        $this->seedMirrorRow('arkas_mirror_anggaran', 'ANG-1', [
            'ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1,
            'IS_AKTIF' => 1, 'IS_APPROVE' => 1,
            'LAST_UPDATE' => '2026-01-10 08:00:00', 'CREATE_DATE' => '2026-01-05 05:00:00',
        ]);
        // Program 3, kegiatan 03.03.14: 2 item dengan Vol/Satuan/Tarif.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P3', [
            'ID_RAPBS' => 'R-P3', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '03.03.14.',
            'NAMA_KEGIATAN' => 'Penyelenggaraan Pesantren Kilat', 'KODE_REKENING' => '5.1.02.01.01.0024',
            'URAIAN' => 'Buku Ramadhan', 'JUMLAH' => 2360000,
            'VOLUME' => 118, 'SATUAN' => 'exemplar', 'HARGA_SATUAN' => 20000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P3B', [
            'ID_RAPBS' => 'R-P3B', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '03.03.21.',
            'NAMA_KEGIATAN' => 'Perayaan Hari Besar', 'KODE_REKENING' => '5.1.02.01.01.0026',
            'URAIAN' => 'Cetak Spanduk', 'JUMLAH' => 250000,
            'VOLUME_TOTAL' => 5, 'UNIT' => 'buah', 'HARGA_SATUAN' => 50000,
        ]);
        // Program 5: 1 item.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P5', [
            'ID_RAPBS' => 'R-P5', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '05.02.05.',
            'NAMA_KEGIATAN' => 'Pengadaan buku pengayaan dan referensi', 'KODE_REKENING' => '5.2.05.01.01.0004',
            'URAIAN' => 'BUKU AL-HIRA SD Kelas IV', 'JUMLAH' => 2070000,
            'VOLUME' => 46, 'SATUAN' => 'exemplar', 'HARGA_SATUAN' => 45000,
        ]);

        // Realisasi BELANJA: R-P3 terealisasi penuh di Februari, R-P3B di Maret.
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K-RPD-1', [
            'ID_KAS_UMUM' => 'K-RPD-1', 'KATEGORI_BKU' => 'BELANJA', 'ID_RAPBS' => 'R-P3',
            'TANGGAL_TRANSAKSI' => '2026-02-10', 'JUMLAH' => 2360000, 'URAIAN' => 'Beli Buku Ramadhan',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K-RPD-2', [
            'ID_KAS_UMUM' => 'K-RPD-2', 'KATEGORI_BKU' => 'BELANJA', 'ID_RAPBS' => 'R-P3B',
            'TANGGAL_TRANSAKSI' => '2026-03-05', 'JUMLAH' => 250000, 'URAIAN' => 'Cetak Spanduk',
        ]);
        // Baris penerimaan harus diabaikan realisasi RPD.
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K-RPD-3', [
            'ID_KAS_UMUM' => 'K-RPD-3', 'KATEGORI_BKU' => 'PENERIMAAN', 'ID_RAPBS' => 'R-P5',
            'TANGGAL_TRANSAKSI' => '2026-01-05', 'JUMLAH' => 999999, 'URAIAN' => 'Terima BOS',
        ]);
    }

    private function buildRpd(): array
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'rpd_bos', 1);

        $this->assertSame('rpd', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);

        return $payload['rpd'];
    }

    public function test_rpd_builds_program_hierarchy(): void
    {
        $rpd = $this->buildRpd();

        $this->assertSame(1, $rpd['quarter']);
        $this->assertSame('I ( Satu )', $rpd['quarter_label']);
        $this->assertSame('BULAN JANUARI S.D MARET 2026', $rpd['month_range']);
        $this->assertTrue($rpd['has_data']);

        // 8 baris program selalu tampil walau nol.
        $programs = array_values(array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'program'));
        $this->assertCount(8, $programs);
        $this->assertSame('01.', $programs[0]['code']);
        $this->assertSame(BospRekapStandardMapper::k7Programs()[3], $programs[2]['label']);
        $this->assertSame('08.', $programs[7]['code']);

        // Hierarki program 3: sub 03.03. -> kegiatan -> item.
        $subs = array_values(array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'sub' && $r['code'] === '03.03.'));
        $this->assertCount(1, $subs);
        $activities = array_values(array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'activity'));
        $codes = array_column($activities, 'code');
        $this->assertContains('03.03.14.', $codes);
        $this->assertContains('03.03.21.', $codes);
        $this->assertContains('05.02.05.', $codes);

        // Nomor urut No berjalan untuk semua baris.
        $nos = array_column($rpd['rows'], 'no');
        $this->assertSame(range(1, count($nos)), $nos);
    }

    public function test_rpd_reads_volume_unit_and_unit_price(): void
    {
        $rpd = $this->buildRpd();

        $items = array_values(array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'item'));
        $this->assertCount(3, $items);

        $byUraian = [];
        foreach ($items as $item) {
            $byUraian[$item['label']] = $item;
        }

        // VOLUME/SATUAN/HARGA_SATUAN.
        $this->assertSame(118.0, $byUraian['Buku Ramadhan']['volume']);
        $this->assertSame('exemplar', $byUraian['Buku Ramadhan']['unit']);
        $this->assertSame(20000.0, $byUraian['Buku Ramadhan']['unit_price']);

        // Fallback VOLUME_TOTAL/UNIT.
        $this->assertSame(5.0, $byUraian['Cetak Spanduk']['volume']);
        $this->assertSame('buah', $byUraian['Cetak Spanduk']['unit']);
        $this->assertSame(50000.0, $byUraian['Cetak Spanduk']['unit_price']);

        $this->assertSame(46.0, $byUraian['BUKU AL-HIRA SD Kelas IV']['volume']);
    }

    public function test_rpd_realization_per_month_per_item(): void
    {
        $rpd = $this->buildRpd();

        $items = [];
        foreach ($rpd['rows'] as $row) {
            if ($row['level'] === 'item') {
                $items[$row['label']] = $row;
            }
        }

        // R-P3 terealisasi penuh di Februari (bulan 2).
        $this->assertSame(0.0, $items['Buku Ramadhan']['months'][1]);
        $this->assertSame(2360000.0, $items['Buku Ramadhan']['months'][2]);
        $this->assertSame(0.0, $items['Buku Ramadhan']['months'][3]);

        // R-P3B terealisasi di Maret (bulan 3).
        $this->assertSame(250000.0, $items['Cetak Spanduk']['months'][3]);

        // R-P5 belum ada realisasi BELANJA (baris PENERIMAAN diabaikan).
        $this->assertSame(0.0, $items['BUKU AL-HIRA SD Kelas IV']['months'][1]);
        $this->assertSame(0.0, $items['BUKU AL-HIRA SD Kelas IV']['months'][2]);
        $this->assertSame(0.0, $items['BUKU AL-HIRA SD Kelas IV']['months'][3]);

        // Agregat program 3 = jumlah item-itemnya.
        $programs = [];
        foreach ($rpd['rows'] as $row) {
            if ($row['level'] === 'program') {
                $programs[$row['code']] = $row;
            }
        }
        $this->assertSame(2610000.0, $programs['03.']['jumlah']);
        $this->assertSame(2360000.0, $programs['03.']['months'][2]);
        $this->assertSame(250000.0, $programs['03.']['months'][3]);
    }

    public function test_rpd_totals_equal_sum_of_items(): void
    {
        $rpd = $this->buildRpd();

        $itemJumlah = 0.0;
        $itemMonths = [1 => 0.0, 2 => 0.0, 3 => 0.0];
        foreach ($rpd['rows'] as $row) {
            if ($row['level'] !== 'item') {
                continue;
            }
            $itemJumlah += $row['jumlah'];
            foreach ([1, 2, 3] as $month) {
                $itemMonths[$month] += $row['months'][$month];
            }
        }

        $this->assertSame(2360000.0 + 250000.0 + 2070000.0, $itemJumlah);
        $this->assertSame($itemJumlah, $rpd['totals']['jumlah']);
        $this->assertSame($itemMonths, $rpd['totals']['months']);

        // Total program = total keseluruhan.
        $programJumlah = array_sum(array_map(
            fn (array $r): float => $r['jumlah'],
            array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'program')
        ));
        $this->assertSame($rpd['totals']['jumlah'], $programJumlah);
    }

    public function test_rpd_without_budget_data_renders_zeros(): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs')->truncate();
        DB::connection('school')->table('arkas_mirror_kas_umum')->truncate();

        $rpd = $this->buildRpd();

        $this->assertFalse($rpd['has_data']);
        $this->assertSame(0.0, $rpd['totals']['jumlah']);
        $programs = array_filter($rpd['rows'], fn (array $r): bool => $r['level'] === 'program');
        $this->assertCount(8, $programs);
    }

    public function test_rpd_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/rpd_bos/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('RINCIAN PENGGUNAAN DANA BOS (RPD BOS)', $html);
        $this->assertStringContainsString('BULAN JANUARI S.D MARET 2026', $html);
        $this->assertStringContainsString('PROGRAM SEKOLAH', $html);
        $this->assertStringContainsString('Rincian Perhitungan', $html);
        $this->assertStringContainsString('Tarif Harga', $html);
        $this->assertStringContainsString('Buku Ramadhan', $html);
        $this->assertStringContainsString('exemplar', $html);
        $this->assertStringContainsString('Sub Total Penggunaan Dana I', $html);
        $this->assertStringContainsString('JUMLAH TOTAL (I + II)', $html);
        $this->assertStringContainsString('BENDAHARA', $html);
    }

    public function test_rpd_excel_export_matches_data(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'rpd_bos', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('RINCIAN PENGGUNAAN DANA BOS (RPD BOS)', $sheet->getCell('A1')->getValue());
        $this->assertSame('No', $sheet->getCell('A8')->getValue());
        $this->assertSame('Kode Program', $sheet->getCell('B8')->getValue());
        $this->assertSame('Uraian', $sheet->getCell('C8')->getValue());
        $this->assertSame('Rincian Perhitungan', $sheet->getCell('D8')->getValue());
        $this->assertSame('Vol', $sheet->getCell('D9')->getValue());
        $this->assertSame('Satuan', $sheet->getCell('E9')->getValue());
        $this->assertSame('Tarif Harga', $sheet->getCell('F9')->getValue());
        $this->assertSame('Jumlah', $sheet->getCell('G8')->getValue());
        $this->assertSame('Bulan', $sheet->getCell('H8')->getValue());
        $this->assertSame('Januari', $sheet->getCell('H9')->getValue());
        $this->assertSame('PROGRAM SEKOLAH', $sheet->getCell('C11')->getValue());
    }
}
