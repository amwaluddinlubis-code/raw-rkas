<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\ArkasMirrorResolver;
use App\Services\SchoolDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Backfill tabel mirror ARKAS tetap dari kolom sumber lokal.
 *
 * Dipakai saat migrasi tenant berdata ke skema tanpa kolom ganda:
 * nilai sumber (no_bukti, tanggal, uraian, nominal, pajak, rekening,
 * kegiatan, penerima) disalin ke arkas_mirror_* SEBELUM kolom lokal
 * di-drop, sehingga tidak ada data yang hilang.
 *
 * Sumber dibaca dari database tenant aktif; bila kolom lokal sudah
 * ter-drop, gunakan --backup=<path sqlite> (dibaca read-only).
 */
class BackfillMirrorFromLocal extends Command
{
    protected $signature = 'spj:backfill-mirror-from-local
        {npsn : NPSN sekolah tenant}
        {--backup= : Path file SQLite backup sumber (bila kolom lokal sudah di-drop)}
        {--dry-run : Hitung saja tanpa menulis}
        {--refresh : Hapus baris MIG-* lama dulu lalu tulis ulang}';

    protected $description = 'Isi mirror ARKAS dari kolom sumber lokal/backup tanpa menghapus data.';

    public function handle(SchoolDatabaseManager $databases): int
    {
        $school = School::query()->where('npsn', $this->argument('npsn'))->first();
        if (! $school) {
            $this->error('Sekolah tidak ditemukan.');

            return self::FAILURE;
        }

        $backupPath = $this->option('backup');
        $backup = null;
        if (filled($backupPath)) {
            if (! is_file($backupPath)) {
                $this->error('File backup tidak ditemukan: '.$backupPath);

                return self::FAILURE;
            }
            $backup = new PDO('sqlite:'.$backupPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }

        $databases->activate($school);
        $db = DB::connection('school');

        if (! $db->getSchemaBuilder()->hasTable('arkas_mirror_kas_umum')) {
            $this->error('Tabel mirror belum ada. Jalankan migrasi tenant dulu.');

            return self::FAILURE;
        }

        $source = $this->loadSourceDataset($db, $backup);
        if ($source === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ((bool) $this->option('refresh') && ! $dryRun) {
            foreach (['arkas_mirror_kas_umum', 'arkas_mirror_rapbs', 'arkas_mirror_rapbs_periode', 'arkas_mirror_ref_kode'] as $table) {
                $conn = $table === 'arkas_mirror_ref_kode' ? DB::connection(null) : $db;
                $deleted = $conn->table($table)->where('source_key', 'like', 'MIG-%')->delete();
                $this->line("Refresh: {$deleted} baris MIG-* dihapus dari {$table}.");
            }
        }

        $stats = ['txn' => 0, 'txn_skip' => 0, 'item' => 0, 'item_skip' => 0, 'tax' => 0, 'chain' => 0];

        foreach ($source['transactions'] as $tx) {
            foreach ($this->backfillTransaction($db, $tx, $source['items'][$tx['id']] ?? [], $dryRun) as $key => $value) {
                $stats[$key] += $value;
            }
        }

        $this->info(sprintf(
            'Mirror backfill %s: %d transaksi baru (%d sudah ada), %d rincian baru (%d sudah ada), %d pajak, %d rantai activity.',
            $dryRun ? 'DRY-RUN' : 'SELESAI',
            $stats['txn'],
            $stats['txn_skip'],
            $stats['item'],
            $stats['item_skip'],
            $stats['tax'],
            $stats['chain']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{transactions: array<int, array<string, mixed>>, items: array<int, array<int, array<string, mixed>>>}|null
     */
    private function loadSourceDataset(mixed $db, ?PDO $backup): ?array
    {
        $reader = $backup !== null
            ? fn (string $sql): array => array_map(
                static fn ($row): array => (array) $row,
                $backup->query($sql)->fetchAll(PDO::FETCH_ASSOC)
            )
            : fn (string $sql): array => array_map(
                static fn ($row): array => (array) $row,
                $db->select($sql)
            );

        try {
            $columns = array_column($reader("SELECT name FROM pragma_table_info('transactions')"), 'name');
        } catch (\Throwable $exception) {
            $this->error('Gagal membaca skema: '.$exception->getMessage());

            return null;
        }

        $hasSource = in_array('no_bukti', $columns, true) && in_array('gross_amount', $columns, true);
        if (! $hasSource) {
            $this->error('Sumber tidak punya kolom lokal (sudah di-drop) dan tidak ada --backup yang valid.');

            return null;
        }

        $transactions = $reader('SELECT t.*, fy.year AS _fy_year FROM transactions t LEFT JOIN fiscal_years fy ON fy.id = t.fiscal_year_id');
        $items = [];
        try {
            foreach ($reader('SELECT * FROM transaction_items') as $item) {
                $items[(int) $item['transaction_id']][] = $item;
            }
        } catch (\Throwable) {
            // transaction_items boleh absen
        }

        $this->line(sprintf('Sumber: %d transaksi, %d rincian.', count($transactions), array_sum(array_map('count', $items))));

        return ['transactions' => $transactions, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $tx
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function backfillTransaction(mixed $db, array $tx, array $items, bool $dryRun): array
    {
        $stats = ['txn' => 0, 'txn_skip' => 0, 'item' => 0, 'item_skip' => 0, 'tax' => 0, 'chain' => 0];
        $txId = (int) $tx['id'];

        $fiscalYear = isset($tx['_fy_year']) && $tx['_fy_year'] !== null
            ? (object) ['year' => $tx['_fy_year']]
            : $db->table('fiscal_years')->where('id', $tx['fiscal_year_id'] ?? null)->first(['year']);
        $tahun = (int) ($fiscalYear->year ?? date('Y'));

        $refKey = 'MIG-R-'.$txId;
        $rapbsKey = 'MIG-A-'.$txId;
        $periodeKey = 'MIG-P-'.$txId;

        $chainRows = [
            ['arkas_mirror_ref_kode', $refKey.'|'.$tahun.'|'.($tx['fund_source_id'] ?? 1).'|0', [
                'id_ref_kode' => $refKey,
                'id_kode' => $tx['activity_code'] ?? null,
                'uraian_kode' => $tx['activity_name'] ?? null,
                'tahun' => $tahun,
                'sumber_dana_id' => $tx['fund_source_id'] ?? 1,
                'bentuk_pendidikan_id' => 0,
            ], 'central'],
            ['arkas_mirror_rapbs', $rapbsKey, [
                'ID_RAPBS' => $rapbsKey,
                'ID_REF_KODE' => $refKey,
                'KODE_REKENING' => $tx['account_code'] ?? null,
                'KODE_KEGIATAN' => $tx['activity_code'] ?? null,
                'NAMA_KEGIATAN' => $tx['activity_name'] ?? null,
                'URAIAN' => $tx['account_name'] ?? null,
                'JUMLAH' => $tx['gross_amount'] ?? 0,
            ], 'school'],
            ['arkas_mirror_rapbs_periode', $periodeKey, [
                'ID_RAPBS_PERIODE' => $periodeKey,
                'ID_RAPBS' => $rapbsKey,
                'ID_PERIODE' => 1,
                'SATUAN' => 'paket',
                'HARGA_SATUAN' => $tx['gross_amount'] ?? 0,
                'JUMLAH' => $tx['gross_amount'] ?? 0,
            ], 'school'],
        ];

        $txnKasKey = filled($tx['id_kas_umum'] ?? null) ? (string) $tx['id_kas_umum'] : 'MIG-KAS-'.$txId;
        // Semantik V2: baris kas txn = baris BELANJA pertama, sehingga
        // JUMLAH/VOLUME mengikuti amount item pertama (bukan bruto agregat).
        // Bila item pertama berbagi kunci ini, baris yang sama dipakai item.
        $firstItem = $items[0] ?? null;
        $txnPayload = [
            'ID_KAS_UMUM' => $txnKasKey,
            'KATEGORI_BKU' => 'BELANJA',
            'NO_BUKTI' => $tx['no_bukti'] ?? null,
            'TANGGAL_TRANSAKSI' => $tx['transaction_date'] ?? null,
            'URAIAN' => $tx['description'] ?? null,
            'KODE_REKENING' => $tx['account_code'] ?? null,
            'NAMA_TOKO' => $tx['recipient_name'] ?? null,
            'JUMLAH' => $firstItem !== null ? ($firstItem['amount'] ?? 0) : ($tx['gross_amount'] ?? 0),
            'VOLUME' => $firstItem !== null ? ($firstItem['quantity'] ?? 1) : 1,
            'ID_RAPBS_PERIODE' => $periodeKey,
            'PARENT_ID_KAS_UMUM' => null,
            'IS_PPN' => 0, 'IS_PPH21' => 0, 'IS_PPH22' => 0, 'IS_PPH23' => 0, 'IS_PPH4' => 0, 'IS_SSPD' => 0,
        ];

        $taxFlags = ['ppn' => 'IS_PPN', 'pph21' => 'IS_PPH21', 'pph22' => 'IS_PPH22', 'pph23' => 'IS_PPH23', 'pph4' => 'IS_PPH4', 'sspd' => 'IS_SSPD'];
        $flagSum = 0.0;

        // Pajak yang sudah ada sebagai baris PAJAK asli (sync kanonik)
        // tidak dibuatkan duplikat MIG-T-*.
        static $existingTax = null;
        if ($existingTax === null) {
            $existingTax = [];
            foreach ($db->table('arkas_mirror_kas_umum')->where('sx_kategori', 'PAJAK')->get(['payload']) as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (! is_array($payload)) {
                    continue;
                }
                foreach ($taxFlags as $field => $flag) {
                    if ((int) ($payload[$flag] ?? 0) === 1) {
                        $existingTax[] = [
                            'parent' => (string) ($payload['PARENT_ID_KAS_UMUM'] ?? ''),
                            'flag' => $flag,
                            'amount' => (float) ($payload['JUMLAH'] ?? 0),
                        ];
                        break;
                    }
                }
            }
        }
        $itemKasKeys = [];
        foreach ($items as $item) {
            if (filled($item['source_item_id'] ?? null)) {
                $itemKasKeys[] = (string) $item['source_item_id'];
            }
        }
        $validParents = array_unique(array_merge([$txnKasKey], $itemKasKeys));
        // Shortfall per flag terhadap baris PAJAK yang sudah ada (sync
        // kanonik): hanya tambahkan kekurangan agar tidak double-count.
        $existingByFlag = [];
        foreach ($existingTax as $row) {
            if (in_array($row['parent'], $validParents, true)) {
                $existingByFlag[$row['flag']] = ($existingByFlag[$row['flag']] ?? 0.0) + $row['amount'];
            }
        }
        $taxRows = [];
        foreach ($taxFlags as $field => $flag) {
            $shortfall = (float) ($tx[$field] ?? 0) - ($existingByFlag[$flag] ?? 0.0);
            if ($shortfall > 0.005) {
                $flagSum += $shortfall;
                $taxRows[] = ['key' => 'MIG-T-'.$txId.'-'.$field, 'payload' => [
                    'ID_KAS_UMUM' => 'MIG-T-'.$txId.'-'.$field,
                    'KATEGORI_BKU' => 'PAJAK',
                    'PARENT_ID_KAS_UMUM' => $txnKasKey,
                    'NO_BUKTI' => $tx['no_bukti'] ?? null,
                    'TANGGAL_TRANSAKSI' => $tx['transaction_date'] ?? null,
                    'JUMLAH' => $shortfall,
                    $flag => 1,
                ]];
            }
        }
        $remainder = (float) ($tx['tax_total'] ?? 0) - $flagSum - array_sum($existingByFlag);
        if ($remainder > 0.005) {
            $taxRows[] = ['key' => 'MIG-T-'.$txId.'-X', 'payload' => [
                'ID_KAS_UMUM' => 'MIG-T-'.$txId.'-X',
                'KATEGORI_BKU' => 'PAJAK',
                'PARENT_ID_KAS_UMUM' => $txnKasKey,
                'NO_BUKTI' => $tx['no_bukti'] ?? null,
                'TANGGAL_TRANSAKSI' => $tx['transaction_date'] ?? null,
                'JUMLAH' => $remainder,
            ]];
        }

        if ($dryRun) {
            $stats['txn']++;
            $stats['item'] += count($items);
            $stats['tax'] += count($taxRows);
            $stats['chain'] += 3;

            return $stats;
        }

        foreach ($chainRows as [$table, $key, $payload, $connection]) {
            if ($this->upsertMirror($db, $connection, $table, $key, $payload)) {
                $stats['chain']++;
            }
        }

        if ($this->upsertMirror($db, 'school', 'arkas_mirror_kas_umum', $txnKasKey, $txnPayload)) {
            $stats['txn']++;
        } else {
            $stats['txn_skip']++;
        }

        foreach ($taxRows as $taxRow) {
            if ($this->upsertMirror($db, 'school', 'arkas_mirror_kas_umum', $taxRow['key'], $taxRow['payload'])) {
                $stats['tax']++;
            }
        }

        foreach ($items as $item) {
            $itemKey = filled($item['source_item_id'] ?? null) ? (string) $item['source_item_id'] : 'MIG-I-'.$item['id'];
            $itemPeriodeKey = 'MIG-IP-'.$item['id'];
            $this->upsertMirror($db, 'school', 'arkas_mirror_rapbs_periode', $itemPeriodeKey, [
                'ID_RAPBS_PERIODE' => $itemPeriodeKey,
                'ID_RAPBS' => $rapbsKey,
                'ID_PERIODE' => 1,
                'SATUAN' => $item['unit'] ?? 'paket',
                'HARGA_SATUAN' => $item['unit_price'] ?? 0,
                'JUMLAH' => $item['amount'] ?? 0,
            ]);
            $stats['chain']++;
            if ($this->upsertMirror($db, 'school', 'arkas_mirror_kas_umum', $itemKey, [
                'ID_KAS_UMUM' => $itemKey,
                'KATEGORI_BKU' => 'BELANJA',
                'NO_BUKTI' => $tx['no_bukti'] ?? null,
                'TANGGAL_TRANSAKSI' => $tx['transaction_date'] ?? null,
                'URAIAN' => $item['description'] ?? null,
                'KODE_REKENING' => $tx['account_code'] ?? null,
                'NAMA_TOKO' => $tx['recipient_name'] ?? null,
                'JUMLAH' => $item['amount'] ?? 0,
                'VOLUME' => $item['quantity'] ?? 1,
                'ID_RAPBS_PERIODE' => $itemPeriodeKey,
                'PARENT_ID_KAS_UMUM' => null,
                'IS_PPN' => 0, 'IS_PPH21' => 0, 'IS_PPH22' => 0, 'IS_PPH23' => 0, 'IS_PPH4' => 0, 'IS_SSPD' => 0,
            ])) {
                $stats['item']++;
            } else {
                $stats['item_skip']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return bool true bila baris baru dibuat
     */
    private function upsertMirror(mixed $db, string $connection, string $table, string $key, array $payload): bool
    {
        $conn = $connection === 'central' ? DB::connection(null) : $db;
        $payload['_backfilled'] = 1;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $hash = hash('sha256', (string) $json);
        $now = now()->toDateTimeString();

        $existing = $conn->table($table)->where('source_key', $key)->first(['source_hash', 'payload']);

        if (! $existing) {
            $conn->table($table)->insert([
                'source_key' => $key,
                'payload' => $json,
                'source_hash' => $hash,
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->forgetMirrorCache($table, $key);

            return true;
        }

        // Timpa hanya baris milik backfill (bermarker); baris sync kanonik
        // tidak pernah diubah oleh backfill.
        $existingPayload = json_decode((string) ($existing->payload ?? ''), true);
        if (is_array($existingPayload) && ($existingPayload['_backfilled'] ?? 0) === 1 && ($existing->source_hash ?? null) !== $hash) {
            $conn->table($table)->where('source_key', $key)->update([
                'payload' => $json,
                'source_hash' => $hash,
                'synced_at' => $now,
                'updated_at' => $now,
            ]);
            $this->forgetMirrorCache($table, $key);
        }

        return false;
    }

    /**
     * Batalkan cache resolver untuk baris mirror yang baru ditulis agar
     * verifikasi dalam proses yang sama membaca data terbaru.
     */
    private function forgetMirrorCache(string $table, ?string $key = null): void
    {
        try {
            app(ArkasMirrorResolver::class)->forget($table, $key);
        } catch (\Throwable) {
            // abaikan: tulis mirror tetap sah tanpa invalidasi cache
        }
    }
}
