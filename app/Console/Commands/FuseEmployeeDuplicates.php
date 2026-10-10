<?php

namespace App\Console\Commands;

use App\Services\EmployeeIdentityService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FuseEmployeeDuplicates extends Command
{
    use ResolvesTenantSchools;

    protected $signature = 'employees:fuse-duplicates
        {--school= : NPSN sekolah target; wajib diisi kecuali memakai --all}
        {--all : Proses semua sekolah yang file database tenant-nya ada}
        {--apply : Tulis perubahan (default hanya laporan dry-run)}';

    protected $description = 'Gabungkan baris pegawai ganda lintas sumber ARKAS/Dapodik per identitas nasional.';

    public function handle(EmployeeIdentityService $identity, SchoolDatabaseManager $databases): int
    {
        $apply = (bool) $this->option('apply');
        $schools = $this->resolveSchools();

        if ($schools === null) {
            return self::FAILURE;
        }

        if ($schools->isEmpty()) {
            $this->warn('Tidak ada database tenant yang ditemukan.');

            return self::SUCCESS;
        }

        $totalGroups = 0;
        $totalMerged = 0;

        foreach ($schools as $school) {
            try {
                $databases->activate($school);
            } catch (\Throwable $exception) {
                $this->warn("[{$school->npsn}] lewati: ".$exception->getMessage());

                continue;
            }

            if (! Schema::connection('school')->hasColumn('employees', 'operator_locked')) {
                $this->warn("[{$school->npsn}] lewati: migrasi provenans pegawai belum dijalankan.");

                continue;
            }

            $report = $identity->fuseDuplicates(! $apply);
            $totalGroups += $report['groups'];
            $totalMerged += $report['merged'];

            $this->line("[{$school->npsn}] {$school->name}: {$report['groups']} grup ganda, {$report['merged']} baris digabung, {$report['backfilled']} normalized_name diisi.");
            foreach ($report['pairs'] as $pair) {
                $this->line('  simpan #'.$pair['keep'].' <- hapus #'.implode(', #', $pair['drop']).' | '.implode(' ; ', $pair['names']));
            }
        }

        $this->info($apply
            ? "Selesai: {$totalGroups} grup, {$totalMerged} baris digabung."
            : "Dry-run selesai: {$totalGroups} grup ganda ditemukan. Ulangi dengan --apply untuk menulis.");

        return self::SUCCESS;
    }
}
