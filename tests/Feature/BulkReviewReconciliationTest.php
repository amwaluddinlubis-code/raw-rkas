<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class BulkReviewReconciliationTest extends TestCase
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

    private function flagWithEvent(int $transactionId, array $before, array $after): int
    {
        DB::connection('school')->table('transactions')->where('id', $transactionId)->update([
            'requires_reconciliation' => true,
            'source_status' => 'ACTIVE',
        ]);

        return (int) DB::connection('school')->table('transaction_source_events')->insertGetId([
            'transaction_id' => $transactionId,
            'event_type' => 'SOURCE_CHANGED',
            'before_hash' => hash('sha256', json_encode($before)),
            'after_hash' => hash('sha256', json_encode($after)),
            'before_snapshot' => json_encode($before),
            'after_snapshot' => json_encode($after),
            'created_at' => now(),
        ]);
    }

    public function test_bulk_review_resolves_metadata_only_and_skips_business_diff(): void
    {
        $snapshot = ['gross_amount' => 500000, 'description' => 'Belanja ATK', 'recipient_name' => 'Toko Maju'];
        $meta = $this->mirrorTransaction([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BPU30',
            'transaction_date' => '2026-02-10', 'gross_amount' => 500000, 'net_amount' => 500000,
        ]);
        $this->flagWithEvent($meta->id, $snapshot, $snapshot);

        $bizBefore = ['gross_amount' => 500000, 'description' => 'Belanja ATK'];
        $bizAfter = ['gross_amount' => 450000, 'description' => 'Belanja ATK'];
        $biz = $this->mirrorTransaction([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BPU31',
            'transaction_date' => '2026-02-11', 'gross_amount' => 500000, 'net_amount' => 500000,
        ]);
        $this->flagWithEvent($biz->id, $bizBefore, $bizAfter);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OPERATOR]))
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('reconciliation.bulk-review'))
            ->assertSessionHas('success');

        $this->assertFalse((bool) $meta->fresh()->requires_reconciliation);
        $this->assertTrue((bool) $biz->fresh()->requires_reconciliation);
        $this->assertSame(
            1,
            DB::connection('school')->table('transaction_source_reconciliations')->where('transaction_id', $meta->id)->count()
        );
        $this->assertSame(
            'REVIEWED_NO_BUSINESS_CHANGE',
            DB::connection('school')->table('transaction_source_reconciliations')->where('transaction_id', $meta->id)->value('resolution')
        );
        $this->assertTrue(
            DB::connection('school')->table('operational_audit_logs')
                ->where('entity_type', 'TRANSACTION')->where('action', 'RESOLUSI_REKONSILIASI_MASSAL')->exists()
        );
    }

    public function test_viewer_cannot_bulk_review(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('reconciliation.bulk-review'))
            ->assertSessionHas('error');
    }
}
