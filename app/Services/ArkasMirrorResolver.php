<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Typed read-through ke tabel mirror ARKAS tetap.
 *
 * Aturan: tabel aplikasi (overlay) tidak menulis ulang kolom sumber —
 * cukup panggil resolver ini. Hasil di-cache per request agar satu
 * transaksi yang dibaca berulang tidak menembak database berkali-kali.
 */
class ArkasMirrorResolver
{
    /** @var array<string, ?array<string, mixed>> */
    private array $cache = [];

    /** @var array<string, array<int, array<string, mixed>>>|null */
    private ?array $pajakIndex = null;

    /**
     * Agregat transactionSource per kombinasi ID kas (satu transaksi yang
     * dibaca berulang tidak menghitung ulang agregat dalam satu request).
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $transactionCache = [];

    /**
     * Generasi cache. Naik setiap forget() dipanggil (tulisan mirror).
     * Konsumen memo (Transaction::mirrorSource) menyimpan generasi saat
     * menghitung dan menghitung ulang bila generasi sudah berubah.
     */
    private int $generation = 0;

    public function generation(): int
    {
        return $this->generation;
    }

    public function forget(?string $mirror = null, ?string $sourceKey = null): void
    {
        $this->generation++;
        $this->pajakIndex = null;
        $this->transactionCache = [];

        if ($mirror === null) {
            $this->cache = [];

            return;
        }

        foreach (array_keys($this->cache) as $key) {
            if (! str_starts_with($key, $mirror.'|')) {
                continue;
            }

            // Kunci get() berbentuk mirror|connection|sourceKey.
            if ($sourceKey !== null && ! str_ends_with($key, '|'.$sourceKey)) {
                continue;
            }

            unset($this->cache[$key]);
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $mirror, string $sourceKey, string $connection = 'school'): ?array
    {
        $cacheKey = $mirror.'|'.$connection.'|'.$sourceKey;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            if (! Schema::connection($connection === 'central' ? null : 'school')->hasTable($mirror)) {
                return $this->cache[$cacheKey] = null;
            }

            $row = DB::connection($connection === 'central' ? null : 'school')
                ->table($mirror)
                ->where('source_key', $sourceKey)
                ->first(['payload']);
        } catch (\Throwable) {
            return $this->cache[$cacheKey] = null;
        }

        if (! $row) {
            return $this->cache[$cacheKey] = null;
        }

        $payload = json_decode((string) $row->payload, true);

        return $this->cache[$cacheKey] = is_array($payload) ? $payload : null;
    }

    /** @return array<string, mixed>|null */
    public function kasUmum(string $idKasUmum): ?array
    {
        return $this->get('arkas_mirror_kas_umum', $idKasUmum, 'school');
    }

    /** @return array<string, mixed>|null */
    public function kasNota(string $idKasNota): ?array
    {
        return $this->get('arkas_mirror_kas_umum_nota', $idKasNota, 'school');
    }

    /** @return array<string, mixed>|null */
    public function rapbs(string $idRapbs): ?array
    {
        return $this->get('arkas_mirror_rapbs', $idRapbs, 'school');
    }

    /** @return array<string, mixed>|null */
    public function rapbsPeriode(string $idRapbsPeriode): ?array
    {
        return $this->get('arkas_mirror_rapbs_periode', $idRapbsPeriode, 'school');
    }

    /** @return array<string, mixed>|null */
    public function refKode(string $sourceKey): ?array
    {
        return $this->get('arkas_mirror_ref_kode', $sourceKey, 'central');
    }

    /** @return array<string, mixed>|null */
    public function refRekening(string $sourceKey): ?array
    {
        return $this->get('arkas_mirror_ref_rekening', $sourceKey, 'central');
    }

    /** @return array<string, mixed>|null */
    public function refSumberDana(int|string $id): ?array
    {
        return $this->get('arkas_mirror_ref_sumber_dana', (string) $id, 'central');
    }

    /** @return array<string, mixed>|null */
    public function refPeriode(int|string $id): ?array
    {
        return $this->get('arkas_mirror_ref_periode', (string) $id, 'central');
    }

    /** @return array<string, mixed>|null */
    public function mstSekolah(string $sekolahId): ?array
    {
        return $this->get('arkas_mirror_mst_sekolah', $sekolahId, 'school');
    }

