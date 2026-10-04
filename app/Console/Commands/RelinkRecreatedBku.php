<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjSourceRelinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Perbaiki duplikat transaksi akibat BKU ARKAS dihapus + dibuat ulang
 * (ID_KAS_UMUM baru untuk isi bisnis yang sama).
 *
 * Default DRY-RUN (read-only). Gunakan --execute untuk menerapkan dengan
 * backup file database otomatis terlebih dahulu.
 */
class RelinkRecreatedBku extends Command
{
    protected $signature = 'spj:relink-recreated-bku
        {npsn : NPSN sekolah}
        {--year= : Tahun anggaran (default tahun berjalan)}
        {--fund=1 : ID sumber dana}
        {--execute : Terapkan relink (default hanya pratinjau)}';

    protected $description = 'Relink transaksi SOURCE_MISSING ke duplikat ACTIVE hasil BKU dibuat ulang (berbasis isi rincian).';

    public function handle(SchoolDatabaseManager $manager, SpjSourceRelinkService $relinker): int
    {
        $npsn = trim((string) $this->argument('npsn'));
        $year = (int) ($this->option('year') ?: now()->year);
        $fundId = (int) ($this->option('fund') ?: 1);

        $school = School::query()->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $manager->activate($school);
        } catch (\Throwable $exception) {
            $this->error('Aktivasi database gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $fiscalYear = FiscalYear::query()->where('year', $year)->where('fund_source_id', $fundId)->first();
        if (! $fiscalYear) {
            $this->error("Tahun anggaran {$year} + sumber dana {$fundId} tidak ditemukan pada database sekolah.");

            return self::FAILURE;
        }

        $preview = $relinker->preview($fiscalYear->id, $fundId);

        $this->info('RELINK BKU DIBUAT ULANG — '.($this->option('execute') ? 'EKSEKUSI' : 'PRATINJAU DRY-RUN'));
        $this->line('Scope: '.$school->npsn.' · TA '.$year.' · fund '.$fundId);
        $this->line('Pasangan relink unik: '.count($preview['relinkable']));
        foreach ($preview['relinkable'] as $pair) {
            $dateFlag = ((string) $pair['old']['first_date'] !== (string) $pair['new']['first_date']) ? ' [TANGGAL BERUBAH → review]' : '';
            $this->line('  lama #'.$pair['old']['transaction']->id.' → baru #'.$pair['new']['transaction']->id
                .' ('.count($pair['old']['items']).' rincian identik, '.$pair['old']['first_date'].' → '.$pair['new']['first_date'].')'.$dateFlag);
        }
        $this->line('Perlu telaah manual (lama): '.count($preview['manual_missing']));
        foreach ($preview['manual_missing'] as $item) {
            $this->line('  tx #'.$item['transaction_id'].' — '.$item['reason']);
        }
        $this->line('Perlu telaah manual (baru): '.count($preview['manual_new']));
        foreach ($preview['manual_new'] as $item) {
            $this->line('  tx #'.$item['transaction_id'].' — '.$item['reason']);
        }

        if (! $this->option('execute')) {
            $this->comment('Dry-run selesai tanpa perubahan. Ulangi dengan --execute untuk menerapkan (backup otomatis).');

            return self::SUCCESS;
        }

        if ($preview['relinkable'] === []) {
            $this->comment('Tidak ada pasangan relink. Tidak ada perubahan.');

            return self::SUCCESS;
        }

        $backupDir = storage_path('app/school-backups/relink-'.$npsn.'-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($backupDir);
        $dbPath = (string) config('database.connections.school.database');
        foreach ([$dbPath, $dbPath.'-wal', $dbPath.'-shm'] as $file) {
            if (is_file($file)) {
                File::copy($file, $backupDir.'/'.basename($file));
            }
        }
        $this->line('Backup: '.$backupDir);

        $result = $relinker->executePairs($fiscalYear->id, $preview['relinkable'], null);

        $this->info('Berhasil di-relink: '.count($result['relinked']));
        foreach ($result['relinked'] as $oldId => $newId) {
            $this->line('  #'.$oldId.' ← #'.$newId.' (duplikat dihapus)');
        }
        if ($result['skipped'] !== []) {
            $this->error('Dilewati: '.count($result['skipped']));
            foreach ($result['skipped'] as $oldId => $reason) {
                $this->line('  #'.$oldId.' — '.$reason);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
