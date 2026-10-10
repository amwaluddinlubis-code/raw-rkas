<?php

namespace App\Support;

/**
 * Format tampilan kanonis yang dipakai ulang seluruh permukaan SPJ.
 *
 * Satu-satunya sumber kebenaran untuk label kategori dan format rupiah
 * di Blade maupun view-data Livewire, agar salinan @php closure di
 * belasan view tidak divergen (mis. arm legacy JASA_HONORARIUM hilang
 * di satu view tetapi ada di view lain).
 */
final class SpjDisplay
{
    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'JASA_HONORARIUM' => 'Honor Pegawai',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
        ];
    }

    public static function typeLabel(mixed $value): string
    {
        $key = strtoupper((string) $value);

        return self::typeLabels()[$key]
            ?? ucwords(strtolower(str_replace('_', ' ', (string) $value)));
    }

    public static function rupiah(mixed $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    /**
     * Format ukuran byte (B/KB/MB) untuk panel diagnostik database.
     */
    public static function bytes(int $bytes): string
    {
        return $bytes < 1024
            ? $bytes.' B'
            : ($bytes < 1048576
                ? number_format($bytes / 1024, 1).' KB'
                : number_format($bytes / 1048576, 2).' MB');
    }

    /**
     * Nilai yang sudah diformat (string) atau teks kosong kanonis.
     * Untuk tanggal/relasi yang belum diformat, format dulu di pemanggil.
     */
    public static function presence(mixed $value, string $empty = 'Belum diisi'): string
    {
        $text = is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');

        return $text !== '' ? $text : $empty;
    }

    /**
     * Varian HTML dari presence() untuk blok label/nilai atribut:
     * nilai di-escape, status kosong memakai penanda rose kanonis.
     */
    public static function presenceHtml(mixed $value, string $empty = 'Belum diisi'): string
    {
        $text = is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');

        if ($text === '') {
            return '<span class="text-rose-500">'.e($empty).'</span>';
        }

        return e($text);
    }
}
