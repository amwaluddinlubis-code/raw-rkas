<?php

namespace App\Services;

use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

/** Compares a Bridge snapshot with tenant staging without writing either side. */
class ArkasReconciliationService
{
    private readonly ArkasSourceKeyResolver $sourceKeys;

    public function __construct(
        private readonly ArkasStagingService $staging,
        ?ArkasSourceKeyResolver $sourceKeys = null,
    ) {
        $this->sourceKeys = $sourceKeys ?? new ArkasSourceKeyResolver;
    }

    /** @return array<string, mixed> */
    public function preview(ArkasImportProfile $profile, FiscalYear $year, ArkasSource $source): array
    {
        $preset = ArkasDomainAdapter::presetFor($profile->source_table);
        $command = $preset['bridge_command'];
        $bridgeYear = in_array($command, ['bku', 'rkas'], true) ? $year->year : null;
        $fundSource = in_array($command, ['bku', 'rkas'], true) ? $year->fund_source_id : null;
        $records = $this->staging->fetch($profile, $year, $source, $command, 'decode', $bridgeYear, $fundSource);
        $staged = DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->get(['source_key', 'payload_hash', 'payload']);
        $stagedHashes = $staged->pluck('payload_hash', 'source_key');
        $stagedPayloads = $staged->pluck('payload', 'source_key')->map(fn ($payload) => is_array($payload) ? $payload : json_decode((string) $payload, true));
        $incomingKeys = [];
        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'removed' => 0];
        $sourceKeyColumn = $profile->source_key_column ?: $preset['source_key_column'];
        $diffs = [];
        foreach ($records as $record) {
            $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $key = $this->sourceKeys->resolve($record, $sourceKeyColumn);
            $incomingKeys[$key] = true;
            if (! $stagedHashes->has($key)) {
                $counts['new']++;
                $diffs = $this->collectDiff($diffs, $record, $key, 'new', is_array($stagedPayloads[$key] ?? null) ? $stagedPayloads[$key] : []);
            } elseif ($stagedHashes->get($key) !== hash('sha256', $payload)) {
                $counts['changed']++;
                $diffs = $this->collectDiff($diffs, $record, $key, 'changed', is_array($stagedPayloads[$key] ?? null) ? $stagedPayloads[$key] : []);
            } else {
                $counts['unchanged']++;
            }
        }
        foreach ($stagedHashes->keys() as $key) {
            if (! isset($incomingKeys[$key])) {
                $counts['removed']++;
            }
        }

        return [...$counts, 'source_count' => count($records), 'staged_count' => $staged->count(), 'profile' => $profile->label, 'table' => $profile->source_table, 'diffs' => array_slice($diffs, 0, 25)];
    }

    /**
     * Catat delta nominal (jumlah/harga/volume/pagu) per baris baru/berubah
     * agar operator bisa meninjau arah dan besarnya sebelum menulis staging.
     *
     * @param  array<int,array<string,mixed>>  $diffs
     * @param  array<string,mixed>  $stagedPayload
     * @return array<int,array<string,mixed>>
     */
    public function collectDiff(array $diffs, array $record, string $key, string $type, array $stagedPayload): array
    {
        $interesting = [];
        foreach ($record as $field => $value) {
            $name = strtolower((string) $field);
            if (! preg_match('/jumlah|nominal|pagu|total|nilai|harga|amount|volume|sisa/i', $name)) {
                continue;
            }
            $new = is_numeric($value) ? (float) $value : null;
            $lowerStaged = [];
            foreach ($stagedPayload as $stagingField => $stagingValue) {
                $lowerStaged[strtolower((string) $stagingField)] = $stagingValue;
            }
            $oldRaw = $lowerStaged[$name] ?? null;
            $old = is_numeric($oldRaw) ? (float) $oldRaw : null;
            if ($new === null && $old === null) {
                continue;
            }
            $interesting[] = ['field' => (string) $field, 'old' => $old, 'new' => $new, 'delta' => ($new ?? 0.0) - ($old ?? 0.0)];
        }

        if ($interesting === []) {
            return $diffs;
        }

        return [...$diffs, ['source_key' => $key, 'type' => $type, 'deltas' => $interesting]];
    }

    /** @param array<string, string> $mapping @return array<int, string> */
    public function validateMapping(array $mapping, ?string $sourceKeyColumn, string $targetDomain): array
    {
        $errors = [];
        $allowed = array_keys(ArkasDomainAdapter::mappingRoles());
        $mapping = array_filter($mapping, static fn (mixed $role): bool => $role !== 'ignore' && filled($role));
        $invalid = array_diff(array_values($mapping), $allowed);
        if ($invalid !== []) {
            $errors[] = 'Ada peran kolom yang tidak dikenali: '.implode(', ', array_unique($invalid)).'.';
        }
        $duplicates = array_diff_assoc($mapping, array_unique($mapping));
        if ($duplicates !== []) {
            $errors[] = 'Satu peran tidak boleh dipakai oleh beberapa kolom: '.implode(', ', array_unique($duplicates)).'.';
        }
        if ($targetDomain !== 'raw' && ! in_array('source_key', $mapping, true)) {
            $errors[] = 'Target domain memerlukan satu kolom dengan peran Kunci.';
        }
        if ($sourceKeyColumn && ! array_key_exists($sourceKeyColumn, $mapping)) {
            $errors[] = 'Kolom kunci sumber harus ikut dipetakan sebagai Kunci.';
        }

        return $errors;
    }
}
