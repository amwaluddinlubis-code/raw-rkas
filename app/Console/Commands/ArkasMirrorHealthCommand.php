<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\ArkasMirrorHealthService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Console\Command;

/**
 * Deteksi baris mirror kas basi (ID diterbitkan ulang ARKAS).
 *
 * Default DRY-RUN: hanya melapor per (sekolah, tahun, dana). Gunakan
 * --repair untuk menghapus baris basi dengan backup file database
 * otomatis terlebih dahulu.
 */
class ArkasMirrorHealthCommand extends Command
{
    protected $signature = 'arkas:mirror-health
        {--npsn= : Batasi pada satu NPSN}
        {--repair : Hapus baris mirror basi (default hanya melapor)}';

    protected $description = 'Bandingkan mirror kas_umum vs arkas_bku_rows per tahun+dana; --repair membersihkan yang basi.';

    public function handle(SchoolDatabaseManager $manager, ArkasMirrorHealthService $health): int
    {
        $query = School::query()->with('databaseRecord')->orderBy('npsn');
        if ($this->option('npsn')) {
            $query->where('npsn', trim((string) $this->option('npsn')));
        }
        $schools = $query->get();
        if ($schools->isEmpty()) {
            $this->error('Sekolah tidak ditemukan.');

            return self::FAILURE;
        }

        $repair = (bool) $this->option('repair');
        $this->info('ARKAS MIRROR HEALTH — '.($repair ? 'REPAIR' : 'DRY-RUN LAPORAN'));

        $failed = 0;
        foreach ($schools as $school) {
            try {
                $manager->activate($school);
            } catch (\Throwable $exception) {
                $this->error($school->npsn.': aktivasi database gagal: '.$exception->getMessage());
                $failed++;

                continue;
            }

            try {
                $report = $health->check();
            } catch (\Throwable $exception) {
                $this->error($school->npsn.': pemeriksaan gagal: '.$exception->getMessage());
                $failed++;

                continue;
            }

            $this->line('['.$school->npsn.'] '.($school->name ?? ''));
            if ($report['scopes'] === []) {
                $this->line('  (tidak ada kas terscope)');
            }
            foreach ($report['scopes'] as $scope) {
                $flag = ($scope['stale_n'] > 0 || $scope['missing_n'] > 0) ? ' BASI' : ' ok';
                $this->line('  TA '.$scope['year'].' fund '.$scope['fund'].': mirror n='.$scope['mirror_n'].' sum='.$this->rupiah($scope['mirror_sum'])
                    .' | bku n='.$scope['bku_n'].' sum='.$this->rupiah($scope['bku_sum'])
                    .' | stale n='.$scope['stale_n'].' sum='.$this->rupiah($scope['stale_sum'])
                    .' | hilang-di-mirror n='.$scope['missing_n'].$flag);
            }
            if ($report['unscoped_n'] > 0) {
                $this->line('  tanpa-scope (tak tersentuh repair): n='.$report['unscoped_n']);
            }

            if (! $report['ok']) {
                $failed++;
            }

            if ($repair && ! $report['ok']) {
                $backup = $health->backupActiveDatabase($school->npsn);
                if ($backup === null) {
                    $this->error('  backup gagal — repair dibatalkan untuk sekolah ini.');

                    continue;
                }
                $this->line('  backup: '.$backup);
                foreach ($health->repair() as $done) {
                    $this->line('  dihapus TA '.$done['year'].' fund '.$done['fund'].': n='.$done['deleted_n'].' sum='.$this->rupiah($done['deleted_sum']));
                }
                $after = $health->check();
                $this->line('  pasca-repair: '.($after['ok'] ? 'SEHAT' : 'MASIH BERMASALAH — telaah manual'));
            }
        }

        if (! $repair) {
            $this->comment('Dry-run selesai tanpa perubahan. Ulangi dengan --repair untuk membersihkan (backup otomatis).');
        }

        return $failed > 0 && ! $repair ? self::FAILURE : self::SUCCESS;
    }

    private function rupiah(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
