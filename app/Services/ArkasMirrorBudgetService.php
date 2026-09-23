<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ArkasMirrorBudgetService
{
    /** @var array<string,array{rows:Collection,names:array<string,string>,realization:array<string,float>,realization_period:array<string,array<string,float>>}> */
    private array $snapshotCache = [];

    /** @return array<string,mixed> */
    public function render(Request $request, int $yearId, int $fundSourceId): array
    {
        $year = (int) (FiscalYear::query()->whereKey($yearId)->value('year') ?: now()->year);
        $revisionModes = $this->revisionModes($fundSourceId, $year);
        $revision = trim((string) $request->query('revisi', 'persetujuan'));
        if ($revision !== 'pengajuan' || ! $revisionModes['hasPendingSubmission']) {
            $revision = 'persetujuan';
        }
        $anggaranIds = $revision === 'pengajuan' && $revisionModes['submitted'] !== null
            ? [$revisionModes['submitted']['id']]
            : null;
        $snapshot = $anggaranIds === null
            ? $this->snapshot($fundSourceId, $year)
            : $this->snapshot($fundSourceId, $year, $anggaranIds);
        $scope = $this->scope($request);
        $search = trim((string) $request->query('q'));
        $program = trim((string) $request->query('program'));
        $subprogram = trim((string) $request->query('sub', $request->query('subprogram')));
        $activity = trim((string) $request->query('kegiatan', $request->query('activity')));
        $isWithin = static fn (string $code, string $parent): bool => $code === $parent || str_starts_with($code, $parent.'.');
        $options = $this->hierarchyOptions($snapshot['rows'], $snapshot['names']);
        $programCodes = $options->pluck('program')->filter()->unique()->all();
        $subprogramCodes = $options->pluck('subprogram')->filter()->unique()->all();
        $activityCodes = $options->pluck('activity')->filter()->unique()->all();
        if (! in_array($program, $programCodes, true)) {
            $program = '';
        }
        if (! in_array($subprogram, $subprogramCodes, true) || ($program !== '' && ! $isWithin($subprogram, $program))) {
            $subprogram = '';
        }
        if (! in_array($activity, $activityCodes, true) || ($subprogram !== '' && ! $isWithin($activity, $subprogram)) || ($subprogram === '' && $program !== '' && ! $isWithin($activity, $program))) {
            $activity = '';
        }
        if ($activity !== '') {
            $selectedActivity = $options->firstWhere('activity', $activity);
            $program = (string) ($selectedActivity['program'] ?? $program);
            $subprogram = (string) ($selectedActivity['subprogram'] ?? $subprogram);
        }

        $rows = $snapshot['rows']->filter(function (array $row) use ($search, $program, $subprogram, $activity): bool {
            $code = $row['activity_code'];
            if (($program !== '' && $row['program_code'] !== $program) || ($subprogram !== '' && $row['subprogram_code'] !== $subprogram) || ($activity !== '' && $code !== $activity)) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower(implode(' ', [$row['activity_code'], $row['activity_name'], $row['account_code'], $row['description']])), mb_strtolower($search));
        })->map(function (array $row) use ($snapshot, $scope): object {
            $row['display_amount'] = $this->scopedAmount($row, $scope['scope'], $scope['value']);
            $row['realization'] = $this->scopedRealization($row, $snapshot, $scope['scope'], $scope['value']);
            $row['variance'] = $row['display_amount'] - $row['realization'];
            $row['volume'] = $this->scopedVolume($row, $scope['scope'], $scope['value']);
            $row['unit'] = ArkasMirrorResolver::field($row['payload'], ['SATUAN', 'UNIT']) ?: '—';
            $row['unit_price'] = (float) (ArkasMirrorResolver::field($row['payload'], ['HARGA_SATUAN']) ?? 0);

            return (object) $row;
        })->filter(fn (object $row): bool => $scope['scope'] === 'year' || $row->display_amount > 0 || $row->volume > 0)->values();

        $tree = [];
        foreach ($rows as $row) {
            $code = trim((string) $row->activity_code, '.');
            $programCode = $row->program_code ?: 'tanpa-program';
            $subCode = $row->subprogram_code ?: $programCode;
            $activityKey = $code !== '' ? $code : 'tanpa-kegiatan';
            $tree[$programCode] ??= ['code' => $programCode, 'name' => $row->program_name ?: ($snapshot['names'][$programCode] ?? 'Program'), 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'subs' => []];
            $tree[$programCode]['subs'][$subCode] ??= ['code' => $subCode, 'name' => $row->subprogram_name ?: ($snapshot['names'][$subCode] ?? 'Subprogram'), 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'activities' => []];
            $tree[$programCode]['subs'][$subCode]['activities'][$activityKey] ??= ['code' => $code ?: 'Tanpa kode kegiatan', 'name' => $row->activity_name ?: 'Kegiatan belum diisi', 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'items' => []];
            $tree[$programCode]['amount'] += $row->display_amount;
            $tree[$programCode]['realization'] += $row->realization;
            $tree[$programCode]['remaining'] += $row->variance;
            $tree[$programCode]['subs'][$subCode]['amount'] += $row->display_amount;
            $tree[$programCode]['subs'][$subCode]['realization'] += $row->realization;
            $tree[$programCode]['subs'][$subCode]['remaining'] += $row->variance;
            $tree[$programCode]['subs'][$subCode]['activities'][$activityKey]['amount'] += $row->display_amount;
            $tree[$programCode]['subs'][$subCode]['activities'][$activityKey]['realization'] += $row->realization;
            $tree[$programCode]['subs'][$subCode]['activities'][$activityKey]['remaining'] += $row->variance;
            $tree[$programCode]['subs'][$subCode]['activities'][$activityKey]['items'][] = $row;
        }
        uksort($tree, fn (string $left, string $right): int => $this->compareHierarchyCodes($left, $right));
        foreach ($tree as &$programNode) {
            uksort($programNode['subs'], fn (string $left, string $right): int => $this->compareHierarchyCodes($left, $right));
            foreach ($programNode['subs'] as &$subNode) {
                uksort($subNode['activities'], fn (string $left, string $right): int => $this->compareHierarchyCodes($left, $right));
                foreach ($subNode['activities'] as &$activityNode) {
                    usort($activityNode['items'], function (object $left, object $right): int {
                        return $this->compareHierarchyCodes((string) $left->activity_code, (string) $right->activity_code)
                            ?: strnatcasecmp((string) $left->account_code, (string) $right->account_code);
                    });
                }
                unset($activityNode);
                $subNode['activities'] = array_values($subNode['activities']);
            }
            unset($subNode);
            $programNode['subs'] = array_values($programNode['subs']);
        }
        unset($programNode);
        $tree = array_values($tree);
        $budget = $rows->sum('display_amount');
        $remaining = $rows->sum('variance');
        $periodLabel = match ($scope['scope']) {
            'month' => Carbon::create($year, $scope['value'], 1)->translatedFormat('F Y'), 'quarter' => 'Triwulan '.$scope['value'].' · '.$year, 'semester' => 'Semester '.$scope['value'].' · '.$year, default => 'Tahun anggaran '.$year
        };
        $fundName = (string) (DB::connection('school')->table('fund_sources')->where('id', $fundSourceId)->value('name') ?: '');

        return ['hierarchyTree' => $tree, 'treeTotals' => ['amount' => array_sum(array_column($tree, 'amount')), 'realization' => array_sum(array_column($tree, 'realization')), 'remaining' => array_sum(array_column($tree, 'remaining')), 'items' => $rows->count()], 'filterContext' => 'pada '.$periodLabel, 'search' => $search, 'budget' => $budget, 'spent' => $budget - $remaining, 'remaining' => $remaining, 'overBudget' => max(0, -$remaining), 'underBudget' => max(0, $remaining), 'activityCount' => $snapshot['rows']->pluck('activity_code')->unique()->count(), 'scope' => $scope['scope'], 'scopeValue' => $scope['value'], 'periodLabel' => $periodLabel, 'programFilter' => $program, 'subprogramFilter' => $subprogram, 'activityFilter' => $activity, 'contextLabel' => trim($year.' · '.$fundName, ' ·'), 'revision' => $revision, 'revisionModes' => $revisionModes];
    }

    /** @return array{scope:string,value:int} */
    public function scope(Request $request): array
    {
        $aliases = ['semua' => 'year', 'bulan' => 'month', 'triwulan' => 'quarter', 'semester' => 'semester'];
        $scope = $aliases[(string) $request->query('mode')] ?? (string) $request->query('scope', 'year');
        $value = (int) $request->query('periode', $request->query('scope_value', 0));
        $valid = ['month' => [1, 12], 'quarter' => [1, 4], 'semester' => [1, 2]];
        if (! isset($valid[$scope]) || $value < $valid[$scope][0] || $value > $valid[$scope][1]) {
            return ['scope' => 'year', 'value' => 0];
        }

        return ['scope' => $scope, 'value' => $value];
    }

    /** @return array{rows:Collection<int,array>,names:array<string,string>,realization:array<string,float>,realization_period:array<string,array<string,float>>} */
    public function snapshot(int $fundSourceId, int $year, ?array $anggaranIds = null): array
    {
        $overrideIds = $anggaranIds === null ? null : array_values(array_unique(array_filter(array_map(strval(...), $anggaranIds))));
        $cacheKey = $fundSourceId.'|'.$year.'|'.implode(',', $overrideIds ?? []);
        if (isset($this->snapshotCache[$cacheKey])) {
            return $this->snapshotCache[$cacheKey];
        }
        $db = DB::connection('school');
        $rows = collect();
        $names = [];
        $validAnggaranIds = $overrideIds ?? $this->latestAnggaranIds($db, $fundSourceId, $year);
        $rapbsRecords = $db->table('arkas_mirror_rapbs')->get(['source_key', 'payload']);
        $references = [];
        $referencesByCode = [];
        if (Schema::hasTable('arkas_mirror_ref_kode')) {
            $referenceQuery = DB::table('arkas_mirror_ref_kode');
            if (Schema::hasColumn('arkas_mirror_ref_kode', 'sx_tahun')) {
                $referenceQuery->where('sx_tahun', $year);
            }
            foreach ($referenceQuery->get(['payload']) as $record) {
                $reference = json_decode((string) $record->payload, true);
                if (! is_array($reference) || ! $this->fundMatches($reference, $fundSourceId)) {
                    continue;
                }
                $refId = (string) (ArkasMirrorResolver::field($reference, ['ID_REF_KODE']) ?? '');
                $refCode = trim((string) (ArkasMirrorResolver::field($reference, ['ID_KODE']) ?? ''), '.');
                if ($refId !== '') {
                    $references[$refId] = $reference;
                }
                if ($refCode !== '') {
                    $referencesByCode[$refCode] = $reference;
                }
            }
        }
        foreach ($rapbsRecords as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload) || ! $this->fundMatches($payload, $fundSourceId) || ! $this->yearMatches($payload, $year)) {
                continue;
            }
            // Baris RKAS yang dihapus di ARKAS bukan pagu berlaku.
            if ((int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                continue;
            }
            $anggaranId = (string) (ArkasMirrorResolver::field($payload, ['ID_ANGGARAN']) ?? '');
            if ($validAnggaranIds !== [] && ! in_array($anggaranId, $validAnggaranIds, true)) {
                continue;
            }
            $refId = (string) (ArkasMirrorResolver::field($payload, ['ID_REF_KODE']) ?? '');
            $reference = $references[$refId] ?? [];
            $activityCode = trim((string) (ArkasMirrorResolver::field($reference, ['ID_KODE']) ?: ArkasMirrorResolver::field($payload, ['KODE_KEGIATAN', 'ID_KODE']) ?? ''), '.');
            $parts = $activityCode === '' ? [] : explode('.', $activityCode);
            $programCode = trim((string) (ArkasMirrorResolver::field($payload, ['KODE_PROGRAM']) ?: ($parts[0] ?? '')), '.');
            $subprogramCode = trim((string) (ArkasMirrorResolver::field($payload, ['KODE_SUB_PROGRAM']) ?: (count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : '')), '.');
            $programReference = $referencesByCode[$programCode] ?? [];
            $subprogramReference = $referencesByCode[$subprogramCode] ?? [];
            $activityName = (string) (ArkasMirrorResolver::field($payload, ['NAMA_KEGIATAN']) ?: ArkasMirrorResolver::field($reference, ['URAIAN_KODE', 'NAMA', 'URAIAN']) ?? 'Kegiatan belum diisi');
            $rows->push(['source_rapbs_id' => (string) $record->source_key, 'activity_code' => $activityCode, 'activity_name' => $activityName, 'program_code' => $programCode, 'program_name' => (string) (ArkasMirrorResolver::field($payload, ['NAMA_PROGRAM']) ?: ArkasMirrorResolver::field($programReference, ['URAIAN_KODE', 'NAMA', 'URAIAN']) ?? ''), 'subprogram_code' => $subprogramCode, 'subprogram_name' => (string) (ArkasMirrorResolver::field($payload, ['NAMA_SUB_PROGRAM']) ?: ArkasMirrorResolver::field($subprogramReference, ['URAIAN_KODE', 'NAMA', 'URAIAN']) ?? ''), 'level_code' => (string) (ArkasMirrorResolver::field($reference, ['ID_LEVEL_KODE']) ?: ArkasMirrorResolver::field($payload, ['ID_LEVEL_KODE']) ?? ''), 'account_code' => (string) (ArkasMirrorResolver::field($payload, ['KODE_REKENING']) ?? ''), 'account_name' => (string) (ArkasMirrorResolver::field($payload, ['NAMA_REKENING', 'URAIAN']) ?? ''), 'description' => (string) (ArkasMirrorResolver::field($payload, ['URAIAN', 'DESCRIPTION']) ?? ''), 'amount' => (float) (ArkasMirrorResolver::field($payload, ['JUMLAH']) ?? 0), 'payload' => $payload]);
            if (is_array($reference)) {
                $refCode = trim((string) ArkasMirrorResolver::field($reference, ['ID_KODE']), '.');
                $refName = (string) (ArkasMirrorResolver::field($reference, ['URAIAN_KODE', 'NAMA']) ?? '');
                if ($refCode !== '' && $refName !== '') {
                    $names[$refCode] = $refName;
                }
            }
        }
        $periods = [];
        $periodsByRapbs = [];
        $periodToRkas = [];
        $periodReferences = $this->periodReferences();
        foreach ($db->table('arkas_mirror_rapbs_periode')->get(['payload']) as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            // Baris yang dihapus di ARKAS (soft_delete = 1) bukan alokasi
            // yang berlaku: tanpa filter ini, split usang ikut terjumlah
            // pada tampilan triwulan/bulan (kasus nyata: split Juli ganda
            // yang sudah dibuang pada revisi yang disahkan).
            if ((int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                continue;
            }
            $periodId = (string) (ArkasMirrorResolver::field($payload, ['ID_PERIODE']) ?? '');
            $coordinates = $periodReferences[$periodId] ?? $this->periodCoordinates(
                $periodId,
                (string) (ArkasMirrorResolver::field($payload, ['NAMA_PERIODE', 'PERIODE']) ?? ''),
            );
            $payload['__PERIOD_NAME'] = $periodReferences[$periodId]['name'] ?? ArkasMirrorResolver::field($payload, ['NAMA_PERIODE', 'PERIODE']);
            $payload['__MONTH_NUMBER'] = $coordinates['month'];
            $payload['__QUARTER_NUMBER'] = $coordinates['quarter'];
            $payload['__SEMESTER_NUMBER'] = $coordinates['semester'];
            $periods[] = $payload;
            $rapbsId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '');
            if ($rapbsId !== '') {
                $periodsByRapbs[$rapbsId][] = $payload;
            }
        }
        foreach ($periods as $period) {
            $periodId = (string) (ArkasMirrorResolver::field($period, ['ID_RAPBS_PERIODE']) ?? '');
            $rapbsId = (string) (ArkasMirrorResolver::field($period, ['ID_RAPBS']) ?? '');
            if ($periodId !== '' && $rapbsId !== '') {
                $periodToRkas[$periodId] = $rapbsId;
            }
        }
        $rows = $rows->map(function (array $row) use ($periodsByRapbs): array {
            $row['periods'] = $periodsByRapbs[$row['source_rapbs_id']] ?? [];

            return $row;
        })->values();
        $realization = [];
        $realizationPeriod = [];
        foreach ($db->table('arkas_mirror_kas_umum')->get(['payload']) as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload) || strtoupper((string) ArkasMirrorResolver::field($payload, ['KATEGORI_BKU'])) !== 'BELANJA' || ! $this->fundMatches($payload, $fundSourceId)) {
                continue;
            }
            $rapbsId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '');
            $rapbsId = $rapbsId !== '' ? $rapbsId : ($periodToRkas[(string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS_PERIODE']) ?? '')] ?? '');
            if ($rapbsId !== '') {
                $realization[$rapbsId] = ($realization[$rapbsId] ?? 0) + (float) (ArkasMirrorResolver::field($payload, ['JUMLAH']) ?? 0);
                $periodId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS_PERIODE']) ?? '');
                if ($periodId !== '') {
                    $realizationPeriod[$rapbsId][$periodId] = ($realizationPeriod[$rapbsId][$periodId] ?? 0) + (float) (ArkasMirrorResolver::field($payload, ['JUMLAH']) ?? 0);
                }
            }
        }

        return $this->snapshotCache[$cacheKey] = ['rows' => $rows, 'names' => $names, 'realization' => $realization, 'realization_period' => $realizationPeriod];
    }

    /** @return array<string, array{name:?string,month:?int,quarter:?int,semester:?int}> */
    private function periodReferences(): array
    {
        if (! Schema::hasTable('arkas_mirror_ref_periode')) {
            return [];
        }

        $references = [];
        foreach (DB::table('arkas_mirror_ref_periode')->get(['payload']) as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $periodId = (string) (ArkasMirrorResolver::field($payload, ['ID_PERIODE', 'id_periode']) ?? '');
            if ($periodId === '') {
                continue;
            }
            $name = (string) (ArkasMirrorResolver::field($payload, ['NAMA_PERIODE', 'PERIODE', 'nama_periode', 'periode']) ?? '');
            $coordinates = $this->periodCoordinates($periodId, $name, $payload);
            $references[$periodId] = ['name' => $name !== '' ? $name : null, ...$coordinates];
        }

        return $references;
    }

    /** @return array{month:?int,quarter:?int,semester:?int} */
    private function periodCoordinates(string $periodId, string $periodName, array $payload = []): array
    {
        $month = $this->validPeriodNumber(ArkasMirrorResolver::field($payload, ['BULAN', 'MONTH', 'MONTH_NUMBER']));
        $quarter = $this->validPeriodNumber(ArkasMirrorResolver::field($payload, ['TRIWULAN', 'QUARTER', 'QUARTER_NUMBER']));
        $semester = $this->validPeriodNumber(ArkasMirrorResolver::field($payload, ['SEMESTER', 'SEMESTER_NUMBER']));
        $normalizedName = mb_strtolower(trim($periodName));
        $monthNames = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
        $namedMonth = array_search($normalizedName, $monthNames, true);
        if ($month === null && $namedMonth !== false) {
            $month = $namedMonth + 1;
        }
        if ($month === null) {
            $numericId = (int) $periodId;
            $month = $numericId >= 81 && $numericId <= 92 ? $numericId - 80 : null;
            if ($month === null && $quarter === null && $numericId >= 1 && $numericId <= 12 && $normalizedName === '') {
                $month = $numericId;
            }
        }
        if ($quarter === null && $month !== null) {
            $quarter = (int) ceil($month / 3);
        }
        if ($semester === null && $month !== null) {
            $semester = (int) ceil($month / 6);
        }
        if ($quarter === null && preg_match('/(?:triwulan|quarter)\s*([1-4])/', $normalizedName, $match) === 1) {
            $quarter = (int) $match[1];
        }
        if ($semester === null && preg_match('/semester\s*([1-2])/', $normalizedName, $match) === 1) {
            $semester = (int) $match[1];
        }

        return ['month' => $month, 'quarter' => $quarter, 'semester' => $semester];
    }

    private function validPeriodNumber(mixed $value): ?int
    {
        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    /** @return array<int,string> */
    private function latestAnggaranIds(object $db, int $fundSourceId, int $year): array
    {
        if (! Schema::connection('school')->hasTable('arkas_mirror_anggaran')) {
            return [];
        }

        $latest = null;
        foreach ($db->table('arkas_mirror_anggaran')->get(['source_key', 'payload']) as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload)
                || (int) (ArkasMirrorResolver::field($payload, ['TAHUN_ANGGARAN', 'TAHUN']) ?? 0) !== $year
                || ! $this->fundMatches($payload, $fundSourceId)
                || (int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                continue;
            }
            $candidate = [
                'id' => (string) (ArkasMirrorResolver::field($payload, ['ID_ANGGARAN']) ?: $record->source_key),
                'active' => (int) (ArkasMirrorResolver::field($payload, ['IS_AKTIF', 'AKTIF']) ?? 0),
                'approved' => (int) (ArkasMirrorResolver::field($payload, ['IS_APPROVE', 'IS_APPROVED', 'APPROVED']) ?? 0),
                'updated' => $this->sourceTimestamp(ArkasMirrorResolver::field($payload, ['LAST_UPDATE', 'UPDATED_AT'])),
                'created' => $this->sourceTimestamp(ArkasMirrorResolver::field($payload, ['CREATE_DATE', 'CREATED_AT'])),
            ];
            if ($latest === null || [$candidate['active'], $candidate['approved'], $candidate['updated'], $candidate['created']] > [$latest['active'], $latest['approved'], $latest['updated'], $latest['created']]) {
                $latest = $candidate;
            }
        }

        return $latest === null ? [] : [$latest['id']];
    }

    /**
     * Dua mode revisi RKAS per tahun+sumber dana:
     * - persetujuan: revisi terakhir yang sudah disetujui (is_approve = 1);
     * - pengajuan: revisi terakhir menurut tanggal pengajuan; bila lebih baru
     *   dari persetujuan maka berstatus is_aktif = 1, is_approve = 0
     *   (menunggu persetujuan).
     *
     * @return array{approved:?array{id:string,active:int,approved:int,updated:int,created:int,dateLabel:string},submitted:?array{id:string,active:int,approved:int,updated:int,created:int,dateLabel:string},hasPendingSubmission:bool}
     */
    public function revisionModes(int $fundSourceId, int $year): array
    {
        $approved = null;
        $submitted = null;
        if (Schema::connection('school')->hasTable('arkas_mirror_anggaran')) {
            foreach (DB::connection('school')->table('arkas_mirror_anggaran')->get(['source_key', 'payload']) as $record) {
                $payload = json_decode((string) $record->payload, true);
                if (! is_array($payload)
                    || (int) (ArkasMirrorResolver::field($payload, ['TAHUN_ANGGARAN', 'TAHUN']) ?? 0) !== $year
                    || ! $this->fundMatches($payload, $fundSourceId)
                    || (int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                    continue;
                }
                $candidate = [
                    'id' => (string) (ArkasMirrorResolver::field($payload, ['ID_ANGGARAN']) ?: $record->source_key),
                    'active' => (int) (ArkasMirrorResolver::field($payload, ['IS_AKTIF', 'AKTIF']) ?? 0),
                    'approved' => (int) (ArkasMirrorResolver::field($payload, ['IS_APPROVE', 'IS_APPROVED', 'APPROVED']) ?? 0),
                    'updated' => $this->sourceTimestamp(ArkasMirrorResolver::field($payload, ['LAST_UPDATE', 'UPDATED_AT'])),
                    'created' => $this->sourceTimestamp(ArkasMirrorResolver::field($payload, ['CREATE_DATE', 'CREATED_AT'])),
                ];
                if ($submitted === null || [$candidate['updated'], $candidate['created']] > [$submitted['updated'], $submitted['created']]) {
                    $submitted = $candidate;
                }
                if ($candidate['approved'] === 1
                    && ($approved === null || [$candidate['updated'], $candidate['created']] > [$approved['updated'], $approved['created']])) {
                    $approved = $candidate;
                }
            }
        }

        $dateLabel = static fn (?array $mode): string => $mode === null || max($mode['updated'], $mode['created']) <= 0
            ? '-'
            : date('d-m-Y', max($mode['updated'], $mode['created']));
        if ($approved !== null) {
            $approved['dateLabel'] = $dateLabel($approved);
        }
        if ($submitted !== null) {
            $submitted['dateLabel'] = $dateLabel($submitted);
        }

        return [
            'approved' => $approved,
            'submitted' => $submitted,
            'hasPendingSubmission' => $approved !== null && $submitted !== null && $submitted['id'] !== $approved['id'],
        ];
    }

    private function sourceTimestamp(mixed $value): int
    {
        if ($value === null || trim((string) $value) === '') {
            return 0;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? 0 : $timestamp;
    }

    private function compareHierarchyCodes(string $left, string $right): int
    {
        $leftParts = array_values(array_filter(explode('.', trim($left, '.')), static fn (string $part): bool => $part !== ''));
        $rightParts = array_values(array_filter(explode('.', trim($right, '.')), static fn (string $part): bool => $part !== ''));

        foreach (range(0, max(count($leftParts), count($rightParts)) - 1) as $index) {
            $leftPart = $leftParts[$index] ?? '';
            $rightPart = $rightParts[$index] ?? '';
            if ($leftPart === $rightPart) {
                continue;
            }
            if ($leftPart === '') {
                return -1;
            }
            if ($rightPart === '') {
                return 1;
            }
            if (ctype_digit($leftPart) && ctype_digit($rightPart)) {
                return (int) $leftPart <=> (int) $rightPart;
            }

            return strnatcasecmp($leftPart, $rightPart);
        }

        return 0;
    }

    private function fundMatches(array $payload, int $fundSourceId): bool
    {
        $fund = ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA', 'SUMBER_DANA_ID']);

        return $fund === null || $fund === '' || (int) $fund === $fundSourceId;
    }

    private function yearMatches(array $payload, int $year): bool
    {
        $sourceYear = ArkasMirrorResolver::field($payload, ['TAHUN', 'TAHUN_ANGGARAN']);

        return $sourceYear === null || $sourceYear === '' || (int) $sourceYear === $year;
    }

    private function scopedAmount(array $row, string $scope, int $value): float
    {
        if ($scope === 'year') {
            return $row['amount'];
        }
        $months = $scope === 'month' ? [$value] : ($scope === 'quarter' ? range(($value - 1) * 3 + 1, $value * 3) : range(($value - 1) * 6 + 1, $value * 6));

        return collect($row['periods'])->filter(fn (array $period): bool => in_array((int) ($period['__MONTH_NUMBER'] ?? 0), $months, true))->sum(fn (array $period): float => (float) (ArkasMirrorResolver::field($period, ['JUMLAH']) ?? 0));
    }

    private function scopedVolume(array $row, string $scope, int $value): float
    {
        if ($scope === 'year') {
            return (float) (ArkasMirrorResolver::field($row['payload'], ['VOLUME_TOTAL']) ?? 0);
        }
        $months = $scope === 'month' ? [$value] : ($scope === 'quarter' ? range(($value - 1) * 3 + 1, $value * 3) : range(($value - 1) * 6 + 1, $value * 6));

        return collect($row['periods'])->filter(fn (array $period): bool => in_array((int) ($period['__MONTH_NUMBER'] ?? 0), $months, true))->sum(fn (array $period): float => (float) (ArkasMirrorResolver::field($period, ['VOLUME']) ?? 0));
    }

    private function scopedRealization(array $row, array $snapshot, string $scope, int $value): float
    {
        if ($scope === 'year') {
            return $snapshot['realization'][$row['source_rapbs_id']] ?? 0.0;
        }

        $periodIds = collect($row['periods'])
            ->filter(function (array $period) use ($scope, $value): bool {
                $periodNumber = $scope === 'month' ? ($period['__MONTH_NUMBER'] ?? null) : ($scope === 'quarter' ? ($period['__QUARTER_NUMBER'] ?? null) : ($period['__SEMESTER_NUMBER'] ?? null));

                return (int) $periodNumber === $value;
            })
            ->map(fn (array $period): string => (string) (ArkasMirrorResolver::field($period, ['ID_RAPBS_PERIODE']) ?? ''))
            ->filter();

        return $periodIds->sum(fn (string $periodId): float => $snapshot['realization_period'][$row['source_rapbs_id']][$periodId] ?? 0.0);
    }

    /** @param Collection<int,array> $rows */
    public function hierarchyOptions($rows, array $names)
    {
        return $rows->map(function (array $row) use ($names): array {
            $code = trim($row['activity_code'], '.');
            $program = $row['program_code'] ?? '';
            $sub = ($row['subprogram_code'] ?? '') !== '' ? $row['subprogram_code'] : null;

            return ['program' => $program, 'program_name' => $row['program_name'] ?: ($names[$program] ?? 'Program'), 'subprogram' => $sub, 'subprogram_name' => $row['subprogram_name'] ?: ($names[$sub] ?? 'Subprogram'), 'activity' => $code, 'activity_name' => $row['activity_name']];
        })->filter(fn (array $row): bool => $row['activity'] !== '')->unique('activity')->values();
    }
}
