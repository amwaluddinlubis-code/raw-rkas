<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\OperationalAuditService;
use App\Services\SpjDescriptionService;
use App\Services\SpjSourceReconciliationService;
use App\Support\ActiveSpjContext;
use App\Support\SpjDisplay;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TransactionDetailWorkspace extends Component
{
    public int $transactionId;

    public string $paymentDescription = '';

    /** @var array<int, string> */
    public array $itemDescriptions = [];

    public string $resolution = '';

    public string $resolutionNotes = '';

    public ?int $sourceEventId = null;

    public bool $unlockLockedResolution = false;

    public function mount(int $transactionId, ActiveSpjContext $context): void
    {
        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || ! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }

        $this->transactionId = $transactionId;
        $this->loadTransaction();
    }

    public function saveDescriptions(SpjDescriptionService $descriptions, ActiveSpjContext $context): void
    {
        abort_unless(
            auth()->user()?->isOperatorOrAdministrator(),
            403,
            'Aksi ini hanya dapat dilakukan administrator atau operator.'
        );

        $transaction = $this->transaction();
        if (! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }
        if ($transaction->spjPackage?->status === 'FINAL') {
            $this->addError('form', 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');
            $this->dispatch('app-notify', type: 'error', message: 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');

            return;
        }

        try {
            $data = $this->validate([
                'paymentDescription' => ['nullable', 'string', 'max:4000'],
                'itemDescriptions' => ['array'],
                'itemDescriptions.*' => ['required', 'string', 'max:4000'],
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('app-notify', type: 'error', message: $exception->validator->errors()->first());

            throw $exception;
        }
        $itemIds = $transaction->items->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_keys($data['itemDescriptions'] ?? []) as $itemId) {
            if (! in_array((int) $itemId, $itemIds, true)) {
                abort(422, 'Rincian transaksi tidak valid.');
            }
        }

        if (array_key_exists('paymentDescription', $data)) {
            $descriptions->updatePaymentDescription($transaction, $data['paymentDescription'] ?? null);
        }
        foreach ($data['itemDescriptions'] ?? [] as $itemId => $description) {
            $transaction->items->firstWhere('id', (int) $itemId)->update([
                'item_description' => trim($description),
            ]);
        }

        $this->loadTransaction();
        session()->flash('success', 'Uraian SPJ berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran.');
        $this->dispatch('app-notify', type: 'success', message: 'Koreksi uraian berhasil disimpan.');
    }

    public function resolveReconciliation(?string $requestedResolution, SpjSourceReconciliationService $service, ActiveSpjContext $context, OperationalAuditService $audit): void
    {
        abort_unless(
            auth()->user()?->isOperatorOrAdministrator(),
            403,
            'Aksi ini hanya dapat dilakukan administrator atau operator.'
        );

        if ($requestedResolution !== null) {
            $this->resolution = $requestedResolution;
        }

        $transaction = $this->transaction();
        if (! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }

        $data = $this->validate([
            'resolution' => ['required', 'string', Rule::in([
                SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
                SpjSourceReconciliationService::ACCEPT_SOURCE,
                SpjSourceReconciliationService::KEEP_OVERLAY,
            ])],
            'sourceEventId' => ['nullable', 'integer'],
            'resolutionNotes' => ['nullable', 'string', 'max:2000'],
            'unlockLockedResolution' => ['boolean'],
        ]);

        $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: 'DRAFT'));
        $wantsUnlock = (bool) ($data['unlockLockedResolution'] ?? false);
        if ($wantsUnlock) {
            if (! $service->temporaryUnlockEnabled()) {
                $this->addError('resolution', 'Jalur penyelesaian sementara sedang ditutup.');

                return;
            }
            if (! auth()->user()?->isAdministrator()) {
                $this->addError('resolution', 'Hanya administrator yang dapat menyelesaikan paket bernomor/final tanpa pembatalan.');

                return;
            }
            if (blank($data['resolutionNotes'] ?? null)) {
                $this->addError('resolutionNotes', 'Alasan tertulis wajib diisi untuk paket bernomor/final.');

                return;
            }
            if (! in_array($packageStatus, ['NUMBERED', 'FINAL'], true)) {
                $this->addError('resolution', 'Paket tidak terkunci; gunakan penyelesaian normal.');

                return;
            }
        }

        try {
            $result = $service->resolve($transaction, $data['resolution'], $data['resolutionNotes'] ?? null, auth()->id() ? (int) auth()->id() : null, $data['sourceEventId'] ?? null, $wantsUnlock);
        } catch (DomainException $exception) {
            $this->addError('resolution', $exception->getMessage());

            return;
        }

        if ($wantsUnlock) {
            $audit->record(
                $transaction->fiscal_year_id,
                'TRANSACTION',
                $transaction->id,
                'RESOLUSI_TERBUKA_'.$packageStatus,
                'Rekonsiliasi '.$result['label'].' tanpa pembatalan (jalur sementara). Alasan: '.trim((string) ($data['resolutionNotes'] ?? '')),
            );
        }

        $this->loadTransaction();
        $this->resolution = '';
        $this->resolutionNotes = '';
        $this->unlockLockedResolution = false;
        $message = 'Rekonsiliasi selesai: '.$result['label'].'.';
        session()->flash('success', $message);
        $this->dispatch('app-notify', type: 'success', message: $message);
    }

    public function dismissArtifacts(SpjSourceReconciliationService $service, ActiveSpjContext $context, OperationalAuditService $audit): void
    {
        abort_unless(
            auth()->user()?->isOperatorOrAdministrator(),
            403,
            'Aksi ini hanya dapat dilakukan administrator atau operator.'
        );

        $transaction = $this->transaction();
        if (! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }

        try {
            $dismissed = $service->dismissCrossYearArtifacts($transaction, $audit, auth()->id() ? (int) auth()->id() : null);
        } catch (DomainException $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->loadTransaction();
        $message = $dismissed > 0
            ? 'Artefak pembanding lintas tahun ditutup tanpa mengubah paket SPJ.'
            : 'Tidak ada artefak lintas tahun yang perlu ditutup.';
        session()->flash('success', $message);
        $this->dispatch('app-notify', type: 'success', message: $message);
    }

    public function render(): View
    {
        $transaction = $this->transaction();
        $reconciliation = app(SpjSourceReconciliationService::class)->forTransaction($transaction);
        $rupiah = fn ($value): string => SpjDisplay::rupiah($value);

        return view('livewire.transaction-detail-workspace', [
            'transaction' => $transaction,
            'headerVisual' => $this->headerVisual($transaction),
            'paymentMethod' => $this->normalizePaymentMethod($transaction->payment_method, $transaction),
            'previousTransaction' => $this->adjacentTransaction($transaction, false),
            'nextTransaction' => $this->adjacentTransaction($transaction, true),
            'rupiah' => $rupiah,
            'totalItems' => $transaction->items->sum(fn ($item): float => (float) $item->sourceValue('amount')),
            'descriptionsFilled' => $transaction->items->filter(fn ($item): bool => filled($item->item_description))->count(),
            'descriptionsComplete' => $transaction->items->isNotEmpty() && $transaction->items->every(fn ($item): bool => filled($item->item_description)),
            'spjTypeLabel' => fn ($value): string => SpjDisplay::typeLabel($value),
            'sourceStatus' => strtoupper((string) ($transaction->source_status ?: 'ACTIVE')),
            'needsAttention' => $reconciliation['needs_attention'],
            'needsReconciliation' => $reconciliation['requires_reconciliation'],
        ]);
    }

    public function moveItem(int $itemId, string $direction): void
    {
        abort_unless(
            auth()->user()?->isOperatorOrAdministrator(),
            403,
            'Aksi ini hanya dapat dilakukan administrator atau operator.'
        );

        $transaction = $this->transaction();
        $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: ''));
        if ($packageStatus === 'FINAL') {
            $this->addError('form', 'Urutan rincian tidak dapat diubah karena paket sudah FINAL.');

            return;
        }
        if ($packageStatus === 'NUMBERED') {
            // Carve-out §4.2/§4.4: pada NUMBERED hanya payment_description dan
            // item_description yang boleh dikoreksi; sort_order tetap terkunci.
            $this->addError('form', 'Urutan rincian tidak dapat diubah karena paket sudah bernomor (NUMBERED).');

            return;
        }
        $ordered = $transaction->items->values()->all();
        $index = collect($ordered)->search(fn ($item): bool => (int) $item->id === $itemId);
        if ($index === false) {
            return;
        }
        $swapWith = $direction === 'up' ? $index - 1 : ($direction === 'down' ? $index + 1 : $index);
        if ($swapWith < 0 || $swapWith >= count($ordered)) {
            return;
        }
        [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];
        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $item) {
                DB::connection('school')->table('transaction_items')->where('id', $item->id)->update(['sort_order' => $position + 1]);
            }
        });
        app(OperationalAuditService::class)->record(
            (int) $transaction->fiscal_year_id ?: null,
            'TRANSACTION',
            (int) $transaction->id,
            'TRANSACTION_ITEMS_REORDERED',
            'Urutan rincian barang/jasa untuk SPJ diperbarui.',
        );
        $this->loadTransaction();
        $this->dispatch('app-notify', type: 'success', message: 'Urutan rincian diperbarui.');
    }

    private function transaction(): Transaction
    {
        return Transaction::query()->with([
            'items' => fn ($query) => $query->orderByRaw('COALESCE(NULLIF(sort_order, 0), id)'), 'goods', 'workers', 'participants', 'travels', 'honors', 'workOrder', 'spjPackage',
        ])->findOrFail($this->transactionId);
    }

    private function loadTransaction(): void
    {
        $transaction = $this->transaction();
        $siplahDescription = $transaction->is_siplah
            ? app(SpjDescriptionService::class)->siplahPaymentDescription($transaction)
            : null;
        $this->paymentDescription = (string) ($transaction->payment_description ?: $siplahDescription ?: $transaction->sourceValue('description') ?: '');
        $this->itemDescriptions = $transaction->items->mapWithKeys(fn ($item): array => [(int) $item->id => (string) ($item->item_description ?: $item->sourceValue('description') ?: '')])->all();
        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction);
        $this->sourceEventId = $report['latest']?->id ? (int) $report['latest']->id : null;
    }

    private function adjacentTransaction(Transaction $transaction, bool $next): ?Transaction
    {
        $query = Transaction::query()->activeContext();
        ArkasMirrorResolver::joinKasUmum($query);
        $query->select('transactions.*');
        $date = $transaction->sourceDateString();
        $mirrorDate = ArkasMirrorResolver::mirrorDate();
        if ($next) {
            return $query->where(function ($query) use ($transaction, $date, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} > ?", [$date])->orWhere(function ($query) use ($transaction, $date, $mirrorDate): void {
                    $query->whereRaw("{$mirrorDate} = ?", [$date])->where('transactions.id', '>', $transaction->id);
                });
            })->orderByRaw($mirrorDate)->orderBy('transactions.id')->first();
        }

        return $query->where(function ($query) use ($transaction, $date, $mirrorDate): void {
            $query->whereRaw("{$mirrorDate} IS NULL")->orWhereRaw("{$mirrorDate} < ?", [$date])->orWhere(function ($query) use ($transaction, $date, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} = ?", [$date])->where('transactions.id', '<', $transaction->id);
            });
        })->orderByRaw("{$mirrorDate} IS NULL DESC")->orderByRaw($mirrorDate.' DESC')->orderByDesc('transactions.id')->first();
    }

    private function headerVisual(Transaction $transaction): array
    {
        $haystack = mb_strtolower(implode(' ', [$transaction->spj_category, $transaction->sourceValue('account_code'), $transaction->sourceValue('account_name'), $transaction->sourceValue('description'), $transaction->sourceValue('activity_name')]));
        if ($transaction->spj_category === 'HONOR_PEGAWAI' || str_contains($haystack, 'honor')) {
            return ['label' => 'Honor Pegawai', 'image' => null];
        }
        if (str_contains($haystack, 'buku')) {
            return ['label' => 'Belanja Buku', 'image' => 'images/spj-categories/belanja-buku.png'];
        }
        if (str_starts_with((string) $transaction->sourceValue('account_code'), '5.2')) {
            return ['label' => 'Belanja Modal Peralatan dan Mesin', 'image' => 'images/spj-categories/belanja-modal.png'];
        }
        if (str_contains($haystack, 'makanan') || str_contains($haystack, 'minuman') || str_contains($haystack, 'konsumsi')) {
            return ['label' => 'Belanja Konsumsi', 'image' => 'images/spj-categories/belanja-konsumsi.png'];
        }
        if (str_starts_with((string) $transaction->sourceValue('account_code'), '5.1.02.04') || str_contains($haystack, 'perjalanan')) {
            return ['label' => 'Perjalanan Dinas', 'image' => 'images/spj-categories/perjalanan-dinas.png'];
        }
        if (str_starts_with((string) $transaction->sourceValue('account_code'), '5.1.02.03') || str_contains($haystack, 'pemeliharaan')) {
            return ['label' => 'Jasa Pemeliharaan', 'image' => 'images/spj-categories/jasa-pemeliharaan.png'];
        }

        return ['label' => 'Barang Habis Pakai / ATK / Peralatan Olahraga dll', 'image' => 'images/spj-categories/barang-habis-pakai.png'];
    }

    private function normalizePaymentMethod(?string $value, Transaction $transaction): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $value;
        }
        if ($transaction->is_siplah) {
            return 'siplah';
        }
        $proofNumber = strtolower((string) $transaction->sourceValue('no_bukti'));
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }
        if (str_contains($value, 'transfer') || str_contains($value, 'cms') || str_contains($value, 'non tunai')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
