<?php

namespace Tests\Support;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ArkasMirrorResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed rantai mirror ARKAS agar transaksi/item uji yang dibuat minimal
 * (hanya kunci + overlay) tetap memiliki fakta sumber lewat resolver.
 *
 * Rantai: kas_umum → rapbs_periode → rapbs → ref_kode (pusat).
 * Dipakai seluruh test pasca-refactor overlay: kolom ganda tidak lagi
 * ditulis ke transactions/transaction_items.
 */
trait SeedsArkasMirror
{
    /**
     * Model field → kunci payload bridge (format enriched `bku`).
     * null = tidak disimpan sebagai kunci payload (dihitung/chain).
     *
     * @var array<string, string|null>
     */
    private const MIRROR_TX_MAP = [
        'no_bukti' => 'NO_BUKTI',
        'transaction_date' => 'TANGGAL_TRANSAKSI',
        'description' => 'URAIAN',
        'recipient_name' => 'NAMA_TOKO',
        'gross_amount' => 'JUMLAH',
        'ppn' => null,
        'pph21' => null,
        'pph22' => null,
        'pph23' => null,
        'pph4' => null,
        'sspd' => null,
        'tax_total' => null,
        'net_amount' => null,
        'activity_code' => null,
        'activity_name' => null,
        'account_code' => null,
        'account_name' => null,
    ];

    /** @var array<string, string|null> */
    private const MIRROR_ITEM_MAP = [
        'description' => 'URAIAN',
        'quantity' => 'VOLUME',
        'unit' => 'SATUAN',
        'unit_price' => null,
        'amount' => 'JUMLAH',
    ];

    private int $mirrorSequence = 0;

    /**
     * Pastikan tabel mirror tersedia (test setup parsial tidak
     * menjalankan seluruh migrasi school). Idempotent.
     */
    protected function ensureMirrorTables(): void
    {
        if (DB::connection('school')->getSchemaBuilder()->hasTable('arkas_mirror_kas_umum')
            && Schema::hasTable('arkas_mirror_ref_kode')
        ) {
            return;
        }

        foreach ([
            ['--database' => 'school', '--path' => 'database/migrations/school/2026_09_19_000001_create_arkas_fixed_mirror_tables.php', '--force' => true],
            ['--database' => 'school', '--path' => 'database/migrations/school/2026_09_19_000002_add_generated_columns_to_arkas_mirror_tables.php', '--force' => true],
            ['--database' => 'school', '--path' => 'database/migrations/school/2026_09_19_000003_fix_generated_key_case.php', '--force' => true],
            ['--path' => 'database/migrations/2026_09_19_000001_create_arkas_fixed_mirror_central_tables.php', '--force' => true],
            ['--path' => 'database/migrations/2026_09_19_000002_add_generated_columns_to_arkas_mirror_ref_kode.php', '--force' => true],
            ['--path' => 'database/migrations/2026_09_19_000003_add_id_ref_lookup_to_arkas_mirror_ref_kode.php', '--force' => true],
        ] as $arguments) {
            try {
                Artisan::call('migrate', $arguments);
            } catch (\Throwable) {
                // tabel sudah ada / migrasi tercatat
            }
        }
    }

    private function nextMirrorKey(string $prefix): string
    {
        return $prefix.'-'.(++$this->mirrorSequence).'-'.substr(md5(spl_object_hash($this).microtime(true).random_int(1, 999999)), 0, 6);
    }