    /** @return array<string, mixed>|null */
    public function sekolahPenjab(string $idPenjab): ?array
    {
        return $this->get('arkas_mirror_sekolah_penjab', $idPenjab, 'school');
    }

    /** @return array<string, mixed>|null */
    public function refKodeByIdRef(string $idRefKode): ?array
    {
        try {
            if (! Schema::connection(null)->hasTable('arkas_mirror_ref_kode')) {
                return null;
            }

            $table = DB::connection(null)->table('arkas_mirror_ref_kode');
            $row = Schema::hasColumn('arkas_mirror_ref_kode', 'sx_id_ref_kode')
                ? $table->where('sx_id_ref_kode', $idRefKode)->first(['payload'])
                : null;
            if (! $row) {
                foreach (DB::connection(null)->table('arkas_mirror_ref_kode')->get(['payload']) as $candidate) {
                    $payload = json_decode((string) $candidate->payload, true);
                    if (is_array($payload) && (string) self::field($payload, ['ID_REF_KODE']) === $idRefKode) {
                        $row = $candidate;

                        break;
                    }
                }
            }
        } catch (\Throwable) {
            return null;
        }

        if (! $row) {
            return null;
        }

        $payload = json_decode((string) $row->payload, true);

        return is_array($payload) ? $payload : null;
    }

    /** @return array<string, mixed>|null */
    public function anggaran(string $idAnggaran): ?array
    {
        return $this->get('arkas_mirror_anggaran', $idAnggaran, 'school');
    }

    /**
     * Seluruh baris PAJAK mirror, diindeks per request agar agregat
     * banyak transaksi tidak memindai tabel berulang kali.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pajakChildren(): array
    {
        if ($this->pajakIndex !== null) {
            $flat = [];
            foreach ($this->pajakIndex as $group) {
                array_push($flat, ...$group);
            }

            return $flat;
        }

        $this->pajakIndex = [];

        try {
            $query = DB::connection('school')
                ->table('arkas_mirror_kas_umum')
                ->select(['payload']);

            // Generated/indexed mirror columns are available on current school
            // databases. Keep a payload-only fallback for older databases that
            // have not received the generated-column migration yet.
            if (Schema::connection('school')->hasColumn('arkas_mirror_kas_umum', 'sx_kategori')) {
                $query->where('sx_kategori', 'PAJAK');
            } else {
                $query->where('payload', 'like', '%KATEGORI_BKU%');
            }

            $candidates = $query->cursor();
        } catch (\Throwable) {
            return [];
        }

        foreach ($candidates as $candidate) {
            $tax = json_decode((string) $candidate->payload, true);
            if (! is_array($tax)) {
                continue;
            }
            if (strtoupper((string) self::field($tax, ['KATEGORI_BKU'])) !== 'PAJAK') {
                continue;
            }
            $parent = (string) (self::field($tax, ['PARENT_ID_KAS_UMUM']) ?? '');
            $this->pajakIndex[$parent][] = $tax;
        }

        $flat = [];
        foreach ($this->pajakIndex as $group) {
            array_push($flat, ...$group);
        }

        return $flat;
    }

    /**
     * Agregat sumber per no_bukti langsung dari mirror (untuk snapshot
     * rekonsiliasi dan perbandingan sebelum/sesudah sync).
     *
     * @return array<string, mixed>|null
     */
    public function aggregateByBukti(string $noBukti): ?array
    {
        try {
            if (! Schema::connection('school')->hasTable('arkas_mirror_kas_umum')) {
                return null;
            }

            $ids = DB::connection('school')->table('arkas_mirror_kas_umum')
                ->where('sx_no_bukti', $noBukti)
                ->where('sx_kategori', 'BELANJA')
                ->pluck('source_key')
                ->all();
        } catch (\Throwable) {
            return null;
        }

        if ($ids === []) {
            return null;
        }

        return $this->transactionSource($ids);
    }

    /**
     * Sama seperti ArkasSynchronizationServiceV2::isTaxDeposit: PBS
     * (penyetoran) bukan pungutan dan tidak boleh dihitung sebagai pajak.
     *
     * @param  array<string, mixed>  $record
     */
    public static function isTaxDeposit(array $record): bool
    {
        $code = strtoupper(trim((string) (self::field($record, ['KODE_BKU']) ?? '')));
        $description = mb_strtolower(trim(implode(' ', [
            self::field($record, ['REK_BKU']) ?? '',
            self::field($record, ['URAIAN']) ?? '',
        ])));

        return $code === 'PBS' || str_contains($description, 'pajak belanja setor') || str_starts_with($description, 'setor ');
    }

