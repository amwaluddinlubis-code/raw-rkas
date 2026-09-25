<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Transaction;
use App\Services\SchoolDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class VerifyMirrorBackfill extends Command
{
    protected $signature = 'spj:verify-mirror-backfill
        {npsn : NPSN sekolah tenant}
        {--backup= : Path file SQLite backup ground truth}';

    protected $description = 'Bandingkan agregat mirror vs backup per transaksi.';

    public function handle(SchoolDatabaseManager $databases): int
    {
        $school = School::query()->where('npsn', $this->argument('npsn'))->firstOrFail();
        $backupPath = $this->option('backup');
        if (! is_file($backupPath)) {
            $this->error('Backup tidak ditemukan.');

            return self::FAILURE;
        }

        $backup = new PDO('sqlite:'.$backupPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $databases->activate($school);

        $expected = [];
        foreach ($backup->query('SELECT * FROM transactions')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expected[(int) $row['id']] = $row;
        }

        $mismatch = 0;
        $checked = 0;
        foreach (DB::connection('school')->table('transactions')->get() as $tx) {
            $exp = $expected[$tx->id] ?? null;
            if (! $exp) {
                $this->warn("Transaksi {$tx->id} tidak ada di backup (data baru pasca-backup).");

                continue;
            }
            $model = Transaction::query()->with('items')->find($tx->id);
            $source = $model->mirrorSource();
            if (! $source) {
                $this->error("Transaksi {$tx->id}: mirror KOSONG.");
                $mismatch++;

                continue;
            }
            $checked++;
            $pairs = [
                'no_bukti' => (string) ($exp['no_bukti'] ?? ''),
                'gross_amount' => (float) ($exp['gross_amount'] ?? 0),
                'tax_total' => (float) ($exp['tax_total'] ?? 0),
                'net_amount' => (float) ($exp['net_amount'] ?? 0),
            ];
            foreach ($pairs as $field => $want) {
                $got = $source[$field] ?? null;
                $ok = is_string($want) ? (string) $got === $want : abs((float) $got - $want) < 0.005;
                if (! $ok) {
                    $this->error("Transaksi {$tx->id} field {$field}: backup=".var_export($want, true).' mirror='.var_export($got, true));
                    $mismatch++;
                }
            }
        }

        $this->info("Verifikasi {$school->npsn}: {$checked} transaksi dicek, {$mismatch} mismatch.");

        return $mismatch === 0 ? self::SUCCESS : self::FAILURE;
    }
}
