<?php

namespace App\UseCases\Spj;

use App\Models\Employee;
use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjPackageValidationService;
use App\Services\SpjWorkflowFilterService;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SpjWorkspaceUseCase
{
    public function __construct(
        private readonly SpjWorkflowFilterService $workflowFilters,
        private readonly ActiveSpjContext $context,
        private readonly SpjPackageTemplateSelector $templateSelector,
    ) {}

    public function handle(Request $request): View|RedirectResponse
    {
        $tab = $request->query('tab', 'persiapan');

        return match ($tab) {
            'persiapan' => $this->tabPersiapan($request),
            'paket' => $this->tabPaket($request),
            'laporan' => app(SpjReportUseCase::class)->tabLaporan($request),
            'monitoring' => app(SpjReportUseCase::class)->tabMonitoring($request),
            default => $this->tabPersiapan($request),
        };
    }

    public function overviewMetrics(): array
    {
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context));

        return [
            'totalPackages' => (clone $packages)->count(),
            'numberedPackages' => (clone $packages)->whereNotNull('document_number')->count(),
            'readyTransactions' => Transaction::query()->forSpjContext($this->context)->has('items')->count(),
        ];
    }

    public function participantRoster()
    {
        $statusId = static fn (Employee $employee): int => is_numeric($employee->payload['status_kepegawaian_id'] ?? null)
            ? (int) $employee->payload['status_kepegawaian_id']
            : PHP_INT_MAX;

        // Roster melayani semua operator (ARKAS-only maupun Dapodik-only):
        // seluruh pegawai aktif tanpa filter sumber, identitas sudah menyatu.
        return Employee::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'staff_type', 'source_type', 'nip', 'nuptk', 'payload'])
            ->sortBy(fn (Employee $employee) => sprintf('%s-%d-%d', mb_strtolower(trim($employee->name)), $employee->source_type === 'DAPODIK' ? 0 : 1, filled($employee->nuptk) ? 0 : 1))
            ->unique(fn (Employee $employee) => mb_strtolower(trim($employee->name)))
            ->sortBy(fn (Employee $employee) => sprintf('%010d-%s', $statusId($employee), mb_strtolower(trim($employee->name))))
            ->values();
    }

    /** @return array<string, array<int, string>> */
    public static function preparationFilterRules(): array
    {
        return [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'state' => ['nullable', 'in:all,attention,needs_details,unprepared,draft,ready,numbered'],
        ];
    }

    /**
     * Query antrean persiapan dari filter eksplisit memakai implementasi yang
     * sama dengan jalur HTTP, untuk dipakai komponen Livewire.
     *
     * @return array{transactions: LengthAwarePaginator, workQueueCounts: array<string, int>, spjTypes: Collection}
     */
    public function preparationData(array $filters, int $perPage): array
    {
        $month = isset($filters['month']) && $filters['month'] !== null ? (int) $filters['month'] : null;
        $quarter = isset($filters['quarter']) && $filters['quarter'] !== null ? (int) $filters['quarter'] : null;

        $query = Transaction::query()->forSpjContext($this->context);
        ArkasMirrorResolver::joinKasUmum($query);
        $query
            ->when($month, fn ($q, $selectedMonth) => $q->whereRaw(ArkasMirrorResolver::mirrorMonth().' = ?', [$selectedMonth]))
            ->when(! $month && $quarter, function ($q) use ($quarter): void {
                $q->whereRaw(ArkasMirrorResolver::mirrorMonth().' >= ?', [(($quarter - 1) * 3) + 1])
                    ->whereRaw(ArkasMirrorResolver::mirrorMonth().' <= ?', [$quarter * 3]);
            })
            ->when($filters['spj_category'] ?? null, fn ($q, $type) => $q->where('transactions.spj_category', $type));

        $workQueueCounts = ['all' => (clone $query)->count()];
        foreach (array_keys($this->workflowFilters->options()) as $state) {
            $workQueueCounts[$state] = $this->workflowFilters->apply(clone $query, $state)->count();
        }
        $workQueueCounts['needs_details'] = $workQueueCounts['attention'];

        $this->workflowFilters->apply($query, $filters['state'] ?? 'all');

        $transactions = $query
            ->with(['spjPackage', 'items:id,transaction_id,source_item_id'])->withCount('items')
            ->select('transactions.*')
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id')
            ->paginate($perPage)->withQueryString();

        // Satu pemanasan memo mirror untuk halaman ini; sel Blade
        // berikutnya membaca dari memo, bukan query per sel.
        Transaction::preloadMirrorSource($transactions->getCollection());

        return [
            'transactions' => $transactions,
            'workQueueCounts' => $workQueueCounts,
            'spjTypes' => Transaction::query()->forSpjContext($this->context)->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
        ];
    }

    /**
     * @param  array{search?:string,status?:string,category?:string}  $filters
     */
    public function packageListData(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $category = strtoupper(trim((string) ($filters['category'] ?? '')));

        return SpjPackage::query()
            ->with(['transaction:id,payment_description,spj_category,fiscal_year_id,fund_source_id'])
            ->when(in_array($status, ['DRAFT', 'READY', 'NUMBERED', 'FINAL', 'CANCELLED'], true), fn ($query) => $query->where('status', $status))
            ->whereHas('transaction', function ($query) use ($search, $category): void {
                $query->forSpjContext($this->context)
                    ->when($category !== '', fn ($transactionQuery) => $transactionQuery->where('transactions.spj_category', $category));
                if ($search !== '') {
                    ArkasMirrorResolver::joinKasUmum($query);
                    $query->where(function ($searchQuery) use ($search): void {
                        $searchQuery->whereRaw('mkas.sx_no_bukti like ?', ['%'.$search.'%'])
                            ->orWhereRaw(ArkasMirrorResolver::mirrorTextSearchExists($search))
                            ->orWhere('transactions.payment_description', 'like', '%'.$search.'%');
                    });
                }
            })
            ->orderByRaw("CASE status WHEN 'CANCELLED' THEN 3 WHEN 'FINAL' THEN 2 WHEN 'NUMBERED' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('numbered_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'package_page')
            ->withQueryString();
    }

    private function tabPersiapan(Request $request): View
    {
        // Filter Livewire memiliki state/validasi sendiri; antrean + daftar
        // dirender oleh <livewire:spj-preparation-filter />, sehingga query
        // persiapan tidak dihitung di sini agar tidak dikerjakan dua kali.
        $request->validate(static::preparationFilterRules());

        return view('spj.index', [
            'tab' => 'persiapan',
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
            'workQueueCounts' => [],
        ]);
    }

    private function tabPaket(Request $request): View|RedirectResponse
    {
        $packageId = $request->query('package_id');

        if (! $packageId) {
            // Daftar paket dirender oleh <livewire:spj-package-list /> dengan
            // paginasinya sendiri; tidak dihitung di sini agar tidak ganda.
            return view('spj.index', [
                'tab' => 'paket',
                'package' => null,
                'packageList' => null,
                'validationIssues' => [],
                'templates' => collect(),
                'transactions' => null,
                ...$this->overviewMetrics(),
                'spjTypes' => [],
                'filters' => [],
                'periodClosures' => collect(),
                'participantRoster' => collect(),
                'consumptionOrderSources' => [],
                'serviceOrderSources' => [],
            ]);
        }

        $package = SpjPackage::query()->with([
            'documents.template',
            'transaction.items.participants',
            'transaction.goods',
            'transaction.workOrder',
            'transaction.workers',
            'transaction.participants',
            'transaction.travels',
            'transaction.honors',
            'transaction.serviceRecipients',
            'transaction.payments',
            'transaction.goodsReceipts.items',
        ])->find($packageId);
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->transaction->items->isEmpty()
            || $package->transaction->items->contains(fn ($item): bool => blank(trim((string) $item->item_description)))) {
            return app(CreateSpjDraftUseCase::class)->handle((string) $package->transaction_id);
        }

        $validator = app(SpjPackageValidationService::class);
        $participantRoster = collect();
        $consumptionOrderSources = [];
        $serviceOrderSources = [];

        if (strtoupper((string) $package->transaction->spj_category) === 'KONSUMSI') {
            $participantRoster = $this->participantRoster();
            $consumptionOrderSources = SpjPackage::query()
                ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context)->where('spj_category', 'KONSUMSI'))
                ->where('id', '!=', $package->id)
                ->with('transaction.items.participants')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(function (SpjPackage $row): array {
                    $participants = $row->transaction->items
                        ->flatMap(fn ($item) => $item->participants)
                        ->sortBy(fn ($participant) => [(int) ($participant->sort_order ?? 0), (int) $participant->getKey()])
                        ->values();

                    return [
                        'id' => $row->id,
                        'label' => ($row->transaction->sourceValue('no_bukti') ?: 'Tanpa bukti').' · '.((($d = $row->transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->translatedFormat('d M Y') : null) ?: '-').' · '.$participants->count().' peserta',
                        'names' => $participants->map(fn ($participant) => $participant->name)->filter()->values()->all(),
                        'participants' => $participants->map(fn ($participant) => [
                            'name' => $participant->name,
                            'position' => $participant->position,
                            'nip' => $participant->nip,
                            'nuptk' => $participant->nuptk,
                            'portions' => $participant->portions ?: 1,
                        ])->all(),
                    ];
                })
                ->filter(fn (array $row) => $row['names'] !== [])
                ->values()
                ->all();
        }

        if (strtoupper((string) $package->transaction->spj_category) === 'JASA_LAINNYA') {
            $serviceOrderSources = SpjPackage::query()
                ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context)->where('spj_category', 'JASA_LAINNYA'))
                ->where('id', '!=', $package->id)
                ->with('transaction.serviceRecipients')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(function (SpjPackage $row): array {
                    $recipients = $row->transaction->serviceRecipients;

                    return [
                        'id' => $row->id,
                        'label' => ($row->transaction->sourceValue('no_bukti') ?: 'Tanpa bukti').' · '.((($d = $row->transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->translatedFormat('d M Y') : null) ?: '-').' · '.$recipients->count().' penerima',
                        'names' => $recipients->pluck('name')->filter()->values()->all(),
                        'recipients' => $recipients->map(fn ($recipient): array => [
                            ...$recipient->only(['name', 'npwp', 'service_type', 'service_description', 'quantity', 'unit', 'rental_days', 'daily_rate', 'receipt_number', 'payment_reference', 'agreement_number', 'notes']),
                            'usage_started_at' => $recipient->usage_started_at?->format('Y-m-d'),
                            'usage_completed_at' => $recipient->usage_completed_at?->format('Y-m-d'),
                            'agreement_date' => $recipient->agreement_date?->format('Y-m-d'),
                        ])->values()->all(),
                    ];
                })
                ->filter(fn (array $row): bool => $row['names'] !== [])
                ->values()
                ->all();
        }
        $validationIssues = $validator->validate($package);
        $templates = $this->templateSelector->forPackage($package);
        $navigation = $this->packageNavigation($package);

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => null,
            'validationIssues' => $validationIssues,
            'templates' => $templates,
            'transactions' => null,
            ...$this->overviewMetrics(),
            ...$navigation,
            'spjTypes' => [],
            'filters' => [],
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->orderBy('quarter')->get()->keyBy('quarter'),
            'participantRoster' => $participantRoster,
            'consumptionOrderSources' => $consumptionOrderSources,
            'serviceOrderSources' => $serviceOrderSources,
        ]);
    }

    /** @return array{previousPackageId: int|null, nextPackageId: int|null} */
    private function packageNavigation(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $rawDate = $transaction->sourceValue('transaction_date');
        $transactionDate = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : null;
        $mirrorDate = ArkasMirrorResolver::mirrorDate();

        $baseQuery = function () {
            $query = Transaction::query()->forSpjContext($this->context);
            ArkasMirrorResolver::joinKasUmum($query);

            return $query->select('transactions.*');
        };

        $previousTransaction = ($baseQuery())
            ->whereHas('spjPackage')
            ->where(function ($query) use ($transactionDate, $transaction, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} < ?", [$transactionDate])
                    ->orWhere(function ($sameDate) use ($transactionDate, $transaction, $mirrorDate): void {
                        $sameDate->whereRaw("{$mirrorDate} = ?", [$transactionDate])
                            ->where('transactions.id', '<', $transaction->id);
                    });
            })
            ->with('spjPackage:id,transaction_id')
            ->orderByRaw($mirrorDate.' DESC')
            ->orderByDesc('transactions.id')
            ->first();

        $nextTransaction = ($baseQuery())
            ->whereHas('spjPackage')
            ->where(function ($query) use ($transactionDate, $transaction, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} > ?", [$transactionDate])
                    ->orWhere(function ($sameDate) use ($transactionDate, $transaction, $mirrorDate): void {
                        $sameDate->whereRaw("{$mirrorDate} = ?", [$transactionDate])
                            ->where('transactions.id', '>', $transaction->id);
                    });
            })
            ->with('spjPackage:id,transaction_id')
            ->orderByRaw($mirrorDate)
            ->orderBy('transactions.id')
            ->first();

        return [
            'previousPackageId' => $previousTransaction?->spjPackage?->id,
            'nextPackageId' => $nextTransaction?->spjPackage?->id,
        ];
    }
}
