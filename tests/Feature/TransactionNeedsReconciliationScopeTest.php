<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class TransactionNeedsReconciliationScopeTest extends TestCase
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

    private function flagged(array $overrides = []): Transaction
    {
        return $this->mirrorTransaction(array_merge([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'transaction_date' => '2026-02-10',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => true,
        ], $overrides));
    }

    private function event(int $transactionId, int $id): void
    {
        DB::connection('school')->table('transaction_source_events')->insert([
            'id' => $id,
            'transaction_id' => $transactionId,
            'event_type' => 'SOURCE_CHANGED',
            'before_snapshot' => json_encode(['gross_amount' => 100000]),
            'after_snapshot' => json_encode(['gross_amount' => 100000]),
            'created_at' => now(),
        ]);
    }

    private function inScope(): array
    {
        return Transaction::query()->needsReconciliation()->pluck('id')->all();
    }

    public function test_unresolved_flagged_transaction_is_included(): void
    {
        $transaction = $this->flagged(['no_bukti' => 'BPU40']);
        $this->event($transaction->id, 1);

        $this->assertContains($transaction->id, $this->inScope());
    }

    public function test_resolved_latest_event_is_excluded(): void
    {
        $transaction = $this->flagged(['no_bukti' => 'BPU41']);
        $this->event($transaction->id, 2);
        DB::connection('school')->table('transaction_source_reconciliations')->insert([
            'transaction_id' => $transaction->id,
            'source_event_id' => 2,
            'resolution' => 'REVIEWED_NO_BUSINESS_CHANGE',
            'source_hash' => $transaction->source_hash,
            'resolved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotContains($transaction->id, $this->inScope());
    }

    public function test_resolution_for_older_event_keeps_latest_unresolved_included(): void
    {
        $transaction = $this->flagged(['no_bukti' => 'BPU42']);
        $this->event($transaction->id, 3);
        $this->event($transaction->id, 4);
        DB::connection('school')->table('transaction_source_reconciliations')->insert([
            'transaction_id' => $transaction->id,
            'source_event_id' => 3,
            'resolution' => 'REVIEWED_NO_BUSINESS_CHANGE',
            'source_hash' => $transaction->source_hash,
            'resolved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertContains($transaction->id, $this->inScope());
    }

    public function test_source_missing_is_included_without_flag(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU43',
            'transaction_date' => '2026-02-10',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'source_status' => 'SOURCE_MISSING',
            'requires_reconciliation' => false,
        ]);

        $this->assertContains($transaction->id, $this->inScope());
    }
}
