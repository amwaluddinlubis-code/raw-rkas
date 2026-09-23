<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only lookup for the Reference module.
 *
 * Source of truth is the central ARKAS fixed mirror (database pusat):
 * - arkas_mirror_ref_rekening (master rekening)
 * - arkas_mirror_ref_acuan_barang (acuan harga barang)
 *
 * Both tables store the ARKAS payload as JSON; this service projects the
 * fields the operator needs via json_extract so the school database is
 * never written to by reference browsing.
 */
class ReferenceLookupService
{
    public const TAB_ACCOUNTS = 'rekening';

    public const TAB_PRICES = 'harga';

    /**
     * @return array<int, string>
     */
    public function availableYears(): array
    {
        $years = [];

        foreach (['arkas_mirror_ref_rekening', 'arkas_mirror_ref_acuan_barang'] as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $yearColumn = Schema::hasColumn($table, 'sx_tahun')
                    ? 'sx_tahun'
                    : "coalesce(json_extract(payload, '$.tahun'), json_extract(payload, '$.TAHUN'))";
                $rows = DB::table($table)
                    ->selectRaw("{$yearColumn} as tahun")
                    ->distinct()
                    ->pluck('tahun');
            } catch (\Throwable) {
                continue;
            }

            foreach ($rows as $year) {
                $year = trim((string) $year, " \t\n\r\0\x0B\"'");
                if ($year !== '' && ctype_digit($year)) {
                    $years[$year] = $year;
                }
            }
        }

        rsort($years);

        return array_values($years);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateAccounts(?string $year, string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('arkas_mirror_ref_rekening')
            ->selectRaw(implode(', ', [
                'source_key',
                'json_extract(payload, \'$."kode_rekening"\') as kode_rekening',
                'json_extract(payload, \'$."rekening"\') as nama_rekening',
                'json_extract(payload, \'$."tahun"\') as tahun',
                'json_extract(payload, \'$."is_ppn"\') as is_ppn',
                'json_extract(payload, \'$."is_pph21"\') as is_pph21',
                'json_extract(payload, \'$."is_pph22"\') as is_pph22',
                'json_extract(payload, \'$."is_pph23"\') as is_pph23',
                'json_extract(payload, \'$."is_pph4"\') as is_pph4',
                'json_extract(payload, \'$."is_sspd"\') as is_sspd',
                'synced_at',
            ]));
        $hasGenerated = Schema::hasColumn('arkas_mirror_ref_rekening', 'sx_tahun');
        $yearColumn = $hasGenerated ? 'sx_tahun' : "json_extract(payload, '$.\"tahun\"')";
        $codeColumn = Schema::hasColumn('arkas_mirror_ref_rekening', 'sx_kode_rekening') ? 'sx_kode_rekening' : "json_extract(payload, '$.\"kode_rekening\"')";
        $nameColumn = Schema::hasColumn('arkas_mirror_ref_rekening', 'sx_rekening') ? 'sx_rekening' : "json_extract(payload, '$.\"rekening\"')";

        if ($year !== null && $year !== '') {
            $query->whereRaw("{$yearColumn} = ?", [$year]);
        }

        $search = trim($search);
        if ($search !== '') {
            $query->where(function ($inner) use ($search, $codeColumn, $nameColumn): void {
                $like = '%'.$search.'%';
                $inner->whereRaw("{$codeColumn} LIKE ?", [$like])
                    ->orWhereRaw("{$nameColumn} LIKE ?", [$like]);
            });
        }

        $query->orderByRaw("{$codeColumn} ASC");

        try {
            return $query->paginate($perPage)->withQueryString();
        } catch (\Throwable) {
            return new ConcretePaginator([], 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginatePriceReferences(?string $year, string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $hasGenerated = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_tahun');
        $yearColumn = $hasGenerated ? 'sx_tahun' : "json_extract(payload, '$.\"tahun\"')";
        $codeColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_kode_rekening') ? 'sx_kode_rekening' : "json_extract(payload, '$.\"kode_rekening\"')";
        $nameColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_nama_barang') ? 'sx_nama_barang' : "json_extract(payload, '$.\"nama_barang\"')";
        $unitColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_satuan') ? 'sx_satuan' : "json_extract(payload, '$.\"satuan\"')";
        $priceColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_harga_barang') ? 'sx_harga_barang' : "json_extract(payload, '$.\"harga_barang\"')";
        $capColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_batas_atas') ? 'sx_batas_atas' : "json_extract(payload, '$.\"batas_atas\"')";
        $idColumn = Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_id_barang') ? 'sx_id_barang' : "json_extract(payload, '$.\"id_barang\"')";

        // Satu baris per nama+satuan: master ARKAS memuat beberapa kode
        // barang untuk nama yang sama sehingga daftar mentah terlihat ganda.
        $grouped = DB::table('arkas_mirror_ref_acuan_barang')
            ->selectRaw(implode(', ', [
                "{$nameColumn} as nama_barang",
                "{$unitColumn} as satuan",
                "MIN(CAST({$priceColumn} AS REAL)) as harga_min",
                "MAX(CAST({$priceColumn} AS REAL)) as harga_max",
                'COUNT(*) as varian',
                "MIN({$codeColumn}) as kode_rekening",
                "MAX(CAST({$capColumn} AS REAL)) as batas_atas",
                "GROUP_CONCAT(DISTINCT {$idColumn}) as kode_list",
            ]));

        if ($year !== null && $year !== '') {
            $grouped->whereRaw("{$yearColumn} = ?", [$year]);
        }

        $search = trim($search);
        if ($search !== '') {
            $grouped->where(function ($inner) use ($search, $nameColumn, $codeColumn, $idColumn): void {
                $like = '%'.$search.'%';
                $inner->whereRaw("{$nameColumn} LIKE ?", [$like])
                    ->orWhereRaw("{$codeColumn} LIKE ?", [$like])
                    ->orWhereRaw("{$idColumn} LIKE ?", [$like]);
            });
        }

        $grouped->groupByRaw("{$nameColumn}, {$unitColumn}");

        try {
            return DB::query()->fromSub($grouped, 'grup')
                ->orderBy('nama_barang')
                ->paginate($perPage)->withQueryString();
        } catch (\Throwable) {
            return new ConcretePaginator([], 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }
    }
}
