<?php

namespace App\Services;

/**
 * Katalog pola lampiran bukti dukung operasional (poster 10 pola).
 *
 * Pure configuration — tidak menyentuh database, lifecycle, numbering,
 * maupun sync. Item bersumber GENERATED hanya informatif (dibuat aplikasi);
 * item EXTERNAL dicentang manual oleh operator dan tidak memblokir
 * penomoran pada fase 1.
 */
final class SpjExternalChecklistPatterns
{
    public const SOURCE_GENERATED = 'generated';

    public const SOURCE_EXTERNAL = 'external';

    /**
     * @return array<string,array{label:string,source:string}>
     */
    public static function items(): array
    {
        return [
            'kwitansi_a2' => ['label' => 'Kwitansi / Bukti Kas Pengeluaran (A2)', 'source' => self::SOURCE_GENERATED],
            'undangan' => ['label' => 'Undangan', 'source' => self::SOURCE_EXTERNAL],
            'spt_sppd' => ['label' => 'SPT (TT Kepsek) dan SPPD (TT Tujuan)', 'source' => self::SOURCE_EXTERNAL],
            'daftar_terima_uang' => ['label' => 'Daftar / Tanda Terima Uang (bila lebih dari 1 orang)', 'source' => self::SOURCE_EXTERNAL],
            'daftar_hadir' => ['label' => 'Daftar Hadir', 'source' => self::SOURCE_EXTERNAL],
            'notulen' => ['label' => 'Notulen', 'source' => self::SOURCE_EXTERNAL],
            'foto_kegiatan' => ['label' => 'Foto / Dokumentasi Kegiatan', 'source' => self::SOURCE_EXTERNAL],
            'sk_gtt_ptt' => ['label' => 'SK GTT/PTT', 'source' => self::SOURCE_EXTERNAL],
            'sk_pembagian_tugas' => ['label' => 'SK Pembagian Tugas', 'source' => self::SOURCE_EXTERNAL],
            'sk_penetapan' => ['label' => 'SK Penetapan', 'source' => self::SOURCE_EXTERNAL],
            'sk_pembina' => ['label' => 'SK Pembina', 'source' => self::SOURCE_EXTERNAL],
            'jadwal' => ['label' => 'Jadwal', 'source' => self::SOURCE_EXTERNAL],
            'nota_pesanan' => ['label' => 'Nota / Surat Pesanan', 'source' => self::SOURCE_EXTERNAL],
            'nota_pembayaran' => ['label' => 'Nota Pembayaran', 'source' => self::SOURCE_EXTERNAL],
            'faktur' => ['label' => 'Faktur', 'source' => self::SOURCE_EXTERNAL],
            'invoice_eksternal' => ['label' => 'Invoice dari penyedia', 'source' => self::SOURCE_EXTERNAL],
            'bukti_transfer' => ['label' => 'Bukti Transfer', 'source' => self::SOURCE_EXTERNAL],
            'berita_acara_serah_terima' => ['label' => 'Berita Acara Serah Terima Barang', 'source' => self::SOURCE_EXTERNAL],
            'bukti_setor_pajak' => ['label' => 'Bukti Setor Pajak', 'source' => self::SOURCE_EXTERNAL],
            'foto_barang' => ['label' => 'Foto Barang', 'source' => self::SOURCE_EXTERNAL],
            'laporan_hasil_perjalanan' => ['label' => 'Laporan Hasil Perjalanan', 'source' => self::SOURCE_EXTERNAL],
            'nama_narasumber' => ['label' => 'Nama Narasumber', 'source' => self::SOURCE_EXTERNAL],
        ];
    }

