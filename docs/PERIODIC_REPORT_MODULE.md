# Modul Laporan Periode

Terakhir diperbarui: **2026-10-07** (`raw-rkas`, branch `main`)

Dokumen ini adalah panduan fitur laporan periode (`/laporan-periode`): generator
internal APP-SPJ untuk cetak browser dan PDF, terpisah dari template Paket SPJ.

Status: **ACTIVE FEATURE GUIDE / PRINT, PDF, DAN EXCEL SOURCE IMPLEMENTED /
VISUAL RUNTIME RVR**. Kontrak teknis modul (registry, scope, boundary data)
berada di `SPJ_PERIODIC_REPORTING.md`.

## BPK Dinas (REKAP REALISASI PENGGUNAAN DANA BOS model Dinas)

Presentasi `bpk_bos`: kop Dinas + `DATA SEKOLAH` (7 baris) + tabel
`DATA REALISASI KEUANGAN` (NO/KETERANGAN/TANGGAL/MASUK/KELUAR/SALDO: saldo
awal bank+tunai, pendapatan operasional Dana BOS/bunga/lain-lain+Setor Tunai,
belanja operasional a–g, belanja modal KIB A–F, saldo akhir + rincian
bank/tunai) + tanda tangan Mengetahui Kepsek / Dibuat Oleh Bendahara BOS +
catatan pengembalian sisa.

Aturan nilai (diverifikasi sel-per-sel terhadap 6 contoh resmi 2025 TW I–IV,
semester I, tahunan):
- kategori belanja dari KODE_REKENING: perjalanan `5.1.02.04%`, koran
  `5.1.02.02.01.0063`, makan `5.1.02.01.01.0052`, jasa `5.1.02.02.01.0013`/
  `0031`/`5.1.02.02.04%`, pemeliharaan c.2 `5.1.02.03%`, KIB B `5.2.02%`,
  KIB E `5.2.05%`+`5.2.*` lainnya, sisanya persediaan;
- Terima Dana BOS distinct (bukti+tanggal+nominal); Beban Bunga = total bunga;
  Setor Tunai ikut penerimaan bila datanya ada;
- KIB A/C/D/F dan pemeliharaan c.1 selalu kosong (tanpa bukti data);
  BOS Kinerja terisi bila ada baris dananya, selain itu kosong.

Keterbatasan yang dicatat: No. Rekening belum ada field-nya (placeholder);
nominal bunga/setor mengikuti mirror (0 bila ARKAS tak membawa nilai);
label tahap bulanan mengikuti pola ARKAS (`BULAN X TAHUN`); selisih 100rb
Setor Tunai pada contoh TW-I resmi tidak dapat direkonsiliasi dari data
(agregat semester/tahunan yang dipakai acuan memasukkan setor).

Implementasi: `SpjPeriodicReportPrintService::bpkData/bpkBelanjaCategory` +
partial `bpk-dinas.blade.php` + style `.bpk-dinas-*`.
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Buku Pembantu Pajak (dimodelkan dari BKP-PAJAK ARKAS)

Presentasi `tax` (`buku_pembantu_pajak`, scope bulanan) mengikuti contoh resmi
`BKP-PAJAK` ARKAS dengan adaptasi data operator:

- **Header ala BKU**: blok identitas sekolah (NPSN, nama, alamat, desa,
  kecamatan, kab/kota, provinsi) + blok meta (sumber dana, tahun, periode,
  tanggal periode, jumlah transaksi), judul `BUKU PEMBANTU PAJAK` dengan
  subjudul `BKU - PAJAK`, serta blok tanda tangan `Menyetujui` Kepala Sekolah
  dan Bendahara. Tidak ada paragraf penutup saldo seperti BKU.
- **Uraian dari operator**: kolom Uraian memakai `payment_description` paket
  operator, fallback ke uraian sumber ARKAS/BKU bila kosong.
- **Kolom Siplah**: `Ya`/`Tidak` dari flag mirror `is_siplah` per transaksi.
- Nilai PPN/PPh/SSPD mengikuti agregat mirror `sourceValue()` yang sama dengan
  halaman `/pajak` (termasuk aturan dedup bayangan backfill), sehingga angka
  laporan selalu konsisten dengan layar.

Implementasi: `SpjPeriodicReportPrintService::taxColumns/taxRows` +
`periodic-reports/partials/document.blade.php` (cabang `tax`).
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Export Excel

Semua report key pada registry dapat diekspor melalui route internal
`/laporan-periode/{scope}/{report}/excel`. Workbook dibuat dari payload yang
sama dengan cetak/PDF dan memiliki sheet `Ringkasan` serta `Laporan`.
Presenter khusus `rekap_bosp`, `bos_a1`, dan `bpk_bos` juga ditulis ke bentuk tabel Excel
yang sesuai dengan data masing-masing.

