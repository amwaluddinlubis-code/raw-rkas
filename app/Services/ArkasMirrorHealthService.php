<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Deteksi + perbaikan baris mirror kas basi.
 *
 * ARKAS menerbitkan ulang ID_KAS_UMUM saat pengesahan ulang; tabel
 * arkas_bku_rows dibersihkan setiap sync (whereNotIn) tetapi mirror hanya
 * upsert sehingga baris lama menumpuk dan realisasi menghitung ganda.
 * Kebenaran acuan = arkas_bku_rows per (fiscal_year_id, fund_source_id).
 * Baris mirror yang tidak bisa di-scope (tanpa tahun/tanggal atau dana)
 * hanya dilaporkan, tidak pernah dihapus.
 */
class ArkasMirrorHealthService
{
    /**
     * @return array{scopes: array<int, array{year: int|string, fund: int|string, fiscal_year_id: ?int, mirror_n: int, mirror_sum: float, bku_n: int, bku_sum: float, stale_n: int, stale_sum: float, missing_n: int}>, unscoped_n: int, ok: bool}
     */
    public function check(): array
    {
        $db = DB::connection('school');
        $years = $db->table('fiscal_years')->pluck('year', 'id')->all();

        $truth = [];
        foreach ($db->table('arkas_bku_rows')->get(['fiscal_year_id', 'fund_source_id', 'source_kas_id', 'amount']) as $row) {
            $key = ((int) ($years[$row->fiscal_year_id] ?? 0)).'|'.((int) $row->fund_source_id);
            $truth[$key]['ids'][(string) $row->source_kas_id] = true;
            $truth[$key]['n'] = ($truth[$key]['n'] ?? 0) + 1;
            $truth[$key]['sum'] = ($truth[$key]['sum'] ?? 0) + (float) $row->amount;
            $truth[$key]['fiscal_year_id'] = (int) $row->fiscal_year_id;
        }

        $scopes = [];
        $mirrorIds = [];
        $unscoped = 0;
        foreach ($db->table('arkas_mirror_kas_umum')->get(['source_key', 'payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                $unscoped++;

                continue;
            }
            $year = substr((string) (ArkasMirrorResolver::field($payload, ['TANGGAL_TRANSAKSI', 'tanggal_transaksi']) ?? ''), 0, 4);
            $fund = (string) (ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA', 'id_ref_sumber_dana']) ?? '');
            $kasId = (string) (ArkasMirrorResolver::field($payload, ['ID_KAS_UMUM', 'id_kas_umum']) ?? '');
            if ($year === '' || $fund === '' || $kasId === '') {
                $unscoped++;

                continue;
            }
            $key = ((int) $year).'|'.((int) $fund);
            $scope = &$scopes[$key];
            $scope['year'] = (int) $year;
            $scope['fund'] = (int) $fund;
            $scope['mirror_n'] = ($scope['mirror_n'] ?? 0) + 1;
            $scope['mirror_sum'] = ($scope['mirror_sum'] ?? 0) + (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'jumlah']) ?? 0);
            $mirrorIds[$key][$kasId] = (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'jumlah']) ?? 0);
            unset($scope);
        }

        $ok = true;
        foreach ($scopes as $key => &$scope) {
            $keep = $truth[$key]['ids'] ?? null;
            $scope['fiscal_year_id'] = $truth[$key]['fiscal_year_id'] ?? null;
            $scope['bku_n'] = $truth[$key]['n'] ?? 0;
            $scope['bku_sum'] = $truth[$key]['sum'] ?? 0;
            $scope['stale_n'] = 0;
            $scope['stale_sum'] = 0;
            $scope['missing_n'] = 0;
            if ($keep === null) {
                $scope['missing_n'] = 0;

                continue;
            }
            foreach ($mirrorIds[$key] as $kasId => $amount) {
                if (! isset($keep[$kasId])) {
                    $scope['stale_n']++;
                    $scope['stale_sum'] += $amount;
                }
            }
            foreach ($keep as $kasId => $_) {
                $found = false;
                foreach ($mirrorIds as $ids) {
                    if (isset($ids[$kasId])) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $scope['missing_n']++;
                }
            }
            if ($scope['stale_n'] > 0 || $scope['missing_n'] > 0 || abs($scope['mirror_sum'] - $scope['bku_sum']) > 0.5) {
                $ok = false;
            }
        }
        unset($scope);

        return ['scopes' => array_values($scopes), 'unscoped_n' => $unscoped, 'ok' => $ok];
    }

    /**
     * Hapus baris mirror basi pada scope yang punya acuan bku. Mengembalikan
     * jumlah dan nilai yang dihapus per scope.
     *
     * @return array<int, array{year: int, fund: int, deleted_n: int, deleted_sum: float}>
     */
    public function repair(): array
    {
        $db = DB::connection('school');
        $years = $db->table('fiscal_years')->pluck('year', 'id')->all();

        $keep = [];
        foreach ($db->table('arkas_bku_rows')->get(['fiscal_year_id', 'fund_source_id', 'source_kas_id']) as $row) {
            $key = ((int) ($years[$row->fiscal_year_id] ?? 0)).'|'.((int) $row->fund_source_id);
            $keep[$key][(string) $row->source_kas_id] = true;
        }

        $deleted = [];
        foreach ($db->table('arkas_mirror_kas_umum')->get(['source_key', 'payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $year = substr((string) (ArkasMirrorResolver::field($payload, ['TANGGAL_TRANSAKSI', 'tanggal_transaksi']) ?? ''), 0, 4);
            $fund = (string) (ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA', 'id_ref_sumber_dana']) ?? '');
            $kasId = (string) (ArkasMirrorResolver::field($payload, ['ID_KAS_UMUM', 'id_kas_umum']) ?? '');
            if ($year === '' || $fund === '' || $kasId === '') {
                continue;
            }
            $key = ((int) $year).'|'.((int) $fund);
            if (! isset($keep[$key]) || isset($keep[$key][$kasId])) {
                continue;
            }
            $db->table('arkas_mirror_kas_umum')->where('source_key', $row->source_key)->delete();
            $index = $key;
            $deleted[$index]['year'] = (int) $year;
            $deleted[$index]['fund'] = (int) $fund;
            $deleted[$index]['deleted_n'] = ($deleted[$index]['deleted_n'] ?? 0) + 1;
            $deleted[$index]['deleted_sum'] = ($deleted[$index]['deleted_sum'] ?? 0) + (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'jumlah']) ?? 0);
        }

        if ($deleted !== []) {
            app(ArkasMirrorResolver::class)->forget('arkas_mirror_kas_umum');
        }

        return array_values($deleted);
    }

    /**
     * Salin file database sekolah aktif sebagai backup sebelum repair.
     * Mengembalikan path backup atau null bila sumber tidak tersedia.
     */
    public function backupActiveDatabase(string $npsn): ?string
    {
        $source = (string) config('database.connections.school.database');
        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            return null;
        }
        $dir = storage_path('app/arkas-mirror-backups');
        File::ensureDirectoryExists($dir);
        $target = $dir.DIRECTORY_SEPARATOR.date('Ymd-His').'-'.$npsn.'.sqlite';
        try {
            File::copy($source, $target);
        } catch (\Throwable) {
            return null;
        }

        return $target;
    }
}
