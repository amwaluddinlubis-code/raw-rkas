<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Relink identitas transaksi ketika ARKAS menghapus + membuat ulang BKU
 * (ID_KAS_UMUM baru untuk isi bisnis yang sama).
 *
 * Prinsip: cocokkan berdasarkan ISI (multiset rincian URAIAN + VOLUME +
 * JUMLAH + SATUAN), bukan NO_BUKTI — dinas dapat menomori ulang bukti.
 * Relink bekerja in-place pada transaksi LAMA sehingga Paket, overlay
 * operator, participants/honors per item, dan numbering tidak terusik.
 * Dipakai oleh sync V2 (fallback adopsi) dan command perbaikan.
 */
final class SpjSourceRelinkService
{
    public function __construct(
        private readonly OperationalAuditService $audit,
    ) {}

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $missingCache = [];

    /**
     * Sidik satu baris BKU/mirror (normalisasi agar perbandingan stabil).
     *
     * @param  array<string, mixed>  $row
     */
    public static function itemFingerprint(array $row): string
    {
        $text = static fn (?string $value): string => preg_replace(
            '/\s+/',
            ' ',
            mb_strtolower(trim((string) $value))
        ) ?? '';

        $number = static function (mixed $value): string {
            if (! is_numeric($value)) {
                return '0';
            }

            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        };

        $uraian = $text(ArkasMirrorResolver::field($row, ['URAIAN']));
        $volume = $number(ArkasMirrorResolver::field($row, ['VOLUME']));
        $jumlah = $number(ArkasMirrorResolver::field($row, ['JUMLAH']));
        $satuan = $text(ArkasMirrorResolver::field($row, ['SATUAN', 'SATUAN_BARANG', 'UNIT']));

        return implode('|', [$uraian, $volume, $jumlah, $satuan]);
    }

    /** @param  array<int, string>  $fingerprints */
    public static function setFingerprint(array $fingerprints): string
    {
        sort($fingerprints);

        return hash('sha256', implode("\n", $fingerprints));
    }

    /**
     * Fallback untuk sync V2: adopsi transaksi SOURCE_MISSING yang isinya
     * identik ketika source_key + id_kas_umum tidak cocok.
     *
     * Melakukan re-point item lama ke ID baru + identitas transaksi lama ke
     * kunci baru, lalu mengembalikan baris lama agar alur V2 normal
     * (update, hash-compare, reconciliation) tetap berjalan.
     *
     * @param  array<int, array<string, mixed>>  $records  baris BELANJA staged satu NO_BUKTI
     */
    public function adoptForSync(int $fiscalYearId, int $fundSourceId, array $records, string $newSourceKey, ?int $runId): ?object
    {
        $newFps = [];
        $newIds = [];
        foreach ($records as $record) {
            $id = (string) ($record['ID_KAS_UMUM'] ?? '');
            if ($id === '') {
                return null;
            }
            $newFps[] = self::itemFingerprint($record);
            $newIds[] = $id;
        }
        if ($newFps === []) {
            return null;
        }

        $match = $this->uniqueMissingMatch($fiscalYearId, $fundSourceId, self::setFingerprint($newFps));
        if ($match === null) {
            return null;
        }

        $this->repointItems($match['items'], $newIds, $runId);

        DB::connection('school')->table('transactions')->where('id', $match['transaction']->id)->update([
            'id_kas_umum' => $records[0]['ID_KAS_UMUM'] ?? $match['transaction']->id_kas_umum,
            'source_key' => $newSourceKey,
            'source_status' => 'ACTIVE',
            'source_missing_since' => null,
            'last_seen_sync_run_id' => $runId,
            'updated_at' => now(),
        ]);

        $this->forgetCandidate($fiscalYearId, $fundSourceId, (int) $match['transaction']->id);
        $this->audit->record($fiscalYearId, 'TRANSACTION', $match['transaction']->id, 'SOURCE_RELINK', 'Identitas sumber diadopsi ulang setelah BKU dibuat ulang di ARKAS (kandidat tunggal dengan rincian identik).');

        return DB::connection('school')->table('transactions')->where('id', $match['transaction']->id)->first();
    }

