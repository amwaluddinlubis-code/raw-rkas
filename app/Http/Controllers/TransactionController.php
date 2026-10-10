<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjDescriptionService;
use App\Services\VendorHistoryService;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        app(OperationalAuditService::class)->recordSpjDescriptionCorrection($transaction);

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

    /**
     * Read-only vendor memory: suggest vendor_owner / receipt_recipient_name
     * from the latest tenant transaction with the same vendor_name.
     */
    public function vendorRecommendation(Request $request, ActiveSpjContext $context, VendorHistoryService $history): JsonResponse
    {
        $data = $request->validate([
            'vendor' => ['required', 'string', 'min:3', 'max:180'],
            'transaction_id' => ['required', 'integer'],
        ]);

        $transaction = Transaction::query()->find($data['transaction_id']);
        if (! $transaction || ! $context->matchesTransaction($transaction)) {
            abort(404);
        }

        $recommendation = $history->latestForVendor($data['vendor'], $transaction->id);
        if (! $recommendation) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true] + $recommendation);
    }
}
