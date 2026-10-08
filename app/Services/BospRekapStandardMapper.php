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

    /**
     * Taksonomi BARIS Format BOS A-1 (8 program resmi). BERBEDA dari grid
     * 7x12 di atas: baris A-1 = segmen pertama kode kegiatan (01–08)
     * apa adanya, sedangkan grid ARKAS menggabung 01+02 pada baris 1.
     * Diverifikasi terhadap keluaran resmi TW IV 2024 (56.055.000 cocok
     * per baris): kode 03–08 jatuh pada baris bernomor sama.
     *
     * @return array<int,string> baris 1–8 => label program resmi
     */
    public static function a1Programs(): array
    {
        return [
            1 => 'Pengembangan Kompetensi Lulusan',
            2 => 'Pengembangan Standar Isi',
            3 => 'Pengembangan Standar Proses',
            4 => 'Pengembangan Standar Pendidik dan Tenaga Kependidikan',
            5 => 'Pengembangan Sarana dan Prasarana',
            6 => 'Pengembangan Standar Pengelolaan',
            7 => 'Pengembangan Standar Pembiayaan',
            8 => 'Pengembangan dan Implementasi Sistem Penilaian',
        ];
    }

    /**
     * Baris A-1 untuk satu kode kegiatan, atau null bila segmen pertama
     * di luar 01–08. Segmen 01/02 terverifikasi struktural (nol pada
     * sampel resmi); 03–08 terverifikasi nominal per baris.
     */
    public static function a1RowForActivity(?string $activityCode): ?int
    {
        $parts = array_values(array_filter(explode('.', trim((string) $activityCode, '.')), fn (string $part): bool => $part !== ''));
        if (! isset($parts[0]) || ! ctype_digit($parts[0])) {
            return null;
        }

        $row = (int) $parts[0];

        return $row >= 1 && $row <= 8 ? $row : null;
    }

    /**
     * Honorarium (Belanja Pegawai A-1) = sub-program 12 (xx.12.*,
     * "Pembayaran Honor"). Diverifikasi: satu-satunya pola honor pada
     * data nyata (07.12.01–04, seluruhnya berkode rekening 5.1.02.02
     * seperti jasa lain) dan jumlahnya sama persis dengan kolom Pegawai
     * resmi. Aturan prefix rekening DITOLAK bukti: tidak ada baris
     * 5.1.01.*, dan baris berkode 5.2.* tercatat resmi di Barang dan Jasa.
     */
    public static function a1IsHonor(?string $activityCode): bool
    {
        $parts = array_values(array_filter(explode('.', trim((string) $activityCode, '.')), fn (string $part): bool => $part !== ''));

        return isset($parts[1]) && (int) $parts[1] === 12;
    }

    /**
     * Komponen BOS K7A (12 baris ARKAS resmi; 2 khusus SMK ditandai).
     * Sumber label: arkas.kemendikdasmen.go.id/komponenbos + Juknis BOSP
     * 2025 (12 komponen BOS Reguler). Sekolah SD/SMP memakai 10 baris
     * non-SMK; baris SMK tetap tampil bernilai nol agar formulir utuh.
     *
     * @return array<int,array{no:int,label:string,smk:bool}>
     */
    public static function k7aComponents(): array
    {
        return [
            1 => ['no' => 1, 'label' => 'Penerimaan Peserta Didik Baru (PPDB)', 'smk' => false],
            2 => ['no' => 2, 'label' => 'Pengembangan Perpustakaan', 'smk' => false],
            3 => ['no' => 3, 'label' => 'Kegiatan Pembelajaran dan Ekstrakurikuler', 'smk' => false],
            4 => ['no' => 4, 'label' => 'Kegiatan Asesmen/Evaluasi Pembelajaran', 'smk' => false],
            5 => ['no' => 5, 'label' => 'Administrasi Kegiatan Sekolah', 'smk' => false],
            6 => ['no' => 6, 'label' => 'Pengembangan Profesi Guru dan Tenaga Kependidikan', 'smk' => false],
            7 => ['no' => 7, 'label' => 'Langganan Daya dan Jasa', 'smk' => false],
            8 => ['no' => 8, 'label' => 'Pemeliharaan Sarana dan Prasarana Sekolah', 'smk' => false],
            9 => ['no' => 9, 'label' => 'Penyediaan Alat Multi Media Pembelajaran', 'smk' => false],
            10 => ['no' => 10, 'label' => 'Bursa Kerja / Prakerin / PKL (khusus SMK)', 'smk' => true],
            11 => ['no' => 11, 'label' => 'Uji Kompetensi dan Sertifikasi (khusus SMK)', 'smk' => true],
            12 => ['no' => 12, 'label' => 'Pembayaran Honor', 'smk' => false],
        ];
    }

    /**
     * Komponen K7A untuk satu baris belanja, atau null bila tak terpetakan.
     *
     * Mirror tidak membawa field komponen, sehingga pemetaan memakai kata
     * kunci NAMA_KEGIATAN/uraian dari data nyata (contoh: "Pelaksanaan
     * Pendaftaran Murid Baru (PMB)", "Pengadaan buku pengayaan dan
     * referensi"). Urutan pemeriksaan disengaja: honor dan administrasi
     * didahulukan agar "honor Tenaga Kependidikan" tidak jatuh ke
     * Pengembangan Profesi GTK dan "bahan habis pakai ... administrasi
     * (termasuk ATK)" tidak jatuh ke Pembelajaran. Frasa penafian
     * "diluar komponen ..." menonaktifkan kata kunci komponen tersebut
     * (kasus nyata: perlengkapan "diluar komponen penyediaan alat
     * multimedia"). Aturan ini RVR sampai dibandingkan dengan keluaran
     * K7A resmi ARKAS/dinas; baris tak terpetakan selalu dilaporkan
     * eksplisit pada payload agar JUMLAH dapat direkonsiliasi.
     */
    public static function k7aComponentForActivity(?string $activityName, ?string $uraian = null, ?string $accountCode = null): ?int
    {
        $haystack = mb_strtolower(trim((string) $activityName).' '.trim((string) $uraian));
        if ($haystack === '') {
            return null;
        }

        $denies = str_contains($haystack, 'diluar komponen') || str_contains($haystack, 'di luar komponen');

        $has = static function (array $needles) use ($haystack): bool {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        };

        // 12. Honor didahulukan (mengalahkan kata "guru"/"tenaga kependidikan").
        if ($has(['honor', 'honorarium', 'gaji ', 'gaji/', 'insentif', 'tunjangan '])) {
            return 12;
        }

        // 1. PPDB.
        if ($has(['ppdb', 'pendaftaran murid baru', 'penerimaan murid baru', 'penerimaan peserta didik', 'penerimaan siswa baru', 'mpls', 'masa pengenalan lingkungan'])) {
            return 1;
        }

        // 2. Perpustakaan.
        if ($has(['perpustakaan', 'buku teks', 'buku pengayaan', 'buku referensi', 'buku bacaan', 'pojok baca', 'minat baca'])) {
            return 2;
        }

        // 4. Asesmen sebelum pembelajaran generik (kata "penilaian" ambigu).
        if ($has(['asesmen', 'penilaian sumatif', 'ulangan tengah semester', 'ulangan akhir semester', 'ulangan dan ujian', 'kisi-kisi', 'penyusunan soal', 'ijazah'])) {
            return 4;
        }

        // 5. Administrasi sebelum pembelajaran generik (kasus "mendukung pembelajaran dan administrasi").
        if ($has(['administrasi', 'tata kelola', 'rkjm', 'rkt ', 'rkas', 'visi misi', 'perencanaan program', 'surat-menyurat', 'surat menyurat', 'penggandaan', 'stempel', 'atk', 'laporan keuangan', 'laporan bos', 'laporan dan/', 'ketatausahaan'])) {
            return 5;
        }

        // 6. Profesi GTK.
        if ($has(['pelatihan', 'bimbingan teknis', 'bimtek', 'workshop', 'in house training', 'komunitas belajar', 'kkg', 'mgmp', 'pengembangan profesi', 'peningkatan kompetensi pendidik', 'penguatan kompetensi guru'])) {
            return 6;
        }

        // 7. Daya dan jasa.
        if ($has(['langganan', 'internet', 'wifi', 'listrik', 'telepon', 'rekening air', 'daya dan jasa'])) {
            return 7;
        }

        // 8. Pemeliharaan.
        if ($has(['pemeliharaan', 'perawatan sekolah', 'rehab', 'renovasi', 'sanitasi', 'kebersihan', 'perbaikan sarana', 'perbaikan prasarana', 'pengecatan', 'lahan, bangunan'])) {
            return 8;
        }

        // 9. Multimedia (dangkal bila ada penafian eksplisit).
        if (! $denies && $has(['multimedia', 'komputer', 'laptop', 'notebook', 'printer', 'proyektor', 'cctv', 'chromebook', 'tablet pembelajaran'])) {
            return 9;
        }

        // 10. BKK/Prakerin (SMK).
        if ($has(['prakerin', 'praktik kerja', 'pkl ', 'bursa kerja', 'pemagangan', 'kebekerjaan'])) {
            return 10;
        }

        // 11. Uji kompetensi (SMK).
        if ($has(['uji kompetensi', 'sertifikasi', 'toeic', 'uji kemahiran'])) {
            return 11;
        }

        // 3. Pembelajaran & ekstrakurikuler (generik, terakhir).
        if ($has(['pembelajaran', 'ekstrakurikuler', 'ekskul', 'pesantren kilat', 'lomba', 'keagamaan', 'pramuka', 'peringatan hari', 'hari besar', 'pentas seni', 'olahraga siswa', 'bahan habis pakai'])) {
            return 3;
        }

        return null;
    }
}
