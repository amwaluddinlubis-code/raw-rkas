<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjSourceReconciliationService;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrossYearArtifactQuarantineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);

        DB::connection('school')->table('fund_sources')->insert([
            'id' => 1, 'code' => 'BOSP', 'name' => 'BOSP', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('school')->table('fiscal_years')->insert([
            'id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_cross_year_baseline_is_quarantined_from_actionable_reconciliation(): void
    {
        $transaction = $this->transaction();
        $this->event($transaction->id, ['transaction_date' => '2024-03-26', 'description' => 'Upah Tukang'], ['transaction_date' => '2026-04-07', 'description' => 'Proyektor']);

        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction->fresh());

        $this->assertNull($report['latest']);
        $this->assertFalse($report['requires_reconciliation']);
        $this->assertFalse($report['needs_attention']);
        $this->assertTrue($report['has_cross_year_artifacts']);
        $this->assertSame(1, $report['artifact_count']);
        $this->assertNull($report['action_hint']);

        $html = view('transactions.partials.detail.source-reconciliation', ['transaction' => $transaction->fresh()])->render();

        $this->assertStringContainsString('REKONSILIASI SELESAI', $html);
        $this->assertStringNotContainsString('Field sumber berubah', $html);
        $this->assertStringContainsString('artefak pembanding lintas tahun', $html);
        $this->assertStringContainsString('Tutup artefak tanpa mengubah paket SPJ', $html);
    }

    public function test_same_year_change_remains_actionable_alongside_artifact(): void
    {
        $transaction = $this->transaction();
        $this->event($transaction->id, ['transaction_date' => '2024-03-26'], ['transaction_date' => '2026-04-07']);
        $this->event($transaction->id, ['transaction_date' => '2026-04-07', 'gross_amount' => 1000000], ['transaction_date' => '2026-04-07', 'gross_amount' => 1200000]);

        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction->fresh());

        $this->assertNotNull($report['latest']);
        $this->assertTrue($report['requires_reconciliation']);
        $this->assertSame(1, $report['artifact_count']);
        $this->assertSame(1200000.0, (float) $report['latest']->after['gross_amount']);
    }

    public function test_dismiss_clears_flag_with_audit_without_touching_package(): void
    {
        $transaction = $this->transaction('DRAFT');
        $packageId = DB::connection('school')->table('spj_packages')->insertGetId([
            'transaction_id' => $transaction->id, 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->event($transaction->id, ['transaction_date' => '2024-03-26'], ['transaction_date' => '2026-04-07']);

        $dismissed = app(SpjSourceReconciliationService::class)->dismissCrossYearArtifacts(
            $transaction->fresh(), app(OperationalAuditService::class), 7
        );

        $this->assertSame(1, $dismissed);
        $this->assertFalse((bool) $transaction->fresh()->requires_reconciliation);
        $this->assertSame('DRAFT', DB::connection('school')->table('spj_packages')->where('id', $packageId)->value('status'));
        $this->assertDatabaseHas('operational_audit_logs', [
            'entity_type' => 'TRANSACTION', 'entity_id' => $transaction->id, 'action' => 'REKONSILIASI_ARTEFAK_DITUTUP',
        ], 'school');
        $this->assertSame(0, DB::connection('school')->table('transaction_source_reconciliations')->count());
    }

    public function test_dismiss_refuses_when_actionable_change_exists(): void
    {
        $transaction = $this->transaction();
        $this->event($transaction->id, ['transaction_date' => '2026-04-07', 'gross_amount' => 1000000], ['transaction_date' => '2026-04-07', 'gross_amount' => 1200000]);

        $this->expectException(DomainException::class);

        app(SpjSourceReconciliationService::class)->dismissCrossYearArtifacts(
            $transaction->fresh(), app(OperationalAuditService::class), 7
        );
    }

    public function test_resolve_refuses_when_only_artifacts_remain(): void
    {
        $transaction = $this->transaction();
        $this->event($transaction->id, ['transaction_date' => '2024-03-26'], ['transaction_date' => '2026-04-07']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak lagi memerlukan rekonsiliasi');

        app(SpjSourceReconciliationService::class)->resolve($transaction->fresh(), SpjSourceReconciliationService::KEEP_OVERLAY, null, 7);
    }

    private function transaction(string $packageStatus = 'DRAFT'): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-'.uniqid(),
            'source_hash' => str_repeat('b', 64),
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => true,
        ]);
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function event(int $transactionId, array $before, array $after): int
    {
        return DB::connection('school')->table('transaction_source_events')->insertGetId([
            'transaction_id' => $transactionId,
            'event_type' => 'SOURCE_CHANGED',
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
