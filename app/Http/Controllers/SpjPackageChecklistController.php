<?php

namespace App\Http\Controllers;

use App\Models\SpjPackage;
use App\Models\User;
use App\Services\SpjDocumentRequirementService;
use App\Services\SpjExternalChecklistPatterns;
use App\Services\SpjOperatorHintService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjProcurementPolicyService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpjPackageChecklistController extends Controller
{
    public function __invoke(
        string $packageId,
        SpjPackageValidationService $validator,
        SpjDocumentRequirementService $requirements,
        SpjProcurementPolicyService $procurement,
        SpjOperatorHintService $operatorHints,
        ActiveSpjContext $context,
    ): View|RedirectResponse {
        $package = SpjPackage::query()
            ->with([
                'transaction.items',
                'transaction.goods',
                'transaction.workOrder',
                'transaction.workers',
                'transaction.participants',
                'transaction.travels',
                'transaction.honors',
                'transaction.payments',
                'transaction.goodsReceipts',
            ])
            ->find($packageId);

        if (! $package || ! $package->transaction || ! $context->matchesTransaction($package->transaction)) {
            return redirect()
                ->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket SPJ tidak ditemukan pada konteks aktif.');
        }

        $checklist = collect($validator->checklist($package));
        $totalChecks = $checklist->count();
        $completedChecks = $checklist->where('passed', true)->count();
        $remainingChecks = $totalChecks - $completedChecks;
        $progress = $totalChecks > 0 ? (int) round(($completedChecks / $totalChecks) * 100) : 100;

        $documentRequirements = collect($requirements->forTransaction($package->transaction));
        $requirementSummary = $requirements->summary($package->transaction);

        $canEdit = in_array(auth()->user()?->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true);
        $canMarkReady = $canEdit
            && $package->status === 'DRAFT'
            && $remainingChecks === 0
            && $requirementSummary['missing_required'] === 0;

        // Checklist bukti dukung eksternal (poster 10 pola): informatif,
        // tidak memblokir penomoran pada fase 1.
        $policy = $procurement->forTransaction($package->transaction);
        $suggestedExternalPattern = SpjExternalChecklistPatterns::suggestedPattern(
            $package->transaction->spj_category,
            ($policy['channel'] ?? '') === 'SIPLAH'
        );
        $requestedExternalPattern = (string) request()->query('pola', $suggestedExternalPattern);
        $externalPatternKey = SpjExternalChecklistPatterns::isValidPattern($requestedExternalPattern)
            ? $requestedExternalPattern
            : $suggestedExternalPattern;
        $externalPatterns = SpjExternalChecklistPatterns::patterns();
        $externalItems = SpjExternalChecklistPatterns::items();
        $externalCheckedKeys = $package->externalChecklistTicks()
            ->where('is_checked', true)
            ->pluck('item_key')
            ->all();
        $externalPatternItems = SpjExternalChecklistPatterns::itemsForPattern($externalPatternKey);
        $externalCheckedCount = count(array_intersect($externalPatternItems, $externalCheckedKeys));

        // Helper operator read-only: tidak memblokir penomoran, tidak
        // mengubah lifecycle/sync/numbering.
        $helperHints = $operatorHints->hints($package, $externalCheckedKeys);
        $bkuMatch = $operatorHints->bkuMatch($package);
        $categorySuggestion = $operatorHints->categorySuggestion($package->transaction);
        $packageTimeline = $this->packageTimeline($package->id, $package->transaction->id);

        // Catatan operator (handover/pengingat): metadata baca-tulis
        // terpisah dari dokumen, tidak memengaruhi validasi penomoran.
        $operatorNotes = $package->operatorNotes()->latest()->limit(50)->get();
        $noteAuthorNames = $operatorNotes->pluck('created_by')->filter()->unique()->isNotEmpty()
            ? User::query()->whereIn('id', $operatorNotes->pluck('created_by')->filter()->unique()->all())->pluck('name', 'id')->all()
            : [];
        $canNote = $canEdit && $package->status !== 'CANCELLED';

        return view('spj.checklist', compact(
            'package',
            'checklist',
            'totalChecks',
            'completedChecks',
            'remainingChecks',
            'progress',
            'documentRequirements',
            'requirementSummary',
            'canEdit',
            'canMarkReady',
            'externalPatterns',
            'externalItems',
            'externalPatternKey',
            'suggestedExternalPattern',
            'externalPatternItems',
            'externalCheckedKeys',
            'externalCheckedCount',
            'helperHints',
            'bkuMatch',
            'categorySuggestion',
            'packageTimeline',
            'operatorNotes',
            'noteAuthorNames',
            'canNote',
        ));
    }

    /**
     * Riwayat paket dalam bahasa operator dari operational_audit_logs
     * (koneksi school = scope tenant). Read-only.
     *
     * @return array<int, array{action: string, label: string, description: string, at: string|null}>
     */
    private function packageTimeline(string|int $packageId, string|int $transactionId): array
    {
        try {
            $rows = DB::connection('school')->table('operational_audit_logs')
                ->whereIn('entity_id', [(string) $packageId, $packageId, (string) $transactionId, $transactionId])
                ->orderByDesc('id')
                ->limit(20)
                ->get(['action', 'description', 'created_at']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(static fn ($row): array => [
            'action' => (string) $row->action,
            'label' => match ((string) $row->action) {
                'PAKET_READY' => 'Dinyatakan siap',
                'CHECKLIST_EKSTERNAL' => 'Checklist bukti dukung',
                'REKONSILIASI_ARTEFAK_DITUTUP' => 'Artefak rekonsiliasi ditutup',
                default => ucwords(strtolower(str_replace('_', ' ', (string) $row->action))),
            },
            'description' => (string) $row->description,
            'at' => $row->created_at ? (string) $row->created_at : null,
        ])->all();
    }
}
