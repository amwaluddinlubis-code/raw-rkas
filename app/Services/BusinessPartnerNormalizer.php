<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-time normalizer for ARKAS-derived supplier rows.
 *
 * ARKAS writes supplier identity as free text (NAMA_TOKO) plus a loosely
 * formatted NPWP, so the same supplier can arrive as several rows, e.g.
 * "AHMAD RIPAI" vs "Ahmad Ripai", or dotted vs plain-digit NPWP. The table
 * unique key (name, npwp) is case- and format-sensitive, so those variants
 * coexist as duplicates. This service groups by normalized identity
 * (case/whitespace-folded name + digit-only NPWP) and fuses each group
 * into a single survivor row.
 *
 * Operator-created rows (is_arkas_synced = false) are never deleted: when
 * such a row collides with ARKAS variants it becomes the survivor.
 */
class BusinessPartnerNormalizer
{
    /**
     * @return array{groups:int, merged:int, dry_run:bool, pairs:array<int, array{keep:int, drop:array<int, int>, names:array<int, string>}>}
     */
    public function normalize(bool $dryRun = true): array
    {
        $pairs = [];
        $merges = [];

        foreach ($this->duplicateGroups() as $group) {
            $ordered = $group->sortBy('id')->values();
            // Operator-created rows are canonical, then the most complete
            // row, then the freshest sync result, then the oldest row.
            $survivor = $ordered->first(fn (object $row) => ! (bool) $row->is_arkas_synced)
                ?? $ordered->sortByDesc(fn (object $row) => $this->completeness($row))->first()
                ?? $ordered->first();
            if ($survivor === null) {
                continue;
            }
            $freshest = $ordered->sortByDesc(fn (object $row) => (string) $row->updated_at)->first();
            $losers = $ordered->where('id', '!==', $survivor->id)->values();

            $pairs[] = [
                'keep' => $survivor->id,
                'drop' => $losers->pluck('id')->all(),
                'names' => $ordered->map(fn (object $row) => '['.$row->id.'] '.($row->name ?? '-').' / '.($row->npwp ?? '-'))->all(),
            ];

            if (! $dryRun) {
                $merges[] = [$survivor->id, $losers, $freshest?->id];
            }
        }

        $merged = 0;
        if (! $dryRun && $merges !== []) {
            DB::connection('school')->transaction(function () use ($merges, &$merged): void {
                foreach ($merges as [$keepId, $losers, $freshestId]) {
                    $this->mergeInto($keepId, $losers, $freshestId);
                    $merged += count($losers);
                }
            });
        }

        return [
            'groups' => count($pairs),
            'merged' => $merged,
            'dry_run' => $dryRun,
            'pairs' => $pairs,
        ];
    }

    /** @return array<int, Collection<int, object>> */
    private function duplicateGroups(): array
    {
        $rows = DB::connection('school')->table('business_partners')->get();
        $groups = [];
        foreach ($rows as $row) {
            $key = $this->normalizeName((string) ($row->name ?? ''))."\x00".$this->normalizeNpwp((string) ($row->npwp ?? ''));
            $groups[$key][] = $row;
        }

        return array_values(array_filter(
            array_map(fn (array $group) => collect($group), $groups),
            fn ($group) => $group->count() > 1
        ));
    }

    private function normalizeName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return mb_strtolower($collapsed);
    }

    private function normalizeNpwp(string $npwp): string
    {
        return preg_replace('/\D/', '', $npwp) ?? '';
    }

    private function completeness(object $row): int
    {
        $score = 0;
        foreach (['npwp', 'phone', 'address'] as $column) {
            if (filled($row->{$column} ?? null)) {
                $score++;
            }
        }
        if ((bool) ($row->is_business_entity ?? false)) {
            $score++;
        }

        return $score;
    }

    /**
     * @param  Collection<int, object>  $losers
     */
    private function mergeInto(int $keepId, object $losers, ?int $freshestId): void
    {
        $db = DB::connection('school');
        $survivor = $db->table('business_partners')->where('id', $keepId)->first();
        if (! $survivor) {
            return;
        }

        $all = collect([$survivor])->merge($losers)->sortByDesc(fn (object $row) => (string) $row->updated_at)->values();
        $update = [];

        // Prefer the freshest sync spelling for the display name/NPWP, but
        // keep the survivor row identity (and manual canonical rows win).
        if ((bool) $survivor->is_arkas_synced && $freshestId !== null) {
            $freshest = $all->firstWhere('id', $freshestId);
            if ($freshest) {
                $update['name'] = $freshest->name;
                $update['npwp'] = $this->preferredNpwp($all);
            }
        } else {
            $update['npwp'] = $this->preferredNpwp($all);
        }

        foreach (['phone', 'address'] as $column) {
            if (! filled($survivor->{$column} ?? null)) {
                $filled = $all->first(fn (object $row) => filled($row->{$column} ?? null));
                if ($filled) {
                    $update[$column] = $filled->{$column};
                }
            }
        }

        if (! (bool) ($survivor->is_business_entity ?? false)
            && $all->contains(fn (object $row) => (bool) ($row->is_business_entity ?? false))) {
            $update['is_business_entity'] = true;
        }

        $mergedPayload = $this->mergePayloads($all);
        if ($mergedPayload !== null) {
            $update['payload'] = $mergedPayload;
        }

        $loserIds = $losers->pluck('id')->all();
        if ($loserIds !== []) {
            // Delete first: the (name, npwp) unique key is enforced
            // immediately, so the survivor rename must land afterwards.
            $db->table('business_partners')->whereIn('id', $loserIds)->delete();
        }
        $update['updated_at'] = now();
        $db->table('business_partners')->where('id', $keepId)->update($update);
    }

    /**
     * @param  Collection<int, object>  $rows  freshest first
     */
    private function preferredNpwp(object $rows): ?string
    {
        $candidates = $rows->map(fn (object $row) => (string) ($row->npwp ?? ''))->filter()->values();
        if ($candidates->isEmpty()) {
            return null;
        }
        // Same digit sequence: keep the longest (usually formatted) variant.
        $byDigits = [];
        foreach ($candidates as $candidate) {
            $byDigits[$this->normalizeNpwp($candidate)][] = $candidate;
        }
        $first = $rows->map(fn (object $row) => (string) ($row->npwp ?? ''))->filter()->first();
        $pool = $byDigits[$this->normalizeNpwp((string) $first)] ?? $candidates->all();

        return collect($pool)->sortByDesc(fn (string $value) => mb_strlen($value))->first();
    }

    /**
     * @param  Collection<int, object>  $rows  freshest first
     */
    private function mergePayloads(object $rows): ?string
    {
        $merged = [];
        foreach ($rows->reverse()->values() as $row) {
            $payload = json_decode((string) ($row->payload ?? ''), true);
            if (is_array($payload)) {
                $merged = array_merge($merged, $payload);
            }
        }

        if ($merged === []) {
            return null;
        }

        return json_encode($merged, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
