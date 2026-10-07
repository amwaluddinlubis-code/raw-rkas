<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionSettlementService
{
    public function __construct(private FiscalPeriodWorkflowService $periods) {}

    /** @param array<string,mixed> $data */
    public function addPayment(Transaction $transaction, array $data): TransactionPayment
    {
        return DB::connection('school')->transaction(function () use ($transaction, $data): TransactionPayment {
            $gross = (float) $data['gross_amount'];
            $tax = (float) ($data['tax_amount'] ?? 0);
            if ($gross <= 0 || $tax < 0 || $tax > $gross) {
                throw new \InvalidArgumentException('Nilai pembayaran atau pajak tidak valid.');
            }
            $paymentDate = Carbon::parse($data['payment_date']);
            if ((int) $paymentDate->format('Y') !== (int) $transaction->fiscalYear->year) {
                throw new \RuntimeException('Tanggal pembayaran harus berada dalam tahun anggaran aktif.');
            }
            $latestReceipt = $transaction->goodsReceipts()->where('status', '!=', 'CANCELLED')->max('receipt_date');
            if ($latestReceipt && $paymentDate->lt(Carbon::parse($latestReceipt))) {
                throw new \RuntimeException('Tanggal pembayaran tidak boleh lebih awal daripada penerimaan barang terakhir.');
            }
            $paid = (float) $transaction->payments()->where('status', '!=', 'CANCELLED')->sum('gross_amount');
            if (round($paid + $gross, 2) > round((float) $transaction->sourceValue('gross_amount'), 2)) {
                throw new \RuntimeException('Total pembayaran tidak boleh melebihi nilai transaksi.');
            }
            $sequence = ((int) $transaction->payments()->max('payment_sequence')) + 1;
            $quarter = ArkasMirrorResolver::quarterOfMonth((int) Carbon::parse($transaction->sourceValue('transaction_date'))->format('n'));

            return $transaction->payments()->create([
                ...$data,
                'scope_key' => 'PAYMENT:'.$sequence,
                'payment_sequence' => $sequence,
                'net_amount' => $gross - $tax,
                'is_late_entry' => $this->periods->isLateEntry($transaction->fiscal_year_id, $quarter),
            ]);
        });
    }

    /** @param array<string,mixed> $data @param array<int,array<string,mixed>> $items */
    public function addGoodsReceipt(Transaction $transaction, array $data, array $items): GoodsReceipt
    {
        return DB::connection('school')->transaction(function () use ($transaction, $data, $items): GoodsReceipt {
            if ($items === []) {
                throw new \InvalidArgumentException('Penerimaan harus memiliki minimal satu rincian barang.');
            }
            // Tanggal terima fisik boleh dikosongkan: otomatis mengikuti
            // tanggal BAP → BAST → pesanan tahap tersebut.
            if (! filled($data['receipt_date'] ?? null)) {
                $data['receipt_date'] = $data['bap_date'] ?? $data['bast_date'] ?? $data['order_date'] ?? null;
            }
            if (! filled($data['receipt_date'] ?? null)) {
                throw new \InvalidArgumentException('Isi tanggal terima fisik atau salah satu tanggal surat tahap (pesanan/BAP/BAST).');
            }
            $receiptDate = Carbon::parse($data['receipt_date']);
            if ((int) $receiptDate->format('Y') !== (int) $transaction->fiscalYear->year) {
                throw new \RuntimeException('Tanggal penerimaan harus berada dalam tahun anggaran aktif.');
            }
            foreach (['order_date' => 'pesanan', 'bap_date' => 'BAP', 'bast_date' => 'BAST', 'invoice_date' => 'invoice'] as $column => $label) {
                if (! filled($data[$column] ?? null)) {
                    continue;
                }
                if ((int) Carbon::parse($data[$column])->format('Y') !== (int) $transaction->fiscalYear->year) {
                    throw new \RuntimeException('Tanggal '.$label.' tahap harus berada dalam tahun anggaran aktif.');
                }
            }
            $earliestOrder = $transaction->goods()->whereNotNull('order_date')->min('order_date');
            if ($earliestOrder && $receiptDate->lt(Carbon::parse($earliestOrder))) {
                throw new \RuntimeException('Tanggal penerimaan tidak boleh lebih awal daripada tanggal pesanan.');
            }
            foreach ($items as $index => $item) {
                $ordered = $transaction->items()->findOrFail($item['transaction_item_id']);
                $received = (float) $ordered->receiptItems()->whereHas('receipt', fn ($query) => $query->where('status', '!=', 'CANCELLED'))->sum('quantity_received');
                if ((float) $item['quantity_received'] <= 0 || round($received + (float) $item['quantity_received'], 4) > round((float) $ordered->sourceValue('quantity'), 4)) {
                    $remaining = round((float) $ordered->sourceValue('quantity') - $received, 4);

                    throw new \RuntimeException('Jumlah barang diterima melebihi jumlah yang dipesan untuk '.$ordered->item_description.'. Sisa yang dapat diterima: '.$remaining.'.');
                }
                // Nilai penerimaan kanonis dari harga satuan sumber, bukan input operator.
                $items[$index]['amount_received'] = $this->receivedAmount($ordered, (float) $item['quantity_received']);
            }
            $sequence = ((int) $transaction->goodsReceipts()->max('receipt_sequence')) + 1;
            $quarter = ArkasMirrorResolver::quarterOfMonth((int) Carbon::parse($transaction->sourceValue('transaction_date'))->format('n'));
            $receipt = $transaction->goodsReceipts()->create([
                ...$data,
                'scope_key' => 'RECEIPT:'.$sequence,
                'receipt_sequence' => $sequence,
                'is_late_entry' => $this->periods->isLateEntry($transaction->fiscal_year_id, $quarter),
            ]);
            $receipt->items()->createMany($items);

            return $receipt->load('items');
        });
    }

    /**
     * Hapus satu tahap penerimaan beserta rincian itemnya. Sequence tahap
     * lain dipertahankan stabil (tidak diurut ulang) agar scope TAHAP:n
     * pada dokumen dan audit tetap merujuk tahap yang sama.
     */
    public function removeGoodsReceipt(Transaction $transaction, GoodsReceipt $receipt): void
    {
        if ((int) $receipt->transaction_id !== (int) $transaction->id) {
            throw new \InvalidArgumentException('Tahap penerimaan tidak termasuk transaksi ini.');
        }

        DB::connection('school')->transaction(function () use ($receipt): void {
            $receipt->items()->delete();
            $receipt->delete();
        });
    }

    /**
     * Rincian barang yang masih memiliki sisa untuk diterima, beserta sisa,
     * satuan, dan harga satuan kanonis (sumber ARKAS/BKU). Dipakai form
     * penerimaan agar select hanya menawarkan barang yang bersisa dan
     * nilai penerimaan terhitung otomatis.
     *
     * @return array<int, array{id:int, label:string, remaining:float, unit:string, unit_price:float}>
     */
    public function receivableItems(Transaction $transaction): array
    {
        $result = [];
        foreach ($transaction->items as $item) {
            $ordered = (float) $item->sourceValue('quantity');
            $received = (float) $item->receiptItems()->whereHas('receipt', fn ($query) => $query->where('status', '!=', 'CANCELLED'))->sum('quantity_received');
            $remaining = round($ordered - $received, 4);
            if ($remaining <= 0) {
                continue;
            }
            $unitPrice = $ordered > 0 ? round((float) $item->sourceValue('amount') / $ordered, 2) : 0.0;
            $result[] = [
                'id' => (int) $item->id,
                'label' => (string) ($item->item_description ?: $item->sourceValue('description') ?: 'Item #'.$item->id),
                'remaining' => $remaining,
                'unit' => (string) ($item->sourceValue('unit') ?: ''),
                'unit_price' => $unitPrice,
            ];
        }

        return $result;
    }

    /**
     * Nilai kanonis satu baris penerimaan: jumlah diterima × harga satuan
     * sumber. Tidak bergantung pada input operator.
     */
    public function receivedAmount(object $item, float $quantity): float
    {
        $ordered = (float) $item->sourceValue('quantity');

        return $ordered > 0 ? round($quantity * (float) $item->sourceValue('amount') / $ordered, 2) : 0.0;
    }
}
