<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\SpjWorkflowFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionsTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: null)]
    public ?int $month = null;

    #[Url(except: null)]
    public ?int $quarter = null;

    #[Url(except: null)]
    public ?int $semester = null;

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->q = trim((string) request('q'));
        $this->status = trim((string) request('status'));
        $this->month = request()->integer('month') ?: null;
        $this->quarter = request()->integer('quarter') ?: null;
        $this->semester = request()->integer('semester') ?: null;
        $this->mode = $this->month ? 'bulan' : ($this->quarter ? 'triwulan' : ($this->semester ? 'semester' : 'semua'));
        $requestedPerPage = request('perPage');

        if ($requestedPerPage === 'all') {
            $this->perPage = 'all';
        } elseif (in_array((int) $requestedPerPage, [15, 25, 50, 100], true)) {
            $this->perPage = (int) $requestedPerPage;
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['q', 'status', 'mode', 'month', 'quarter', 'semester', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, $this->allowedModes(), true)) {
            return;
        }

        $this->mode = $mode;
        $this->month = null;
        $this->quarter = null;
        $this->semester = null;
        $this->resetPage();
    }

    public function modes(): array
    {
        return [
            ['semua', 'Semua'],
            ['semester', 'Semester'],
            ['triwulan', 'Triwulan'],
            ['bulan', 'Bulan'],
        ];
    }

    private function allowedModes(): array
    {
        return ['semua', 'semester', 'triwulan', 'bulan'];
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'status', 'mode', 'month', 'quarter', 'semester']);
        $this->resetPage();
    }

    public function getFilteredStatsProperty(): object
    {
        $rows = (clone $this->filteredQuery())->select('transactions.*')->get();
        Transaction::preloadMirrorSource($rows);

        return (object) [
            'count' => $rows->count(),
            'gross' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
            'tax' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('tax_total')),
            'net' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('net_amount')),
        ];
    }

    public function getStatusesProperty(): Collection
    {
        return $this->workflowFilters()->labels();
    }

    public function getTransactionsProperty(): LengthAwarePaginator
    {
        $query = $this->filteredQuery()
            ->with(['spjPackage:id,transaction_id,document_number,status,finalized_at', 'items:id,transaction_id,source_item_id'])
            ->withCount('items');

        $perPage = $this->perPage === 'all' ? 100 : (int) $this->perPage;
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $paginator = $query
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id')
            ->paginate($perPage);

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $lastPage = $paginator->lastPage();
            $this->paginators['page'] = $lastPage;

            $paginator = $query->paginate($perPage, ['*'], 'page', $lastPage);
        }

        Transaction::preloadMirrorSource($paginator->getCollection());

        return $paginator;
    }

    public function render(): View
    {
        return view('livewire.transactions-table', [
            'filteredStats' => $this->filteredStats,
            'statuses' => $this->statuses,
            'transactions' => $this->transactions,
        ]);
    }

    /** @return array{status:string,label:string} */
    public function workStatusFor(Transaction $transaction): array
    {
        if ($transaction->source_status === 'SOURCE_MISSING') {
            return ['status' => 'SOURCE_MISSING', 'label' => 'Perlu Perhatian'];
        }

        if ($transaction->requires_reconciliation) {
            return ['status' => 'RECONCILIATION', 'label' => 'Perlu Perhatian'];
        }

        $package = $transaction->spjPackage;
        $packageStatus = strtoupper((string) ($package?->status ?? ''));

        if (in_array($packageStatus, ['CANCELLED', 'CANCELED'], true)) {
            return ['status' => 'CANCELLED', 'label' => 'Dibatalkan'];
        }

        if ($package?->finalized_at || in_array($packageStatus, ['FINAL', 'ARCHIVED', 'ARSIP'], true)) {
            return ['status' => 'FINAL', 'label' => 'Final'];
        }

        if (filled($package?->document_number) || in_array($packageStatus, ['NUMBERED', 'BERNOMOR'], true)) {
            return ['status' => 'NUMBERED', 'label' => 'Sudah Bernomor'];
        }

        if ($packageStatus === 'READY') {
            return ['status' => 'READY', 'label' => 'Siap Dinomori'];
        }

        if ($package) {
            return ['status' => 'DRAFT', 'label' => 'Perlu Dilengkapi'];
        }

        return ['status' => 'BELUM_LENGKAP', 'label' => 'Belum Dikerjakan'];
    }

    private function baseQuery(): Builder
    {
        return Transaction::query()->activeContext();
    }

    private function filteredQuery(): Builder
    {
        $activeYear = $this->activeYear();
        $query = clone $this->baseQuery();
        ArkasMirrorResolver::joinKasUmum($query);
        $query->select('transactions.*');

        if ($this->month) {
            $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' = ?', [$this->month]);
        } elseif ($this->quarter) {
            $query->whereBetween(ArkasMirrorResolver::mirrorDate(), [
                now()->setYear($activeYear->year)->setMonth(($this->quarter - 1) * 3 + 1)->startOfMonth()->format('Y-m-d'),
                now()->setYear($activeYear->year)->setMonth($this->quarter * 3)->endOfMonth()->format('Y-m-d'),
            ]);
        } elseif ($this->semester) {
            $query->whereBetween(ArkasMirrorResolver::mirrorDate(), [
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 1 : 7)->startOfMonth()->format('Y-m-d'),
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 6 : 12)->endOfMonth()->format('Y-m-d'),
            ]);
        }

        if (trim($this->q) !== '') {
            $search = trim($this->q);
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw('mkas.sx_no_bukti like ?', ["%{$search}%"])
                    ->orWhereRaw(ArkasMirrorResolver::mirrorTextSearchExists($search))
                    ->orWhere('transactions.payment_description', 'like', "%{$search}%")
                    ->orWhere('transactions.receipt_recipient_name', 'like', "%{$search}%");
            });
        }

        if ($this->status !== '') {
            $state = $this->workflowFilters()->stateForLabel($this->status);
            if ($state) {
                $this->workflowFilters()->apply($query, $state);
            }
        }

        return $query;
    }

    private function activeYear(): FiscalYear
    {
        return Cache::remember($this->cacheKey('active-year'), 300, fn () => FiscalYear::query()->findOrFail(session('active_fiscal_year_id')));
    }

    private function cacheKey(string $reference): string
    {
        return implode(':', ['school', session('active_school_id'), 'year', session('active_fiscal_year_id'), $reference]);
    }

    private function workflowFilters(): SpjWorkflowFilterService
    {
        return app(SpjWorkflowFilterService::class);
    }

    public function paymentMethodFor(Transaction $transaction): string
    {
        $current = strtolower((string) $transaction->payment_method);

        if (in_array($current, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $current;
        }

        if ($transaction->is_siplah) {
            return 'siplah';
        }

        $proofNumber = strtolower((string) $transaction->sourceValue('no_bukti'));
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }

        if (str_contains($current, 'non tunai') || str_contains($current, 'transfer') || str_contains($current, 'cms')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