## Buku Pembantu Kas (bentuk BKU, sisi tunai)

Presentasi `cash_ledger` (`buku_pembantu_kas`) memakai ulang mesin BKU dengan
bentuk yang sama: blok identitas sekolah + meta periode, tabel ledger 8 kolom
(Tanggal, Kode Kegiatan, Kode Rekening, No. Bukti, Uraian, Penerimaan,
Pengeluaran, Saldo), paragraf penutup saldo, dan tanda tangan `Menyetujui`.

Perbedaan dari BKU: hanya baris sisi tunai (Saldo Awal Tunai, Pergeseran
Tunai, Kas Keluar, pasangan Terima/Setor pajak); saldo berjalan dan penutup
hanya kas tunai (judul `BUKU PEMBANTU KAS`, tanpa split bank); kertas folio
landscape sama seperti BKU. Baris bank (Saldo Awal Bank, Terima Dana BOS,
Kas Keluar Non Tunai, Tarik Tunai) tidak tampil.

Implementasi: `SpjPeriodicReportPrintService::bkuLedgerRows(..., $cashOnly)`
+ cabang `cash_ledger` di `document.blade.php`.
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Dedup bayangan backfill pajak + fallback uraian BKU

`spj:backfill-mirror-from-local` menulis baris `MIG-T-*` (tanpa
`REK_BKU`/uraian) untuk shortfall pajak. Setelah sinkronisasi
susulan membawa baris PAJAK kanonis yang sama (bukti + jenis +
nominal sama), baris bayangan itu tampil sebagai baris kembar
beruraian kosong dengan nominal `0/0` (kasus nyata 10208183 TW1
2026: `BPU07`–`BPU22`). `bkuDedupBackfillTax()` membuang bayangan
yang sudah dilengkapi baris kanonis (kunci sama seperti dedup
`ArkasMirrorResolver::transactionSource`); shortfall murni ditulis
ulang sebagai `Terima … (data lokal)` agar tetap terhitung dan
terbaca asalnya.

Pelengkap: `REK_BKU` varian `Sisa` dinormalisasi ke bentuk dasarnya
(`bkuBaseRek`) untuk grup/hitung; `Bunga Bank` dihitung sebagai
penerimaan sisi bank dan `Pajak Bunga` sebagai pengeluaran sisi bank
(sebelumnya keduanya `0/0` walau mirror membawa nominal); uraian
memakai `URAIAN`, fallback `URAIAN_PAJAK`, lalu `REK_BKU`; parent grup
memakai payment/uraian non-kosong pertama segrup (bukan baris pertama
buta) sehingga tidak ada lagi sel uraian kosong berupa
`<strong></strong>`.

## Rekapitulasi Pengeluaran Dana BOS (model ARKAS resmi)

Presentasi `rekap_bosp` (`rekapitulasi_pengeluaran_dana_bos`, semua scope
berperiode + tahunan) meniru laporan ARKAS: judul + rentang tanggal +
label tahap/bulan, blok identitas (NPSN, sekolah, kecamatan, kab/kota,
provinsi, sumber dana), grid 7 SNP × 12 sub program + baris/kolom JUMLAH,
footer saldo (periode sebelumnya, penerimaan, penggunaan, akhir saldo),
tanda tangan Menyetujui Kepsek + Bendahara/Penanggungjawab.

Pemetaan sel dari kode kegiatan baris mirror: segmen program → baris SNP,
segmen kedua → kolom sub program (`BospRekapStandardMapper`; peta program
diverifikasi terhadap uraian `ref_kode` 2026). Nilai sel = jumlah BELANJA.
Penerimaan = Terima Dana BOS distinct (bukti+tanggal+nominal, karena ARKAS
mendupilkasi baris terima per revisi anggaran). Saldo sebelumnya = year to
date sebelum periode. Aktivitas di luar grid resmi tidak masuk sel.

Batasan Blade/Livewire: hindari kurung bersarang di dalam direktif pada
partial ini (mis. `@foreach(range(...) ...)`) — precompiler marker Livewire
gagal menokennya dan writ chunk mentah hingga ParseError; pindahkan ke blok
`@php...@endphp` atau variabel service.

Implementasi: `SpjPeriodicReportPrintService::rekapBospData/Title` +
cabang `rekap_bosp` di `document.blade.php`.
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Format BOS A-1 (rekapitulasi resmi per 8 program)

