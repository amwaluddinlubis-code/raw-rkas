<?php

namespace App\UseCases\Spj;

use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjSourceReconciliationService;
use App\Support\ActiveSpjContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BulkReviewMetadataReconciliationUseCase
{
    private const BATCH_LIMIT = 200;

    public function __construct(
        private readonly SpjSourceReconciliationService $reconciliation,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function handle(Request $request): RedirectResponse
    {
        $user = $request->user() ?? auth()->user();
        if (! $user || ! $user->isOperatorOrAdministrator()) {
            return back()->with('error', 'Hanya operator atau administrator yang dapat meninjau rekonsiliasi.');
        }

        $candidates = Transaction::query()
            ->activeContext()
            ->where('requires_reconciliation', true)
            ->where('source_status', '!=', 'SOURCE_MISSING')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT + 1)
            ->get();

        $capped = $candidates->count() > self::BATCH_LIMIT;
        $reviewed = 0;
        $skipped = 0;

        foreach ($candidates->take(self::BATCH_LIMIT) as $transaction) {
            if (! $this->context->matchesTransaction($transaction)) {
                $skipped++;

                continue;
            }
            $report = $this->reconciliation->forTransaction($transaction->load('spjPackage'));
            $latest = $report['latest'];
            if ($latest === null || $latest->changes !== []) {
                // Ada perubahan nilai bisnis (atau tanpa event): wajib keputusan
                // individual, tidak boleh ikut tinjau massal.
                $skipped++;

                continue;
            }

            try {
                $result = $this->reconciliation->resolve(
                    $transaction,
                    SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
                    'Tinjau massal metadata: '.$latest->label.'.',
                    $user->id,
                    (int) $latest->id,
                );
            } catch (DomainException) {
                $skipped++;

                continue;
            }

            $this->audit->record(
                $transaction->fiscal_year_id,
                'TRANSACTION',
                $transaction->id,
                'RESOLUSI_REKONSILIASI_MASSAL',
                'Rekonsiliasi '.$result['label'].' via tinjau massal.'
            );
            $reviewed++;
        }

        $message = "Tinjau massal selesai: {$reviewed} transaksi ditandai sudah ditinjau.";
        if ($skipped > 0) {
            $message .= " {$skipped} dilewati (ada perubahan nilai bisnis / di luar konteks) dan tetap perlu keputusan individual.";
        }
        if ($capped) {
            $message .= ' Batas '.self::BATCH_LIMIT.' transaksi per proses; ulangi untuk sisanya.';
        }

        return back()->with($reviewed > 0 ? 'success' : 'warning', $message);
    }
}
