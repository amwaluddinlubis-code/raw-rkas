<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\RkasReportExcelService;
use App\Services\RkasReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

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
    }

    public function test_bulanan_filters_single_month_and_rejects_invalid(): void
    {
        $payload = app(RkasReportService::class)->build('bulanan', $this->reportOptions(['revision' => 'ANG-1', 'month' => 7]));

        $this->assertNotNull($payload);
        $this->assertSame(5000000.0, $payload['totals']['scoped']);
        $this->assertStringContainsString('Juli', $payload['scope_label']);

        $this->assertNull(app(RkasReportService::class)->build('bulanan', $this->reportOptions(['month' => 0])));
    }

    public function test_excel_export_returns_readable_file(): void
    {
        $payload = app(RkasReportService::class)->build('tahunan', $this->reportOptions(['revision' => 'ANG-1']));
        $path = app(RkasReportExcelService::class)->export($payload);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
        @unlink($path);
    }

    public function test_pdf_renders_for_all_scopes(): void
    {
        foreach (['tahunan', 'tahap', 'triwulan'] as $scope) {
            $payload = app(RkasReportService::class)->build($scope, $this->reportOptions(['revision' => 'ANG-1']));
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
