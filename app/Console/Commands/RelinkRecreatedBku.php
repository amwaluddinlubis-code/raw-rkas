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
        {--execute : Terapkan relink (default hanya pratinjau)}
        {--suggest : Tampilkan saran kandidat pasangan per transaksi missing (read-only)}
        {--pair=* : Pasangan eksplisit OLD:NEW pilihan operator (dapat diulang, butuh --execute)}';

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
        $this->line('Pasangan relink via nomor pesanan Siplah: '.count($preview['order_relinkable'] ?? []));
        foreach ($preview['order_relinkable'] ?? [] as $pair) {
            $reviewFlag = ! empty($pair['snapshot_review']) ? ' [→ review]' : '';
            $this->line('  lama #'.$pair['old']['transaction']->id.' → baru #'.$pair['new']['transaction']->id
                .' (pesanan '.($pair['order_match'] ?? '-').', '.count($pair['old']['items']).' rincian)'.$reviewFlag);
        }
        $this->line('Pasangan relink via snapshot: '.count($preview['snapshot_relinkable'] ?? []));
        foreach ($preview['snapshot_relinkable'] ?? [] as $pair) {
            $reviewFlag = ! empty($pair['snapshot_review']) ? ' [VENDOR BERUBAH → review]' : '';
            $this->line('  lama #'.$pair['old']['transaction']->id.' → baru #'.$pair['new']['transaction']->id
                .' (sidik snapshot agregat, '.count($pair['old']['items']).' rincian, tanggal '.$pair['old']['first_date'].')'.$reviewFlag);
        }
        $this->line('Perlu telaah manual (lama): '.count($preview['manual_missing']));
        foreach ($preview['manual_missing'] as $item) {
            $this->line('  tx #'.$item['transaction_id'].' — '.$item['reason']);
        }
        $this->line('Perlu telaah manual (baru): '.count($preview['manual_new']));
        foreach ($preview['manual_new'] as $item) {
            $this->line('  tx #'.$item['transaction_id'].' — '.$item['reason']);
        }
        $this->line('Perlu telaah manual (snapshot): '.count($preview['snapshot_manual'] ?? []));
        foreach (array_slice($preview['snapshot_manual'] ?? [], 0, 40) as $item) {
            $this->line('  tx #'.$item['transaction_id'].' — '.$item['reason']);
        }
        if (count($preview['snapshot_manual'] ?? []) > 40) {
            $this->line('  ... dan '.(count($preview['snapshot_manual']) - 40).' lainnya');
        }

        if ($this->option('suggest')) {
            $this->info('Saran kandidat pasangan (skor kata + bonus akun/tanggal/nominal):');
            foreach ($relinker->suggestPairs($fiscalYear->id, $fundId) as $oldId => $candidates) {
                if ($candidates === []) {
                    $this->line("  lama #{$oldId} — tanpa kandidat");

                    continue;
                }
                $top = array_map(fn (array $c): string => "#{$c['new_id']} (skor {$c['score']}: ".implode(',', array_slice($c['shared'], 0, 4)).(count($c['bonus']) ? ' + '.implode(',', $c['bonus']) : '').')', array_slice($candidates, 0, 3));
                $this->line("  lama #{$oldId} → ".implode(' | ', $top));
            }
            $this->comment('Terapkan pilihan manual via --pair=OLD:NEW bersama --execute.');
        }

        if (! $this->option('execute')) {
            $this->comment('Dry-run selesai tanpa perubahan. Ulangi dengan --execute untuk menerapkan (backup otomatis).');

            return self::SUCCESS;
        }

        $explicitPairs = [];
        foreach ((array) $this->option('pair') as $raw) {
            if (! preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', (string) $raw, $match)) {
                $this->error("Format --pair tidak valid: '{$raw}'. Gunakan OLD:NEW, mis. --pair=47:241.");

                return self::FAILURE;
            }
            try {
                $explicitPairs[] = $relinker->buildExplicitPair($fiscalYear->id, $fundId, (int) $match[1], (int) $match[2]);
                $this->line("  pasangan manual #{$match[1]} → #{$match[2]} (valid)");
            } catch (\RuntimeException $exception) {
                $this->error("Pasangan manual #{$match[1]} → #{$match[2]} ditolak: ".$exception->getMessage());

                return self::FAILURE;
            }
        }

        if (($preview['relinkable'] === []) && ($preview['snapshot_relinkable'] ?? []) === [] && ($preview['order_relinkable'] ?? []) === [] && $explicitPairs === []) {
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

        $result = $relinker->executePairs($fiscalYear->id, array_merge($preview['relinkable'], $preview['order_relinkable'] ?? [], $preview['snapshot_relinkable'] ?? [], $explicitPairs), null);

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
