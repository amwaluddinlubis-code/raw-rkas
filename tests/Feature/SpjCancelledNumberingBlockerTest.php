<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjNumberingGateService;
use App\Services\SpjNumberingOrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

/**
 * CANCELLED adalah status terminal: paket yang dibatalkan tidak pernah bisa
 * FINAL sehingga tidak boleh memblokir penomoran triwulan berikutnya maupun
 * penutupan triwulan (T1).
 */
class SpjCancelledNumberingBlockerTest extends TestCase
{
    use SeedsArkasMirror;

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

    public function test_single_numbering_blocker_ignores_cancelled_packages(): void
    {
        $year = $this->year();
        $cancelled = $this->package($year, 'B-001', '2026-01-05', '100');
        $target = $this->package($year, 'B-002', '2026-01-10', '200');
        $cancelled->forceFill(['status' => 'CANCELLED'])->save();
        session()->put(['active_fiscal_year_id' => $year->id, 'active_fund_source_id' => 1]);

        $blocker = app(SpjNumberingOrderService::class)->singleNumberingBlocker($target->fresh(), ['SPJ']);

        $this->assertNull($blocker, 'Paket CANCELLED tidak boleh memblokir penomoran satuan paket lain.');
    }

    public function test_previous_quarter_final_blocker_ignores_cancelled_packages(): void
    {
        $year = $this->year();
        $cancelled = $this->package($year, 'B-001', '2026-01-15', '100');
        $cancelled->forceFill(['status' => 'CANCELLED'])->save();
        session()->put(['active_fiscal_year_id' => $year->id, 'active_fund_source_id' => 1]);

        $blocker = app(SpjNumberingGateService::class)->previousQuarterFinalBlocker(2);

        $this->assertNull($blocker, 'Paket CANCELLED triwulan sebelumnya tidak boleh memblokir penomoran triwulan berjalan.');
    }

    public function test_close_period_ignores_cancelled_packages(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'B-001', '2026-01-15', '100');
        $package->forceFill(['status' => 'CANCELLED'])->save();

        $workflow = app(FiscalPeriodWorkflowService::class);
        $period = $workflow->markNumbered($workflow->period($year->id, 1), 1);

        $closed = $workflow->close($period->fresh(), 1, 1);

        $this->assertSame('CLOSED', $closed->status);
    }

    public function test_finalize_package_still_rejects_cancelled(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'B-001', '2026-01-05', '100');
        $package->forceFill(['status' => 'CANCELLED'])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hanya paket NUMBERED yang dapat difinalkan.');

        app(SpjDocumentLifecycleService::class)->finalizePackage($package->fresh(), 1);
    }

    private function year(): FiscalYear
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);

        return FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    private function package(FiscalYear $year, string $proofNumber, string $date, ?string $sourceId = null): SpjPackage
    {
        return $this->packageWithSourceItems($year, $proofNumber, $date, $sourceId, [$sourceId]);
    }

    /** @param array<int, string|null> $sourceItemIds */
    private function packageWithSourceItems(FiscalYear $year, string $proofNumber, string $date, ?string $sourceId, array $sourceItemIds): SpjPackage
    {
        $normalizedSourceIds = collect($sourceItemIds)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->sort()
            ->values()
            ->all();
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => $sourceId,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'source_key' => $normalizedSourceIds !== [] ? hash('sha256', implode('|', $normalizedSourceIds)) : null,
        ]);
        foreach ($sourceItemIds as $sourceItemId) {
            $this->mirrorItem($transaction, [
                'source_item_id' => $sourceItemId,
                'description' => 'Barang',
                'amount' => 1000,
            ]);
        }

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
