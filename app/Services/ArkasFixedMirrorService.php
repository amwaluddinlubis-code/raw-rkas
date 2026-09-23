<?php

namespace App\Services;

use App\Models\ArkasSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArkasFixedMirrorService
{
    public function __construct(
        private readonly ArkasBridgeClient $bridge,
        private readonly OperationalAuditService $audit,
    ) {}

    /**
     * Daftar tabel tetap: 13 pusat + 17 sekolah.
     *
     * @return array<int, array{source: string, mirror: string, keys: array<int, string>, label: string, connection: string}>
     */
    public static function registry(): array
    {
        $items = [];

        foreach (config('arkas_mirror.central', []) as $entry) {
            $items[] = $entry + ['connection' => 'central'];
        }

        foreach (config('arkas_mirror.school', []) as $entry) {
            $items[] = $entry + ['connection' => 'school'];
        }

        return $items;
    }

    /**
     * Ambil satu baris mirror tanpa menduplikasi kolom ke tabel aplikasi.
     * Overlay cukup memanggil helper ini.
     */
    public static function find(string $mirror, string $sourceKey, string $connection = 'school'): ?array
    {
        $row = DB::connection($connection === 'central' ? null : 'school')
            ->table($mirror)
            ->where('source_key', $sourceKey)
            ->first();

        if (! $row) {
            return null;
        }

        $payload = json_decode((string) $row->payload, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>}
     */
    public function syncAll(ArkasSource $source, ?int $fiscalYearId = null, ?\Closure $onProgress = null): array
    {
        $centralTables = config('arkas_mirror.central', []);
        $schoolTables = config('arkas_mirror.school', []);
        $total = count($centralTables) + count($schoolTables);
        $done = 0;
        $forward = static function () use (&$done, $total, $onProgress): void {
            $done++;
            if ($onProgress) {
                $onProgress($done, $total, '');
            }
        };

        $central = $this->syncCentral($source, $forward);
        $school = $this->syncSchool($source, $fiscalYearId, $forward);

        return $this->mergeSummaries($central, $school);
    }

    /**
     * Mirror referensi: 13 tabel ref_* ke database pusat. Cukup dijalankan
     * sekali (sekolah pertama); sekolah berikutnya tidak perlu mengulang.
     *
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>}
     */
    public function syncCentral(ArkasSource $source, ?\Closure $onProgress = null): array
    {
        $lock = Cache::lock('arkas-fixed-mirror-refs', 900);

        if (! $lock->get()) {
            throw new \RuntimeException('Mirror referensi ARKAS sedang berjalan. Tunggu sampai proses sebelumnya selesai.');
        }

        try {
            $summary = $this->emptySummary();
            $tables = config('arkas_mirror.central', []);

            foreach ($tables as $index => $entry) {
                $this->accumulate($summary, $entry, $this->syncTable($source, $entry + ['connection' => 'central']));
                if ($onProgress) {
                    $onProgress($index + 1, count($tables), $entry['source']);
                }
            }

            $source->forceFill(['last_synced_at' => now()])->save();

            return $summary;
        } finally {
            $lock->release();
        }
    }

    /**
     * Mirror sekolah: 17 tabel operasional ke database sekolah aktif.
     * Dijalankan per sekolah (termasuk sekolah pertama, setelah referensi).
     *
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>}
     */
    public function syncSchool(ArkasSource $source, ?int $fiscalYearId = null, ?\Closure $onProgress = null): array
    {
        $lock = Cache::lock('arkas-fixed-mirror:'.$source->school_id, 900);

        if (! $lock->get()) {
            throw new \RuntimeException('Mirror ARKAS sedang berjalan untuk sekolah ini. Tunggu sampai proses sebelumnya selesai.');
        }

        try {
            $summary = $this->emptySummary();
            $tables = config('arkas_mirror.school', []);

            foreach ($tables as $index => $entry) {
                $this->accumulate($summary, $entry, $this->syncTable($source, $entry + ['connection' => 'school']));
                if ($onProgress) {
                    $onProgress($index + 1, count($tables), $entry['source']);
                }
            }

            $source->forceFill(['last_synced_at' => now()])->save();

            $this->audit->record(
                $fiscalYearId,
                'arkas_fixed_mirror',
                (string) $source->school_id,
                'ARKAS_MIRROR_SYNC',
                'Mirror sekolah ARKAS selesai: '.$summary['read'].' dibaca, '.$summary['written'].' ditulis ('.$summary['new'].' baru, '.$summary['changed'].' berubah).'
            );

            return $summary;
        } finally {
            $lock->release();
        }
    }

    /** @return array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>} */
    private function emptySummary(): array
    {
        return ['read' => 0, 'written' => 0, 'new' => 0, 'changed' => 0, 'unchanged' => 0, 'tables' => []];
    }

    /**
     * @param  array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>  $summary
     * @param  array{source: string}  $entry
     * @param  array{read: int, written: int, new: int, changed: int, unchanged: int}  $stats
     */
    private function accumulate(array &$summary, array $entry, array $stats): void
    {
        $summary['read'] += $stats['read'];
        $summary['written'] += $stats['written'];
        $summary['new'] += $stats['new'];
        $summary['changed'] += $stats['changed'];
        $summary['unchanged'] += $stats['unchanged'];
        $summary['tables'][$entry['source']] = $stats;
    }

    /** @param  array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array<string, mixed}>}  $a */
    private function mergeSummaries(array $a, array $b): array
    {
        return [
            'read' => $a['read'] + $b['read'],
            'written' => $a['written'] + $b['written'],
            'new' => $a['new'] + $b['new'],
            'changed' => $a['changed'] + $b['changed'],
            'unchanged' => $a['unchanged'] + $b['unchanged'],
            'tables' => $a['tables'] + $b['tables'],
        ];
    }

    /**
     * Upsert satu baris mirror (dipakai sync kanonik agar mirror selalu
     * terisi dari jalur sync mana pun, bukan hanya importer tetap).
     *
     * @param  array<string, mixed>  $payload
     * @return bool true bila baris baru dibuat atau payload berubah
     */
    public function upsertMirrorRow(string $connection, string $mirror, string $sourceKey, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $hash = hash('sha256', (string) $json);
        $now = now();
        $db = DB::connection($connection === 'central' ? null : 'school');

        $existing = $db->table($mirror)->where('source_key', $sourceKey)->first();

        if (! $existing) {
            $db->table($mirror)->insert([
                'source_key' => $sourceKey,
                'payload' => $json,
                'source_hash' => $hash,
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->forgetMirrorCache($mirror, $sourceKey);

            return true;
        }

        if (($existing->source_hash ?? null) !== $hash) {
            $db->table($mirror)->where('source_key', $sourceKey)->update([
                'payload' => $json,
                'source_hash' => $hash,
                'synced_at' => $now,
                'updated_at' => $now,
            ]);
            $this->forgetMirrorCache($mirror, $sourceKey);

            return true;
        }

        return false;
    }

    /**
     * Batalkan cache resolver untuk baris mirror yang baru ditulis agar
     * pembaca berikutnya dalam request yang sama melihat data terbaru.
     * Tidak boleh menggagalkan sinkronisasi bila resolver tidak tersedia.
     */
    private function forgetMirrorCache(string $mirror, ?string $sourceKey = null): void
    {
        try {
            app(ArkasMirrorResolver::class)->forget($mirror, $sourceKey);
        } catch (\Throwable) {
            // abaikan: tulis mirror tetap sah tanpa invalidasi cache
        }
    }

    /**
     * @param  array{source: string, mirror: string, keys: array<int, string>, connection: string}  $entry
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int}
     */
    public function syncTable(ArkasSource $source, array $entry): array
    {
        if ($entry['source'] === 'kas_umum') {
            return $this->syncKasUmumEnriched($source, $entry);
        }

        $output = $this->bridge->execute($source, 'rows', null, $entry['source'], null, 100000);
        $records = ArkasPipePayload::decode($output, 'rows:'.$entry['source']);

        return $this->upsertRecords($entry, $records);
    }

    /**
     * kas_umum wajib memakai payload enriched perintah `bku` (ada
     * KATEGORI_BKU/JUMLAH/NAMA_TOKO/flag pajak) per tahun anggaran,
     * karena perintah `rows` mentah tidak membawa kolom-kolom itu.
     *
     * @param  array{source: string, mirror: string, keys: array<int, string>, connection: string}  $entry
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int}
     */
    private function syncKasUmumEnriched(ArkasSource $source, array $entry): array
    {
        $stats = ['read' => 0, 'written' => 0, 'new' => 0, 'changed' => 0, 'unchanged' => 0];

        foreach ($this->sourceYears($source) as $year) {
            try {
                $output = $this->bridge->execute($source, 'bku', $year, null, null, 100000);
            } catch (\Throwable) {
                continue;
            }

            $records = ArkasPipePayload::decode($output, 'bku:'.$year);
            $partial = $this->upsertRecords($entry, $records);

            foreach (['read', 'written', 'new', 'changed', 'unchanged'] as $key) {
                $stats[$key] += $partial[$key];
            }
        }

        Log::info('ARKAS fixed mirror table synced.', [
            'source' => $entry['source'],
            'mirror' => $entry['mirror'],
            'read' => $stats['read'],
            'written' => $stats['written'],
        ]);

        return $stats;
    }

    /** @return array<int, int> */
    private function sourceYears(ArkasSource $source): array
    {
        $years = [];

        try {
            $anggaran = DB::connection('school')->table('arkas_mirror_anggaran')->pluck('payload');
            foreach ($anggaran as $payload) {
                $row = json_decode((string) $payload, true);
                $year = (int) (ArkasMirrorResolver::field(is_array($row) ? $row : [], ['TAHUN_ANGGARAN']) ?? 0);
                if ($year > 2000) {
                    $years[$year] = $year;
                }
            }
        } catch (\Throwable) {
            // fallback ke tahun kalender berjalan di bawah
        }

        if ($years === []) {
            $years[(int) now()->year] = (int) now()->year;
        }

        sort($years);

        return array_values($years);
    }

    /**
     * @param  array{source: string, mirror: string, keys: array<int, string>, connection: string}  $entry
     * @param  array<int, array<string, mixed>>  $records
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int}
     */
    private function upsertRecords(array $entry, array $records): array
    {
        $connection = $entry['connection'] === 'central' ? null : 'school';
        $db = DB::connection($connection);

        $stats = ['read' => count($records), 'written' => 0, 'new' => 0, 'changed' => 0, 'unchanged' => 0];
        $now = now();

        $db->transaction(function () use ($db, $entry, $records, $now, &$stats): void {
            foreach ($records as $record) {
                $key = $this->sourceKey($record, $entry['keys']);

                if ($key === '') {
                    continue;
                }

                $payload = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $hash = hash('sha256', (string) $payload);
                $existing = $db->table($entry['mirror'])->where('source_key', $key)->first();

                if (! $existing) {
                    $db->table($entry['mirror'])->insert([
                        'source_key' => $key,
                        'payload' => $payload,
                        'source_hash' => $hash,
                        'synced_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $stats['new']++;
                    $stats['written']++;

                    continue;
                }

                if (($existing->source_hash ?? null) !== $hash) {
                    $db->table($entry['mirror'])->where('source_key', $key)->update([
                        'payload' => $payload,
                        'source_hash' => $hash,
                        'synced_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $stats['changed']++;
                    $stats['written']++;

                    continue;
                }

                $stats['unchanged']++;
            }
        });

        if ($stats['written'] > 0) {
            $this->forgetMirrorCache($entry['mirror']);
        }

        if ($entry['source'] !== 'kas_umum') {
            Log::info('ARKAS fixed mirror table synced.', [
                'source' => $entry['source'],
                'mirror' => $entry['mirror'],
                'read' => $stats['read'],
                'written' => $stats['written'],
            ]);
        }

        return $stats;
    }

    /** @param  array<int, string>  $keyColumns */
    private function sourceKey(array $record, array $keyColumns): string
    {
        $lookup = [];
        foreach ($record as $column => $value) {
            $lookup[strtolower((string) $column)] = trim((string) $value);
        }

        $parts = [];
        foreach ($keyColumns as $column) {
            $parts[] = $lookup[strtolower($column)] ?? '';
        }

        $key = implode('|', $parts);

        if (trim($key, '|') === '') {
            return hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }

        return mb_substr($key, 0, 255);
    }
}
