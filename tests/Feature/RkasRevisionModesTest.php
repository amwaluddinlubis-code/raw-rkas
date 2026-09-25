<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use App\Services\ArkasMirrorBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'IS_REVISI' => 0, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'IS_REVISI' => 100, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);
        $this->seedAnggaran('ANG-3', ['ID_ANGGARAN' => 'ANG-3', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 0, 'IS_REVISI' => 101, 'LAST_UPDATE' => '2026-09-20 10:00:00', 'CREATE_DATE' => '2026-09-20 08:00:00']);

        $tabs = app(ArkasMirrorBudgetService::class)->revisions(1, 2026);

        $this->assertSame(['ANG-1', 'ANG-2', 'ANG-3'], array_column($tabs, 'id'));
        $this->assertSame(['approved', 'approved', 'pending'], array_column($tabs, 'status'));
    }

    public function test_revisions_omits_pending_tab_when_all_approved(): void
    {
        $this->seedAnggaran('ANG-1', ['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-05-06 08:00:00', 'CREATE_DATE' => '2026-04-16 05:00:00']);
        $this->seedAnggaran('ANG-2', ['ID_ANGGARAN' => 'ANG-2', 'TAHUN_ANGGARAN' => 2026, 'ID_REF_SUMBER_DANA' => 1, 'IS_AKTIF' => 1, 'IS_APPROVE' => 1, 'LAST_UPDATE' => '2026-09-18 20:00:00', 'CREATE_DATE' => '2026-09-17 12:00:00']);

        $tabs = app(ArkasMirrorBudgetService::class)->revisions(1, 2026);

        $this->assertSame(['ANG-1', 'ANG-2'], array_column($tabs, 'id'));
        $this->assertSame(['approved', 'approved'], array_column($tabs, 'status'));
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
