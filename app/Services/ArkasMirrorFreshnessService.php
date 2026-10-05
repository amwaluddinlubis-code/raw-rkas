<?php

namespace App\Services;

use App\Models\BackgroundOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ArkasMirrorFreshnessService
{
    private const STALE_AFTER_HOURS = 24;

    /** @return array<string,mixed> */
    public function summarize(int $schoolId, int $fiscalYearId): array
    {
        $referencesRun = null;
        $schoolRun = null;
        if (Schema::hasTable('background_operations')) {
            $referencesRun = BackgroundOperation::query()
                ->where('school_id', $schoolId)
                ->where('type', 'ARKAS_FIXED_MIRROR_REFS')
                ->latest('id')
                ->first();
            $schoolRun = BackgroundOperation::query()
                ->where('school_id', $schoolId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('type', 'ARKAS_FIXED_MIRROR_SCHOOL')
                ->latest('id')
                ->first();
        }

        $centralTables = [
            'Kode kegiatan' => ['connection' => null, 'table' => 'arkas_mirror_ref_kode'],
            'Periode' => ['connection' => null, 'table' => 'arkas_mirror_ref_periode'],
        ];
        $schoolTables = [
            'Anggaran' => ['connection' => 'school', 'table' => 'arkas_mirror_anggaran'],
            'RKAS' => ['connection' => 'school', 'table' => 'arkas_mirror_rapbs'],
            'Periode RKAS' => ['connection' => 'school', 'table' => 'arkas_mirror_rapbs_periode'],
            'BKU' => ['connection' => 'school', 'table' => 'arkas_mirror_kas_umum'],
        ];

        $missing = [];
        $counts = [];
        foreach ($centralTables + $schoolTables as $label => $entry) {
            $schema = Schema::connection($entry['connection']);
            if (! $schema->hasTable($entry['table'])) {
                $missing[] = $label;
                $counts[$label] = null;

                continue;
            }

            $counts[$label] = DB::connection($entry['connection'])->table($entry['table'])->count();
            if (isset($schoolTables[$label]) && $counts[$label] === 0 && $label !== 'BKU') {
                $missing[] = $label.' (belum berisi data)';
            }
        }

        $lanes = [
            'referensi' => $this->lane($referencesRun),
            'sekolah' => $this->lane($schoolRun),
        ];
        $level = $this->overallLevel($lanes, $missing);

        return [
            'level' => $level,
            'label' => match ($level) {
                'fresh' => 'Data mutakhir',
                'stale' => 'Perlu sinkronisasi ulang',
                'failed' => 'Sinkronisasi gagal',
                'running' => 'Sinkronisasi berlangsung',
                'incomplete' => 'Data sumber belum lengkap',
                default => 'Belum ada catatan sinkronisasi',
            },
            'lanes' => $lanes,
            'counts' => $counts,
            'missing' => $missing,
            'stale_after_hours' => self::STALE_AFTER_HOURS,
        ];
    }

    /** @return array<string,mixed> */
    private function lane(?BackgroundOperation $operation): array
    {
        if ($operation === null) {
            return ['status' => 'NEVER', 'label' => 'Belum pernah berjalan', 'level' => 'unknown', 'finished_at' => null, 'records_read' => null, 'records_written' => null, 'message' => 'Belum pernah berjalan.'];
        }

        $finishedAt = $operation->finished_at;
        $status = strtoupper((string) $operation->status);
        $level = match ($status) {
            'QUEUED', 'RUNNING' => 'running',
            'FAILED' => 'failed',
            'COMPLETED' => $finishedAt === null ? 'unknown' : ($finishedAt->lt(now()->subHours(self::STALE_AFTER_HOURS)) ? 'stale' : 'fresh'),
            default => 'unknown',
        };
        $result = (array) ($operation->result ?? []);

        return [
            'status' => $status,
            'label' => match ($level) {
                'fresh' => 'Mutakhir',
                'stale' => 'Perlu diperbarui',
                'failed' => 'Gagal',
                'running' => 'Berlangsung',
                default => 'Belum diketahui',
            },
            'level' => $level,
            'finished_at' => $finishedAt,
            'records_read' => isset($result['read']) ? (int) $result['read'] : null,
            'records_written' => isset($result['written']) ? (int) $result['written'] : null,
            'message' => (string) ($operation->message ?? ''),
        ];
    }

    /** @param array<string,array<string,mixed>> $lanes
     * @param  array<int,string>  $missing
     */
    private function overallLevel(array $lanes, array $missing): string
    {
        $levels = array_column($lanes, 'level');
        if (in_array('failed', $levels, true)) {
            return 'failed';
        }
        if (in_array('running', $levels, true)) {
            return 'running';
        }
        if ($missing !== []) {
            return 'incomplete';
        }
        if (in_array('stale', $levels, true)) {
            return 'stale';
        }
        if (in_array('unknown', $levels, true)) {
            return 'unknown';
        }

        return 'fresh';
    }
}
