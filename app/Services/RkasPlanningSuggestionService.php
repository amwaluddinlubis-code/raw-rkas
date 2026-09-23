<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saran perencanaan ARKAS dari data tersinkron.
 *
 * Modul 1 (pagu awal): dipakai saat RKAS tahun berjalan masih 0 atau belum
 * ada realisasi; baseline-nya pagu tahun lalu per kegiatan plus serapan
 * tahun lalu sebagai bahan musyawarah. Bukan angka final.
 *
 * Modul 2 (sisa pagu): memandu realokasi/penyerapan sisa pagu berjalan
 * per kegiatan berdasarkan tingkat serapan.
 */
class RkasPlanningSuggestionService
{
    /**
     * @return array{current: array{year_id:int, budget:float, spent:float}, initial: array{applicable:bool, base_year:?int, rows:array}, remaining: array{rows:array, totals:array}}
     */
    public function build(int $yearId, int $fundSourceId): array
    {
        if (Schema::connection('school')->hasTable('arkas_mirror_rapbs')) {
            return $this->buildFromMirror($yearId, $fundSourceId);
        }

        $current = $this->yearTotals($yearId, $fundSourceId);

        return [
            'current' => ['year_id' => $yearId] + $current,
            'initial' => $this->initialBudgetSuggestions($yearId, $fundSourceId, $current),
            'remaining' => $this->remainingBudgetSuggestions($yearId, $fundSourceId),
        ];
    }

    /**
     * Build suggestions from fixed mirror rows. The legacy normalized tables
     * remain a compatibility fallback only for databases not yet mirrored.
     *
     * @return array{current: array{year_id:int, budget:float, spent:float}, initial: array{applicable:bool, base_year:?int, rows:array}, remaining: array{rows:array, totals:array}}
     */
    private function buildFromMirror(int $yearId, int $fundSourceId): array
    {
        $year = (int) (FiscalYear::query()->whereKey($yearId)->value('year') ?: now()->year);
        $rkas = $this->mirrorRkasRows($fundSourceId);
        $realization = $this->mirrorRealization($fundSourceId);
        $currentRows = $rkas->filter(fn (array $row): bool => $row['year'] === null || $row['year'] === $year);
        $current = $this->totalsForRows($currentRows, $realization);
        $baseYear = $rkas->pluck('year')->filter(fn (?int $value): bool => $value !== null && $value < $year)->max();
        $baseRows = $baseYear === null ? collect() : $rkas->filter(fn (array $row): bool => $row['year'] === $baseYear);

        return [
            'current' => ['year_id' => $yearId] + $current,
            'initial' => [
                'applicable' => $current['budget'] <= 0 || $current['spent'] <= 0,
                'base_year' => $baseYear,
                'rows' => $this->initialMirrorSuggestions($baseRows, $realization),
            ],
            'remaining' => $this->remainingMirrorSuggestions($currentRows, $realization),
        ];
    }

