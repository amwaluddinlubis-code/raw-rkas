<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArkasActivityHierarchyResolver
{
    /** @var array<string,array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}> */
    private array $cache = [];

    /**
     * Resolve hirarki Program -> Sub Program untuk transaksi ARKAS tanpa
     * mengubah source transaction. Nama parent berasal dari ref_kode mirror
     * pusat; kode mempunyai fallback deterministik dari kode kegiatan.
     *
     * @return array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}
     */
    public function resolve(Transaction $transaction): array
    {
        $fallback = $this->codesForActivity((string) $transaction->sourceValue('activity_code'));
        $resolved = [
            'program_code' => $fallback['program_code'],
            'program_name' => '',
            'sub_program_code' => $fallback['sub_program_code'],
            'sub_program_name' => '',
        ];

        $sourceKasId = trim((string) $transaction->id_kas_umum);
        if ($sourceKasId === '') {
            return $resolved;
        }

        $cacheKey = (string) $transaction->fiscal_year_id.'|'.(string) $transaction->fund_source_id.'|'.$sourceKasId.'|'.(string) $transaction->sourceValue('activity_code');
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $mirrorResolved = $this->resolveFromMirror($transaction, $sourceKasId, $resolved);
        if ($mirrorResolved !== null) {
            return $this->cache[$cacheKey] = $mirrorResolved;
        }

        if (! Schema::connection('school')->hasTable('arkas_bku_rows')
            || ! Schema::connection('school')->hasTable('arkas_rkas_items')) {
            return $this->cache[$cacheKey] = $resolved;
        }

        $bkuRow = DB::connection('school')->table('arkas_bku_rows')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->when(filled($transaction->fund_source_id), fn ($query) => $query->where('fund_source_id', $transaction->fund_source_id))
            ->where('source_kas_id', $sourceKasId)
            ->first(['payload']);
        $bkuPayload = $this->decodePayload($bkuRow->payload ?? null);
        $sourceRapbsId = $this->firstText($bkuPayload, ['ID_RAPBS', 'id_rapbs']);

        if ($sourceRapbsId === null) {
            return $this->cache[$cacheKey] = $resolved;
        }

        $rkasRow = DB::connection('school')->table('arkas_rkas_items')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->when(filled($transaction->fund_source_id), fn ($query) => $query->where('fund_source_id', $transaction->fund_source_id))
            ->where('source_rapbs_id', $sourceRapbsId)
            ->first(['payload']);
        $rkasPayload = $this->decodePayload($rkasRow->payload ?? null);

        $resolved['program_code'] = $this->firstText($rkasPayload, ['KODE_PROGRAM', 'kode_program']) ?? $resolved['program_code'];
        $resolved['program_name'] = $this->firstText($rkasPayload, ['NAMA_PROGRAM', 'nama_program']) ?? '';
        $resolved['sub_program_code'] = $this->firstText($rkasPayload, ['KODE_SUB_PROGRAM', 'kode_sub_program']) ?? $resolved['sub_program_code'];
        $resolved['sub_program_name'] = $this->firstText($rkasPayload, ['NAMA_SUB_PROGRAM', 'nama_sub_program']) ?? '';

        return $this->cache[$cacheKey] = $resolved;
    }

    /**
     * Resolve the hierarchy from the fixed mirror chain:
     * kas_umum -> rapbs_periode -> rapbs -> ref_kode.
     *
     * @param  array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}  $resolved
     * @return array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}|null
     */
    private function resolveFromMirror(Transaction $transaction, string $sourceKasId, array $resolved): ?array
    {
        try {
            if (! Schema::connection('school')->hasTable('arkas_mirror_kas_umum')) {
                return null;
            }

            $mirror = app(ArkasMirrorResolver::class);
            $kas = $mirror->kasUmum($sourceKasId);
            if ($kas === null) {
                return null;
            }

            $rapbsPeriodId = ArkasMirrorResolver::field($kas, ['ID_RAPBS_PERIODE']);
            $rapbsPeriod = filled($rapbsPeriodId) ? $mirror->rapbsPeriode((string) $rapbsPeriodId) : null;
            $rapbsId = $rapbsPeriod !== null
                ? ArkasMirrorResolver::field($rapbsPeriod, ['ID_RAPBS'])
                : ArkasMirrorResolver::field($kas, ['ID_RAPBS']);
            $rapbs = filled($rapbsId) ? $mirror->rapbs((string) $rapbsId) : null;
            if ($rapbs === null) {
                return null;
            }

            $refId = ArkasMirrorResolver::field($rapbs, ['ID_REF_KODE']);
            $reference = filled($refId) ? $mirror->refKodeByIdRef((string) $refId) : null;
            $activityCode = (string) ($reference ? ArkasMirrorResolver::field($reference, ['ID_KODE']) : '');
            $activityCode = $activityCode !== ''
                ? $activityCode
                : (string) ArkasMirrorResolver::field($rapbs, ['KODE_KEGIATAN']);
            if ($activityCode === '') {
                return null;
            }

            $codes = $this->codesForActivity($activityCode);
            $program = $this->mirrorReferenceByCode($codes['program_code'], $transaction);
            $subProgram = $this->mirrorReferenceByCode($codes['sub_program_code'], $transaction);
            $programCode = (string) (ArkasMirrorResolver::field($rapbs, ['KODE_PROGRAM']) ?: $codes['program_code']);
            $subProgramCode = (string) (ArkasMirrorResolver::field($rapbs, ['KODE_SUB_PROGRAM']) ?: $codes['sub_program_code']);
            $programName = (string) ArkasMirrorResolver::field($rapbs, ['NAMA_PROGRAM']);
            $subProgramName = (string) ArkasMirrorResolver::field($rapbs, ['NAMA_SUB_PROGRAM']);

            return [
                'program_code' => $programCode,
                'program_name' => $programName !== '' ? $programName : (string) ($program ? ArkasMirrorResolver::field($program, ['URAIAN_KODE']) : ''),
                'sub_program_code' => $subProgramCode,
                'sub_program_name' => $subProgramName !== '' ? $subProgramName : (string) ($subProgram ? ArkasMirrorResolver::field($subProgram, ['URAIAN_KODE']) : ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    private function mirrorReferenceByCode(string $code, Transaction $transaction): ?array
    {
        $code = trim($code);
        if ($code === '' || ! Schema::hasTable('arkas_mirror_ref_kode')) {
            return null;
        }

        $year = $transaction->sourceCarbon()?->year ?: now()->year;
        $table = DB::connection(null)->table('arkas_mirror_ref_kode');
        $rows = Schema::hasColumn('arkas_mirror_ref_kode', 'sx_id_kode')
            && Schema::hasColumn('arkas_mirror_ref_kode', 'sx_tahun')
            ? $table->where('sx_id_kode', $code)->where('sx_tahun', $year)->get(['payload'])
            : collect();
        if ($rows->isEmpty()) {
            $rows = $table->get(['payload']);
        }
        $fundSourceId = (int) $transaction->fund_source_id;

        foreach ($rows as $row) {
            $payload = $this->decodePayload($row->payload ?? null);
            if ((string) ArkasMirrorResolver::field($payload, ['ID_KODE']) !== $code
                || (int) (ArkasMirrorResolver::field($payload, ['TAHUN', 'tahun']) ?? 0) !== (int) $year) {
                continue;
            }
            $payloadFundSource = (int) (ArkasMirrorResolver::field($payload, ['SUMBER_DANA_ID', 'ID_REF_SUMBER_DANA', 'sumber_dana_id']) ?? 0);
            if ($fundSourceId === 0 || $payloadFundSource === 0 || $payloadFundSource === $fundSourceId) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @return array{program_code:string,sub_program_code:string}
     */
    public function codesForActivity(string $activityCode): array
    {
        $raw = trim($activityCode);
        $normalized = trim($raw, '.');
        $parts = array_values(array_filter(
            array_map('trim', explode('.', $normalized)),
            static fn (string $part): bool => $part !== ''
        ));
        $trailingDot = str_ends_with($raw, '.') ? '.' : '';

        return [
            'program_code' => count($parts) >= 1 ? $parts[0].$trailingDot : '',
            'sub_program_code' => count($parts) >= 2 ? $parts[0].'.'.$parts[1].$trailingDot : '',
        ];
    }

    /** @return array<string,mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_object($payload)) {
            return (array) $payload;
        }
        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string,mixed> $payload @param array<int,string> $keys */
    private function firstText(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
