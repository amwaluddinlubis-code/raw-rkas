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

class FormatK2ReportTest extends TestCase
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
            'school_code' => 'SCH-K2',
            'name' => 'SDN 316 Ranto Panjang',
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

        $this->seedBudget();
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

    private function seedBudget(): void
    {
        $this->seedMirrorRow('arkas_mirror_anggaran', 'ANG-1', [
            'ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1,
            'IS_AKTIF' => 1, 'IS_APPROVE' => 1,
            'LAST_UPDATE' => '2026-01-10 08:00:00', 'CREATE_DATE' => '2026-01-05 05:00:00',
        ]);
        // Program 3 (Standar Proses): 2.610.000 seluruhnya di TW I.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P3', [
            'ID_RAPBS' => 'R-P3', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '03.01.01.',
            'KODE_REKENING' => '5.1.02.01.01.0024', 'URAIAN' => 'ATK TW1', 'JUMLAH' => 2610000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs_periode', 'P-P3-1', [
            'ID_RAPBS_PERIODE' => 'P-P3-1', 'ID_RAPBS' => 'R-P3', 'ID_PERIODE' => '81', 'JUMLAH' => 2610000,
        ]);
        // Program 5 (Sarana): 5.898.000 terbagi TW I 3.000.000 + TW II 2.898.000.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P5', [
            'ID_RAPBS' => 'R-P5', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '05.02.03.',
            'KODE_REKENING' => '5.2.05.01.01.0004', 'URAIAN' => 'Buku Teks', 'JUMLAH' => 5898000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs_periode', 'P-P5-1', [
            'ID_RAPBS_PERIODE' => 'P-P5-1', 'ID_RAPBS' => 'R-P5', 'ID_PERIODE' => '82', 'JUMLAH' => 3000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs_periode', 'P-P5-2', [
            'ID_RAPBS_PERIODE' => 'P-P5-2', 'ID_RAPBS' => 'R-P5', 'ID_PERIODE' => '84', 'JUMLAH' => 2898000,
        ]);
        // Program 7 tanpa rincian periode: total tahunan jatuh ke triwulan berjalan.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P7', [
            'ID_RAPBS' => 'R-P7', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '07.07.01.',
            'KODE_REKENING' => '5.1.01.01.01.0001', 'URAIAN' => 'Honor', 'JUMLAH' => 10679000,
        ]);
    }

    public function test_k2_groups_budget_by_program_and_quarter(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k2', 1);

        $this->assertSame('k2', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);

        $k2 = $payload['k2'];
        $this->assertCount(8, $k2['programs']);
        $this->assertSame('1.1', $k2['programs'][1]['no']);
        $this->assertSame(BospRekapStandardMapper::a1Programs()[3], $k2['programs'][3]['label']);

        // Triwulan I hanya memuat anggaran TW I; kolom TW II–IV bernilai 0
        // dan Jumlah = kumulatif TW I.
        $this->assertSame(2610000.0, $k2['programs'][3]['jumlah']);
        $this->assertSame(2610000.0, $k2['programs'][3]['tw'][1]);
        $this->assertSame(0.0, $k2['programs'][3]['tw'][2]);

        $this->assertSame(3000000.0, $k2['programs'][5]['jumlah']);
        $this->assertSame(3000000.0, $k2['programs'][5]['tw'][1]);
        $this->assertSame(0.0, $k2['programs'][5]['tw'][2]);

        // Tanpa rincian periode: total tahunan tampil di triwulan berjalan (1).
        $this->assertSame(10679000.0, $k2['programs'][7]['jumlah']);
        $this->assertSame(10679000.0, $k2['programs'][7]['tw'][1]);
        $this->assertSame(0.0, $k2['programs'][7]['tw'][3]);

        $expected = 2610000.0 + 3000000.0 + 10679000.0;
        $this->assertSame($expected, $k2['totals']['jumlah']);
        $this->assertSame($expected, array_sum($k2['totals']['tw']));
        $this->assertSame($k2['totals']['jumlah'], array_sum(array_column($k2['programs'], 'jumlah')));

        $this->assertSame(1, $k2['quarter']);
        $this->assertSame('I ( Satu )', $k2['quarter_label']);
        $this->assertStringContainsString('2026', $k2['month_range']);
        $this->assertTrue($k2['has_data']);
    }

    public function test_k2_shows_cumulative_quarters_until_selected_period(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k2', 2);
        $k2 = $payload['k2'];

        $this->assertSame(2, $k2['quarter']);
        $this->assertCount(8, $k2['programs']);

        // Program 5: TW I 3.000.000 + TW II 2.898.000 tampil; TW III/IV nol.
        $this->assertSame(3000000.0, $k2['programs'][5]['tw'][1]);
        $this->assertSame(2898000.0, $k2['programs'][5]['tw'][2]);
        $this->assertSame(0.0, $k2['programs'][5]['tw'][3]);
        $this->assertSame(0.0, $k2['programs'][5]['tw'][4]);
        $this->assertSame(5898000.0, $k2['programs'][5]['jumlah']);

        // Program 3 hanya teranggarkan TW I: tetap tampil di laporan TW II.
        $this->assertSame(2610000.0, $k2['programs'][3]['tw'][1]);
        $this->assertSame(2610000.0, $k2['programs'][3]['jumlah']);

        // Jumlah = penjumlahan kolom yang tampil (kumulatif s.d. TW II).
        $this->assertSame(2610000.0 + 5898000.0 + 10679000.0, $k2['totals']['jumlah']);
        $this->assertSame($k2['totals']['jumlah'], array_sum($k2['totals']['tw']));
    }

    public function test_k2_monthly_uses_quarter_of_selected_month_as_cutoff(): void
    {
        // Februari berada di TW I: angka sama dengan laporan triwulan I,
        // label periode memakai nama bulan.
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'format_k2', 2);
        $k2 = $payload['k2'];

        $this->assertSame('k2', $payload['presentation']);
        $this->assertSame('quarter', $k2['columns']);
        $this->assertSame(1, $k2['quarter']);
        $this->assertSame('Februari', $k2['period_label']);
        $this->assertSame('Februari', $k2['quarter_label']);
        $this->assertStringContainsString('01 Februari', $k2['period_range']);
        $this->assertStringContainsString('28 Februari 2026', $k2['period_range']);

        $this->assertSame(2610000.0 + 3000000.0 + 10679000.0, $k2['totals']['jumlah']);
        $this->assertSame(0.0, $k2['totals']['tw'][2]);
        $this->assertTrue($k2['has_data']);
    }

    public function test_k2_monthly_in_second_quarter_is_cumulative_until_that_quarter(): void
    {
        // Mei berada di TW II: kolom TW III/IV nol, Jumlah kumulatif TW I+II.
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'format_k2', 5);
        $k2 = $payload['k2'];

        $this->assertSame(2, $k2['quarter']);
        $this->assertSame('Mei', $k2['period_label']);
        $this->assertSame(3000000.0, $k2['programs'][5]['tw'][1]);
        $this->assertSame(2898000.0, $k2['programs'][5]['tw'][2]);
        $this->assertSame(0.0, $k2['programs'][5]['tw'][3]);
        $this->assertSame(2610000.0 + 5898000.0 + 10679000.0, $k2['totals']['jumlah']);
        $this->assertSame($k2['totals']['jumlah'], array_sum($k2['totals']['tw']));
    }

    public function test_k2_monthly_print_route_renders_month_label(): void
    {
        $response = $this->get('/laporan-periode/bulan/format_k2/cetak?periode_laporan=2');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Format BOS K-2', $html);
        $this->assertStringContainsString('Triwulan / Bulan', $html);
        $this->assertStringContainsString('Februari / 01 Februari  s.d 28 Februari 2026', $html);
    }

    public function test_k2_without_budget_data_renders_zeros(): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs')->truncate();
        DB::connection('school')->table('arkas_mirror_rapbs_periode')->truncate();

        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k2', 2);

        $k2 = $payload['k2'];
        $this->assertCount(8, $k2['programs']);
        $this->assertSame(0.0, $k2['totals']['jumlah']);
        $this->assertFalse($k2['has_data']);
        $this->assertSame(2, $k2['quarter']);
        $this->assertSame('II ( Dua )', $k2['quarter_label']);
    }

    public function test_k2_semester_shows_two_semester_columns(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('semester', 'format_k2', 1);

        $this->assertSame('k2', $payload['presentation']);

        $k2 = $payload['k2'];
        $this->assertSame('semester', $k2['columns']);
        $this->assertSame('Semester', $k2['group_label']);
        $this->assertSame('Semester / Bulan', $k2['period_row_label']);
        $this->assertSame('I ( Satu )', $k2['period_label']);
        $this->assertSame('01 Januari  s.d 30 Juni 2026', $k2['period_range']);
        $this->assertCount(8, $k2['programs']);

        // Program 5 (TW I 3.000.000 + TW II 2.898.000) masuk Semester I.
        $this->assertSame(3000000.0, $k2['programs'][5]['tw'][1]);
        $this->assertSame(2898000.0, $k2['programs'][5]['tw'][2]);
        $this->assertSame(5898000.0, $k2['programs'][5]['semester'][1]);
        $this->assertSame(0.0, $k2['programs'][5]['semester'][2]);
        $this->assertSame(5898000.0, $k2['programs'][5]['jumlah']);

        // Program 3 hanya TW I: tetap tampil di Semester I.
        $this->assertSame(2610000.0, $k2['programs'][3]['semester'][1]);
        $this->assertSame(2610000.0, $k2['programs'][3]['jumlah']);

        // Tanpa rincian periode: total tahunan jatuh ke periode berjalan (TW II).
        $this->assertSame(10679000.0, $k2['programs'][7]['semester'][1]);
        $this->assertSame(0.0, $k2['programs'][7]['semester'][2]);

        $expected = 2610000.0 + 5898000.0 + 10679000.0;
        $this->assertSame($expected, $k2['totals']['jumlah']);
        $this->assertSame(19187000.0, $k2['totals']['semester'][1]);
        $this->assertSame(0.0, $k2['totals']['semester'][2]);
        $this->assertSame($k2['totals']['jumlah'], array_sum($k2['totals']['semester']));
    }

    public function test_k2_semester_ii_includes_first_and_second_semester(): void
    {
        // Program 4 dengan anggaran TW III (September) untuk mengisi Semester II.
        $this->seedMirrorRow('arkas_mirror_rapbs', 'R-P4', [
            'ID_RAPBS' => 'R-P4', 'ID_ANGGARAN' => 'ANG-1', 'KODE_KEGIATAN' => '04.01.01.',
            'KODE_REKENING' => '5.1.02.01.01.0024', 'URAIAN' => 'ATK TW3', 'JUMLAH' => 2000000,
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs_periode', 'P-P4-3', [
            'ID_RAPBS_PERIODE' => 'P-P4-3', 'ID_RAPBS' => 'R-P4', 'ID_PERIODE' => '89', 'JUMLAH' => 2000000,
        ]);

        $k2 = app(SpjPeriodicReportPrintService::class)->build('semester', 'format_k2', 2)['k2'];

        $this->assertSame('II ( Dua )', $k2['period_label']);
        $this->assertSame('01 Juli  s.d 31 Desember 2026', $k2['period_range']);

        // Semester II memuat Semester I (kumulatif) + Semester II.
        $this->assertSame(2610000.0, $k2['programs'][3]['semester'][1]);
        $this->assertSame(0.0, $k2['programs'][3]['semester'][2]);
        $this->assertSame(2000000.0, $k2['programs'][4]['semester'][2]);
        $this->assertSame(5898000.0, $k2['programs'][5]['semester'][1]);
        $this->assertSame(0.0, $k2['programs'][5]['semester'][2]);

        $this->assertSame(8508000.0, $k2['totals']['semester'][1]);
        $this->assertSame(12679000.0, $k2['totals']['semester'][2]);
        $this->assertSame(21187000.0, $k2['totals']['jumlah']);
        $this->assertSame($k2['totals']['jumlah'], array_sum($k2['totals']['semester']));
    }

    public function test_k2_semester_print_route_renders_semester_columns(): void
    {
        $response = $this->get('/laporan-periode/semester/format_k2/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Format BOS K-2', $html);
        $this->assertStringContainsString('Semester / Bulan', $html);
        $this->assertStringContainsString('01 Januari  s.d 30 Juni 2026', $html);
        $this->assertStringContainsString('<th colspan="2">Semester</th>', $html);
        $this->assertStringNotContainsString('Triwulan / Bulan', $html);
    }

    public function test_k2_semester_excel_export_uses_semester_columns(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('semester', 'format_k2', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('Semester I', $sheet->getCell('D1')->getValue());
        $this->assertSame('Semester II', $sheet->getCell('E1')->getValue());
        $this->assertNull($sheet->getCell('F1')->getValue());
        $this->assertEquals('1.3', $sheet->getCell('A5')->getValue());
        $this->assertEquals(2610000, $sheet->getCell('C5')->getValue());
        $this->assertEquals(2610000, $sheet->getCell('D5')->getValue());
        $this->assertEquals(0, $sheet->getCell('E5')->getValue());
        $this->assertSame('JUMLAH', $sheet->getCell('B11')->getValue());
        $this->assertEquals(2610000 + 5898000 + 10679000, $sheet->getCell('C11')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_k2_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/format_k2/cetak?periode_laporan=1');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('RENCANA KEGIATAN DAN ANGGARAN SEKOLAH ( RKAS )', $html);
        $this->assertStringContainsString('Format BOS K-2', $html);
        $this->assertStringContainsString('TAHUN ANGGARAN 2026', $html);
        $this->assertStringContainsString('Program Sekolah', $html);
        $this->assertStringContainsString('JUMLAH', $html);
        $this->assertStringNotContainsString('Belanja Lainnya', $html);
        $this->assertStringContainsString('Komite Sekolah', $html);
    }

    public function test_k2_excel_export_matches_data(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('triwulan', 'format_k2', 1);
        $path = app(SpjPeriodicReportExcelService::class)->export($payload);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Laporan');
        $this->assertNotNull($sheet);
        $this->assertSame('No', $sheet->getCell('A1')->getValue());
        $this->assertSame('Uraian', $sheet->getCell('B1')->getValue());
        $this->assertSame('Jumlah (dalam Rp)', $sheet->getCell('C1')->getValue());
        $this->assertSame('TW IV', $sheet->getCell('G1')->getValue());
        $this->assertSame('I', $sheet->getCell('A2')->getValue());
        $this->assertSame('Program Sekolah', $sheet->getCell('B2')->getValue());
        $this->assertEquals('1.3', $sheet->getCell('A5')->getValue());
        $this->assertEquals(2610000, $sheet->getCell('C5')->getValue());
        $this->assertEquals(2610000, $sheet->getCell('D5')->getValue());
        $this->assertSame('JUMLAH', $sheet->getCell('B11')->getValue());
        $this->assertEquals(2610000 + 3000000 + 10679000, $sheet->getCell('C11')->getValue());
        $book->disconnectWorksheets();
    }
}
