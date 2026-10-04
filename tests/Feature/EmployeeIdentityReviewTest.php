<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use App\Services\EmployeeIdentityReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeIdentityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOS', 'name' => 'BOS']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOS', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_ambiguous_groups_returns_only_multi_member_same_normalized_name(): void
    {
        Employee::query()->create(['name' => 'TARMINI', 'normalized_name' => 'tarmini', 'source_type' => 'ARKAS_PEGAWAI', 'source_key' => 'A1', 'nuptk' => '123']);
        Employee::query()->create(['name' => 'Tarmini, S.Pd.', 'normalized_name' => 'tarmini', 'source_type' => 'ARKAS_PTK', 'source_key' => 'A2', 'nip' => '456']);
        Employee::query()->create(['name' => 'Andi', 'normalized_name' => 'andi', 'source_type' => 'MANUAL', 'source_key' => 'M1']);

        $groups = app(EmployeeIdentityReviewService::class)->ambiguousGroups();

        $this->assertCount(1, $groups);
        $this->assertSame('tarmini', $groups[0]['key']);
        $this->assertCount(2, $groups[0]['members']);
    }

    public function test_identity_review_page_renders_group_and_members(): void
    {
        Employee::query()->create(['name' => 'BUDI', 'normalized_name' => 'budi', 'source_type' => 'ARKAS_PEGAWAI', 'source_key' => 'B1']);
        Employee::query()->create(['name' => 'Budi', 'normalized_name' => 'budi', 'source_type' => 'MANUAL', 'source_key' => 'B2']);

        $response = $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1, 'active_school_id' => 1])->get(route('employees.identity-review'));

        $response->assertOk();
        $response->assertSee('budi');
        $response->assertSee('ARKAS_PEGAWAI');
        $response->assertSee('MANUAL');
    }
}
