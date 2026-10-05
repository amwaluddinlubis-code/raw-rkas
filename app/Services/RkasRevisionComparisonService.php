<?php

namespace App\Services;

final class RkasRevisionComparisonService
{
    public function __construct(private readonly ArkasMirrorBudgetService $budgets) {}

    /** @return array<string,mixed>|null */
    public function compare(int $fundSourceId, int $year, string $fromId, string $toId): ?array
    {
        if ($fromId === $toId) {
            return null;
        }

        $revisions = $this->budgets->revisions($fundSourceId, $year);
        $from = collect($revisions)->firstWhere('id', $fromId);
        $to = collect($revisions)->firstWhere('id', $toId);
        if ($from === null || $to === null) {
            return null;
        }

        $before = $this->budgets->snapshot($fundSourceId, $year, [$fromId]);
        $after = $this->budgets->snapshot($fundSourceId, $year, [$toId]);
        $beforeRows = $this->groupRows($before['rows']->all());
        $afterRows = $this->groupRows($after['rows']->all());
        $keys = array_values(array_unique([...array_keys($beforeRows), ...array_keys($afterRows)]));
        $rows = [];
        $counts = ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0];

        foreach ($keys as $key) {
            $old = $beforeRows[$key] ?? null;
            $new = $afterRows[$key] ?? null;
            $status = match (true) {
                $old === null => 'added',
                $new === null => 'removed',
                $this->changed($old, $new) => 'changed',
                default => 'unchanged',
            };
            $counts[$status]++;
            if ($status === 'unchanged') {
                continue;
            }

            $base = $new ?? $old;
            $rows[] = [
                'identity' => $key,
                'status' => $status,
                'activity_code' => $base['activity_code'],
                'activity_name' => $base['activity_name'],
                'account_code' => $base['account_code'],
                'description' => $base['description'],
                'from_amount' => $old['amount'] ?? 0.0,
                'to_amount' => $new['amount'] ?? 0.0,
                'amount_delta' => ($new['amount'] ?? 0.0) - ($old['amount'] ?? 0.0),
                'from_volume' => $old['volume'] ?? 0.0,
                'to_volume' => $new['volume'] ?? 0.0,
                'volume_delta' => ($new['volume'] ?? 0.0) - ($old['volume'] ?? 0.0),
                'from_unit' => $old['unit'] ?? '—',
                'to_unit' => $new['unit'] ?? '—',
                'periods' => $this->periodDeltas($old['periods'] ?? [], $new['periods'] ?? []),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strnatcasecmp($left['activity_code'].' '.$left['account_code'].' '.$left['description'], $right['activity_code'].' '.$right['account_code'].' '.$right['description']));

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'counts' => $counts,
            'totals' => [
                'from' => $before['rows']->sum('amount'),
                'to' => $after['rows']->sum('amount'),
                'delta' => $after['rows']->sum('amount') - $before['rows']->sum('amount'),
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function groupRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $identity = (string) ($row['identity_key'] ?? '');
            if ($identity === '') {
                $identity = 'source:'.(string) $row['source_rapbs_id'];
            }
            $volume = (float) (ArkasMirrorResolver::field($row['payload'], ['VOLUME_TOTAL', 'VOLUME']) ?? 0);
            $unit = (string) (ArkasMirrorResolver::field($row['payload'], ['SATUAN', 'UNIT']) ?? '—');
            $groups[$identity] ??= [
                'activity_code' => $row['activity_code'],
                'activity_name' => $row['activity_name'],
                'account_code' => $row['account_code'],
                'description' => $row['description'],
                'amount' => 0.0,
                'volume' => 0.0,
                'unit' => $unit,
                'periods' => array_fill(1, 12, ['amount' => 0.0, 'volume' => 0.0]),
            ];
            $groups[$identity]['amount'] += (float) $row['amount'];
            $groups[$identity]['volume'] += $volume;
            foreach ($row['periods'] as $period) {
                $month = (int) ($period['__MONTH_NUMBER'] ?? 0);
                if ($month >= 1 && $month <= 12) {
                    $groups[$identity]['periods'][$month]['amount'] += (float) (ArkasMirrorResolver::field($period, ['JUMLAH']) ?? 0);
                    $groups[$identity]['periods'][$month]['volume'] += (float) (ArkasMirrorResolver::field($period, ['VOLUME']) ?? 0);
                }
            }
        }

        return $groups;
    }

    /** @param array<string,mixed> $old
     * @param  array<string,mixed>  $new
     */
    private function changed(array $old, array $new): bool
    {
        return abs($old['amount'] - $new['amount']) > 0.0001
            || abs($old['volume'] - $new['volume']) > 0.0001
            || $old['unit'] !== $new['unit']
            || $old['activity_name'] !== $new['activity_name']
            || $old['description'] !== $new['description']
            || $old['periods'] !== $new['periods'];
    }

    /** @param array<int,array{amount:float,volume:float}> $old
     * @param  array<int,array{amount:float,volume:float}>  $new
     * @return array<int,array{month:int,from:float,to:float,delta:float,volume_from:float,volume_to:float,volume_delta:float}>
     */
    private function periodDeltas(array $old, array $new): array
    {
        $deltas = [];
        for ($month = 1; $month <= 12; $month++) {
            $from = (float) ($old[$month]['amount'] ?? 0);
            $to = (float) ($new[$month]['amount'] ?? 0);
            $volumeFrom = (float) ($old[$month]['volume'] ?? 0);
            $volumeTo = (float) ($new[$month]['volume'] ?? 0);
            if (abs($from - $to) > 0.0001 || abs($volumeFrom - $volumeTo) > 0.0001) {
                $deltas[] = [
                    'month' => $month,
                    'from' => $from,
                    'to' => $to,
                    'delta' => $to - $from,
                    'volume_from' => $volumeFrom,
                    'volume_to' => $volumeTo,
                    'volume_delta' => $volumeTo - $volumeFrom,
                ];
            }
        }

        return $deltas;
    }
}
