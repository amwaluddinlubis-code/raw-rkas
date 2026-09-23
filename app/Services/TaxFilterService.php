<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaxFilterService
{
    /**
     * Rekap pajak dari parameter eksplisit memakai implementasi yang
     * sama dengan jalur HTTP, untuk dipakai controller dan komponen Livewire.
     *
     * Nilai sumber (bruto/pajak per jenis) dibaca dari mirror ARKAS karena
     * satu transaksi dapat memiliki banyak baris BKU (JOIN 1:1 tak valid).
     *
     * @return array{summary: object, filteredSummary: object, transactions: LengthAwarePaginator, year: FiscalYear}
     */
    public function taxData(string $search, ?int $month, ?int $quarter, ?int $semester, int $perPage, int $page = 1, string $siplah = '', string $jenisPajak = ''): array
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));

        /** @var Collection<int, Transaction> $all */
        $all = Transaction::query()->activeContext()->with('items')->get();
        Transaction::preloadMirrorSource($all);

        $taxed = $all->filter(fn (Transaction $transaction): bool => (float) $transaction->sourceValue('tax_total') > 0)->values();
        if ($siplah === 'ya') {
            $taxed = $taxed->filter(fn (Transaction $transaction): bool => (bool) $transaction->sourceValue('is_siplah'))->values();
        } elseif ($siplah === 'tidak') {
            $taxed = $taxed->filter(fn (Transaction $transaction): bool => ! (bool) $transaction->sourceValue('is_siplah'))->values();
        }
        if (in_array($jenisPajak, ['ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'], true)) {
            $taxed = $taxed->filter(fn (Transaction $transaction): bool => (float) $transaction->sourceValue($jenisPajak) > 0)->values();
        }
        $summary = $this->summarize($taxed);

        $filtered = $taxed
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(function (Transaction $transaction) use ($search): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $transaction->sourceValue('no_bukti'),
                    $transaction->sourceValue('description'),
                    $transaction->sourceValue('recipient_name'),
                ]));

                return str_contains($haystack, mb_strtolower($search));
            })->values())
            ->when($month, fn (Collection $rows): Collection => $rows->filter(
                fn (Transaction $transaction): bool => (int) ($transaction->sourceCarbon()?->format('n') ?? 0) === (int) $month
            )->values())
            ->when(! $month && $quarter, function (Collection $rows) use ($quarter, $year): Collection {
                $from = Carbon::create($year->year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();
                $to = Carbon::create($year->year, $quarter * 3, 1)->endOfMonth();

                return $rows->filter(function (Transaction $transaction) use ($from, $to): bool {
                    $date = $transaction->sourceCarbon();

                    return $date && $date->between($from, $to);
                })->values();
            })
            ->when(! $month && ! $quarter && $semester, function (Collection $rows) use ($semester, $year): Collection {
                $from = Carbon::create($year->year, $semester === 1 ? 1 : 7, 1)->startOfMonth();
                $to = Carbon::create($year->year, $semester === 1 ? 6 : 12, 1)->endOfMonth();

                return $rows->filter(function (Transaction $transaction) use ($from, $to): bool {
                    $date = $transaction->sourceCarbon();

                    return $date && $date->between($from, $to);
                })->values();
            });

        $filteredSummary = $this->summarize($filtered);

        $page = max(1, $page);
        $ordered = $filtered->sortByDesc(fn (Transaction $transaction): array => [
            $transaction->sourceCarbon()?->format('Y-m-d') ?? '',
            $transaction->id,
        ])->values();
        $transactions = new LengthAwarePaginator(
            $ordered->forPage($page, $perPage)->values(),
            $ordered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return compact('summary', 'filteredSummary', 'transactions', 'year');
    }

    /** @param  Collection<int, Transaction>  $rows */
    private function summarize(Collection $rows): object
    {
        return (object) [
            'count' => $rows->count(),
            'ppn' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('ppn')),
            'pph21' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph21')),
            'pph22' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph22')),
            'pph23' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph23')),
            'pph4' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph4')),
            'sspd' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('sspd')),
            'total' => $rows->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('tax_total')),
        ];
    }
}
