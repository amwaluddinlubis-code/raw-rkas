<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Support\ActiveSpjContext;
use Illuminate\Database\Eloquent\Builder;
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
     * Ringkasan per triwulan untuk halaman workflow penomoran.
     *
     * Semantik query disengaja identik dengan jalur HTTP lama dan BERBEDA
     * dari recap(): hanya transaksi ber-rincian, scope mirror mentah tanpa
     * fallback yatim. Jangan disatukan dengan recap() tanpa mengubah
     * angka yang dilihat operator.
     *
     * @return Collection<int, array{quarter:int, transactions:int, without_package:int, draft:int, ready:int, numbered:int, final:int, blocked:int, closure:?FiscalPeriodClosure}>
     */
    public function quarterSummaries(): Collection
    {
        $closures = FiscalPeriodClosure::query()
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->orderBy('quarter')
            ->get()
            ->keyBy('quarter');

        return collect(range(1, 4))->mapWithKeys(function (int $quarter) use ($closures): array {
            $transactionQuery = $this->summaryScope(Transaction::query()->activeContext(), $quarter);
            $packageQuery = SpjPackage::query()
                ->whereHas('transaction', fn (Builder $query): Builder => $this->summaryScope($query->activeContext(), $quarter));

            $transactionsWithItems = (clone $transactionQuery)->has('items')->count();
            $withoutPackage = (clone $transactionQuery)->has('items')->doesntHave('spjPackage')->count();
            $draft = (clone $packageQuery)->where('status', 'DRAFT')->count();
            $ready = (clone $packageQuery)->where('status', 'READY')->count();
            $numbered = (clone $packageQuery)->where('status', 'NUMBERED')->count();
            $final = (clone $packageQuery)->where('status', 'FINAL')->count();

            return [$quarter => [
                'quarter' => $quarter,
                'transactions' => $transactionsWithItems,
                'without_package' => $withoutPackage,
                'draft' => $draft,
                'ready' => $ready,
                'numbered' => $numbered,
                'final' => $final,
                'blocked' => $withoutPackage + $draft,
                'closure' => $closures->get($quarter),
            ]];
        });
    }

    /**
     * Scope triwulan listing workflow (lihat quarterSummaries() untuk
     * peringatan semantik vs recap()).
     */
    public function summaryScope(Builder $query, int $quarter): Builder
    {
        [$startMonth, $endMonth] = ArkasMirrorResolver::quarterMonthRange($quarter);
        ArkasMirrorResolver::joinKasUmum($query);

        return $query->select('transactions.*')
            ->whereRaw(ArkasMirrorResolver::mirrorMonth().' >= ?', [$startMonth])
            ->whereRaw(ArkasMirrorResolver::mirrorMonth().' <= ?', [$endMonth]);
    }

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

        $isStatus = static fn (Transaction $transaction, string $status): bool => strtoupper((string) $transaction->spjPackage?->status) === $status;

        return [
            'quarter' => $quarter,
            'counts' => $counts,
            'totals' => [
                'gross' => Transaction::sumSource($transactions, 'gross_amount'),
                'tax' => Transaction::sumSource($transactions, 'tax_total'),
                'net' => Transaction::sumSource($transactions, 'net_amount'),
            ],
            'draft' => $transactions->filter(fn (Transaction $t): bool => $isStatus($t, 'DRAFT'))->take(50)->values(),
            'ready' => $transactions->filter(fn (Transaction $t): bool => $isStatus($t, 'READY'))->take(50)->values(),
        ];
    }
}