    /**
     * Pasangan relink untuk command perbaikan (read-only).
     *
     * Grup dengan isi identik ganda dipasangkan berurutan menurut tanggal
     * (pengiriman bulanan seperti pulsa Juli/Agustus/September tetap
     * berpasangan bulan-ke-bulan); selisih tanggal ditandai untuk review.
     *
     * @return array{relinkable: array<int, array<string, mixed>>, manual_missing: array<int, array<string, mixed>>, manual_new: array<int, array<string, mixed>>}
     */
    public function preview(int $fiscalYearId, int $fundSourceId): array
    {
        $missing = $this->missingCandidates($fiscalYearId, $fundSourceId);
        $new = $this->newUnpackaged($fiscalYearId, $fundSourceId);

        $relinkable = [];
        $manualMissing = [];
        $manualNew = [];
        $usedNew = [];

        foreach ($missing['complete'] as $candidate) {
            if ($candidate['complete'] === false) {
                $manualMissing[] = [
                    'transaction_id' => $candidate['transaction']->id,
                    'reason' => 'mirror rincian lama tidak lengkap — butuh telaah manual',
                ];
            }
        }

        // Kelompokkan sisi baru yang lengkap per sidik isi.
        $newBySet = [];
        foreach ($new['complete'] as $candidate) {
            if ($candidate['complete'] === false) {
                $manualNew[] = [
                    'transaction_id' => $candidate['transaction']->id,
                    'reason' => 'mirror rincian baru tidak lengkap — butuh telaah manual',
                ];

                continue;
            }
            $newBySet[$candidate['set_fp']][] = $candidate;
        }

        // Kelompokkan sisi lama yang lengkap per sidik isi.
        $oldBySet = [];
        foreach ($missing['complete'] as $candidate) {
            if ($candidate['complete'] === false) {
                continue;
            }
            $oldBySet[$candidate['set_fp']][] = $candidate;
        }

        foreach ($oldBySet as $setFp => $olds) {
            $news = array_filter(
                $newBySet[$setFp] ?? [],
                fn (array $p): bool => ! in_array($p['transaction']->id, $usedNew, true)
            );
            $news = array_values($news);

            if ($news === []) {
                foreach ($olds as $candidate) {
                    $manualMissing[] = [
                        'transaction_id' => $candidate['transaction']->id,
                        'reason' => 'tidak ada transaksi baru dengan rincian identik',
                    ];
                }

                continue;
            }

            // Pasangan berurutan tanggal; sisa tiap sisi masuk telaah manual.
            usort($olds, fn (array $a, array $b): int => strcmp((string) $a['first_date'], (string) $b['first_date']));
            usort($news, fn (array $a, array $b): int => strcmp((string) $a['first_date'], (string) $b['first_date']));
            $pairs = min(count($olds), count($news));
            for ($i = 0; $i < $pairs; $i++) {
                $usedNew[] = $news[$i]['transaction']->id;
                $relinkable[] = ['old' => $olds[$i], 'new' => $news[$i]];
            }
            foreach (array_slice($olds, $pairs) as $candidate) {
                $manualMissing[] = [
                    'transaction_id' => $candidate['transaction']->id,
                    'reason' => 'kandidat baru habis untuk isi identik ini — butuh telaah manual',
                ];
            }
        }

        foreach ($new['complete'] as $candidate) {
            if ($candidate['complete'] === false) {
                continue;
            }
            if (! in_array($candidate['transaction']->id, $usedNew, true)) {
                $manualNew[] = ['transaction_id' => $candidate['transaction']->id, 'reason' => 'transaksi baru tanpa pasangan lama — kemungkinan BKU benar-benar baru'];
            }
        }

        return ['relinkable' => $relinkable, 'manual_missing' => $manualMissing, 'manual_new' => $manualNew];
    }