    /**
     * Baris backfill (spj:backfill-mirror-from-local) dikenali dari prefix
     * ID kanonisnya. Dipakai untuk dedup bayangan di bawah.
     *
     * @param  array<string, mixed>  $record
     */
    private static function isBackfillRow(array $record): bool
    {
        $id = strtoupper(trim((string) (self::field($record, ['ID_KAS_UMUM']) ?? '')));

        return str_starts_with($id, 'MIG-');
    }

    /**
     * Keranjang jenis pajak satu baris PAJAK (sama dengan klasifikasi di
     * transactionSource).
     *
     * @param  array<string, mixed>  $record
     */
    private static function taxBucket(array $record): string
    {
        if ((int) (self::field($record, ['IS_PPN']) ?? 0) === 1) {
            return 'ppn';
        }
        if ((int) (self::field($record, ['IS_PPH21']) ?? 0) === 1) {
            return 'pph21';
        }
        if ((int) (self::field($record, ['IS_PPH22']) ?? 0) === 1) {
            return 'pph22';
        }
        if ((int) (self::field($record, ['IS_PPH23']) ?? 0) === 1) {
            return 'pph23';
        }
        if ((int) (self::field($record, ['IS_PPH4']) ?? 0) === 1) {
            return 'pph4';
        }
        if ((int) (self::field($record, ['IS_SSPD']) ?? 0) === 1) {
            return 'sspd';
        }

        return '__unknown';
    }

    /**
     * Ambil beberapa baris kas_umum mirror sekaligus (satu query).
     *
     * @param  array<int, string>  $ids
     * @return array<string, array<string, mixed>> keyed by ID_KAS_UMUM
     */
    public function kasRowsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        try {
            if (! Schema::connection('school')->hasTable('arkas_mirror_kas_umum')) {
                return [];
            }

            $rows = DB::connection('school')->table('arkas_mirror_kas_umum')
                ->whereIn('source_key', $ids)
                ->get(['source_key', 'payload']);
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (is_array($payload)) {
                $result[$row->source_key] = $payload;
            }
        }

