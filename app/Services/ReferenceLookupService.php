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

    public const TAB_ACTIVITIES = 'kegiatan';

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

        // Fallback ke hierarki kegiatan saat dua tabel referensi pusat belum
        // dipakai atau belum tersinkron.
        try {
            if (Schema::hasTable('activity_hierarchy_references')) {
                foreach (DB::table('fiscal_years')->get(['id', 'year']) as $fy) {
                    $has = DB::table('activity_hierarchy_references')->where('fiscal_year_id', $fy->id)->exists();
                    if ($has) {
                        $years[(string) $fy->year] = (string) $fy->year;
                    }
                }
            }
        } catch (\Throwable) {
            // abaikan
        }

        rsort($years);

        return array_values($years);
    }

    public function paginateActivities(?string $year, string $search = '', int $perPage = 15, string $programFilter = '', string $subFilter = '', string $kegiatanFilter = ''): LengthAwarePaginator
    {
        try {
            $programs = [];
            $subs = [];
            foreach (DB::table('arkas_mirror_ref_kode')->cursor(['payload']) as $row) {
                $p = json_decode((string) ($row->payload ?? ''), true);
                if (! is_array($p)) {
                    continue;
                }
                $level = (string) ($p['id_level_kode'] ?? '');
                if ($level === '1') {
                    $programs[(string) ($p['id_ref_kode'] ?? '')] = $p;
                } elseif ($level === '2') {
                    $subs[(string) ($p['id_ref_kode'] ?? '')] = $p;
                }
            }

            $rows = [];
            $search = trim($search);
            $like = $search !== '' ? mb_strtolower($search) : null;
            $seen = [];
            foreach (DB::table('arkas_mirror_ref_kode')->cursor(['payload']) as $row) {
                $p = json_decode((string) ($row->payload ?? ''), true);
                if (! is_array($p) || (string) ($p['id_level_kode'] ?? '') !== '3') {
                    continue;
                }
                if ($year !== null && $year !== '' && (string) ($p['tahun'] ?? '') !== $year) {
                    continue;
                }
                $sub = $subs[(string) ($p['parent_kode'] ?? '')] ?? null;
                $program = $sub !== null ? ($programs[(string) ($sub['parent_kode'] ?? '')] ?? null) : null;
                if ($program === null || $sub === null) {
                    continue;
                }
                if ($programFilter !== '' && (string) ($program['id_kode'] ?? '') !== $programFilter) {
                    continue;
                }
                if ($subFilter !== '' && (string) ($sub['id_kode'] ?? '') !== $subFilter) {
                    continue;
                }
                if ($kegiatanFilter !== '' && (string) ($p['id_kode'] ?? '') !== $kegiatanFilter) {
                    continue;
                }

                $dedupKey = ($program['id_kode'] ?? '').'|'.($sub['id_kode'] ?? '').'|'.($p['id_kode'] ?? '');
                if (isset($seen[$dedupKey])) {
                    continue;
                }
                $seen[$dedupKey] = true;
                if ($like !== null) {
                    $hay = mb_strtolower(($program['id_kode'] ?? '').' '.($program['uraian_kode'] ?? '').' '.($sub['id_kode'] ?? '').' '.($sub['uraian_kode'] ?? '').' '.($p['id_kode'] ?? '').' '.($p['uraian_kode'] ?? ''));
                    if (! str_contains($hay, $like)) {
                        continue;
                    }
                }
                $rows[] = (object) [
                    'program_code' => (string) ($program['id_kode'] ?? ''),
                    'program_name' => (string) ($program['uraian_kode'] ?? ''),
                    'sub_program_code' => (string) ($sub['id_kode'] ?? ''),
                    'sub_program_name' => (string) ($sub['uraian_kode'] ?? ''),
                    'activity_code' => (string) ($p['id_kode'] ?? ''),
                    'activity_name' => (string) ($p['uraian_kode'] ?? ''),
                ];
            }
            usort($rows, fn ($a, $b) => [$a->program_code, $a->sub_program_code, $a->activity_code] <=> [$b->program_code, $b->sub_program_code, $b->activity_code]);

            $page = max(1, (int) request()->query('page', 1));
            $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

            return new ConcretePaginator($slice, count($rows), $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
        } catch (\Throwable) {
            return new ConcretePaginator([], 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }
    }

    /** @return array{0:array<int,array{kode:string,nama:string}>,1:array<int,array{kode:string,nama:string}>,2:array<int,array{kode:string,nama:string}>} */
    public function activityOptions(?string $year, string $programFilter = '', string $subFilter = ''): array
    {
        try {
            $programs = [];
            $subs = [];
            $kegiatans = [];
            foreach (DB::table('arkas_mirror_ref_kode')->cursor(['payload']) as $row) {
                $p = json_decode((string) ($row->payload ?? ''), true);
                if (! is_array($p)) {
                    continue;
                }
                if ($year !== null && $year !== '' && (string) ($p['tahun'] ?? '') !== $year) {
                    continue;
                }
                $level = (string) ($p['id_level_kode'] ?? '');
                if ($level === '1') {
                    $programs[(string) $p['id_kode']] = ['kode' => (string) $p['id_kode'], 'nama' => (string) ($p['uraian_kode'] ?? '')];
                } elseif ($level === '2' && ($programFilter === '' || $this->codeStartsWith((string) ($p['id_kode'] ?? ''), $programFilter))) {
                    $subs[(string) $p['id_kode']] = ['kode' => (string) $p['id_kode'], 'nama' => (string) ($p['uraian_kode'] ?? '')];
                } elseif ($level === '3' && ($programFilter === '' || $this->codeStartsWith((string) ($p['id_kode'] ?? ''), $programFilter)) && ($subFilter === '' || $this->codeStartsWith((string) ($p['id_kode'] ?? ''), $subFilter))) {
                    $kegiatans[(string) $p['id_kode']] = ['kode' => (string) $p['id_kode'], 'nama' => (string) ($p['uraian_kode'] ?? '')];
                }
            }
            uksort($programs, fn ($a, $b) => strnatcmp($a, $b));
            uksort($subs, fn ($a, $b) => strnatcmp($a, $b));
            uksort($kegiatans, fn ($a, $b) => strnatcmp($a, $b));

            return [array_values($programs), array_values($subs), array_values($kegiatans)];
        } catch (\Throwable) {
            return [[], [], []];
        }
    }

    private function codeStartsWith(string $code, string $prefix): bool
    {
        return $prefix === '' || $code === $prefix || str_starts_with($code, rtrim($prefix, '.').'.');
    }

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
