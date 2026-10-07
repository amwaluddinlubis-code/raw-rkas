<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingPolicyService;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjDocumentLifecycleUseCase;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

/**
 * replaceDocument() harus atomik: cancel dokumen lama dan assign nomor
 * pengganti berjalan dalam satu transaksi DB (S2). Bila assign gagal,
 * cancel ikut di-rollback sehingga paket tidak tertinggal CANCELLED
 * tanpa pengganti.
 */
class SpjDocumentReplaceAtomicityTest extends TestCase
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

    public function test_replace_document_rolls_back_cancel_when_replacement_numbering_fails(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'B-001', '2026-01-05', '100');
        $document = app(SpjDocumentNumberService::class)->assign($package, 'SPJ', Carbon::parse('2026-01-05'), '10200001');
        $this->assertSame('NUMBERED', $document->status);

        $this->bindAdminContext($year);
        $this->app->instance(SpjDocumentNumberService::class, new class(app(SpjNumberingPolicyService::class)) extends SpjDocumentNumberService
        {
            public function assign(
                SpjPackage $package,
                string $documentType,
                CarbonInterface $documentDate,
                string $schoolCode,
                string $scopeKey = 'MAIN',
                ?int $templateId = null,
                ?string $npsn = null,
            ): SpjDocument {
                throw new RuntimeException('simulasi kegagalan assign');
            }
        });

        $useCase = $this->app->make(SpjDocumentLifecycleUseCase::class);
        $request = Request::create('/spj/dokumen/'.$document->id.'/ganti', 'POST', ['reason' => 'uji atomic']);

        try {
            $useCase->replaceDocument($request, (string) $document->id);
            $this->fail('replaceDocument harus melempar saat assign pengganti gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulasi kegagalan assign', $exception->getMessage());
        }

        $this->assertSame('NUMBERED', $document->fresh()->status, 'Cancel harus ikut di-rollback bila assign gagal.');
        $this->assertSame('NUMBERED', $package->fresh()->status);
        $this->assertSame(1, $package->documents()->count(), 'Dokumen pengganti tidak boleh terbentuk saat assign gagal.');
        $this->assertFalse(
            DB::connection('school')->table('operational_audit_logs')->where('action', 'GANTI_DOKUMEN')->exists(),
            'Audit GANTI_DOKUMEN tidak boleh tercatat untuk penggantian yang gagal.'
        );
    }

    public function test_replace_document_succeeds_within_single_transaction(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'B-001', '2026-01-05', '100');
        $document = app(SpjDocumentNumberService::class)->assign($package, 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        $this->bindAdminContext($year);
        session()->setPreviousUrl('/spj/paket/uji');

        $response = $this->app->make(SpjDocumentLifecycleUseCase::class)
            ->replaceDocument(Request::create('/spj/dokumen/'.$document->id.'/ganti', 'POST', ['reason' => 'nomor rusak']), (string) $document->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('CANCELLED', $document->fresh()->status);

        $replacement = SpjDocument::query()->where('replaces_document_id', $document->id)->first();
        $this->assertNotNull($replacement, 'Dokumen pengganti harus terbentuk.');
        $this->assertSame('NUMBERED', $replacement->status);
        $this->assertSame(2, $replacement->sequence_number);
        $this->assertTrue((bool) $replacement->is_late_entry);
        $this->assertSame('NUMBERED', $package->fresh()->status);
        $this->assertTrue(
            DB::connection('school')->table('operational_audit_logs')->where('action', 'GANTI_DOKUMEN')->exists(),
            'Audit GANTI_DOKUMEN harus tercatat untuk penggantian yang berhasil.'
        );
    }

    private function bindAdminContext(FiscalYear $year): void
    {
        School::query()->create(['npsn' => '10200001', 'name' => 'Sekolah Uji']);
        $this->app->instance(ActiveSpjContext::class, new ActiveSpjContext(
            schoolIdOverride: 1,
            fiscalYearIdOverride: $year->id,
            fundSourceIdOverride: 1,
            actorIdOverride: 1,
            administratorOverride: true,
        ));
    }

    private function year(): FiscalYear
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);

        return FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    private function package(FiscalYear $year, string $proofNumber, string $date, ?string $sourceId = null): SpjPackage
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => $sourceId,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'source_key' => hash('sha256', (string) $sourceId),
        ]);
        $this->mirrorItem($transaction, [
            'source_item_id' => $sourceId,
            'description' => 'Barang',
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
