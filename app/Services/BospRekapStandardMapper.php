<?php

namespace App\Services;

/**
 * Pemetaan grid REKAPITULASI REALISASI PENGGUNAAN DANA BOSP model ARKAS.
 *
 * Baris = 7 Standar Nasional Pendidikan (dari segmen program kode kegiatan,
 * diverifikasi terhadap uraian ref_kode 2026). Kolom = 12 sub program (dari
 * segmen kedua kode kegiatan). Aktivitas dengan sub di luar 1–12 tidak
 * memiliki kolom resmi dan dilaporkan agar total tetap teraudit.
 */
final class BospRekapStandardMapper
{
    /** @return list<string> label baris SNP 1–7 */
    public static function standardRows(): array
    {
        return [
            'Pengembangan Standar Isi',
            'Standar Proses',
            'Standar Tenaga Kependidikan',
            'Standar Sarana dan Prasarana',
            'Standar Pengelolaan',
            'Pengembangan standar pembiayaan',
            'Standar Penilaian Pendidikan',
        ];
    }

    /** @return array<int,string> nomor sub program => label (11 kosong seperti ARKAS) */
    public static function subPrograms(): array
    {
        return [
            1 => 'Penerimaan Murid baru',
            2 => 'Pengembangan Perpustakaan',
            3 => 'Pelaksanaan Kegiatan Pembelajaran dan Ekstrakurikuler',
            4 => 'Pelaksanaan Kegiatan Asesmen/Evaluasi Pembelajaran',
            5 => 'Pelaksanaan Administrasi Kegiatan Sekolah',
            6 => 'Pengembangan Profesi Pendidik dan Tenaga Kependidikan',
            7 => 'Pembiayaan Langganan Daya dan Jasa',
            8 => 'Pemeliharaan Sarana dan Prasarana Sekolah',
            9 => 'Penyediaan Alat Multi Media Pembelajaran',
            10 => 'Penyelenggaraan Kegiatan Peningkatan Komptetensi Keahlian',
            11 => '',
            12 => 'Pembayaran Honor',
        ];
    }

    /** @return array<string,int> segmen program => baris SNP 1–7 */
    public static function programRows(): array
    {
        return ['01' => 1, '02' => 1, '03' => 2, '04' => 3, '05' => 4, '06' => 5, '07' => 6, '08' => 7];
    }

    /**
     * Petakan kode kegiatan (mis. 03.03.14) ke sel grid.
     *
     * @return array{row:int,col:int}|null null bila di luar grid resmi.
     */
    public static function cellForActivity(?string $activityCode): ?array
    {
        $parts = array_values(array_filter(explode('.', trim((string) $activityCode, '.')), fn (string $part): bool => $part !== ''));
        if (count($parts) < 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return null;
        }

        $row = self::programRows()[str_pad($parts[0], 2, '0', STR_PAD_LEFT)] ?? null;
        $col = (int) $parts[1];
        if ($row === null || $col < 1 || $col > 12) {
            return null;
        }

        return ['row' => $row, 'col' => $col];
    }
}