Presentasi `bos_a1` (`bos_a1`, scope triwulan; Fase 1) meniru Formulir BOS
A-1: judul + `PERIODE TANGGAL : 01 JANUARI - 31 MARET 2024` + kotak Format
BOS A-1, blok identitas (NPSN, sekolah, desa/kecamatan, kab/kota, provinsi,
sumber dana), grid 8 program × (Belanja Pegawai | Barang dan Jasa | 3 kolom
Belanja Modal | TOTAL) + baris TOTAL, sel nol sebagai `Rp -`, tanda tangan
Menyetujui Kepsek + `{desa}, {tanggal akhir periode}` / Pemegang Kas
Sekolah. Orientasi landscape folio; Excel menulis grid + baris TOTAL.

Aturan grid terverifikasi terhadap keluaran resmi TW IV 2024 (seluruh angka
cocok per baris): baris = segmen pertama kode kegiatan 01–08
(`BospRekapStandardMapper::a1RowForActivity`); kolom Pegawai = sub-program
12 honorarium (`a1IsHonor`) — aturan prefix rekening DITOLAK bukti (tidak
ada baris `5.1.01.*`, honor tercatat `5.1.02.02`, dan baris berkode `5.2.*`
resmi masuk Barang dan Jasa); selainnya Barang dan Jasa dari
`gross_amount`. Kolom modal dipertahankan nol + RVR: seluruh sampel resmi
bernilai nol sehingga belum ada aturan populasi terverifikasi. Transaksi
di luar 01–08 tercatat `unmapped` pada payload (tidak masuk grid agar
TOTAL = jumlah baris).

Implementasi: `SpjPeriodicReportPrintService::bosA1Data/Title`,
`SpjPeriodicReportExcelService::writeBosA1`, cabang `bos_a1` di
`document.blade.php` (header, tabel, signature khusus).
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Laporan BPK Format BOS

Paket `bpk_bos` tersedia pada scope bulanan, triwulan, semester, dan tahunan.
Laporan dibuat langsung dari mirror `kas_umum`, sehingga pengguna tidak perlu
mengunggah workbook Excel. Presentasi internalnya merangkum saldo awal,
penerimaan dana BOS, belanja operasional, belanja modal/aset, dan saldo akhir.

Belanja dikelompokkan berdasarkan kode/nama rekening dan uraian sumber. Kode
rekening `5.2` serta uraian yang mengandung indikator aset diperlakukan sebagai
belanja modal; belanja lainnya dipetakan ke kategori operasional yang tersedia.
Hasil dapat dibuka melalui cetak browser atau PDF dari halaman Laporan Periode.
Format ini adalah laporan internal APP-SPJ yang perlu dicocokkan dengan bukti
fisik dan format resmi instansi sebelum ditandatangani.

## Register Penutupan Kas K7B + Berita Acara Pemeriksaan Kas K7C (2026-10-07)

Presentasi `k7b`/`k7c` (scope bulanan) mengikuti formulir resmi BOS-K7B/K7C:
K7B memuat tanggal penutupan + penutup kas + penutupan lalu, Total
Penerimaan (D) / Pengeluaran (K) / Saldo Buku (A) dari ledger BKU periode
yang sama, rincian pecahan uang kertas/logam sebagai baris isian manual
(tidak tersedia di mirror), saldo bank dari BKU, Perbedaan (A-B), dan
tanda tangan Yang diperiksa Bendahara + Yang Memeriksa Kepsek. K7C memuat
narasi pemeriksaan (nomor SK memakai placeholder manual), rincian
uang kertas+logam / saldo bank / surat berharga, jumlah, saldo BKU,
selisih, dan tanda tangan Bendahara + Kepsek.

Implementasi: `SpjPeriodicReportPrintService::k7bData/k7cData`, cabang
`k7b`/`k7c` di `document.blade.php`, `SpjPeriodicReportExcelService::
writeK7b/writeK7c`. Status visual/runtime: RVR sampai QA browser/PDF
aktual dijalankan.

## Status verifikasi (2026-10-07)

Perubahan pada `SpjPeriodicReportPrintService` (dedup bayangan backfill pajak,
normalisasi `REK_BKU` varian `Sisa`, perhitungan bunga/pajak sisi bank, dan
fallback uraian BKU) sudah tercakup regression:

```text
BkuOfficialLedgerTest              : 12 passed / 124 assertions
filter PeriodicReport              : 13 passed / 152 assertions
K7bK7cReportTest                   : 5 passed / 45 assertions
```

Artinya kontrak **source/data** terverifikasi; tampilan cetaknya tetap **RVR**
karena belum dibuka di browser/PDF viewer aktual.
