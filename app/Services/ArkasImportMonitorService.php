<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoring kesehatan importer ARKAS lintas sekolah: status run terakhir
 * per tabel, umur sinkronisasi, dan metrik. Read-only; membuka database
 * tenant tiap sekolah dan mengembalikan konteks semula.
 */
final class ArkasImportMonitorService
{
    private const STALE_DAYS = 7;

    public function __construct(private readonly SchoolDatabaseManager $databases) {}

    /** @return array{rows:array<int,array>,stale_tables:int,failed_tables:int,never_run_tables:int} */
    public function summarize(): array
    {
        $rows = [];
        foreach (School::query()->with('databaseRecord')->get() as $school) {
            $entry = $this->forSchool($school);
            if ($entry !== null) {
                $rows = array_merge($rows, $entry);
            }
        }

        return [
            'rows' => $rows,
            'stale_tables' => collect($rows)->filter(fn (array $row) => $row['stale'])->count(),
            'failed_tables' => collect($rows)->filter(fn (array $row) => $row['status'] === 'FAILED')->count(),
            'never_run_tables' => collect($rows)->filter(fn (array $row) => $row['status'] === 'NEVER')->count(),
        ];
    }

    /** @return array<int,array>|null */
    private function forSchool(School $school): ?array
    {
        if (! $school->databaseRecord || ! is_string($school->databaseRecord->database_path) || ! is_file($school->databaseRecord->database_path)) {
            return null;
        }
        try {
            $original = config('database.connections.school.database');
            config()->set('database.connections.school.database', $school->databaseRecord->database_path);
            DB::purge('school');
            if (! Schema::connection('school')->hasTable('arkas_import_runs')) {
                return $original !== null ? $this->restore($original) : null;
            }
            $runs = DB::connection('school')
                ->table('arkas_import_runs as runs')
                ->leftJoin('arkas_import_profiles as profiles', 'profiles.id', '=', 'runs.profile_id')
                ->select('runs.*', 'profiles.source_table')
                ->orderByDesc('runs.id')
                ->get();
            $latestByKey = [];
            foreach ($runs as $run) {
                $profileKey = $run->profile_id;
                $key = $profileKey.'|'.(int) ($run->fiscal_year_id ?? 0);
                $latestByKey[$key] ??= $run;
            }
            $rows = [];
            foreach ($latestByKey as $run) {
                $finished = $run->finished_at ? Carbon::parse($run->finished_at) : null;
                $rows[] = [
                    'school' => $school->name,
                    'table' => $run->source_table ?? '-',
                    'fiscal_year_id' => $run->fiscal_year_id,
                    'status' => strtoupper((string) $run->status),
                    'records_written' => (int) ($run->records_written ?? 0),
                    'records_removed' => (int) ($run->records_removed ?? 0),
                    'finished_at' => $finished,
                    'stale' => $finished === null || $finished->lt(now()->subDays(self::STALE_DAYS)),
                ];
            }
            if ($original !== null) {
                $this->restore($original);
            }

            return $rows;
        } catch (\Throwable) {
            return null;
        }
    }

    private function restore(string $original): ?array
    {
        config()->set('database.connections.school.database', $original);
        DB::purge('school');

        return null;
    }
}
