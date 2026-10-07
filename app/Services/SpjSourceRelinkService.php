<?php

namespace App\Services;

use Illuminate\Support\Carbon;
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
     * Sidik agregat transaksi dari snapshot sumber. Dipakai ketika rincian
     * mirror lama sudah di-prune (sync sudah berjalan setelah BKU dibuat
     * ulang) sehingga itemFingerprint tak bisa dibangun untuk sisi lama.
     *
     * Bentuk snapshot identik dengan after_snapshot sync V2
     * (transaction_date, description, activity_*, account_*, recipient_*,
     * gross/tax/net, is_siplah).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function snapshotFingerprint(array $snapshot, bool $includeRecipient = true): string
    {
        $text = static fn (mixed $value): string => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
        $number = static function (mixed $value): string {
            if (! is_numeric($value)) {
                return '0';
            }

            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        };
        $date = static function (mixed $value) use ($text): string {
            try {
                return Carbon::parse((string) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return $text($value);
            }
        };

        return hash('sha256', implode("\n", [
            $date($snapshot['transaction_date'] ?? null),
            $text($snapshot['description'] ?? null),
            $text($snapshot['activity_code'] ?? null),
            $text($snapshot['activity_name'] ?? null),
            $text($snapshot['account_code'] ?? null),
            $text($snapshot['account_name'] ?? null),
            $includeRecipient ? $text($snapshot['recipient_name'] ?? null) : '0',
            $number($snapshot['gross_amount'] ?? null),
            $number($snapshot['ppn'] ?? null),
            $number($snapshot['pph21'] ?? null),
            $number($snapshot['pph22'] ?? null),
            $number($snapshot['pph23'] ?? null),
            $number($snapshot['pph4'] ?? null),
            $number($snapshot['sspd'] ?? null),
            $number($snapshot['tax_total'] ?? null),
            $number($snapshot['net_amount'] ?? null),
            ! empty($snapshot['is_siplah']) ? '1' : '0',
        ]));
    }

    /**
     * Konten sumber terakhir yang diketahui untuk transaksi missing:
     * after_snapshot event SOURCE_CHANGED terbaru. Null bila tak ada
     * (transaksi tak pernah berubah sejak dibuat).
     */
    public function lastContentSnapshot(int $transactionId): ?array
    {
        $event = DB::connection('school')->table('transaction_source_events')
            ->where('transaction_id', $transactionId)
            ->where('event_type', 'SOURCE_CHANGED')
            ->orderByDesc('id')
            ->first();
        if (! $event || ! is_string($event->after_snapshot ?? null) || trim((string) $event->after_snapshot) === '') {
            return null;
        }
        $snapshot = json_decode((string) $event->after_snapshot, true);

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Bangun ulang snapshot agregat dari mirror AKTIF untuk satu transaksi
     * (bentuk identik dengan after_snapshot sync V2). Null bila ada rincian
     * yang tak lagi termuat di mirror.
     */
    public function rebuiltSnapshot(object $transaction): ?array
    {
        $kasIds = DB::connection('school')->table('transaction_items')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->pluck('source_item_id')
            ->map(static fn ($id): string => (string) $id)
            ->filter()
            ->values()
            ->all();
        if ($kasIds === []) {
            return null;
        }
        $aggregate = app(ArkasMirrorResolver::class)->transactionSource($kasIds);
        if (! is_array($aggregate)) {
            return null;
        }
        unset($aggregate['rows'], $aggregate['no_bukti']);

        return $aggregate;
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

        $newRows = [];
        foreach ($newIds as $index => $id) {
            $newRows[] = ['id' => $id, 'fp' => $newFps[$index] ?? ''];
        }
        $repoint = $this->repointItems($match['items'], $newRows, $runId);
        if ($repoint['skipped'] !== []) {
            $reasons = [];
            foreach ($repoint['skipped'] as $oldItemId => $reason) {
                $reasons[] = 'item #'.$oldItemId.': '.$reason;
            }
            $this->audit->record($fiscalYearId, 'TRANSACTION', $match['transaction']->id, 'SOURCE_RELINK', 'Adopsi ulang: '.$repoint['paired'].' rincian terpasangkan; '.count($repoint['skipped']).' rincian tidak terpasangkan berbasis fingerprint dan dibiarkan untuk telaah manual ('.implode('; ', $reasons).').');
        }

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
     * @return array{relinkable: array<int, array<string, mixed>>, manual_missing: array<int, array<string, mixed>>, manual_new: array<int, array<string, mixed>>, order_relinkable: array<int, array<string, mixed>>, order_manual: array<int, array<string, mixed>>, snapshot_relinkable: array<int, array<string, mixed>>, snapshot_manual: array<int, array<string, mixed>>}
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

        // Nomor pesanan Siplah identik = identitas pembelian yang sama.
        // Dijalankan sebelum sidik snapshot agar pasangan eksak tak
        // berebut dengan fuzzy.
        $missingIds = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'SOURCE_MISSING')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $pairedOldIds = array_map(static fn (array $pair): int => (int) $pair['old']['transaction']->id, $relinkable);
        $order = $this->previewOrderPairs(
            $fiscalYearId,
            $fundSourceId,
            array_values(array_diff($missingIds, $pairedOldIds)),
            $usedNew
        );
        $usedNew = array_merge($usedNew, $order['used_new']);

        // Sisi lama yang rincian mirrornya sudah di-prune dicoba ulang via
        // sidik snapshot (isi agregat terakhir yang diketahui).
        $snapshotOldIds = [];
        foreach ($missing['complete'] as $candidate) {
            if ($candidate['complete'] === false) {
                $snapshotOldIds[] = (int) $candidate['transaction']->id;
            }
        }
        $snapshot = $this->previewSnapshotPairs($fiscalYearId, $fundSourceId, $snapshotOldIds, $usedNew);

        return [
            'relinkable' => $relinkable,
            'manual_missing' => $manualMissing,
            'manual_new' => $manualNew,
            'order_relinkable' => $order['order_relinkable'],
            'order_manual' => $order['order_manual'],
            'snapshot_relinkable' => $snapshot['snapshot_relinkable'],
            'snapshot_manual' => $snapshot['snapshot_manual'],
        ];
    }

    /**
     * Pasangan relink via sidik snapshot untuk sisi lama yang rincian
     * mirrornya sudah di-prune (sync sudah berjalan setelah BKU dibuat
     * ulang di ARKAS). Hanya pasangan unik 1:1 dengan tanggal dan jumlah
     * rincian yang sama yang otomatis; selebihnya telaah manual.
     *
     * @param  array<int, int>  $oldPoolIds  batasi sisi lama (mis. yang incomplete di jalur rincian)
     * @param  array<int, int>  $skipNewIds  transaksi baru yang sudah terpakai di jalur rincian
     * @return array{snapshot_relinkable: array<int, array<string, mixed>>, snapshot_manual: array<int, array<string, mixed>>}
     */
    public function previewSnapshotPairs(int $fiscalYearId, int $fundSourceId, array $oldPoolIds = [], array $skipNewIds = []): array
    {
        $relinkable = [];
        $manual = [];

        $missingQuery = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'SOURCE_MISSING');
        if ($oldPoolIds !== []) {
            $missingQuery->whereIn('id', $oldPoolIds);
        }
        $missing = $missingQuery->get();

        $oldByFp = [];
        foreach ($missing as $row) {
            $snapshot = $this->lastContentSnapshot((int) $row->id);
            if ($snapshot === null) {
                $manual[] = [
                    'transaction_id' => (int) $row->id,
                    'reason' => 'tanpa snapshot perubahan sumber — butuh telaah manual',
                ];

                continue;
            }
            $oldByFp[self::snapshotFingerprint($snapshot)][(int) $row->id] = ['transaction' => $row, 'snapshot' => $snapshot];
        }

        $newQuery = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'ACTIVE')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('spj_packages')->whereColumn('spj_packages.transaction_id', 'transactions.id');
            });
        if ($skipNewIds !== []) {
            $newQuery->whereNotIn('id', $skipNewIds);
        }
        $news = $newQuery->get();

        $newByFp = [];
        foreach ($news as $row) {
            $snapshot = $this->rebuiltSnapshot($row);
            if ($snapshot === null) {
                continue;
            }
            $newByFp[self::snapshotFingerprint($snapshot)][(int) $row->id] = ['transaction' => $row, 'snapshot' => $snapshot];
        }

        $usedNew = [];
        // Tingkat 1: sidik penuh (termasuk penerima) unik 1:1.
        // Tingkat 2: sidik identitas (tanpa penerima) — BKU dibuat ulang
        // dengan koreksi vendor; pasangan diposisikan berurutan dan
        // ditandai review agar operator menelaah perubahan vendor.
        $identityOlds = [];
        $identityNews = [];
        foreach ($oldByFp as $fp => $olds) {
            $candidates = array_filter(
                $newByFp[$fp] ?? [],
                fn (array $candidate): bool => ! in_array((int) $candidate['transaction']->id, $usedNew, true)
            );
            $candidates = array_values($candidates);
            $olds = array_values($olds);

            if (count($olds) === 1 && count($candidates) === 1) {
                $pair = $this->buildSnapshotPair($olds[0], $candidates[0], false);
                if (is_array($pair)) {
                    $usedNew[] = (int) $candidates[0]['transaction']->id;
                    $relinkable[] = $pair;

                    continue;
                }
                $manual[] = [
                    'transaction_id' => (int) $olds[0]['transaction']->id,
                    'reason' => $pair,
                ];

                continue;
            }

            foreach ($olds as $candidate) {
                $identityOlds[self::snapshotFingerprint($candidate['snapshot'], false)][] = $candidate;
            }
            foreach ($candidates as $candidate) {
                $identityNews[self::snapshotFingerprint($candidate['snapshot'], false)][] = $candidate;
            }
        }

        // Sisi baru yang tak bertabrakan sidik penuh ikut dikelompokkan
        // identitas agar koreksi vendor tetap ketemu pasangannya.
        foreach ($newByFp as $candidates) {
            foreach ($candidates as $candidate) {
                if (in_array((int) $candidate['transaction']->id, $usedNew, true)) {
                    continue;
                }
                $fp = self::snapshotFingerprint($candidate['snapshot'], false);
                $exists = false;
                foreach ($identityNews[$fp] ?? [] as $stored) {
                    if ((int) $stored['transaction']->id === (int) $candidate['transaction']->id) {
                        $exists = true;

                        break;
                    }
                }
                if (! $exists) {
                    $identityNews[$fp][] = $candidate;
                }
            }
        }

        foreach ($identityOlds as $fp => $olds) {
            $candidates = array_filter(
                $identityNews[$fp] ?? [],
                fn (array $candidate): bool => ! in_array((int) $candidate['transaction']->id, $usedNew, true)
            );
            $candidates = array_values($candidates);
            $olds = array_values($olds);
            usort($olds, fn (array $a, array $b): int => strcmp(
                (string) ($a['snapshot']['transaction_date'] ?? '').'#'.$a['transaction']->id,
                (string) ($b['snapshot']['transaction_date'] ?? '').'#'.$b['transaction']->id
            ));
            usort($candidates, fn (array $a, array $b): int => strcmp(
                (string) ($a['snapshot']['transaction_date'] ?? '').'#'.$a['transaction']->id,
                (string) ($b['snapshot']['transaction_date'] ?? '').'#'.$b['transaction']->id
            ));

            if ($olds === [] || count($olds) !== count($candidates)) {
                foreach ($olds as $candidate) {
                    $manual[] = [
                        'transaction_id' => (int) $candidate['transaction']->id,
                        'reason' => 'kandidat identitas sama tak seimbang ('.count($olds).' lama : '.count($candidates).' baru) — butuh telaah manual',
                    ];
                }

                continue;
            }

            foreach ($olds as $index => $old) {
                $pair = $this->buildSnapshotPair($old, $candidates[$index], true);
                if (is_array($pair)) {
                    $usedNew[] = (int) $candidates[$index]['transaction']->id;
                    $relinkable[] = $pair;
                } else {
                    $manual[] = [
                        'transaction_id' => (int) $old['transaction']->id,
                        'reason' => $pair,
                    ];
                }
            }
        }

        foreach ($newByFp as $fp => $candidates) {
            foreach ($candidates as $candidate) {
                if (! in_array((int) $candidate['transaction']->id, $usedNew, true)
                    && ! array_key_exists($fp, $oldByFp)) {
                    $manual[] = [
                        'transaction_id' => (int) $candidate['transaction']->id,
                        'reason' => 'transaksi baru tanpa pasangan lama (snapshot)',
                    ];
                }
            }
        }

        return ['snapshot_relinkable' => $relinkable, 'snapshot_manual' => $manual];
    }

    /**
     * Pasangan via nomor pesanan Siplah yang sama persis (identitas
     * pembelian unik). Terkuat setelah sidik isi: nomor pesanan tak
     * berubah saat BKU dibuat ulang. Guard jumlah rincian; beda tanggal
     * atau tanggal lama tak diketahui → flag review.
     *
     * @param  array<int, int>  $oldPoolIds
     * @param  array<int, int>  $skipNewIds
     * @return array{order_relinkable: array<int, array<string, mixed>>, order_manual: array<int, array<string, mixed>>, used_new: array<int, int>}
     */
    public function previewOrderPairs(int $fiscalYearId, int $fundSourceId, array $oldPoolIds = [], array $skipNewIds = []): array
    {
        $relinkable = [];
        $manual = [];
        $usedNew = [];

        $missingQuery = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'SOURCE_MISSING')
            ->whereNotNull('siplah_order_number');
        if ($oldPoolIds !== []) {
            $missingQuery->whereIn('id', $oldPoolIds);
        }
        $missing = $missingQuery->get();

        $newQuery = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'ACTIVE')
            ->whereNotNull('siplah_order_number')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('spj_packages')->whereColumn('spj_packages.transaction_id', 'transactions.id');
            });
        if ($skipNewIds !== []) {
            $newQuery->whereNotIn('id', $skipNewIds);
        }
        $news = $newQuery->get();

        $newByOrder = [];
        foreach ($news as $row) {
            $key = mb_strtoupper(trim((string) $row->siplah_order_number));
            if ($key === '') {
                continue;
            }
            $newByOrder[$key][] = $row;
        }

        foreach ($missing as $row) {
            $key = mb_strtoupper(trim((string) $row->siplah_order_number));
            $candidates = array_filter(
                $newByOrder[$key] ?? [],
                fn (object $candidate): bool => ! in_array((int) $candidate->id, $usedNew, true)
            );
            $candidates = array_values($candidates);
            if (count($candidates) !== 1) {
                continue;
            }
            $new = $candidates[0];

            $oldCount = DB::connection('school')->table('transaction_items')->where('transaction_id', $row->id)->count();
            $newIds = DB::connection('school')->table('transaction_items')->where('transaction_id', $new->id)->orderBy('id')
                ->pluck('source_item_id')->map(static fn ($id): string => (string) $id)->all();
            if ($oldCount === 0 || $oldCount !== count($newIds)) {
                $manual[] = [
                    'transaction_id' => (int) $row->id,
                    'reason' => 'nomor pesanan sama tetapi jumlah rincian beda ('.$oldCount.' vs '.count($newIds).') — butuh telaah manual',
                ];

                continue;
            }

            $newSnapshot = $this->rebuiltSnapshot($new);
            if ($newSnapshot === null) {
                $manual[] = [
                    'transaction_id' => (int) $row->id,
                    'reason' => 'rincian baru tak termuat di mirror — butuh telaah manual',
                ];

                continue;
            }
            $oldSnapshot = $this->lastContentSnapshot((int) $row->id);
            $oldDate = $oldSnapshot['transaction_date'] ?? null;
            $newDate = $newSnapshot['transaction_date'] ?? null;
            $needsReview = $oldDate === null
                || trim((string) $oldDate) !== trim((string) $newDate);

            $oldItems = DB::connection('school')->table('transaction_items')->where('transaction_id', $row->id)->orderBy('id')->get();
            $usedNew[] = (int) $new->id;
            $relinkable[] = [
                'old' => [
                    'transaction' => $row,
                    'items' => $oldItems->map(static fn (object $item): array => ['item' => $item, 'fp' => ''])->all(),
                    'first_date' => $oldDate,
                ],
                'new' => [
                    'transaction' => $new,
                    'ids' => $newIds,
                    'first_date' => $newDate,
                ],
                'via_snapshot' => true,
                'explicit' => false,
                'snapshot_fp' => self::snapshotFingerprint($newSnapshot),
                'snapshot_review' => $needsReview,
                'order_match' => trim((string) $row->siplah_order_number),
            ];
        }

        return ['order_relinkable' => $relinkable, 'order_manual' => $manual, 'used_new' => $usedNew];
    }

    /**
     * Bangun satu pasangan snapshot (guard jumlah rincian + tanggal sama).
     * Mengembalikan pasangan atau string alasan manual.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>|string
     */
    private function buildSnapshotPair(array $old, array $new, bool $needsReview): array|string
    {
        $oldItemIds = DB::connection('school')->table('transaction_items')
            ->where('transaction_id', $old['transaction']->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $newItemIds = DB::connection('school')->table('transaction_items')
            ->where('transaction_id', $new['transaction']->id)
            ->orderBy('id')
            ->pluck('source_item_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        if ($oldItemIds === [] || count($oldItemIds) !== count($newItemIds)) {
            return 'jumlah rincian lama ('.count($oldItemIds).') vs baru ('.count($newItemIds).') berbeda — butuh telaah manual';
        }
        if (trim((string) ($old['snapshot']['transaction_date'] ?? '')) !== trim((string) ($new['snapshot']['transaction_date'] ?? ''))) {
            return 'tanggal berubah ('.($old['snapshot']['transaction_date'] ?? '-').' → '.($new['snapshot']['transaction_date'] ?? '-').') — butuh telaah manual';
        }

        return [
            'old' => [
                'transaction' => $old['transaction'],
                'items' => array_map(static fn (int $id): array => ['item' => (object) ['id' => $id], 'fp' => ''],
                    array_map(intval(...), $oldItemIds)),
                'snapshot' => $old['snapshot'],
                'first_date' => $old['snapshot']['transaction_date'] ?? null,
            ],
            'new' => [
                'transaction' => $new['transaction'],
                'ids' => $newItemIds,
                'snapshot' => $new['snapshot'],
                'first_date' => $new['snapshot']['transaction_date'] ?? null,
            ],
            'via_snapshot' => true,
            'snapshot_fp' => self::snapshotFingerprint($new['snapshot']),
            'snapshot_review' => $needsReview,
        ];
    }

    /**
     * Saran kandidat pasangan untuk sisi lama yang tak terpasangkan otomatis
     * (read-only). Skor = kelangkaan kata bersama antara overlay operator
     * lama (uraian/vendor/penerima) dan isi mirror baru, plus bonus
     * kecocokan akun/tanggal/nominal bila snapshot lama tersedia.
     *
     * @return array<int, array<int, array<string, mixed>>> old_id => saran
     */
    public function suggestPairs(int $fiscalYearId, int $fundSourceId, int $maxPerOld = 5): array
    {
        $missing = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'SOURCE_MISSING')
            ->orderBy('id')
            ->get();
        $news = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_status', 'ACTIVE')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('spj_packages')->whereColumn('spj_packages.transaction_id', 'transactions.id');
            })
            ->orderBy('id')
            ->get();

        $tokenize = static fn (?string $value): array => array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9]+/', mb_strtolower((string) $value)) ?: [],
            static fn (string $word): bool => mb_strlen($word) > 3
        )));

        $newTexts = [];
        $docFreq = [];
        foreach ($news as $row) {
            $texts = [];
            foreach ($this->newMirrorTexts((int) $row->id) as $text) {
                $texts[] = $text;
            }
            $words = $tokenize(implode(' ', $texts));
            $newTexts[(int) $row->id] = ['transaction' => $row, 'words' => $words, 'snapshot' => $this->rebuiltSnapshot($row)];
            foreach (array_unique($words) as $word) {
                $docFreq[$word] = ($docFreq[$word] ?? 0) + 1;
            }
        }

        $suggestions = [];
        foreach ($missing as $row) {
            $oldId = (int) $row->id;
            $oldSnapshot = $this->lastContentSnapshot($oldId);
            $oldText = implode(' ', [
                (string) ($row->payment_description ?? ''),
                (string) ($row->vendor_name ?? ''),
                (string) ($row->spj_recipient_name ?? ''),
                (string) ($row->receipt_recipient_name ?? ''),
                $oldSnapshot ? implode(' ', [
                    (string) ($oldSnapshot['description'] ?? ''),
                    (string) ($oldSnapshot['activity_name'] ?? ''),
                    (string) ($oldSnapshot['account_name'] ?? ''),
                    (string) ($oldSnapshot['recipient_name'] ?? ''),
                ]) : '',
            ]);
            $oldWords = $tokenize($oldText);

            $ranked = [];
            foreach ($newTexts as $newId => $candidate) {
                $shared = array_values(array_intersect($oldWords, $candidate['words']));
                if ($shared === []) {
                    continue;
                }
                $score = 0.0;
                foreach ($shared as $word) {
                    $score += 1.0 / (float) ($docFreq[$word] ?? 1);
                }
                $bonus = [];
                $newSnapshot = $candidate['snapshot'];
                if ($oldSnapshot !== null && is_array($newSnapshot)) {
                    if (trim((string) ($oldSnapshot['account_code'] ?? '')) !== ''
                        && trim((string) ($oldSnapshot['account_code'] ?? '')) === trim((string) ($newSnapshot['account_code'] ?? ''))) {
                        $score += 2.0;
                        $bonus[] = 'akun sama';
                    }
                    if (trim((string) ($oldSnapshot['transaction_date'] ?? '')) !== ''
                        && trim((string) ($oldSnapshot['transaction_date'] ?? '')) === trim((string) ($newSnapshot['transaction_date'] ?? ''))) {
                        $score += 1.0;
                        $bonus[] = 'tanggal sama';
                    }
                    if (is_numeric($oldSnapshot['gross_amount'] ?? null) && is_numeric($newSnapshot['gross_amount'] ?? null)
                        && (float) $oldSnapshot['gross_amount'] === (float) $newSnapshot['gross_amount']) {
                        $score += 2.0;
                        $bonus[] = 'nominal sama';
                    }
                }
                $ranked[] = [
                    'new_id' => $newId,
                    'score' => round($score, 3),
                    'shared' => $shared,
                    'bonus' => $bonus,
                    'date' => $newSnapshot['transaction_date'] ?? null,
                    'desc' => $newSnapshot['description'] ?? null,
                    'gross' => $newSnapshot['gross_amount'] ?? null,
                ];
            }
            usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $suggestions[$oldId] = array_slice($ranked, 0, max(1, $maxPerOld));
        }

        return $suggestions;
    }

    /**
     * Teks mirror (URAIAN + NAMA_TOKO + NO_BUKTI) untuk seluruh rincian satu
     * transaksi baru. Dipakai pembobotan saran pasangan.
     *
     * @return array<int, string>
     */
    private function newMirrorTexts(int $transactionId): array
    {
        $texts = [];
        $resolver = app(ArkasMirrorResolver::class);
        $items = DB::connection('school')->table('transaction_items')
            ->where('transaction_id', $transactionId)
            ->orderBy('id')
            ->get();
        foreach ($items as $item) {
            $payload = $resolver->kasUmum((string) $item->source_item_id);
            if (! is_array($payload)) {
                continue;
            }
            $texts[] = implode(' ', [
                (string) (ArkasMirrorResolver::field($payload, ['URAIAN']) ?? ''),
                (string) (ArkasMirrorResolver::field($payload, ['NAMA_TOKO']) ?? ''),
                (string) (ArkasMirrorResolver::field($payload, ['NO_BUKTI']) ?? ''),
            ]);
        }

        return $texts;
    }

    /**
     * Bangun pasangan eksplisit pilihan operator (--pair OLD:NEW).
     * Validasi ketat: scope FY+dana sama, lama masih missing, baru masih
     * aktif tanpa paket, jumlah rincian sama dan > 0.
     *
     * @return array<string, mixed> pasangan siap executePairs (selalu review)
     */
    public function buildExplicitPair(int $fiscalYearId, int $fundSourceId, int $oldId, int $newId): array
    {
        $old = DB::connection('school')->table('transactions')->where('id', $oldId)->first();
        $new = DB::connection('school')->table('transactions')->where('id', $newId)->first();
        if (! $old || (int) $old->fiscal_year_id !== $fiscalYearId || (int) $old->fund_source_id !== $fundSourceId) {
            throw new \RuntimeException("transaksi lama #{$oldId} tidak ada pada scope FY+dana aktif");
        }
        if (! $new) {
            throw new \RuntimeException("transaksi baru #{$newId} tidak ada");
        }
        if ((int) $new->fiscal_year_id !== $fiscalYearId || (int) $new->fund_source_id !== $fundSourceId) {
            throw new \RuntimeException("transaksi baru #{$newId} beda scope FY+dana");
        }
        if ($old->source_status !== 'SOURCE_MISSING') {
            throw new \RuntimeException("transaksi lama #{$oldId} tidak lagi missing ({$old->source_status})");
        }
        if ($new->source_status !== 'ACTIVE') {
            throw new \RuntimeException("transaksi baru #{$newId} tidak aktif ({$new->source_status})");
        }
        if ($this->newHasPackage($newId)) {
            throw new \RuntimeException("transaksi baru #{$newId} sudah memiliki paket");
        }

        $oldCount = DB::connection('school')->table('transaction_items')->where('transaction_id', $oldId)->count();
        $newIds = DB::connection('school')->table('transaction_items')->where('transaction_id', $newId)->orderBy('id')
            ->pluck('source_item_id')->map(static fn ($id): string => (string) $id)->all();
        if ($oldCount === 0 || $oldCount !== count($newIds)) {
            throw new \RuntimeException("jumlah rincian lama ({$oldCount}) vs baru (".count($newIds).') berbeda');
        }

        $oldItems = DB::connection('school')->table('transaction_items')->where('transaction_id', $oldId)->orderBy('id')->get();

        return [
            'old' => [
                'transaction' => $old,
                'items' => $oldItems->map(static fn (object $item): array => ['item' => $item, 'fp' => ''])->all(),
                'first_date' => null,
            ],
            'new' => [
                'transaction' => $new,
                'ids' => $newIds,
                'first_date' => null,
            ],
            'via_snapshot' => true,
            'explicit' => true,
            'snapshot_fp' => '',
            'snapshot_review' => true,
        ];
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
                    $newRows = [];
                    foreach (DB::connection('school')->table('transaction_items')->where('transaction_id', $newId)->orderBy('id')->get() as $position => $newItem) {
                        $newItemId = (string) $newItem->source_item_id;
                        $newRowsById[$newItemId] = $newItem;
                        $newRows[] = ['id' => $newItemId, 'fp' => (string) ($new['fps'][$position] ?? '')];
                    }
                    $repoint = $this->repointItems($old['items'], $newRows, $runId, $newRowsById);
                    if ($repoint['skipped'] !== []) {
                        $reasons = [];
                        foreach ($repoint['skipped'] as $oldItemId => $reason) {
                            $reasons[] = 'item #'.$oldItemId.': '.$reason;
                        }
                        throw new \RuntimeException('re-point rincian tidak lengkap berbasis fingerprint: '.implode('; ', $reasons));
                    }

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

                    $this->audit->record($fiscalYearId, 'TRANSACTION', $oldId, 'SOURCE_RELINK', 'Transaksi #'.$oldId.' di-relink ke identitas BKU baru (duplikat #'.$newId.' dihapus).'.(($pair['explicit'] ?? false) ? ' Pasangan manual pilihan operator.' : ''));
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
     * Pairing berbasis fingerprint isi per item (lihat
     * pairItemsByFingerprint()): baris yang sidiknya tidak cocok/tidak
     * ditemukan TIDAK dipasangkan diam-diam — dilewati dan dilaporkan.
     *
     * @param  array<int, array{item: object, fp: string}>  $oldRows  berurutan ID
     * @param  array<int, array{id: string, fp: string}>    $newRows  berurutan ID
     * @param  array<string, object>  $newRowsById  baris item baru (command repair; kosong pada jalur sync)
     * @return array{paired: int, skipped: array<int, string>}  old_item_id => alasan
     */
    private function repointItems(array $oldRows, array $newRows, ?int $runId, array $newRowsById = []): array
    {
        $pairing = self::pairItemsByFingerprint($oldRows, $newRows);
        $paired = 0;

        foreach ($oldRows as $entry) {
            $oldItemId = (int) $entry['item']->id;
            if (! array_key_exists($oldItemId, $pairing['paired'])) {
                continue;
            }
            $newId = $pairing['paired'][$oldItemId];
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
            DB::connection('school')->table('transaction_items')->where('id', $oldItemId)->update($update);
            $paired++;
        }

        return ['paired' => $paired, 'skipped' => $pairing['skipped']];
    }

    /**
     * Pasangkan baris item lama ke ID sumber baru berbasis fingerprint isi.
     *
     * Baris lama dipasangkan ke baris baru dengan sidik isi yang sama;
     * baris kembar isi-identik dipasangkan deterministik berurutan ID di
     * dalam bucket sidik yang sama. Baris yang sidiknya tidak cocok/tidak
     * ditemukan dilewati dengan alasan tercatat (tidak dipasangkan
     * posisional diam-diam).
     *
     * Pengecualian: bila tidak ada sidik pada kedua sisi (jalur
     * snapshot/perbaikan manual — mirror lama sudah di-prune), kesetaraan
     * sudah dibuktikan di level snapshot + jumlah rincian oleh pemanggil,
     * sehingga pairing kembali ke urutan deterministik lama.
     *
     * @param  array<int, array{item: object, fp: string}>  $oldRows
     * @param  array<int, array{id: string, fp: string}>    $newRows
     * @return array{paired: array<int, string>, skipped: array<int, string>}  old_item_id => new_id / alasan
     */
    public static function pairItemsByFingerprint(array $oldRows, array $newRows): array
    {
        $paired = [];
        $skipped = [];

        $fpsAvailable = false;
        foreach ([$oldRows, $newRows] as $rows) {
            foreach ($rows as $entry) {
                if (filled($entry['fp'] ?? '')) {
                    $fpsAvailable = true;
                    break 2;
                }
            }
        }

        if (! $fpsAvailable) {
            foreach ($oldRows as $index => $entry) {
                $newId = $newRows[$index]['id'] ?? null;
                if ($newId === null) {
                    $skipped[(int) $entry['item']->id] = 'tidak ada baris baru pada posisi '.$index.' — tidak dipasangkan';

                    continue;
                }
                $paired[(int) $entry['item']->id] = (string) $newId;
            }

            return ['paired' => $paired, 'skipped' => $skipped];
        }

        $newByFp = [];
        foreach ($newRows as $entry) {
            $newByFp[(string) ($entry['fp'] ?? '')][] = (string) $entry['id'];
        }

        foreach ($oldRows as $entry) {
            $oldItemId = (int) $entry['item']->id;
            $fp = (string) ($entry['fp'] ?? '');
            if ($fp === '' || ($newByFp[$fp] ?? []) === []) {
                $skipped[$oldItemId] = $fp === ''
                    ? 'fingerprint isi tidak tersedia — tidak dipasangkan'
                    : 'tidak ada baris baru dengan fingerprint isi yang sama — tidak dipasangkan';

                continue;
            }
            $paired[$oldItemId] = array_shift($newByFp[$fp]);
        }

        return ['paired' => $paired, 'skipped' => $skipped];
    }

    /** @param  array<string, mixed>  $pair */
    /**
     * Validasi ulang pasangan snapshot sesaat sebelum eksekusi: sisi lama
     * masih missing, sisi baru masih aktif tanpa paket, dan sidik snapshot
     * sisi baru tak berubah. Rincian lama tak bisa disidik ulang (mirror
     * di-prune) sehingga kesetaraan isi dipegang oleh sidik saat preview
     * + jumlah rincian yang sama.
     */
    private function refreshSnapshotPair(array $pair): ?array
    {
        $oldId = (int) $pair['old']['transaction']->id;
        $newId = (int) $pair['new']['transaction']->id;

        $old = DB::connection('school')->table('transactions')->where('id', $oldId)->first();
        $new = DB::connection('school')->table('transactions')->where('id', $newId)->first();
        if (! $old || ! $new || $old->source_status !== 'SOURCE_MISSING' || $new->source_status !== 'ACTIVE') {
            return null;
        }
        if ($this->newHasPackage($newId)) {
            return null;
        }

        $rebuilt = $this->rebuiltSnapshot($new);
        if (! ($pair['explicit'] ?? false)
            && ($rebuilt === null || self::snapshotFingerprint($rebuilt) !== (string) ($pair['snapshot_fp'] ?? ''))) {
            return null;
        }

        $oldItems = DB::connection('school')->table('transaction_items')->where('transaction_id', $oldId)->orderBy('id')->get();
        $newIds = DB::connection('school')->table('transaction_items')->where('transaction_id', $newId)->orderBy('id')
            ->pluck('source_item_id')->map(static fn ($id): string => (string) $id)->all();
        if ($oldItems->isEmpty() || $oldItems->count() !== count($newIds)) {
            return null;
        }

        $oldRows = $oldItems->map(static fn (object $item): array => ['item' => $item, 'fp' => ''])->all();

        return [
            ['transaction' => $old, 'items' => $oldRows, 'first_date' => $pair['old']['first_date'] ?? null, 'first_payload' => [],
                'snapshot_review' => (bool) ($pair['snapshot_review'] ?? false)],
            ['transaction' => $new, 'ids' => $newIds, 'first_date' => $pair['new']['first_date'] ?? null, 'first_payload' => []],
        ];
    }

    private function refreshPair(array $pair): ?array
    {
        if (($pair['via_snapshot'] ?? false) === true) {
            return $this->refreshSnapshotPair($pair);
        }

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
            ['transaction' => $new, 'ids' => $newBuilt['ids'], 'fps' => $newBuilt['fps'], 'first_date' => $newBuilt['first_date'], 'first_payload' => $newBuilt['first_payload']],
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
        // Pasangan snapshot dengan koreksi vendor selalu butuh telaah.
        return (bool) ($old['snapshot_review'] ?? false) || $this->firstDatesDiffer($old, $new);
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
