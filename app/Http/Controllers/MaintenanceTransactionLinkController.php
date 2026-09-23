<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MaintenanceTransactionLinkController extends Controller
{
    public function show(string $transactionId): JsonResponse
    {
        $transaction = $this->findActiveTransaction($transactionId);

        $candidates = $this->candidateQuery($transaction)
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id')
            ->select('transactions.*')
            ->get()
            ->map(fn (Transaction $candidate): array => [
                'id' => $candidate->id,
                'label' => $candidate->sourceValue('no_bukti').' - '.$candidate->payment_description,
            ])
            ->values();

        return response()->json([
            'current_role' => $this->currentRole($transaction),
            'candidates' => $candidates,
            'selected' => [
                'material_transaction_id' => $transaction->maintenance_material_transaction_id,
                'labor_transaction_id' => $transaction->maintenance_labor_transaction_id,
            ],
        ]);
    }

    public function update(Request $request, string $transactionId): JsonResponse
    {
        $transaction = $this->findActiveTransaction($transactionId);

        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            abort(423, 'Transaksi dikunci karena Paket SPJ sudah bernomor atau final.');
        }

        $data = $request->validate([
            'material_transaction_id' => ['nullable', 'integer'],
            'labor_transaction_id' => ['nullable', 'integer'],
        ]);

        $currentRole = $this->currentRole($transaction);

        if ($currentRole === 'labor' && filled($data['labor_transaction_id'] ?? null)) {
            throw ValidationException::withMessages([
                'labor_transaction_id' => 'Transaksi yang sedang dikerjakan sudah merupakan transaksi upah. Pilih hanya transaksi bahan/barang terkait.',
            ]);
        }

        if ($currentRole === 'material' && filled($data['material_transaction_id'] ?? null)) {
            throw ValidationException::withMessages([
                'material_transaction_id' => 'Transaksi yang sedang dikerjakan sudah merupakan transaksi bahan/barang. Pilih hanya transaksi upah terkait.',
            ]);
        }

        foreach (['material_transaction_id', 'labor_transaction_id'] as $field) {
            $candidateId = $data[$field] ?? null;
            if ($candidateId === null) {
                continue;
            }

            if (! (clone $this->candidateQuery($transaction))->whereKey($candidateId)->exists()) {
                throw ValidationException::withMessages([
                    $field => 'Transaksi terkait tidak memenuhi syarat tahun anggaran, sumber dana, tanggal, atau status sumber.',
                ]);
            }
        }

        if (
            filled($data['material_transaction_id'] ?? null)
            && filled($data['labor_transaction_id'] ?? null)
            && (int) $data['material_transaction_id'] === (int) $data['labor_transaction_id']
        ) {
            throw ValidationException::withMessages([
                'labor_transaction_id' => 'Transaksi bahan dan transaksi upah harus berbeda.',
            ]);
        }

        $transaction->forceFill([
            'maintenance_material_transaction_id' => $currentRole === 'material'
                ? null
                : ($data['material_transaction_id'] ?? null),
            'maintenance_labor_transaction_id' => $currentRole === 'labor'
                ? null
                : ($data['labor_transaction_id'] ?? null),
        ])->save();

        return response()->json(['message' => 'Transaksi terkait pemeliharaan berhasil disimpan.']);
    }

    private function findActiveTransaction(string $transactionId): Transaction
    {
        $transaction = Transaction::query()
            ->with('spjPackage')
            ->activeContext()
            ->find($transactionId);

        abort_unless($transaction, 404, 'Transaksi tidak ditemukan pada konteks aktif.');

        return $transaction;
    }

    private function candidateQuery(Transaction $transaction): Builder
    {
        $rawDate = $transaction->sourceValue('transaction_date');
        $date = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : null;

        $query = Transaction::query()->activeContext();
        ArkasMirrorResolver::joinKasUmum($query);

        return $query
            ->where('transactions.id', '!=', $transaction->id)
            ->when($date, fn (Builder $query) => $query->whereRaw(ArkasMirrorResolver::mirrorDate().' >= ?', [$date]))
            ->where('source_status', 'ACTIVE')
            ->where('requires_reconciliation', false)
            ->whereNotNull('payment_description')
            ->where('payment_description', '!=', '');
    }

    private function currentRole(Transaction $transaction): string
    {
        $searchable = strtolower(implode(' ', array_filter([
            $transaction->payment_description,
            $transaction->sourceValue('description'),
            $transaction->sourceValue('account_name'),
        ])));

        foreach (['upah', 'tukang', 'tenaga kerja', 'pekerja', 'ongkos kerja', 'honor pekerja'] as $term) {
            if (str_contains($searchable, $term)) {
                return 'labor';
            }
        }

        foreach (['bahan', 'barang', 'material', 'pembelian', 'semen', 'cat', 'pasir', 'batu', 'kayu', 'paku', 'besi'] as $term) {
            if (str_contains($searchable, $term)) {
                return 'material';
            }
        }

        return 'unknown';
    }
}