    /** @return Collection<int, array{source_rapbs_id:string,activity_code:string,activity_name:string,amount:float,year:?int}> */
    private function mirrorRkasRows(int $fundSourceId): Collection
    {
        $rows = DB::connection('school')->table('arkas_mirror_rapbs')->get(['source_key', 'payload']);

        return $rows->map(function (object $row) use ($fundSourceId): ?array {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                return null;
            }
            // Saran perencanaan tidak menawarkan baris yang sudah dihapus
            // di ARKAS.
            if ((int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                return null;
            }
            $fund = (int) (ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA', 'SUMBER_DANA_ID']) ?? 0);
            if ($fund !== 0 && $fund !== $fundSourceId) {
                return null;
            }
            $year = ArkasMirrorResolver::field($payload, ['TAHUN', 'TAHUN_ANGGARAN', 'tahun']);

            return [
                'source_rapbs_id' => (string) $row->source_key,
                'activity_code' => trim((string) (ArkasMirrorResolver::field($payload, ['KODE_KEGIATAN', 'ID_KODE']) ?? ''), '.'),
                'activity_name' => (string) (ArkasMirrorResolver::field($payload, ['NAMA_KEGIATAN', 'URAIAN_KODE', 'URAIAN']) ?? 'Kegiatan belum diisi'),
                'amount' => (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'amount']) ?? 0),
                'year' => is_numeric($year) ? (int) $year : null,
            ];
        })->filter()->values();
    }

    /** @return array<string, float> */
    private function mirrorRealization(int $fundSourceId): array
    {
        $periodToRkas = DB::connection('school')->table('arkas_mirror_rapbs_periode')->get(['source_key', 'payload'])
            ->mapWithKeys(function (object $row): array {
                $payload = json_decode((string) $row->payload, true) ?: [];

                return [(string) $row->source_key => (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '')];
            })->all();
        $realization = [];

        foreach (DB::connection('school')->table('arkas_mirror_kas_umum')->get(['payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)
                || strtoupper((string) ArkasMirrorResolver::field($payload, ['KATEGORI_BKU'])) !== 'BELANJA'
                || (int) (ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA']) ?? 0) !== $fundSourceId) {
                continue;
            }
            $rapbsId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '');
            if ($rapbsId === '') {
                $rapbsId = $periodToRkas[(string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS_PERIODE']) ?? '')] ?? '';
            }
            if ($rapbsId !== '') {
                $realization[$rapbsId] = ($realization[$rapbsId] ?? 0) + (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'amount']) ?? 0);
            }
        }

        return $realization;
    }

    /** @param Collection<int, array> $rows @param array<string,float> $realization @return array{budget:float,spent:float} */
    private function totalsForRows(Collection $rows, array $realization): array
    {
        return [
            'budget' => $rows->sum('amount'),
            'spent' => $rows->sum(fn (array $row): float => $realization[$row['source_rapbs_id']] ?? 0),
        ];
    }

    /** @param Collection<int,array> $rows @param array<string,float> $realization */
    private function initialMirrorSuggestions(Collection $rows, array $realization): array
    {
        return $this->groupMirrorRows($rows, $realization)->map(function (array $row): array {
            $rate = $row['budget'] > 0 ? $row['spent'] / $row['budget'] * 100 : 0;

            return ['activity_code' => $row['activity_code'], 'activity_name' => $row['activity_name'], 'last_budget' => $row['budget'], 'last_spent' => $row['spent'], 'absorption' => round($rate, 1), 'absorption_label' => $this->absorptionLabel($rate, $row['budget']), 'suggested' => $row['budget']];
        })->values()->all();
    }

    /** @param Collection<int,array> $rows @param array<string,float> $realization */
    private function remainingMirrorSuggestions(Collection $rows, array $realization): array
    {
        $grouped = $this->groupMirrorRows($rows, $realization)->map(function (array $row): array {
            $remaining = $row['budget'] - $row['spent'];
            $rate = $row['budget'] > 0 ? $row['spent'] / $row['budget'] * 100 : 0;

            return ['activity_code' => $row['activity_code'], 'activity_name' => $row['activity_name'], 'budget' => $row['budget'], 'spent' => $row['spent'], 'remaining' => $remaining, 'absorption' => round($rate, 1), 'status' => $this->remainingStatus($row['budget'], $row['spent'], $rate), 'suggestion' => $this->remainingSuggestion($row['budget'], $row['spent'], $remaining, $rate)];
        })->values()->all();

        return ['rows' => $grouped, 'totals' => ['budget' => array_sum(array_column($grouped, 'budget')), 'spent' => array_sum(array_column($grouped, 'spent')), 'remaining' => array_sum(array_column($grouped, 'remaining'))]];
    }

    /** @param Collection<int,array> $rows @param array<string,float> $realization @return Collection<int,array> */
    private function groupMirrorRows(Collection $rows, array $realization): Collection
    {
        return $rows->groupBy('activity_code')->map(function (Collection $group, string $code) use ($realization): array {
            $first = $group->first();

            return ['activity_code' => $code !== '' ? $code : '-', 'activity_name' => $first['activity_name'], 'budget' => $group->sum('amount'), 'spent' => $group->sum(fn (array $row): float => $realization[$row['source_rapbs_id']] ?? 0)];
        });
    }

    /**
     * @return array{budget:float, spent:float}
     */
    private function yearTotals(int $yearId, int $fundSourceId): array
    {
        $db = DB::connection('school');

        return [
            'budget' => (float) $db->table('arkas_rkas_items')
                ->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->sum('amount'),
            'spent' => (float) $db->table('arkas_bku_rows')
                ->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)
                ->where('category', 'BELANJA')->sum('amount'),
        ];
    }

    private function realizationSubquery(int $yearId, int $fundSourceId): mixed
    {
        return DB::connection('school')->table('arkas_bku_rows')
            ->selectRaw("json_extract(payload, '\$.ID_RAPBS') as source_rapbs_id, SUM(amount) as realization")
            ->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)
            ->where('category', 'BELANJA')
            ->groupByRaw("json_extract(payload, '\$.ID_RAPBS')");
    }

    private function baseYearId(int $yearId, int $fundSourceId, int $currentYear): ?array
    {
        $row = DB::connection('school')->table('arkas_rkas_items as r')
            ->join('fiscal_years as y', 'y.id', '=', 'r.fiscal_year_id')
            ->where('r.fund_source_id', $fundSourceId)
            ->where('y.year', '<', $currentYear)
            ->selectRaw('r.fiscal_year_id, y.year, SUM(r.amount) as budget')
            ->groupBy('r.fiscal_year_id', 'y.year')
            ->orderByDesc('y.year')
            ->first();

        return $row ? ['id' => (int) $row->fiscal_year_id, 'year' => (int) $row->year] : null;
    }

    private function initialBudgetSuggestions(int $yearId, int $fundSourceId, array $current): array
    {
        $db = DB::connection('school');
        $currentYear = (int) ($db->table('fiscal_years')->where('id', $yearId)->value('year') ?? 0);
        $base = $this->baseYearId($yearId, $fundSourceId, $currentYear);
        $applicable = $current['budget'] <= 0 || $current['spent'] <= 0;

        if ($base === null) {
            return ['applicable' => $applicable, 'base_year' => null, 'rows' => []];
        }

        $realization = $this->realizationSubquery($base['id'], $fundSourceId);
        $rows = $db->table('arkas_rkas_items as r')
            ->leftJoinSub($realization, 'b', fn ($join) => $join->on('b.source_rapbs_id', '=', 'r.source_rapbs_id'))
            ->where('r.fiscal_year_id', $base['id'])->where('r.fund_source_id', $fundSourceId)
            ->selectRaw('r.activity_code, r.activity_name, SUM(r.amount) as budget, COALESCE(SUM(b.realization), 0) as spent')
            ->groupBy('r.activity_code', 'r.activity_name')
            ->orderBy('r.activity_code')
            ->get()
            ->map(function (object $row): array {
                $budget = (float) $row->budget;
                $spent = (float) $row->spent;
                $rate = $budget > 0 ? $spent / $budget * 100 : 0;

                return [
                    'activity_code' => $row->activity_code ?: '-',
                    'activity_name' => $row->activity_name ?: 'Kegiatan belum diisi',
                    'last_budget' => $budget,
                    'last_spent' => (float) $row->spent,
                    'absorption' => round($rate, 1),
                    'absorption_label' => $this->absorptionLabel($rate, $budget),
                    // Baseline musyawarah: mulai dari pagu tahun lalu.
                    'suggested' => $budget,
                ];
            })->all();

        return ['applicable' => $applicable, 'base_year' => $base['year'], 'rows' => $rows];
    }

    private function remainingBudgetSuggestions(int $yearId, int $fundSourceId): array
    {
        $db = DB::connection('school');
        $realization = $this->realizationSubquery($yearId, $fundSourceId);
        $rows = $db->table('arkas_rkas_items as r')
            ->leftJoinSub($realization, 'b', fn ($join) => $join->on('b.source_rapbs_id', '=', 'r.source_rapbs_id'))
            ->where('r.fiscal_year_id', $yearId)->where('r.fund_source_id', $fundSourceId)
            ->selectRaw('r.activity_code, r.activity_name, SUM(r.amount) as budget, COALESCE(SUM(b.realization), 0) as spent')
            ->groupBy('r.activity_code', 'r.activity_name')
            ->orderBy('r.activity_code')
            ->get()
            ->map(function (object $row): array {
                $budget = (float) $row->budget;
                $spent = (float) $row->spent;
                $remaining = $budget - $spent;
                $rate = $budget > 0 ? $spent / $budget * 100 : 0;

                return [
                    'activity_code' => $row->activity_code ?: '-',
                    'activity_name' => $row->activity_name ?: 'Kegiatan belum diisi',
                    'budget' => $budget,
                    'spent' => $spent,
                    'remaining' => $remaining,
                    'absorption' => round($rate, 1),
                    'status' => $this->remainingStatus($budget, $spent, $rate),
                    'suggestion' => $this->remainingSuggestion($budget, $spent, $remaining, $rate),
                ];
            })->all();

        $totals = [
            'budget' => array_sum(array_column($rows, 'budget')),
            'spent' => array_sum(array_column($rows, 'spent')),
            'remaining' => array_sum(array_column($rows, 'remaining')),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function absorptionLabel(float $rate, float $budget): string
    {
        if ($budget <= 0) {
            return 'Tanpa pagu';
        }
        if ($rate >= 80) {
            return 'Serapan baik';
        }
        if ($rate > 0) {
            return 'Serapan rendah';
        }

        return 'Tak terserap';
    }

    private function remainingStatus(float $budget, float $spent, float $rate): string
    {
        if ($spent > $budget) {
            return 'Lewat pagu';
        }
        if ($budget <= 0) {
            return 'Tanpa pagu';
        }
        if ($rate >= 80) {
            return 'Terserap baik';
        }
        if ($rate > 0) {
            return 'Tersendat';
        }

        return 'Belum tersentuh';
    }

    private function remainingSuggestion(float $budget, float $spent, float $remaining, float $rate): string
    {
        if ($spent > $budget) {
            return 'Kurangi belanja kegiatan ini / geser ke perubahan RKAS.';
        }
        if ($budget <= 0) {
            return 'Tidak ada pagu; abaikan kecuali ada penambahan.';
        }
        if ($rate >= 80) {
            return 'Pertahankan ritme; sisakan cadangan secukupnya.';
        }
        if ($rate > 0) {
            return 'Percepat realisasi sisa Rp '.number_format($remaining, 0, ',', '.').'.';
        }

        return 'Segera realisasikan atau usulkan realokasi Rp '.number_format($remaining, 0, ',', '.').'.';
    }
}