    /**
     * Eksekusi relink pasangan hasil preview. Wajib didahului backup file.
     *
     * @param  array<int, array<string, mixed>>  $pairs  dari preview()['relinkable']
     * @return array{relinked: array<int, int>, skipped: array<int, string>} old_id => new_id
     */
    public function executePairs(int $fiscalYearId, array $pairs, ?int $runId): array
    {
        $relinked = [];
        $skipped = [];

        foreach ($pairs as $pair) {
            $oldId = (int) $pair['old']['transaction']->id;
            $newId = (int) $pair['new']['transaction']->id;

            try {
                DB::connection('school')->transaction(function () use ($fiscalYearId, $pair, $oldId, $newId, $runId, &$relinked): void {
                    $fresh = $this->refreshPair($pair);
                    if ($fresh === null) {
                        throw new \RuntimeException('pasangan berubah sebelum eksekusi');
                    }
                    [$old, $new] = $fresh;

                    if ($this->newHasPackage($newId)) {
                        throw new \RuntimeException('transaksi baru sudah memiliki paket');
                    }

                    // Baris item baru keyed by ID untuk salinan deskripsi Siplah.
                    $newRowsById = [];
                    foreach (DB::connection('school')->table('transaction_items')->where('transaction_id', $newId)->orderBy('id')->get() as $newItem) {
                        $newRowsById[(string) $newItem->source_item_id] = $newItem;
                    }
                    $this->repointItems($old['items'], $new['ids'], $runId, $newRowsById);

                    $oldRow = (array) $old['transaction'];
                    $newRow = (array) $new['transaction'];

                    // Hapus baris item baru yang sudah diadopsi; pindahkan sisanya.
                    DB::connection('school')->table('transaction_items')
                        ->where('transaction_id', $newId)
                        ->whereIn('source_item_id', $new['ids'])
                        ->delete();
                    DB::connection('school')->table('transaction_items')
                        ->where('transaction_id', $newId)
                        ->update(['transaction_id' => $oldId, 'source_status' => 'ACTIVE', 'source_missing_since' => null, 'updated_at' => now()]);

                    // Hapus duplikat BARU dahulu agar source_key unik bebas
                    // sebelum identitas lama diadopsi.
                    DB::connection('school')->table('transactions')->where('id', $newId)->delete();

                    $update = [
                        'id_kas_umum' => $newRow['id_kas_umum'],
                        'source_key' => $newRow['source_key'],
                        'source_hash' => $newRow['source_hash'],
                        'source_status' => 'ACTIVE',
                        'source_missing_since' => null,
                        'last_seen_sync_run_id' => $runId ?? $newRow['last_seen_sync_run_id'],
                        'rkas_date' => $newRow['rkas_date'],
                        'requires_reconciliation' => $this->needsReview($old, $new),
                        'updated_at' => now(),
                    ];
                    // Overlay operator tidak ditimpa: hanya isi yang masih kosong.
                    foreach (['payment_method', 'vendor_name', 'vendor_npwp', 'invoice_number', 'invoice_date', 'siplah_order_number'] as $field) {
                        if (blank($oldRow[$field] ?? null) && filled($newRow[$field] ?? null)) {
                            $update[$field] = $newRow[$field];
                        }
                    }
                    DB::connection('school')->table('transactions')->where('id', $oldId)->update($update);

                    $this->audit->record($fiscalYearId, 'TRANSACTION', $oldId, 'SOURCE_RELINK', 'Transaksi #'.$oldId.' di-relink ke identitas BKU baru (duplikat #'.$newId.' dihapus).');
                    $relinked[$oldId] = $newId;
                });
            } catch (\Throwable $exception) {
                $skipped[$oldId] = $exception->getMessage();
            }
        }

        $this->missingCache = [];

        return ['relinked' => $relinked, 'skipped' => $skipped];
    }

    /** @return array{complete: array<int, array<string, mixed>}} */
    private function missingCandidates(int $fiscalYearId, int $fundSourceId): array
    {
        $key = $fiscalYearId.':'.$fundSourceId;
        if (array_key_exists($key, $this->missingCache)) {
            return $this->missingCache[$key];
        }

        $rows = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'SOURCE_MISSING')
            ->get();

