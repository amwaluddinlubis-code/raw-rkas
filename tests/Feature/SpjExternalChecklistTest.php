<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjExternalChecklistTick;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjExternalChecklistTest extends TestCase
{
    use SeedsArkasMirror;

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

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_operator_can_toggle_external_checklist_item(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU10',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 500000,
            'net_amount' => 500000,
            'spj_category' => 'BARANG',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.external-checklist.toggle', $package->id), [
                'item_key' => 'foto_barang',
                'pola' => 'pengadaan_penggandaan',
            ])
            ->assertRedirect(route('spj.checklist', ['packageId' => $package->id, 'pola' => 'pengadaan_penggandaan']));

        $this->assertTrue(
            SpjExternalChecklistTick::query()
                ->where('spj_package_id', $package->id)
                ->where('item_key', 'foto_barang')
                ->where('is_checked', true)
                ->exists()
        );

        // Toggle kedua membatalkan tanda.
        $this->actingAs($operator)
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.external-checklist.toggle', $package->id), ['item_key' => 'foto_barang'])
            ->assertRedirect();

        $this->assertFalse(
            SpjExternalChecklistTick::query()
                ->where('spj_package_id', $package->id)
                ->where('item_key', 'foto_barang')
                ->where('is_checked', true)
                ->exists()
        );
    }

    public function test_generated_item_and_locked_package_are_rejected(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU11',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 500000,
            'net_amount' => 500000,
            'spj_category' => 'BARANG',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'NUMBERED']);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        // Item GENERATED tidak bisa dicentang + paket NUMBERED terkunci.
        $this->actingAs($operator)
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.external-checklist.toggle', $package->id), ['item_key' => 'kwitansi_a2'])
            ->assertSessionHas('error');

        $this->assertSame(0, SpjExternalChecklistTick::query()->where('spj_package_id', $package->id)->count());
    }

    public function test_viewer_and_unknown_item_are_rejected(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU12',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 500000,
            'net_amount' => 500000,
            'spj_category' => 'KONSUMSI',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.external-checklist.toggle', $package->id), ['item_key' => 'daftar_hadir'])
            ->assertSessionHas('error');

        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.external-checklist.toggle', $package->id), ['item_key' => 'tidak_ada'])
            ->assertSessionHas('error');

        $this->assertSame(0, SpjExternalChecklistTick::query()->where('spj_package_id', $package->id)->count());
    }
}