        return $result;
    }

    /**
     * Agregat sumber transaksi dari mirror (semantik sama dengan
     * ArkasSynchronizationServiceV2: BELANJA per no_bukti + anak PAJAK).
     *
     * @param  array<int, string>  $kasIds  ID_KAS_UMUM baris BELANJA
     * @param  string|null  $extraTaxParent  kunci kas tambahan yang anak
     *                                       PAJAK-nya ikut dihitung (id_kas_umum transaksi; pada data V2
     *                                       sama dengan salah satu baris BELANJA sehingga no-op)
     * @return array<string, mixed>|null
     */
    public function transactionSource(array $kasIds, ?string $extraTaxParent = null): ?array
    {
        $normalized = array_values(array_unique(array_filter(array_map(strval(...), $kasIds))));
        sort($normalized);
        $cacheKey = implode(',', $normalized).'|'.(string) $extraTaxParent;

        if (array_key_exists($cacheKey, $this->transactionCache)) {
            return $this->transactionCache[$cacheKey];
        }

        $rows = $this->kasRowsByIds($kasIds);

        if ($rows === []) {
            return $this->transactionCache[$cacheKey] = null;
        }

        $belanja = array_values(array_filter(
            $rows,
            static fn (array $row): bool => strtoupper((string) self::field($row, ['KATEGORI_BKU'])) === 'BELANJA'
        ));

        if ($belanja === []) {
            $belanja = array_values($rows);
        }

        $first = $belanja[0];
        $gross = 0.0;
        foreach ($belanja as $row) {
            $gross += (float) str_replace(',', '.', (string) (self::field($row, ['JUMLAH']) ?? 0));
        }

        $taxes = ['ppn' => 0.0, 'pph21' => 0.0, 'pph22' => 0.0, 'pph23' => 0.0, 'pph4' => 0.0, 'sspd' => 0.0];
        $parents = [];
        foreach ($belanja as $row) {
            $id = (string) (self::field($row, ['ID_KAS_UMUM']) ?? '');
            if ($id !== '') {
                $parents[$id] = true;
            }
        }
        if ($extraTaxParent !== null && $extraTaxParent !== '') {
            $parents[$extraTaxParent] = true;
        }

        $candidates = [];
        foreach ($this->pajakChildren() as $tax) {
            $parent = (string) (self::field($tax, ['PARENT_ID_KAS_UMUM']) ?? '');
            if (! isset($parents[$parent])) {
                continue;
            }
            if (self::isTaxDeposit($tax)) {
                continue;
            }
            $candidates[] = [$tax, $parent];
        }

        // Baris backfill MIG-* mengalah pada baris sync kanonis yang
        // menaunginya: shortfall yang diisi backfill bisa sudah dilengkapi
        // sync susulan (kasus nyata: MIG-T-ppn dan PBT "Terima PPN" bernilai
        // sama, kadang di bawah induk belanja duplikat yang berbeda dalam
        // satu transaksi) sehingga keduanya ikut terjumlah dan pajak
        // terhitung ganda. Kunci bayangan: keranjang + nominal yang sama
        // persis dalam lingkup agregasi ini; baris kanonis tidak pernah
        // dilewati sehingga shortfall murni tetap terhitung.
        $canonicalKeys = [];
        foreach ($candidates as [$tax]) {
            if (self::isBackfillRow($tax)) {
                continue;
            }
            $amount = (float) str_replace(',', '.', (string) (self::field($tax, ['JUMLAH']) ?? 0));
            $canonicalKeys[self::taxBucket($tax).'|'.number_format($amount, 2, '.', '')] = true;
        }

        foreach ($candidates as [$tax]) {
            $key = self::taxBucket($tax);
            $amount = (float) str_replace(',', '.', (string) (self::field($tax, ['JUMLAH']) ?? 0));
            if (self::isBackfillRow($tax)
                && isset($canonicalKeys[$key.'|'.number_format($amount, 2, '.', '')])) {
                continue;
            }
            $taxes[$key] = ($taxes[$key] ?? 0.0) + $amount;
        }

        $taxTotal = array_sum($taxes);

        $enrichment = $this->resolveActivity($first);

        return $this->transactionCache[$cacheKey] = [
            'no_bukti' => self::field($first, ['NO_BUKTI']),
            'transaction_date' => self::field($first, ['TANGGAL_TRANSAKSI']),
            'description' => self::field($first, ['URAIAN']),
            'activity_code' => $enrichment['activity_code'],
            'activity_name' => $enrichment['activity_name'],
            'account_code' => self::field($first, ['KODE_REKENING']),
            'account_name' => $enrichment['account_name'],
            'recipient_name' => self::field($first, ['NAMA_TOKO']),
            'is_siplah' => (bool) self::field($first, ['IS_SIPLAH']),
            'gross_amount' => $gross,
            'ppn' => $taxes['ppn'],
            'pph21' => $taxes['pph21'],
            'pph22' => $taxes['pph22'],
            'pph23' => $taxes['pph23'],
            'pph4' => $taxes['pph4'],
            'sspd' => $taxes['sspd'],
            'tax_total' => $taxTotal,
            'net_amount' => $gross - $taxTotal,
            'rows' => $belanja,
        ];
    }

    /**
     * LEFT JOIN transaksi ke baris kas_umum mirror (kunci: id_kas_umum).
     * mkas.sx_* adalah kunci query kanonik pasca-DROP kolom ganda.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function joinKasUmum(mixed $query, string $alias = 'mkas'): void
    {
        $query->leftJoin('arkas_mirror_kas_umum as '.$alias, $alias.'.source_key', '=', 'transactions.id_kas_umum');
    }

    public static function mirrorDate(string $alias = 'mkas'): string
    {
        return "{$alias}.sx_tanggal";
    }

    /**
     * EXISTS pencarian teks pada payload mirror baris kas milik transaksi
     * (via id_kas_umum atau rincian source_item_id). Dipakai search box
     * yang sebelumnya memakai LIKE kolom lokal yang kini di-drop.
     */
    public static function mirrorTextSearchExists(string $search): string
    {
        return 'EXISTS (SELECT 1 FROM arkas_mirror_kas_umum msearch'
            .' WHERE (msearch.source_key = transactions.id_kas_umum'
            .' OR msearch.source_key IN (SELECT source_item_id FROM transaction_items WHERE transaction_items.transaction_id = transactions.id))'
            .' AND msearch.payload LIKE '.self::quoteLike($search).')';
    }

    private static function quoteLike(string $search): string
    {
        return "'%".str_replace(['%', '_', "'"], ['\\%', '\\_', "''"], $search)."%'";
    }

    public static function mirrorMonth(string $alias = 'mkas'): string
    {
        return "CAST(strftime('%m', ".self::mirrorDate($alias).') AS INTEGER)';
    }

    /**
     * Filter tanggal mirror dengan fallback baris yatim.
     *
     * Kasus nyata: id_kas_umum transaksi menunjuk kunci pra-rebuild yang
     * sudah tidak ada di mirror (hasil LEFT JOIN: baris mkas NULL) sehingga
     * transaksi gugur dari lingkup periode walau item-itemnya bertanggal
     * valid (BPU23-26 hilang dari BKP April). Bila baris utama hilang,
     * tanggal diambil dari baris mirror milik item transaksi. Baris sehat
     * (id_kas_umum cocok) tidak berubah perilaku: tepat satu periode,
     * tanpa ganda lintas bulan.
     *
     * `{d}` pada predikat diganti kolom tanggal lingkup utama / fallback.
     * joinKasUmum() wajib dipanggil lebih dulu agar alias tersedia.
     */
    public static function whereMirrorDate(mixed $query, string $predicate, array $bindings, string $alias = 'mkas'): void
    {
        $primary = str_replace('{d}', $alias.'.sx_tanggal', $predicate);
        $fallback = str_replace('{d}', 'mi.sx_tanggal', $predicate);
        $query->where(function ($scope) use ($primary, $fallback, $bindings, $alias): void {
            $scope->whereRaw($primary, $bindings)
                ->orWhere(function ($orphan) use ($alias, $fallback, $bindings): void {
                    $orphan->whereNull($alias.'.source_key')
                        ->whereRaw(
                            'EXISTS (SELECT 1 FROM arkas_mirror_kas_umum mi WHERE mi.source_key IN '
                            .'(SELECT ti.source_item_id FROM transaction_items ti WHERE ti.transaction_id = transactions.id) '
                            .'AND '.$fallback.')',
                            $bindings
                        );
                });
        });
    }

    /**
     * Rantai kegiatan: kas_umum.ID_RAPBS_PERIODE → rapbs_periode → rapbs
     * → ref_kode (pusat). Fallback ID_RAPBS langsung dari baris kas.
     *
     * @param  array<string, mixed>  $kasRow
     * @return array{activity_code: mixed, activity_name: mixed, account_name: mixed}
     */
    private function resolveActivity(array $kasRow): array
    {
        $empty = ['activity_code' => null, 'activity_name' => null, 'account_name' => null];

        $rapbsPeriodeId = self::field($kasRow, ['ID_RAPBS_PERIODE']);
        $rapbsPeriode = is_string($rapbsPeriodeId) && $rapbsPeriodeId !== ''
            ? $this->rapbsPeriode($rapbsPeriodeId)
            : null;

        $rapbsId = $rapbsPeriode !== null
            ? self::field($rapbsPeriode, ['ID_RAPBS'])
            : self::field($kasRow, ['ID_RAPBS']);

        $rapbs = is_string($rapbsId) && $rapbsId !== '' ? $this->rapbs((string) $rapbsId) : null;

        if ($rapbs === null) {
            return $empty;
        }

        $idRefKode = self::field($rapbs, ['ID_REF_KODE']);
        $refKode = is_string($idRefKode) && $idRefKode !== '' ? $this->refKodeByIdRef($idRefKode) : null;
        $activityCode = $refKode !== null ? self::field($refKode, ['ID_KODE']) : null;
        $activityName = $refKode !== null ? self::field($refKode, ['URAIAN_KODE']) : null;

        if ($activityCode === null || $activityCode === '') {
            $activityCode = self::field($rapbs, ['KODE_KEGIATAN']);
        }
        if ($activityName === null || $activityName === '') {
            $activityName = self::field($rapbs, ['NAMA_KEGIATAN']);
        }

        return [
            'activity_code' => $activityCode,
            'activity_name' => $activityName,
            'account_name' => self::field($rapbs, ['URAIAN']),
        ];
    }

    /** @param  array<string, mixed>  $record @param  array<int, string>  $keys */
    public static function field(array $record, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        $lower = array_change_key_case($record, CASE_LOWER);
        foreach ($keys as $key) {
            if (array_key_exists(strtolower($key), $lower)) {
                return $lower[strtolower($key)];
            }
        }

        return null;
    }
}
