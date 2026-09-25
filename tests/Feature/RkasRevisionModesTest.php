<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use App\Services\ArkasMirrorBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkasRevisionModesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedAnggaran(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_anggaran')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedRapbs(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedRapbsPeriode(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_rapbs_periode')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedKas(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_kas_umum')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pending_submission_is_detected_when_newer_than_approval(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-06-16 09:00:00', 'CREATE_DATE' => '2026-06-09 04:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 0, 'LAST_UPDATE' => '2026-07-02 10:00:00', 'CREATE_DATE' => '2026-07-01 08:00:00']);

        $modes = app(ArkasMirrorBudgetService::class)->revisionModes(1, 2026);

        $this->assertSame('ANG-1', $modes['approved']['id']);
        $this->assertSame('ANG-2', $modes['submitted']['id']);
        $this->assertTrue($modes['hasPendingSubmission']);
        $this->assertSame(1, $modes['submitted']['active']);
        $this->assertSame(0, $modes['submitted']['approved']);
    }

    public function test_no_pending_submission_when_all_revisions_approved(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-06-16 09:00:00', 'CREATE_DATE' => '2026-06-09 04:00:00']);

        $modes = app(ArkasMirrorBudgetService::class)->revisionModes(1, 2026);

        $this->assertSame('ANG-1', $modes['approved']['id']);
        $this->assertSame('ANG-1', $modes['submitted']['id']);
        $this->assertFalse($modes['hasPendingSubmission']);
    }

    public function test_deleted_revisions_are_ignored(): void
    {
        $this->seedAnggaran('ANG-9', ['ID_ANGGARAN' => 'ANG-9', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 0, 'SOFT_DELETE' => 1, 'LAST_UPDATE' => '2026-08-01 10:00:00', 'CREATE_DATE' => '2026-08-01 08:00:00']);

        $modes = app(ArkasMirrorBudgetService::class)->revisionModes(1, 2026);

        $this->assertNull($modes['approved']);
        $this->assertNull($modes['submitted']);
        $this->assertFalse($modes['hasPendingSubmission']);
    }

    public function test_revisions_lists_all_approved_plus_pending_submission(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'IS_REVISI' => 0, 'TANGGAL_PENGESAHAN' => '2026-05-06T08:00:00+00:00', 'LAST_UPDATE' => '2026-09-17 02:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'IS_REVISI' => 100, 'TANGGAL_PENGESAHAN' => '2026-09-18T20:00:00+00:00', 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        $this->seedAnggaran('ANG-3', ['ID_ANGGARAN' => 'ANG-3', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 0, 'IS_REVISI' => 101, 'TANGGAL_PENGAJUAN' => '2026-09-20 08:00:00', 'LAST_UPDATE' => '2026-09-20 10:00:00', 'CREATE_DATE' => '2026-09-20 08:00:00']);

        $tabs = app(ArkasMirrorBudgetService::class)->revisions(1, 2026);

        $this->assertSame(['ANG-1', 'ANG-2', 'ANG-3'], array_column($tabs, 'id'));
        $this->assertSame(['approved', 'approved', 'pending'], array_column($tabs, 'status'));
        $this->assertSame([1, 2, 3], array_column($tabs, 'seq'));
        $this->assertSame('06-05-2026', $tabs[0]['dateLabel']);
        $this->assertSame('18-09-2026', $tabs[1]['dateLabel']);
        $this->assertSame('20-09-2026', $tabs[2]['dateLabel']);
    }

    public function test_revisions_omits_pending_tab_when_all_approved(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);

        $tabs = app(ArkasMirrorBudgetService::class)->revisions(1, 2026);

        $this->assertSame(['ANG-1', 'ANG-2'], array_column($tabs, 'id'));
        $this->assertSame(['approved', 'approved'], array_column($tabs, 'status'));
    }

    public function test_old_revision_shows_realization_via_line_identity_fallback(): void
    {
        $this->seedAnggaran('ANG-OLD', ['ID_ANGGARAN' => 'ANG-OLD', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-NEW', ['ID_ANGGARAN' => 'ANG-NEW', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        $this->seedRapbs('OLD-1', ['ID_RAPBS' => 'OLD-1', 'ID_ANGGARAN' => 'ANG-OLD', 'ID_REF_KODE' => 'REF-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Batu Kali', 'JUMLAH' => 500000]);
        $this->seedRapbs('NEW-1', ['ID_RAPBS' => 'NEW-1', 'ID_ANGGARAN' => 'ANG-NEW', 'ID_REF_KODE' => 'REF-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Batu Kali', 'JUMLAH' => 500000]);
        $this->seedKas('KAS-1', ['ID_RAPBS' => 'NEW-1', 'KATEGORI_BKU' => 'BELANJA', 'ID_REF_SUMBER_DANA' => 1, 'JUMLAH' => 200000]);

        $old = app(ArkasMirrorBudgetService::class)->snapshot(1, 2026, ['ANG-OLD']);
        $new = app(ArkasMirrorBudgetService::class)->snapshot(1, 2026, ['ANG-NEW']);

        // Revisi terbaru tidak berubah: tautan langsung tetap dipakai.
        $this->assertSame(200000.0, $new['realization']['NEW-1']);
        // Revisi lama memakai fallback identitas: total tampil tetap pas, tidak ganda.
        $this->assertSame(200000.0, $old['realization_fallback']['OLD-1']);
        $displayedTotal = $old['rows']->sum(fn (array $row): float => (float) ($old['realization'][$row['source_rapbs_id']] ?? 0) + (float) ($old['realization_fallback'][$row['source_rapbs_id']] ?? 0));
        $this->assertSame(200000.0, $displayedTotal);
    }

    public function test_identity_fallback_splits_proportionally_on_duplicate_lines(): void
    {
        $this->seedAnggaran('ANG-OLD', ['ID_ANGGARAN' => 'ANG-OLD', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-NEW', ['ID_ANGGARAN' => 'ANG-NEW', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        $this->seedRapbs('OLD-1', ['ID_RAPBS' => 'OLD-1', 'ID_ANGGARAN' => 'ANG-OLD', 'ID_REF_KODE' => 'REF-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Batu Kali', 'JUMLAH' => 750000]);
        $this->seedRapbs('OLD-2', ['ID_RAPBS' => 'OLD-2', 'ID_ANGGARAN' => 'ANG-OLD', 'ID_REF_KODE' => 'REF-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Batu Kali', 'JUMLAH' => 250000]);
        $this->seedRapbs('NEW-1', ['ID_RAPBS' => 'NEW-1', 'ID_ANGGARAN' => 'ANG-NEW', 'ID_REF_KODE' => 'REF-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Batu Kali', 'JUMLAH' => 1000000]);
        $this->seedKas('KAS-1', ['ID_RAPBS' => 'NEW-1', 'KATEGORI_BKU' => 'BELANJA', 'ID_REF_SUMBER_DANA' => 1, 'JUMLAH' => 200000]);

        $old = app(ArkasMirrorBudgetService::class)->snapshot(1, 2026, ['ANG-OLD']);

        $this->assertSame(150000.0, $old['realization_fallback']['OLD-1']);
        $this->assertSame(50000.0, $old['realization_fallback']['OLD-2']);
    }

    public function test_year_volume_falls_back_to_volume_field(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedRapbs('R-1', ['ID_RAPBS' => 'R-1', 'ID_ANGGARAN' => 'ANG-1', 'ID_REF_KODE' => 'REF-1', 'KODE_KEGIATAN' => '07.12.02.', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Kertas HVS', 'VOLUME' => 10, 'SATUAN' => 'rim', 'HARGA_SATUAN' => 50000, 'JUMLAH' => 500000]);

        $data = app(ArkasMirrorBudgetService::class)->render(Request::create('/', 'GET'), 1, 1);
        $item = $data['hierarchyTree'][0]['subs'][0]['activities'][0]['items'][0];

        $this->assertSame('Kertas HVS', $item->description);
        $this->assertSame(10.0, $item->volume);
        $this->assertSame('rim', $item->unit);
    }

    public function test_identity_fallback_ignores_other_years_money(): void
    {
        $this->seedAnggaran('ANG-OLD', ['ID_ANGGARAN' => 'ANG-OLD', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-NEW', ['ID_ANGGARAN' => 'ANG-NEW', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        $this->seedAnggaran('ANG-2024', ['ID_ANGGARAN' => 'ANG-2024', 'TAHUN_ANGGARAN' => 2024, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2024-10-12 08:00:00', 'CREATE_DATE' => '2024-06-10 05:00:00']);
        // Baris rapbs skema huruf kecil tanpa tahun sendiri (ikut scope anggaran).
        $this->seedRapbs('OLD-1', ['id_rapbs' => 'OLD-1', 'id_anggaran' => 'ANG-OLD', 'id_ref_kode' => 'REF-1', 'kode_rekening' => '5.1.02.01', 'uraian' => 'Batu Kali', 'jumlah' => 500000]);
        $this->seedRapbs('NEW-1', ['id_rapbs' => 'NEW-1', 'id_anggaran' => 'ANG-NEW', 'id_ref_kode' => 'REF-1', 'kode_rekening' => '5.1.02.01', 'uraian' => 'Batu Kali', 'jumlah' => 500000]);
        $this->seedRapbs('Y24-1', ['id_rapbs' => 'Y24-1', 'id_anggaran' => 'ANG-2024', 'id_ref_kode' => 'REF-1', 'kode_rekening' => '5.1.02.01', 'uraian' => 'Batu Kali', 'jumlah' => 9000000]);
        $this->seedKas('KAS-NEW', ['ID_RAPBS' => 'NEW-1', 'KATEGORI_BKU' => 'BELANJA', 'ID_REF_SUMBER_DANA' => 1, 'JUMLAH' => 200000]);
        $this->seedKas('KAS-2024', ['ID_RAPBS' => 'Y24-1', 'KATEGORI_BKU' => 'BELANJA', 'ID_REF_SUMBER_DANA' => 1, 'JUMLAH' => 8000000]);

        $old = app(ArkasMirrorBudgetService::class)->snapshot(1, 2026, ['ANG-OLD']);

        $this->assertSame(200000.0, $old['realization_fallback']['OLD-1']);
    }

    public function test_soft_deleted_periode_splits_are_excluded_from_snapshot(): void
    {
        $this->seedRapbs('RAPBS-1', ['ID_RAPBS' => 'RAPBS-1', 'ID_ANGGARAN' => 'ANG-1', 'KODE_REKENING' => '5.1.02.01', 'JUMLAH' => 1000000]);
        $this->seedRapbs('RAPBS-DEL', ['ID_RAPBS' => 'RAPBS-DEL', 'ID_ANGGARAN' => 'ANG-1', 'KODE_REKENING' => '5.1.02.02', 'JUMLAH' => 500000, 'SOFT_DELETE' => 1]);
        $this->seedRapbsPeriode('PER-1', ['ID_RAPBS_PERIODE' => 'PER-1', 'ID_RAPBS' => 'RAPBS-1', 'ID_PERIODE' => '87', 'JUMLAH' => 1000000]);
        $this->seedRapbsPeriode('PER-2', ['ID_RAPBS_PERIODE' => 'PER-2', 'ID_RAPBS' => 'RAPBS-1', 'ID_PERIODE' => '86', 'JUMLAH' => 1000000, 'SOFT_DELETE' => 1]);

        $snapshot = app(ArkasMirrorBudgetService::class)->snapshot(1, 2026, ['ANG-1']);

        $this->assertCount(1, $snapshot['rows']);
        $periods = $snapshot['rows']->first()['periods'];
        $this->assertCount(1, $periods);
        $this->assertSame('PER-1', $periods[0]['ID_RAPBS_PERIODE']);
    }
}
