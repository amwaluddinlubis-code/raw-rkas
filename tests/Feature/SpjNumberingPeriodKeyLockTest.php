<?php

namespace Tests\Feature;

use App\Models\DocumentNumberFormat;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjNumberingRollbackUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

/**
 * R6: rebuildSequences() harus memakai period_key yang tersimpan saat nomor
 * diterbitkan, bukan reset_period format yang berlaku saat ini. Bila operator
 * mengubah reset_period setelah nomor terbit, rebuild yang memakai periode
 * current akan menggabung grup yang salah dan berpotensi mengulang nomor.
 */
class SpjNumberingPeriodKeyLockTest extends TestCase
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

    public function test_assign_stores_numbering_period_key(): void
    {
        [$year, $fund] = $this->contextData('MONTH');
        $package = $this->package($year, $fund->id, '2026-01-10');

        $document = app(SpjDocumentNumberService::class)->assign($package, 'SPJ', $package->transaction->sourceCarbon(), '10200001');

        $this->assertSame('2026-01', $document->fresh()->numbering_period_key);
    }

    public function test_rebuild_after_reset_period_change_keeps_issuance_periods(): void
    {
        [$year, $fund] = $this->contextData('MONTH');
        $numbers = app(SpjDocumentNumberService::class);

        // Reset bulanan: Jan -> 2 dokumen (1, 2), Feb -> 1 dokumen (1).
        $jan1 = $this->package($year, $fund->id, '2026-01-10');
        $jan2 = $this->package($year, $fund->id, '2026-01-11');
        $feb1 = $this->package($year, $fund->id, '2026-02-10');
        $numbers->assign($jan1, 'SPJ', $jan1->transaction->sourceCarbon(), '10200001');
        $numbers->assign($jan2, 'SPJ', $jan2->transaction->sourceCarbon(), '10200001');
        $numbers->assign($feb1, 'SPJ', $feb1->transaction->sourceCarbon(), '10200001');

        // Operator mengubah reset menjadi tahunan SETELAH nomor terbit.
        DocumentNumberFormat::query()->where('fiscal_year_id', $year->id)->update(['reset_period' => 'YEAR']);

        // Rollback dari nomor urut 2 menghapus dokumen Jan kedua; rebuild jalan.
        $this->rollbackUseCase($year->id, $fund->id)->rollbackFromSequence($this->request([
            'sequence_number' => 2,
            'reason' => 'Koreksi data',
        ]));

        $sequences = DB::connection('school')->table('document_number_sequences')
            ->where('fiscal_year_id', $year->id)
            ->where('fund_source_id', $fund->id)
            ->where('format_name', 'SPJ')
            ->pluck('last_number', 'period_key')
            ->all();

        // Periode penerbitan dipertahankan: tidak ada grup '2026' gabungan.
        $this->assertSame(1, $sequences['2026-01'] ?? null);
        $this->assertSame(1, $sequences['2026-02'] ?? null);
        $this->assertArrayNotHasKey('2026', $sequences);
    }

    private function contextData(string $resetPeriod): array
    {
        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
        ]);
        DocumentNumberFormat::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
            'format_pattern' => '{SEQ}/SPJ/{MONTH}/{YEAR}',
            'reset_period' => $resetPeriod,
            'padding' => 4,
            'is_active' => true,
        ]);

        return [$year, $fund];
    }

    private function package(FiscalYear $year, int $fundSourceId, string $date): SpjPackage
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fundSourceId,
            'id_kas_umum' => uniqid('KAS-', true),
            'no_bukti' => uniqid('BPU-', true),
            'transaction_date' => $date,
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'BARANG',
        ]);
        $this->mirrorItem($transaction, [
            'description' => 'Barang',
            'item_description' => 'Barang',
            'quantity' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY'])->load('transaction');
    }

    private function rollbackUseCase(int $yearId, int $fundSourceId): SpjNumberingRollbackUseCase
    {
        return new SpjNumberingRollbackUseCase(
            app(OperationalAuditService::class),
            new ActiveSpjContext(1, $yearId, $fundSourceId, 99, true),
        );
    }

    private function request(array $data): Request
    {
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        return $request;
    }
}
