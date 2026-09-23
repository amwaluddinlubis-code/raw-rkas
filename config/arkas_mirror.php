<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ARKAS Fixed Mirror Registry
    |--------------------------------------------------------------------------
    |
    | Importer tetap untuk mirror ARKAS. Tidak ada mapping/profile yang bisa
    | diubah operator — daftar tabel dikunci di sini agar user tidak bingung
    | dengan Generic Importer lama.
    |
    | Aturan:
    | - 13 tabel ref_* -> database pusat (koneksi default).
    | - 17 tabel operasional -> database sekolah (koneksi school).
    | - ptk, tabel kosong, dan tabel internal ARKAS dikecualikan.
    | - Setiap tabel mirror menyimpan source_key + payload JSON + hash,
    |   sehingga tabel aplikasi cukup memanggil (tidak menulis ulang kolom).
    |
    */

    'central' => [
        ['source' => 'ref_acuan_barang', 'mirror' => 'arkas_mirror_ref_acuan_barang', 'keys' => ['id_barang', 'tahun', 'nama_barang'], 'label' => 'Acuan Barang'],
        ['source' => 'ref_bku', 'mirror' => 'arkas_mirror_ref_bku', 'keys' => ['id_ref_bku'], 'label' => 'Jenis BKU'],
        ['source' => 'ref_indikator', 'mirror' => 'arkas_mirror_ref_indikator', 'keys' => ['id_ref_indikator'], 'label' => 'Indikator'],
        ['source' => 'ref_jabatan', 'mirror' => 'arkas_mirror_ref_jabatan', 'keys' => ['jabatan_id'], 'label' => 'Jabatan'],
        ['source' => 'ref_jenis_instansi', 'mirror' => 'arkas_mirror_ref_jenis_instansi', 'keys' => ['jenis_instansi_id'], 'label' => 'Jenis Instansi'],
        ['source' => 'ref_kode', 'mirror' => 'arkas_mirror_ref_kode', 'keys' => ['id_ref_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'], 'label' => 'Kode Kegiatan'],
        ['source' => 'ref_level_kode', 'mirror' => 'arkas_mirror_ref_level_kode', 'keys' => ['id_level_kode'], 'label' => 'Level Kode'],
        ['source' => 'ref_level_wilayah', 'mirror' => 'arkas_mirror_ref_level_wilayah', 'keys' => ['id_level_wilayah'], 'label' => 'Level Wilayah'],
        ['source' => 'ref_negara', 'mirror' => 'arkas_mirror_ref_negara', 'keys' => ['negara_id'], 'label' => 'Negara'],
        ['source' => 'ref_periode', 'mirror' => 'arkas_mirror_ref_periode', 'keys' => ['id_periode'], 'label' => 'Periode'],
        ['source' => 'ref_rekening', 'mirror' => 'arkas_mirror_ref_rekening', 'keys' => ['kode_rekening', 'tahun'], 'label' => 'Rekening'],
        ['source' => 'ref_satuan', 'mirror' => 'arkas_mirror_ref_satuan', 'keys' => ['ref_satuan_id'], 'label' => 'Satuan'],
        ['source' => 'ref_sumber_dana', 'mirror' => 'arkas_mirror_ref_sumber_dana', 'keys' => ['id_ref_sumber_dana'], 'label' => 'Sumber Dana'],
    ],

    'school' => [
        ['source' => 'aktivasi_bku', 'mirror' => 'arkas_mirror_aktivasi_bku', 'keys' => ['id_anggaran', 'id_periode'], 'label' => 'Aktivasi BKU'],
        ['source' => 'anggaran', 'mirror' => 'arkas_mirror_anggaran', 'keys' => ['id_anggaran'], 'label' => 'Anggaran'],
        ['source' => 'instansi', 'mirror' => 'arkas_mirror_instansi', 'keys' => ['instansi_id'], 'label' => 'Instansi'],
        ['source' => 'instansi_pengguna', 'mirror' => 'arkas_mirror_instansi_pengguna', 'keys' => ['instansi_pengguna_id'], 'label' => 'Pengguna Instansi'],
        ['source' => 'kas_umum', 'mirror' => 'arkas_mirror_kas_umum', 'keys' => ['id_kas_umum'], 'label' => 'BKU / Kas Umum'],
        ['source' => 'kas_umum_nota', 'mirror' => 'arkas_mirror_kas_umum_nota', 'keys' => ['id_kas_nota'], 'label' => 'Nota BKU'],
        ['source' => 'kas_umum_nota_pajak', 'mirror' => 'arkas_mirror_kas_umum_nota_pajak', 'keys' => ['id_kas_nota', 'ntpn'], 'label' => 'Pajak Nota'],
        ['source' => 'mst_sekolah', 'mirror' => 'arkas_mirror_mst_sekolah', 'keys' => ['sekolah_id'], 'label' => 'Sekolah'],
        ['source' => 'mst_wilayah', 'mirror' => 'arkas_mirror_mst_wilayah', 'keys' => ['kode_wilayah'], 'label' => 'Wilayah'],
        ['source' => 'pengguna', 'mirror' => 'arkas_mirror_pengguna', 'keys' => ['pengguna_id'], 'label' => 'Pengguna ARKAS'],
        ['source' => 'rapbs', 'mirror' => 'arkas_mirror_rapbs', 'keys' => ['id_rapbs'], 'label' => 'RKAS'],
        ['source' => 'rapbs_periode', 'mirror' => 'arkas_mirror_rapbs_periode', 'keys' => ['id_rapbs_periode'], 'label' => 'RKAS Periode'],
        ['source' => 'rapbs_ptk', 'mirror' => 'arkas_mirror_rapbs_ptk', 'keys' => ['id_rapbs', 'ptk_id'], 'label' => 'RKAS PTK'],
        ['source' => 'rpt_bku', 'mirror' => 'arkas_mirror_rpt_bku', 'keys' => ['tanggal_transaksi', 'no_bukti', 'id_rapbs_periode', 'id_anggaran', 'kode_rekening', 'id_ref_bku'], 'label' => 'Laporan BKU'],
        ['source' => 'salur', 'mirror' => 'arkas_mirror_salur', 'keys' => ['sekolah_id', 'tahap', 'gelombang', 'jenis', 'tahun'], 'label' => 'Penyaluran'],
        ['source' => 'sekolah_history', 'mirror' => 'arkas_mirror_sekolah_history', 'keys' => ['sekolah_id', 'tahun'], 'label' => 'Riwayat Sekolah'],
        ['source' => 'sekolah_penjab', 'mirror' => 'arkas_mirror_sekolah_penjab', 'keys' => ['id_penjab'], 'label' => 'Penanggung Jawab'],
    ],
];