    /**
     * @return array<string,array{label:string,items:array<int,string>}>
     */
    public static function patterns(): array
    {
        return [
            'kkg' => [
                'label' => 'KKG',
                'items' => ['kwitansi_a2', 'undangan', 'spt_sppd', 'daftar_terima_uang', 'daftar_hadir', 'notulen', 'foto_kegiatan'],
            ],
            'rapat_k3s' => [
                'label' => 'Rapat K3S',
                'items' => ['kwitansi_a2', 'undangan', 'spt_sppd', 'daftar_hadir', 'notulen', 'foto_kegiatan'],
            ],
            'honor_gtt_ptt' => [
                'label' => 'Honor GTT/PTT',
                'items' => ['kwitansi_a2', 'sk_gtt_ptt', 'sk_pembagian_tugas', 'daftar_terima_uang'],
            ],
            'perjalanan_dinas' => [
                'label' => 'Perjalanan Dinas',
                'items' => ['kwitansi_a2', 'spt_sppd', 'daftar_terima_uang', 'laporan_hasil_perjalanan', 'foto_kegiatan'],
            ],
            'belanja_siplah' => [
                'label' => 'Belanja SiPlah',
                'items' => ['kwitansi_a2', 'bukti_transfer', 'nota_pesanan', 'faktur', 'invoice_eksternal', 'berita_acara_serah_terima', 'bukti_setor_pajak', 'foto_barang'],
            ],
            'makan_minum_rapat' => [
                'label' => 'Makan Minum / Rapat',
                'items' => ['kwitansi_a2', 'undangan', 'nota_pesanan', 'daftar_hadir', 'notulen', 'foto_kegiatan', 'bukti_setor_pajak'],
            ],
            'honor_narasumber' => [
                'label' => 'Honor Narasumber',
                'items' => ['kwitansi_a2', 'undangan', 'sk_penetapan', 'daftar_terima_uang', 'nama_narasumber'],
            ],
            'pengadaan_penggandaan' => [
                'label' => 'Pengadaan / Penggandaan',
                'items' => ['kwitansi_a2', 'nota_pesanan', 'nota_pembayaran', 'berita_acara_serah_terima', 'foto_barang', 'bukti_setor_pajak'],
            ],
            'ekstrakurikuler' => [
                'label' => 'Ekstrakurikuler',
                'items' => ['kwitansi_a2', 'sk_pembina', 'jadwal', 'daftar_hadir', 'foto_kegiatan'],
            ],
            'jasa_tukang' => [
                'label' => 'Jasa Tukang dll',
                'items' => ['kwitansi_a2', 'daftar_terima_uang', 'daftar_hadir', 'foto_kegiatan'],
            ],
        ];
    }

    /** @return array<int,string> */
    public static function patternKeys(): array
    {
        return array_keys(self::patterns());
    }

    /** @return array<int,string> */
    public static function toggleableItemKeys(): array
    {
        return array_keys(array_filter(
            self::items(),
            fn (array $item): bool => $item['source'] === self::SOURCE_EXTERNAL
        ));
    }

    public static function isValidPattern(string $key): bool
    {
        return isset(self::patterns()[$key]);
    }

    public static function isValidItem(string $key): bool
    {
        return isset(self::items()[$key]);
    }

    public static function isToggleable(string $key): bool
    {
        return (self::items()[$key]['source'] ?? null) === self::SOURCE_EXTERNAL;
    }

    /** @return array<int,string> */
    public static function itemsForPattern(string $patternKey): array
    {
        return self::patterns()[$patternKey]['items'] ?? [];
    }

    public static function patternLabel(string $patternKey): string
    {
        return self::patterns()[$patternKey]['label'] ?? $patternKey;
    }

    public static function itemLabel(string $itemKey): string
    {
        return self::items()[$itemKey]['label'] ?? $itemKey;
    }

    /**
     * Pola yang disarankan untuk kategori canonical + channel pengadaan.
     * SiPlah adalah channel, bukan kategori — pola belanja_siplah hanya
     * disarankan bila channel = SIPLAH.
     */
    public static function suggestedPattern(?string $spjCategory, bool $isSiplah): string
    {
        if ($isSiplah) {
            return 'belanja_siplah';
        }

        return match (strtoupper((string) $spjCategory)) {
            'KONSUMSI' => 'makan_minum_rapat',
            'SPPD', 'PERJALANAN_DINAS' => 'perjalanan_dinas',
            'HONOR_PEGAWAI', 'JASA_HONORARIUM' => 'honor_gtt_ptt',
            'PEMELIHARAAN', 'UPAH' => 'jasa_tukang',
            'JASA_LAINNYA', 'JASA' => 'pengadaan_penggandaan',
            default => 'pengadaan_penggandaan',
        };
    }
}