        $candidates = [];
        $resolver = app(ArkasMirrorResolver::class);
        foreach ($rows as $row) {
            $items = DB::connection('school')->table('transaction_items')
                ->where('transaction_id', $row->id)
                ->orderBy('id')
                ->get();
            $itemRows = [];
            $fps = [];
            $complete = true;
            $firstDate = null;
            $firstPayload = null;
            foreach ($items as $item) {
                $payload = $resolver->kasUmum((string) $item->source_item_id);
                if (! is_array($payload)) {
                    $complete = false;
                    break;
                }
                $firstDate ??= ArkasMirrorResolver::field($payload, ['TANGGAL_TRANSAKSI']);
                $firstPayload ??= $payload;
                $fp = self::itemFingerprint($payload);
                $itemRows[] = ['item' => $item, 'fp' => $fp];
                $fps[] = $fp;
            }
            if ($itemRows === []) {
                $complete = false;
            }
            $candidates[] = [
                'transaction' => $row,
                'items' => $itemRows,
                'set_fp' => $complete ? self::setFingerprint($fps) : '',
                'first_date' => $firstDate,
                'first_payload' => $firstPayload,
                'complete' => $complete,
            ];
        }

        return $this->missingCache[$key] = ['complete' => array_values($candidates)];
    }

    /** @return array{complete: array<int, array<string, mixed>>} */
    private function newUnpackaged(int $fiscalYearId, int $fundSourceId): array
    {
        $rows = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'ACTIVE')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('spj_packages')->whereColumn('spj_packages.transaction_id', 'transactions.id');
            })
            ->get();

        $candidates = [];
        $resolver = app(ArkasMirrorResolver::class);
        foreach ($rows as $row) {
            $items = DB::connection('school')->table('transaction_items')
                ->where('transaction_id', $row->id)
                ->orderBy('id')
                ->get();
            $fps = [];
            $ids = [];
            $complete = true;
            $firstDate = null;
            $firstPayload = null;
            foreach ($items as $item) {
                $payload = $resolver->kasUmum((string) $item->source_item_id);
                if (! is_array($payload)) {
                    $complete = false;
                    break;
                }
                $firstDate ??= ArkasMirrorResolver::field($payload, ['TANGGAL_TRANSAKSI']);
                $firstPayload ??= $payload;
                $fps[] = self::itemFingerprint($payload);
                $ids[] = (string) $item->source_item_id;
            }
            if ($fps === []) {
                $complete = false;
            }
            $candidates[] = [
                'transaction' => $row,
                'fps' => $fps,
                'ids' => $ids,
                'set_fp' => $complete ? self::setFingerprint($fps) : '',
                'first_date' => $firstDate,
                'first_payload' => $firstPayload,
                'complete' => $complete,
            ];
        }

        return ['complete' => $candidates];
    }

    /** @return array<string, mixed>|null */
    private function uniqueMissingMatch(int $fiscalYearId, int $fundSourceId, string $setFp): ?array
    {
        $matches = array_values(array_filter(
            $this->missingCandidates($fiscalYearId, $fundSourceId)['complete'],
            static fn (array $c): bool => $c['complete'] && $c['set_fp'] === $setFp
        ));

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function forgetCandidate(int $fiscalYearId, int $fundSourceId, int $transactionId): void
    {
        $key = $fiscalYearId.':'.$fundSourceId;
        if (! array_key_exists($key, $this->missingCache)) {
            return;
        }
        $this->missingCache[$key]['complete'] = array_values(array_filter(
            $this->missingCache[$key]['complete'],
            static fn (array $c): bool => (int) $c['transaction']->id !== $transactionId
        ));
    }

    /**
     * Re-point baris item lama ke ID sumber baru (in-place: deskripsi,
     * participants, dan honors tidak terusik; deskripsi Siplah yang hanya
     * ada di baris baru ikut disalin bila baris lama masih kosong).
     *
     * Baris kembar isi-identik dipasangkan deterministik berurutan ID.
     *
     * @param  array<int, array{item: object, fp: string}>  $oldRows  berurutan ID
     * @param  array<int, string>  $newIds  ID sumber baru berurutan
     * @param  array<string, object>  $newRowsById  baris item baru (command repair; kosong pada jalur sync)
     */
    private function repointItems(array $oldRows, array $newIds, ?int $runId, array $newRowsById = []): void
    {
        foreach ($oldRows as $index => $entry) {
            $newId = $newIds[$index] ?? null;
            if ($newId === null) {
                continue;
            }
            $update = [
                'source_item_id' => $newId,
                'source_status' => 'ACTIVE',
                'source_missing_since' => null,
                'last_seen_sync_run_id' => $runId,
                'updated_at' => now(),
            ];
            $newRow = $newRowsById[$newId] ?? null;
            if ($newRow && blank($entry['item']->item_description) && filled($newRow->item_description)) {
                $update['item_description'] = $newRow->item_description;
            }
            DB::connection('school')->table('transaction_items')->where('id', $entry['item']->id)->update($update);
        }
    }

    /** @param  array<string, mixed>  $pair */
    private function refreshPair(array $pair): ?array
    {
        $oldId = (int) $pair['old']['transaction']->id;
        $newId = (int) $pair['new']['transaction']->id;

        $old = DB::connection('school')->table('transactions')->where('id', $oldId)->first();
        $new = DB::connection('school')->table('transactions')->where('id', $newId)->first();
        if (! $old || ! $new || $old->source_status !== 'SOURCE_MISSING' || $new->source_status !== 'ACTIVE') {
            return null;
        }

        // Bangun ulang fp dari kondisi terkini memakai mirror aktif.
        $resolver = app(ArkasMirrorResolver::class);
        $build = function (int $txId) use ($resolver): ?array {
            $items = DB::connection('school')->table('transaction_items')->where('transaction_id', $txId)->orderBy('id')->get();
            $rows = [];
            $fps = [];
            $ids = [];
            $firstDate = null;
            $firstPayload = null;
            foreach ($items as $item) {
                $payload = $resolver->kasUmum((string) $item->source_item_id);
                if (! is_array($payload)) {
                    return null;
                }
                $firstDate ??= ArkasMirrorResolver::field($payload, ['TANGGAL_TRANSAKSI']);
                $firstPayload ??= $payload;
                $fp = self::itemFingerprint($payload);
                $rows[] = ['item' => $item, 'fp' => $fp];
                $fps[] = $fp;
                $ids[] = (string) $item->source_item_id;
            }
            if ($rows === []) {
                return null;
            }

            return ['rows' => $rows, 'fps' => $fps, 'ids' => $ids, 'first_date' => $firstDate, 'first_payload' => $firstPayload];
        };

        $oldBuilt = $build($oldId);
        $newBuilt = $build($newId);
        if ($oldBuilt === null || $newBuilt === null) {
            return null;
        }
        if (self::setFingerprint($oldBuilt['fps']) !== self::setFingerprint($newBuilt['fps'])) {
            return null;
        }

        return [
            ['transaction' => $old, 'items' => $oldBuilt['rows'], 'first_date' => $oldBuilt['first_date'], 'first_payload' => $oldBuilt['first_payload']],
            ['transaction' => $new, 'ids' => $newBuilt['ids'], 'first_date' => $newBuilt['first_date'], 'first_payload' => $newBuilt['first_payload']],
        ];
    }

    private function newHasPackage(int $transactionId): bool
    {
        return DB::connection('school')->table('spj_packages')->where('transaction_id', $transactionId)->exists();
    }

    /** @param  array<string, mixed>  $old  @param  array<string, mixed>  $new */
    private function needsReview(array $old, array $new): bool
    {
        // Rincian dan nominal sudah dibuktikan identik oleh fingerprint;
        // review hanya dipicu bila tanggal transaksi ikut berubah.
        return $this->firstDatesDiffer($old, $new);
    }

    /** @param  array<string, mixed>  $old  @param  array<string, mixed>  $new */
    private function firstDatesDiffer(array $old, array $new): bool
    {
        $oldDate = ArkasMirrorResolver::field($old['first_payload'] ?? [], ['TANGGAL_TRANSAKSI']);
        $newDate = ArkasMirrorResolver::field($new['first_payload'] ?? [], ['TANGGAL_TRANSAKSI']);

        return $this->reviewScalar($oldDate) !== $this->reviewScalar($newDate);
    }

    private function reviewScalar(mixed $value): string
    {
        return trim((string) $value);
    }
}