    private function insertMirrorRow(string $connection, string $table, string $key, array $payload): void
    {
        $this->ensureMirrorTables();

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        DB::connection($connection === 'central' ? null : 'school')->table($table)->updateOrInsert(
            ['source_key' => $key],
            [
                'payload' => $json,
                'source_hash' => hash('sha256', $json),
                'synced_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Resolver kini singleton per proses: seed harus membatalkan cache
        // agar pembacaan berikutnya melihat baris yang baru ditulis.
        try {
            app(ArkasMirrorResolver::class)->forget($table, $key);
        } catch (\Throwable) {
            // abaikan: seed tetap sah tanpa invalidasi cache
        }
    }

    /**
     * Seed satu baris kas_umum mirror (format enriched perintah `bku`).
     *
     * @param  array<string, mixed>  $fields
     */
    protected function seedMirrorKasUmum(?string $key = null, array $fields = []): string
    {
        $key ??= $this->nextMirrorKey('KAS-TEST');

        $payload = array_merge([
            'ID_KAS_UMUM' => $key,
            'ID_REF_SUMBER_DANA' => 1,
            'KATEGORI_BKU' => 'BELANJA',
            'NO_BUKTI' => 'BK-TEST-'.$key,
            'TANGGAL_TRANSAKSI' => '2026-02-10',
            'URAIAN' => 'Uraian mirror '.$key,
            'KODE_REKENING' => '5.2.1',
            'NAMA_TOKO' => '',
            'JUMLAH' => 1000000,
            'VOLUME' => 1,
            'ID_RAPBS_PERIODE' => null,
            'PARENT_ID_KAS_UMUM' => null,
            'IS_PPN' => 0,
            'IS_PPH21' => 0,
            'IS_PPH22' => 0,
            'IS_PPH23' => 0,
            'IS_PPH4' => 0,
            'IS_SSPD' => 0,
        ], $fields, ['ID_KAS_UMUM' => $key]);

        $this->insertMirrorRow('school', 'arkas_mirror_kas_umum', $key, $payload);

        return $key;
    }

    /**
     * Seed rantai activity (rapbs_periode → rapbs → ref_kode pusat).
     *
     * @return array{rapbs_periode: string, rapbs: string}
     */
    protected function seedMirrorActivityChain(array $fields = []): array
    {
        $rapbsKey = $this->nextMirrorKey('RAPBS');
        $periodeKey = $this->nextMirrorKey('RAPBSPER');
        $refKey = $this->nextMirrorKey('REFKODE');

        $this->insertMirrorRow('school', 'arkas_mirror_rapbs', $rapbsKey, [
            'ID_RAPBS' => $rapbsKey,
            'ID_REF_KODE' => $refKey,
            'KODE_REKENING' => $fields['KODE_REKENING'] ?? '5.2.1',
            'KODE_KEGIATAN' => $fields['ID_KODE'] ?? '07.12.01.',
            'NAMA_KEGIATAN' => $fields['URAIAN_KODE'] ?? 'Kegiatan Mirror',
            'URAIAN' => $fields['ACCOUNT_NAME'] ?? 'Rekening Mirror',
            'JUMLAH' => $fields['JUMLAH'] ?? 1000000,
        ]);

        $this->insertMirrorRow('school', 'arkas_mirror_rapbs_periode', $periodeKey, [
            'ID_RAPBS_PERIODE' => $periodeKey,
            'ID_RAPBS' => $rapbsKey,
            'ID_PERIODE' => 1,
            'SATUAN' => $fields['SATUAN'] ?? 'paket',
            'HARGA_SATUAN' => $fields['HARGA_SATUAN'] ?? 1000000,
            'JUMLAH' => $fields['JUMLAH'] ?? 1000000,
        ]);

        $this->insertMirrorRow('central', 'arkas_mirror_ref_kode', $refKey.'|2026|1|5', [
            'id_ref_kode' => $refKey,
            'id_kode' => $fields['ID_KODE'] ?? '07.12.01.',
            'uraian_kode' => $fields['URAIAN_KODE'] ?? 'Kegiatan Mirror',
            'tahun' => 2026,
            'sumber_dana_id' => 1,
            'bentuk_pendidikan_id' => 5,
        ]);

        return ['rapbs_periode' => $periodeKey, 'rapbs' => $rapbsKey];
    }

    /**
     * Buat transaksi uji minimal + rantai mirror untuk fakta sumbernya.
     *
     * Kunci `mirror_*` pada $overrides diteruskan ke seed kas_umum
     * (mis. ['mirror_NO_BUKTI' => 'BK-1']); atribut sumber lain
     * (no_bukti, transaction_date, description, gross_amount, ...)
     * otomatis menjadi fakta mirror, BUKAN kolom lokal.
     *
     * @param  array<string, mixed>  $overrides  overlay/kunci transaksi
     */
    protected function mirrorTransaction(array $overrides = []): Transaction
    {
        $mirror = [];
        foreach ($overrides as $key => $value) {
            if (str_starts_with($key, 'mirror_')) {
                $mirror[substr($key, 7)] = $value;
                unset($overrides[$key]);
            }
        }

        // Nilai activity untuk rantai (dibaca sebelum unset).
        $chainValues = [
            'ID_KODE' => $overrides['activity_code'] ?? '07.12.01.',
            'URAIAN_KODE' => $overrides['activity_name'] ?? 'Kegiatan Mirror',
            'KODE_REKENING' => $overrides['account_code'] ?? '5.2.1',
            'ACCOUNT_NAME' => $overrides['account_name'] ?? 'Rekening Mirror',
        ];

        // Jumlah pajak per jenis untuk anak PAJAK (diekstrak dulu sebelum
        // map loop menghapusnya dari overrides; intermediate, bukan payload).
        $taxAmounts = [];
        foreach (['ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total'] as $taxField) {
            if (array_key_exists($taxField, $overrides)) {
                $taxAmounts[$taxField] = (float) $overrides[$taxField];
            }
        }

        foreach (self::MIRROR_TX_MAP as $field => $bridgeKey) {
            if (array_key_exists($field, $overrides)) {
                if ($bridgeKey !== null) {
                    $mirror[$bridgeKey] = $overrides[$field];
                }
                unset($overrides[$field]);
            }
        }

        foreach (array_keys($taxAmounts) as $taxField) {
            unset($overrides[$taxField]);
        }

        $chain = $this->seedMirrorActivityChain($chainValues);

        $taxFlags = ['ppn' => 'IS_PPN', 'pph21' => 'IS_PPH21', 'pph22' => 'IS_PPH22', 'pph23' => 'IS_PPH23', 'pph4' => 'IS_PPH4', 'sspd' => 'IS_SSPD'];
        $flagSum = 0.0;
        if (getenv('MIRROR_DEBUG')) {
            fwrite(STDERR, "\nMIRROR_ARR=".json_encode($mirror)."\n");
        }
        $key = $this->seedMirrorKasUmum($overrides['id_kas_umum'] ?? null, array_merge([
            'ID_RAPBS_PERIODE' => $chain['rapbs_periode'],
        ], $mirror));
        unset($overrides['id_kas_umum']);

        foreach ($taxFlags as $field => $flag) {
            $amount = (float) ($taxAmounts[$field] ?? 0);
            if ($amount > 0) {
                $flagSum += $amount;
                $this->seedMirrorKasUmum(null, [
                    'KATEGORI_BKU' => 'PAJAK',
                    'PARENT_ID_KAS_UMUM' => $key,
                    'NO_BUKTI' => $mirror['NO_BUKTI'] ?? 'BK-TEST-'.$key,
                    'TANGGAL_TRANSAKSI' => $mirror['TANGGAL_TRANSAKSI'] ?? '2026-02-10',
                    'JUMLAH' => $amount,
                    $flag => 1,
                ]);
            }
        }

        $remainder = (float) ($taxAmounts['tax_total'] ?? 0) - $flagSum;
        if ($remainder > 0) {
            $this->seedMirrorKasUmum(null, [
                'KATEGORI_BKU' => 'PAJAK',
                'PARENT_ID_KAS_UMUM' => $key,
                'NO_BUKTI' => $mirror['NO_BUKTI'] ?? 'BK-TEST-'.$key,
                'TANGGAL_TRANSAKSI' => $mirror['TANGGAL_TRANSAKSI'] ?? '2026-02-10',
                'JUMLAH' => $remainder,
            ]);
        }

        return Transaction::query()->create(array_merge([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => $key,
            'source_key' => hash('sha256', $key),
            'status' => 'DRAFT',
        ], $overrides));
    }

    /**
     * Buat item uji minimal + baris mirror untuk fakta sumbernya.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function mirrorItem(Transaction $transaction, array $attributes = []): TransactionItem
    {
        $mirror = [];
        foreach ($attributes as $key => $value) {
            if (str_starts_with($key, 'mirror_')) {
                $mirror[substr($key, 7)] = $value;
                unset($attributes[$key]);
            }
        }

        $explicitUnitPrice = $attributes['unit_price'] ?? null;

        // Item pertama tanpa amount eksplisit mewarisi bruto transaksi
        // (cermin data V2: agregat item = bruto). Item berikutnya default 1000.
        if (! array_key_exists('amount', $attributes)) {
            $attributes['amount'] = $transaction->items()->count() === 0
                ? (float) ($transaction->sourceValue('gross_amount') ?: 1000)
                : 1000;
        }

        foreach (self::MIRROR_ITEM_MAP as $field => $bridgeKey) {
            if (array_key_exists($field, $attributes)) {
                if ($bridgeKey !== null) {
                    $mirror[$bridgeKey] = $attributes[$field];
                }
                unset($attributes[$field]);
            }
        }

        $unitPrice = $mirror['UNIT_PRICE'] ?? $explicitUnitPrice;
        unset($mirror['UNIT_PRICE']);

        $chain = $this->seedMirrorActivityChain([
            'SATUAN' => $mirror['SATUAN'] ?? 'paket',
            'HARGA_SATUAN' => $unitPrice ?? ($mirror['JUMLAH'] ?? 1000),
            'JUMLAH' => $mirror['JUMLAH'] ?? 1000,
        ]);

        $noBukti = $transaction->sourceValue('no_bukti') ?: 'BK-TEST';
        $rawDate = $transaction->sourceValue('transaction_date');
        $tanggal = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : '2026-02-10';
        $namaToko = $transaction->sourceValue('recipient_name') ?: '';
        $kodeRekening = $transaction->sourceValue('account_code') ?: '5.2.1';
        $key = $this->seedMirrorKasUmum($attributes['source_item_id'] ?? null, array_merge([
            'NO_BUKTI' => is_string($noBukti) ? $noBukti : 'BK-TEST',
            'TANGGAL_TRANSAKSI' => $tanggal,
            'ID_RAPBS_PERIODE' => $chain['rapbs_periode'],
            'KODE_REKENING' => is_string($kodeRekening) ? $kodeRekening : '5.2.1',
            'NAMA_TOKO' => is_string($namaToko) ? $namaToko : '',
            'URAIAN' => $mirror['URAIAN'] ?? 'Uraian item mirror',
            'VOLUME' => $mirror['VOLUME'] ?? 1,
            'JUMLAH' => $mirror['JUMLAH'] ?? 1000,
        ], $mirror));
        unset($attributes['source_item_id']);

        return $transaction->items()->create(array_merge([
            'source_item_id' => $key,
        ], $attributes));
    }

    /**
     * Perbarui fakta sumber baris kas_umum mirror milik transaksi
     * (pengganti forceFill kolom lokal yang kini di-drop).
     *
     * @param  array<string, mixed>  $bridgeFields  kunci payload bridge
     */
    protected function setMirrorSource(Transaction $transaction, array $bridgeFields): void
    {
        $key = $transaction->id_kas_umum;
        if (blank($key)) {
            return;
        }

        $row = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->where('source_key', $key)
            ->first(['payload', 'source_hash']);

        $payload = $row ? (json_decode((string) $row->payload, true) ?: []) : [];
        $payload = array_merge($payload, $bridgeFields);
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        DB::connection('school')->table('arkas_mirror_kas_umum')->updateOrInsert(
            ['source_key' => $key],
            [
                'payload' => $json,
                'source_hash' => hash('sha256', $json),
                'synced_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        app(ArkasMirrorResolver::class)->forget('arkas_mirror_kas_umum', $key);
    }

    protected function findTransactionByBukti(string $noBukti): ?Transaction
    {
        $key = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->where('sx_no_bukti', $noBukti)
            ->value('source_key');

        if (! $key) {
            return null;
        }

        return Transaction::query()->where('id_kas_umum', $key)->first();
    }
}
