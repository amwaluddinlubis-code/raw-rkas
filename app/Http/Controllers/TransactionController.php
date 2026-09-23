<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\SpjDescriptionService;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function updateSpjDescriptions(Request $request, string $transactionId, ActiveSpjContext $context, SpjDescriptionService $descriptions): RedirectResponse
    {
        $transaction = Transaction::query()->with('items')->find($transactionId);
        if (! $transaction || ! $context->matchesTransaction($transaction)) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan pada tahun aktif.');
        }
        if ($transaction->spjPackage?->status === 'FINAL') {
            return back()->with('error', 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL. Lakukan koreksi melalui lifecycle resmi terlebih dahulu.');
        }

        $data = $request->validate([
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.item_description' => ['required', 'string', 'max:4000'],
        ]);

        $itemIds = $transaction->items->pluck('id')->all();
        foreach ($data['items'] ?? [] as $itemData) {
            if (! in_array((int) $itemData['id'], $itemIds, true)) {
                abort(422, 'Rincian transaksi tidak valid.');
            }
        }

        if (array_key_exists('payment_description', $data)) {
            $descriptions->updatePaymentDescription($transaction, $data['payment_description'] ?? null);
        }

        foreach ($data['items'] ?? [] as $itemData) {
            $transaction->items->firstWhere('id', (int) $itemData['id'])->update([
                'item_description' => trim($itemData['item_description']),
            ]);
        }

        return back()->with('success', 'Uraian pembayaran dan barang/jasa untuk SPJ berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran.');
    }

    public function index(Request $request): View
    {
        return view('transactions.index');
    }

    public function show(string $transactionId, ActiveSpjContext $context): View|RedirectResponse
    {
        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || ! $context->matchesTransaction($transaction)) {
            return redirect()->route('transactions.index')->with(
                'error',
                'Transaksi tidak ditemukan pada sekolah atau tahun anggaran yang sedang aktif. Jalankan sinkronisasi ARKAS atau buka transaksi dari daftar.'
            );
        }

        return view('transactions.show', ['transactionId' => $transaction->id]);
    }

    /** @return array{0: ?Transaction, 1: ?Transaction} */
    private function adjacentTransactions(Transaction $transaction): array
    {
        $baseQuery = function () {
            $query = Transaction::query()->activeContext();
            ArkasMirrorResolver::joinKasUmum($query);

            return $query->select('transactions.*');
        };
        $rawDate = $transaction->sourceValue('transaction_date');
        $transactionDate = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : null;
        $mirrorDate = ArkasMirrorResolver::mirrorDate();

        if ($transactionDate === null) {
            $previous = ($baseQuery())->whereRaw("{$mirrorDate} IS NULL")->where('transactions.id', '<', $transaction->id)->orderByDesc('transactions.id')->first();
            $next = ($baseQuery())
                ->where(function (Builder $query) use ($transaction, $mirrorDate): void {
                    $query->where(function (Builder $query) use ($transaction, $mirrorDate): void {
                        $query->whereRaw("{$mirrorDate} IS NULL")->where('transactions.id', '>', $transaction->id);
                    })->orWhereRaw("{$mirrorDate} IS NOT NULL");
                })
                ->orderByRaw("{$mirrorDate} IS NULL DESC")->orderByRaw($mirrorDate)->orderBy('transactions.id')->first();

            return [$previous, $next];
        }

        $previous = ($baseQuery())
            ->where(function (Builder $query) use ($transactionDate, $transaction, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} IS NULL")
                    ->orWhereRaw("{$mirrorDate} < ?", [$transactionDate])
                    ->orWhere(function (Builder $query) use ($transactionDate, $transaction, $mirrorDate): void {
                        $query->whereRaw("{$mirrorDate} = ?", [$transactionDate])->where('transactions.id', '<', $transaction->id);
                    });
            })
            ->orderByRaw("{$mirrorDate} IS NULL DESC")->orderByRaw($mirrorDate.' DESC')->orderByDesc('transactions.id')->first();

        $next = ($baseQuery())
            ->where(function (Builder $query) use ($transactionDate, $transaction, $mirrorDate): void {
                $query->whereRaw("{$mirrorDate} > ?", [$transactionDate])
                    ->orWhere(function (Builder $query) use ($transactionDate, $transaction, $mirrorDate): void {
                        $query->whereRaw("{$mirrorDate} = ?", [$transactionDate])->where('transactions.id', '>', $transaction->id);
                    });
            })
            ->orderByRaw($mirrorDate)->orderBy('transactions.id')->first();

        return [$previous, $next];
    }

    private function headerVisual(Transaction $transaction): array
    {
        $haystack = mb_strtolower(implode(' ', [
            $transaction->spj_category, $transaction->sourceValue('account_code'), $transaction->sourceValue('account_name'),
            $transaction->sourceValue('description'), $transaction->sourceValue('activity_name'),
        ]));

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
