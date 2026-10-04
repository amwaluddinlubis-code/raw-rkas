<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan audit read-only atas jejak mirror: baris ditambah/dihapus/
 * berubah antar dua pengesahan terakhir, periode berstatus soft-deleted
 * yang dibuang dari kalkulasi, dan kas yang tidak bisa diatribusikan ke
 * revisi aktif (jatuh ke logika orphan share).
 */
final class ArkasMirrorAuditService
{
    public function __construct(private readonly ArkasMirrorBudgetService $budgets) {}

    /** @return array<string,mixed> */
    public function summarize(int $fundSourceId, int $year): array
    {
        $revisions = $this->budgets->revisions($fundSourceId, $year);
        $approved = collect($revisions)->where('status', 'approved')->values();
        $latestId = $approved->last()['id'] ?? null;
        $previousId = $approved->count() >= 2 ? $approved[$approved->count() - 2]['id'] : null;

        $latest = $latestId !== null ? $this->budgets->snapshot($fundSourceId, $year, [$latestId]) : null;
        $previous = $previousId !== null ? $this->budgets->snapshot($fundSourceId, $year, [$previousId]) : null;

        $shapes = [
            'added' => ['rows' => 0, 'amount' => 0.0],
            'removed' => ['rows' => 0, 'amount' => 0.0],
            'changed' => ['rows' => 0, 'delta' => 0.0],
        ];

        if ($latest !== null && $previous !== null) {
            $latestMap = $this->shapeMap($latest['rows']);
            $previousMap = $this->shapeMap($previous['rows']);
            foreach ($latestMap as $key => $amount) {
                if (! array_key_exists($key, $previousMap)) {
                    $shapes['added']['rows']++;
                    $shapes['added']['amount'] += $amount;
                } elseif (abs($amount - $previousMap[$key]) > 0.5) {
                    $shapes['changed']['rows']++;
                    $shapes['changed']['delta'] += $amount - $previousMap[$key];
                }
            }
            foreach ($previousMap as $key => $amount) {
                if (! array_key_exists($key, $latestMap)) {
                    $shapes['removed']['rows']++;
                    $shapes['removed']['amount'] += $amount;
                }
            }
        }

        $softDeletedPeriods = DB::connection('school')->table('arkas_mirror_rapbs_periode')
            ->get(['payload'])
            ->filter(fn ($row) => (($payload = json_decode((string) $row->payload, true)) !== null) && (int) ($payload['SOFT_DELETE'] ?? $payload['soft_delete'] ?? 0) === 1)
            ->count();

        $orphanKasIdentities = 0;
        if ($latest !== null) {
            $rowIdentities = $latest['rows']->pluck('identity_key')->filter()->flip();
            $kasByIdentity = [];
            foreach (DB::connection('school')->table('arkas_mirror_kas_umum')->get(['payload']) as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (! is_array($payload) || strtoupper((string) array_change_key_case($payload, CASE_UPPER)['KATEGORI_BKU'] ?? '') !== 'BELANJA') {
                    continue;
                }
                $rapbs = array_change_key_case($payload, CASE_UPPER)['ID_RAPBS'] ?? null;
                if ($rapbs) {
                    $kasByIdentity[$rapbs] = ($kasByIdentity[$rapbs] ?? 0) + (float) (array_change_key_case($payload, CASE_UPPER)['JUMLAH'] ?? 0);
                }
            }
            $knownRapbs = $latest['rows']->pluck('source_rapbs_id')->flip();
            foreach ($kasByIdentity as $rapbsId => $amount) {
                if (! $knownRapbs->has($rapbsId)) {
                    $orphanKasIdentities++;
                }
            }
        }

        return [
            'latest_revision' => $latestId,
            'previous_revision' => $previousId,
            'shapes' => $shapes,
            'soft_deleted_periods' => $softDeletedPeriods,
            'orphan_kas_rows' => $orphanKasIdentities,
        ];
    }

    /** @param Collection<int,array> $rows
     *  @return array<string,float> */
    private function shapeMap(Collection $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = ($row['identity_key'] ?? '') !== '' ? $row['identity_key'] : ($row['source_rapbs_id'] ?? uniqid());
            $map[$key] = ($map[$key] ?? 0.0) + (float) ($row['amount'] ?? 0);
        }

        return $map;
    }
}
