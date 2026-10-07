<?php

namespace Tests\Feature;

use App\Livewire\SpjAttributeList;
use App\Livewire\SpjPreparationFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjPackagePeriodFilterTest extends TestCase
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

        session([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_package_period_modes_filter_by_mirror_month_without_error(): void
    {
        $march = $this->packagedTransaction('BPU-MAR', '2026-03-10');
        $june = $this->packagedTransaction('BPU-JUN', '2026-06-10');
        $september = $this->packagedTransaction('BPU-SEP', '2026-09-10');

        $marchIds = [$march->spjPackage->id];
        $juneIds = [$june->spjPackage->id];
        $septemberIds = [$september->spjPackage->id];

        $this->assertSame($marchIds, $this->packageIds(['mode' => 'triwulan', 'periode' => 1]));
        $this->assertSame($juneIds, $this->packageIds(['mode' => 'bulan', 'periode' => 6]));
        $this->assertEqualsCanonicalizing(
            array_merge($marchIds, $juneIds),
            $this->packageIds(['mode' => 'semester', 'periode' => 1])
        );
        $this->assertSame($septemberIds, $this->packageIds(['mode' => 'semester', 'periode' => 2]));
    }

    public function test_package_period_ignores_out_of_range_values(): void
    {
        $this->packagedTransaction('BPU-MAR', '2026-03-10');
        $this->packagedTransaction('BPU-JUN', '2026-06-10');

        $this->assertCount(2, $this->packageIds(['mode' => 'triwulan', 'periode' => 9]));
        $this->assertCount(2, $this->packageIds(['mode' => 'semua', 'periode' => null]));
    }

    public function test_attribute_period_modes_filter_by_mirror_month_without_error(): void
    {
        $march = $this->packagedTransaction('BPU-MAR', '2026-03-10');
        $june = $this->packagedTransaction('BPU-JUN', '2026-06-10');

        $attributeIds = fn (array $filters): array => app(SpjWorkspaceUseCase::class)
            ->attributeListData(15, $filters)->getCollection()->pluck('id')->all();

        $this->assertSame([$march->spjPackage->id], $attributeIds(['mode' => 'triwulan', 'periode' => 1]));
        $this->assertSame([$june->spjPackage->id], $attributeIds(['mode' => 'bulan', 'periode' => 6]));
    }

    public function test_attribute_period_select_only_renders_outside_semua_mode(): void
    {
        Livewire::test(SpjAttributeList::class)
            ->assertDontSee('id="spj-attribute-period"', false)
            ->set('mode', 'triwulan')
            ->assertSee('id="spj-attribute-period"', false);
    }

    public function test_preparation_mode_resets_period_like_other_tabs(): void
    {
        Livewire::test(SpjPreparationFilter::class)
            ->call('setMode', 'triwulan')
            ->assertSet('mode', 'triwulan')
            ->set('periode', 1)
            ->assertSet('periode', 1)
            ->call('setMode', 'bulan')
            ->assertSet('mode', 'bulan')
            ->assertSet('periode', null);
    }

    public function test_preparation_mount_maps_legacy_month_and_quarter_params(): void
    {
        Livewire::withQueryParams(['month' => 4])
            ->test(SpjPreparationFilter::class)
            ->assertSet('mode', 'bulan')
            ->assertSet('periode', 4);

        Livewire::withQueryParams(['quarter' => 2])
            ->test(SpjPreparationFilter::class)
            ->assertSet('mode', 'triwulan')
            ->assertSet('periode', 2);
    }

    /** @param array<string,mixed> $filters @return list<int> */
    private function packageIds(array $filters): array
    {
        return app(SpjWorkspaceUseCase::class)
            ->packageListData(15, $filters)->getCollection()->pluck('id')->all();
    }

    private function packagedTransaction(string $noBukti, string $date): Transaction
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $noBukti,
            'transaction_date' => $date,
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'spj_category' => 'BARANG',
        ]);

        $this->mirrorItem($transaction, [
            'description' => 'Barang uji',
            'item_description' => 'Barang uji',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        $transaction->spjPackage()->create([
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
            'status' => 'DRAFT',
        ]);

        return $transaction->refresh();
    }
}
