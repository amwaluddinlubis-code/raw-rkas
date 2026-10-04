<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArkasImportMonitorAndAuditTest extends TestCase
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

    public function test_import_monitor_page_renders(): void
    {
        $response = $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1, 'active_school_id' => 1])->get(route('arkas.import-monitor'));

        $response->assertOk();
    }

    public function test_mirror_audit_page_renders(): void
    {
        $response = $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1, 'active_school_id' => 1])->get(route('rkas-budget.audit'));

        $response->assertOk();
    }
}
