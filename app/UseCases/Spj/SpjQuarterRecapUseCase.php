<?php

namespace App\UseCases\Spj;

use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Collection;

/**
 * Rekap 1-halaman per triwulan untuk Bendahara/Kepsek.
 *
 * Read-only: agregat status paket + total nilai sumber (mirror) dalam
 * konteks School + Fiscal Year + Fund Source aktif.
 */
final class SpjQuarterRecapUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $context,
    ) {}

    /**
     * @return array{
     *   quarter: int,
     *   counts: array{transactions: int, without_package: int, draft: int, ready: int, numbered: int, final: int, cancelled: int},
     *   totals: array{gross: float, tax: float, net: float},
     *   draft: Collection<int, Transaction>,
     *   ready: Collection<int, Transaction>
     * }
     */
    public function recap(int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        [$fromMonth, $toMonth] = ArkasMirrorResolver::quarterMonthRange($quarter);

        $transactions = Transaction::query()
            ->forSpjContext($this->context)
            ->with(['spjPackage:id,transaction_id,status,document_number', 'items:id,transaction_id,source_item_id'])
            ->select('transactions.*')
            ->tap(function ($query) use ($fromMonth, $toMonth): void {
                ArkasMirrorResolver::joinKasUmum($query);
                ArkasMirrorResolver::whereMirrorDate(
                    $query,
                    "CAST(strftime('%m', {d}) AS INTEGER) BETWEEN ? AND ?",
                    [$fromMonth, $toMonth]
                );
            })
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id')
            ->get();

        Transaction::preloadMirrorSource($transactions);

        $counts = [
            'transactions' => $transactions->count(),
            'without_package' => 0,
            'draft' => 0,
            'ready' => 0,
            'numbered' => 0,
            'final' => 0,
            'cancelled' => 0,
        ];

        foreach ($transactions as $transaction) {
            $status = strtoupper((string) $transaction->spjPackage?->status);
            match ($status) {
                'DRAFT' => $counts['draft']++,
                'READY' => $counts['ready']++,
                'NUMBERED' => $counts['numbered']++,
                'FINAL' => $counts['final']++,
                'CANCELLED' => $counts['cancelled']++,
                default => $counts['without_package']++,
            };
        }

        $sum = static fn (string $field): float => $transactions->sum(
            static fn (Transaction $transaction): float => (float) $transaction->sourceValue($field)
        );

        $isStatus = static fn (Transaction $transaction, string $status): bool => strtoupper((string) $transaction->spjPackage?->status) === $status;

        return [
            'quarter' => $quarter,
            'counts' => $counts,
            'totals' => [
                'gross' => $sum('gross_amount'),
                'tax' => $sum('tax_total'),
                'net' => $sum('net_amount'),
            ],
            'draft' => $transactions->filter(fn (Transaction $t): bool => $isStatus($t, 'DRAFT'))->take(50)->values(),
            'ready' => $transactions->filter(fn (Transaction $t): bool => $isStatus($t, 'READY'))->take(50)->values(),
        ];
    }
}
