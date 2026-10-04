<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Simulator what-if pagu RKAS: mengubah total pagu per kegiatan dan
 * memproyeksikan dampaknya per triwulan menggunakan proporsi split
 * periode yang sudah ada. Read-only; tidak menulis staging/mirro.
 */
final class RkasPaguSimulationService
{
    /**
     * @param  Collection<int,array>  $rows
     * @param  array<string,float>  $overrides  activity_code => total baru
     * @return array{activities:array<int,array{code:string,name:string,current:float,proposed:float}>,current_quarters:array<int,float>,proposed_quarters:array<int,float>,delta_quarters:array<int,float>}
     */
    public function simulate(Collection $rows, array $overrides): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $code = (string) ($row['activity_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $quarterTotals = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
            foreach ($row['periods'] ?? [] as $period) {
                $quarter = (int) ($period['__QUARTER_NUMBER'] ?? 0);
                if ($quarter >= 1 && $quarter <= 4) {
                    $quarterTotals[$quarter] += (float) (ArkasMirrorResolver::field($period, ['JUMLAH']) ?? 0);
                }
            }
            $groups[$code] ??= ['code' => $code, 'name' => (string) ($row['activity_name'] ?? ''), 'total' => 0.0, 'quarters' => [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0]];
            $groups[$code]['total'] += (float) ($row['amount'] ?? 0);
            foreach ($quarterTotals as $q => $v) {
                $groups[$code]['quarters'][$q] += $v;
            }
        }

        $activities = [];
        $currentQuarters = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $proposedQuarters = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        foreach ($groups as $group) {
            $proposed = array_key_exists($group['code'], $overrides) ? (float) $overrides[$group['code']] : $group['total'];
            $activities[] = ['code' => $group['code'], 'name' => $group['name'], 'current' => $group['total'], 'proposed' => $proposed];
            foreach ([1, 2, 3, 4] as $q) {
                $weight = $group['total'] > 0 ? $group['quarters'][$q] / $group['total'] : 0.0;
                $currentQuarters[$q] += $group['quarters'][$q];
                $proposedQuarters[$q] += $proposed * $weight;
            }
        }

        return [
            'activities' => array_values($activities),
            'current_quarters' => $currentQuarters,
            'proposed_quarters' => $proposedQuarters,
            'delta_quarters' => [
                1 => $proposedQuarters[1] - $currentQuarters[1],
                2 => $proposedQuarters[2] - $currentQuarters[2],
                3 => $proposedQuarters[3] - $currentQuarters[3],
                4 => $proposedQuarters[4] - $currentQuarters[4],
            ],
        ];
    }
}
