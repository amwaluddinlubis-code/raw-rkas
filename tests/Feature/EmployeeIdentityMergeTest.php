<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\EmployeeIdentityService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeIdentityMergeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_backfill_fills_missing_normalized_names(): void
    {
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:1', 'name' => 'Rista Dewi',
        ]);

        $affected = app(EmployeeIdentityService::class)->backfillNormalizedNames();

        $this->assertSame(1, $affected);
        $this->assertSame('rista dewi', Employee::query()->firstOrFail()->normalized_name);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->seedDuplicatePair();

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['groups']);
        $this->assertSame(0, $report['merged']);
        $this->assertSame(2, Employee::query()->count());
    }

    public function test_fuse_merges_dapodik_and_ptk_rows(): void
    {
        [$dapodik, $ptk] = $this->seedDuplicatePair();

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(1, $report['groups']);
        $this->assertSame(1, $report['merged']);
        $this->assertSame(1, Employee::query()->count());

        $kept = Employee::query()->firstOrFail();
        $this->assertSame($dapodik->id, $kept->id);
        $this->assertSame('DAPODIK', $kept->source_type);
        $this->assertSame('Bank Arkas', $kept->bank_name);
        $this->assertNotNull($kept->last_seen_arkas_at);
        $this->assertNotNull($kept->last_seen_dapodik_at);
        $this->assertSame('rista dewi', $kept->normalized_name);
    }

    public function test_upgrade_migration_fuses_existing_strong_identity_duplicates(): void
    {
        [$dapodik] = $this->seedDuplicatePair();

        $migration = require database_path('migrations/school/2026_09_10_125500_fuse_unified_employee_rows.php');
        $migration->up();

        $this->assertSame(1, Employee::query()->count());
        $kept = Employee::query()->firstOrFail();
        $this->assertSame($dapodik->id, $kept->id);
        $this->assertNotNull($kept->last_seen_arkas_at);
        $this->assertNotNull($kept->last_seen_dapodik_at);
        $this->assertSame('Bank Arkas', $kept->bank_name);
    }

    public function test_fuse_does_not_guess_legacy_identity_from_name_only(): void
    {
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:b', 'name' => 'RISTA DEWI',
            'normalized_name' => 'rista dewi',
        ]);
        Employee::query()->create([
            'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:a', 'name' => 'Rista Dewi',
            'normalized_name' => 'rista dewi', 'dapodik_id' => 'uuid-sama',
        ]);

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(0, $report['groups']);
        $this->assertSame(0, $report['merged']);
        $this->assertSame(2, Employee::query()->count());
    }

    public function test_find_match_prefers_nuptk_then_unique_name(): void
    {
        $employee = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:9', 'name' => 'Satrizal',
            'nuptk' => '123456', 'normalized_name' => 'satrizal',
        ]);

        $identity = app(EmployeeIdentityService::class);

        $this->assertSame($employee->id, $identity->findMatch('123456', null, null, 'Nama Berbeda')->id);
        $this->assertSame($employee->id, $identity->findMatch(null, null, null, 'SATRIZAL')->id);
        $this->assertNull($identity->findMatch(null, null, null, 'Orang Asing'));
        $this->assertNull($identity->findMatch(null, null, null, '-'));
    }

    public function test_find_match_does_not_guess_when_normalized_name_is_ambiguous(): void
    {
        Employee::query()->create([
            'source_type' => 'ARKAS', 'source_key' => 'ARKAS:PTK:1', 'name' => 'Andi Saputra',
            'normalized_name' => 'andi saputra', 'nip' => '111',
        ]);
        Employee::query()->create([
            'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:2', 'name' => 'ANDI SAPUTRA',
            'normalized_name' => 'andi saputra', 'nip' => '222',
        ]);

        $this->assertNull(app(EmployeeIdentityService::class)->findMatch(null, null, null, 'Andi Saputra'));
    }

    public function test_sweep_deactivates_only_rows_unseen_by_all_sources(): void
    {
        $stale = now()->subDays(500);
        $fresh = now();

        $gone = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:old', 'name' => 'Sudah Pindah',
            'normalized_name' => 'sudah pindah', 'last_seen_arkas_at' => $stale,
        ]);
        $keptArkas = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:new', 'name' => 'Masih Ada',
            'normalized_name' => 'masih ada', 'last_seen_arkas_at' => $fresh,
        ]);
        $keptManual = Employee::query()->create([
            'source_type' => 'MANUAL', 'source_key' => 'MANUAL:x', 'name' => 'Honorer Manual',
            'normalized_name' => 'honorer manual', 'operator_locked' => true,
        ]);
        $keptCross = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:cross', 'name' => 'Lintas Sumber',
            'normalized_name' => 'lintas sumber', 'last_seen_arkas_at' => $stale, 'last_seen_dapodik_at' => $fresh,
        ]);

        $swept = app(EmployeeIdentityService::class)->sweepAfterSync([$keptArkas->id, $keptCross->id], 'arkas', now());

        $this->assertSame(1, $swept);
        $this->assertFalse(Employee::query()->findOrFail($gone->id)->is_active);
        $this->assertTrue(Employee::query()->findOrFail($keptArkas->id)->is_active);
        $this->assertTrue(Employee::query()->findOrFail($keptManual->id)->is_active);
        $this->assertTrue(Employee::query()->findOrFail($keptCross->id)->is_active);
    }

    public function test_sweep_with_empty_feed_writes_nothing(): void
    {
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:1', 'name' => 'Jangan Hapus',
            'normalized_name' => 'jangan hapus', 'last_seen_arkas_at' => now()->subDays(500),
        ]);

        $this->assertSame(0, app(EmployeeIdentityService::class)->sweepAfterSync([], 'arkas', now()));
        $this->assertTrue(Employee::query()->firstOrFail()->is_active);
    }

    public function test_locked_rows_are_never_swept(): void
    {
        $locked = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:lock', 'name' => 'Dikunci Operator',
            'normalized_name' => 'dikunci operator', 'last_seen_arkas_at' => now()->subDays(500),
            'operator_locked' => true,
        ]);

        app(EmployeeIdentityService::class)->sweepAfterSync([999999], 'arkas', now());

        $this->assertTrue(Employee::query()->findOrFail($locked->id)->is_active);
    }

    public function test_effective_active_keeps_employee_active_when_any_known_source_is_active(): void
    {
        $identity = app(EmployeeIdentityService::class);

        $this->assertTrue($identity->effectiveActive(true, true));
        $this->assertTrue($identity->effectiveActive(true, null));
        $this->assertTrue($identity->effectiveActive(null, true));
        $this->assertTrue($identity->effectiveActive(null, null));
        $this->assertTrue($identity->effectiveActive(false, true));
        $this->assertTrue($identity->effectiveActive(true, false));
        $this->assertFalse($identity->effectiveActive(false, false));
        $this->assertFalse($identity->effectiveActive(false, null));
        $this->assertFalse($identity->effectiveActive(null, false));
    }

    public function test_fuse_merges_titled_variants_with_complementary_ids(): void
    {
        $pegawai = Employee::query()->create([
            'source_type' => 'PEGAWAI', 'source_key' => 'ARKAS:PEGAWAI:1', 'name' => 'TARMINI',
            'normalized_name' => 'tarmini', 'nuptk' => '2663764665300022',
            'last_seen_arkas_at' => now(), 'last_known_active_arkas' => true,
        ]);
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'ARKAS:PTK:2', 'name' => 'TARMINI, S.Pd.',
            'normalized_name' => 'tarmini s pd', 'nip' => '198603312022212019',
            'position' => 'BENDAHARA BOS',
            'last_seen_arkas_at' => now(), 'last_known_active_arkas' => true,
        ]);

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(1, $report['groups']);
        $this->assertSame(1, $report['merged']);
        $this->assertSame(1, Employee::query()->count());

        $kept = Employee::query()->firstOrFail();
        $this->assertSame($pegawai->id, $kept->id);
        $this->assertSame('2663764665300022', $kept->nuptk);
        $this->assertSame('198603312022212019', $kept->nip);
        $this->assertSame('BENDAHARA BOS', $kept->position);
    }

    public function test_fuse_skips_titled_variants_with_conflicting_ids(): void
    {
        Employee::query()->create([
            'source_type' => 'PEGAWAI', 'source_key' => 'ARKAS:PEGAWAI:1', 'name' => 'TARMINI',
            'normalized_name' => 'tarmini', 'nip' => '111',
        ]);
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'ARKAS:PTK:2', 'name' => 'TARMINI, S.Pd.',
            'normalized_name' => 'tarmini s pd', 'nip' => '222', 'nuptk' => '999',
        ]);

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(0, $report['groups']);
        $this->assertSame(2, Employee::query()->count());
    }

    public function test_fuse_skips_titled_variants_without_ids(): void
    {
        Employee::query()->create([
            'source_type' => 'ARKAS', 'source_key' => 'ARKAS:1', 'name' => 'SUTRI BATUBARA',
            'normalized_name' => 'sutri batubara',
        ]);
        Employee::query()->create([
            'source_type' => 'ARKAS', 'source_key' => 'ARKAS:2', 'name' => 'Sutri Batubara, S.Pd.',
            'normalized_name' => 'sutri batubara s pd',
        ]);

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(0, $report['groups']);
        $this->assertSame(2, Employee::query()->count());
    }

    public function test_fuse_skips_titled_variants_with_manual_or_locked_rows(): void
    {
        Employee::query()->create([
            'source_type' => 'MANUAL', 'source_key' => 'MANUAL:1', 'name' => 'TARMINI',
            'normalized_name' => 'tarmini', 'nuptk' => '2663764665300022',
        ]);
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'ARKAS:PTK:2', 'name' => 'TARMINI, S.Pd.',
            'normalized_name' => 'tarmini s pd', 'nip' => '198603312022212019',
        ]);
        Employee::query()->create([
            'source_type' => 'PEGAWAI', 'source_key' => 'ARKAS:PEGAWAI:3', 'name' => 'UBAIDAH NASUTION',
            'normalized_name' => 'ubaidah nasution', 'nuptk' => '9533748649300012',
            'operator_locked' => true,
        ]);
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'ARKAS:PTK:4', 'name' => 'UBAIDAH NASUTION, S.Pd.',
            'normalized_name' => 'ubaidah nasution s pd', 'nip' => '197002012000032007',
        ]);

        $report = app(EmployeeIdentityService::class)->fuseDuplicates(false);

        $this->assertSame(0, $report['groups']);
        $this->assertSame(4, Employee::query()->count());
    }

    public function test_core_name_strips_titles(): void
    {
        $identity = app(EmployeeIdentityService::class);

        $this->assertSame('tarmini', $identity->coreName('TARMINI, S.Pd.'));
        $this->assertSame('ubaidah nasution', $identity->coreName('UBAIDAH NASUTION, S.Pd.'));
        $this->assertSame('muhammad hidayat', $identity->coreName('H. Muhammad Hidayat, M.Pd.'));
        $this->assertSame('sutri batubara', $identity->coreName('SUTRI BATUBARA'));
    }

    /** @return array{0: Employee, 1: Employee} */
    private function seedDuplicatePair(): array
    {
        $dapodik = Employee::query()->create([
            'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:gtk-1', 'name' => 'Rista Dewi',
            'normalized_name' => 'rista dewi', 'nuptk' => '654321',
            'last_seen_dapodik_at' => now(), 'last_known_active_dapodik' => true,
        ]);
        $ptk = Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:77', 'name' => 'RISTA DEWI',
            'nuptk' => '654321', 'bank_name' => 'Bank Arkas',
            'last_seen_arkas_at' => now(), 'last_known_active_arkas' => true,
        ]);

        return [$dapodik, $ptk];
    }
}
