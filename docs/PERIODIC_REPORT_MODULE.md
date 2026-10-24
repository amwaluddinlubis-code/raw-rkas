# Modul Laporan Periode

Terakhir diperbarui: **2026-10-24** (`raw-rkas`, branch `main`)

Dokumen ini adalah panduan fitur laporan periode (`/laporan-periode`): generator
internal APP-SPJ untuk cetak browser dan PDF, terpisah dari template Paket SPJ.

Status: **ACTIVE FEATURE GUIDE / PRINT, PDF, DAN EXCEL SOURCE IMPLEMENTED /
VISUAL RUNTIME RVR**. Kontrak teknis modul (registry, scope, boundary data)
berada di `SPJ_PERIODIC_REPORTING.md`. Registry aktual berisi **41 slot**
(bulanan 14 + triwulan 10 + semester 10 + tahunan 7) dari **18 report key**.
Daftar per scope dan key berada di kontrak teknis; dokumen ini menjelaskan
aturan nilai per format yang sudah mempunyai presenter khusus.

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
partial `report-bpk-1.blade.php` (blok BPK dipisah dari `document.blade.php`
pada 2026-10-21; partial lama `bpk-dinas.blade.php` dihapus) + style
`.bpk-dinas-*`. Lebar kolom memakai array yang dapat dikustom lewat
`config('spj.periodic_report_column_widths.bpk_bos')` dengan kunci
`meta`/`table`/`signature` (default di partial), dikunci
`BpkDinasFormatTest`.
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
Presenter khusus yang ditulis ke bentuk tabel Excel masing-masing:
`rekap_bosp`, `bos_a1`, `bpk_bos`, `k7b`, `k7c`, `k7`, `k2`, `rpd`, dan
`sptjm`. Presenter lain memakai tabel generik dari kolom/baris payload.

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
partial `report-rekap-bosp-1.blade.php` (dipisah dari `document.blade.php`
pada 2026-10-21) + cabang `rekap_bosp` di `document.blade.php`.
Kertas: **A4 landscape** (`rekap_bosp` masuk daftar landscape pada
`SpjPeriodicReportPrintService::orientation()`; permintaan pengguna
2026-10-21), sehingga grid 7 SNP × 12 sub program tidak terpotong. Dikunci
oleh `RekapBudgetRealizationTest` (payload `orientation`/`paper` + CSS
`size: A4 landscape;` pada rute cetak).
Lebar kolom memakai array yang dapat dikustom:
`config('spj.periodic_report_column_widths.rekap_bosp')` dengan kunci
`no`/`standar_nasional`/`sub_program`/`jumlah` (kolom grid),
`identity` (blok identitas), dan `meta` (blok saldo); kunci yang tidak
diisi memakai default di partial `report-rekap-bosp-1.blade.php`.
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## Format BOS A-1 (rekapitulasi resmi per 8 program)

Presentasi `bos_a1` (`bos_a1`, scope bulanan/triwulan; Fase 1) meniru Formulir BOS
A-1: judul + `PERIODE TANGGAL : 01 JANUARI - 31 MARET 2024` + kotak Format
BOS A-1, blok identitas (NPSN, sekolah, desa/kecamatan, kab/kota, provinsi,
sumber dana), grid 8 program × (Belanja Pegawai | Barang dan Jasa | 3 kolom
Belanja Modal | TOTAL) + baris TOTAL, sel nol sebagai `-`, tanda tangan
Menyetujui Kepsek + `{desa}, {tanggal akhir periode}` / Bendahara /
Pemegang Kas. Orientasi landscape folio; Excel menulis grid + baris TOTAL.

Format nominal (2026-10-21, dikonfirmasi pengguna): sel uang A-1 dirender
tanpa prefix `Rp ` (`report-a1-2.blade.php`, `$a1Money`), sehingga nilai
tampil `1.000.000` dan sel nol tampil `-`; satuan rupiah tidak diulang per
sel. Excel tetap menulis angka numerik dengan format ribuan
(`formatMoneyRange`).

Aturan grid terverifikasi terhadap keluaran resmi TW IV 2024 (seluruh angka
cocok per baris): baris = segmen pertama kode kegiatan 01–08
(`BospRekapStandardMapper::a1RowForActivity`); kolom Pegawai = sub-program
12 honorarium (`a1IsHonor`) — aturan prefix rekening `5.1.01.*` DITOLAK bukti
(tidak ada baris `5.1.01.*`, honor tercatat `5.1.02.02`); selainnya Barang
dan Jasa dari `gross_amount`.

Pemisahan Belanja Modal memakai kode rekening (ditetapkan 2026-10-20 atas
laporan pengguna bahwa rincian belanja modal selalu 0): `5.2.02.*` →
Peralatan dan Mesin, `5.2` lainnya (mis. `5.2.05.*` buku/aset tetap lainnya)
→ Aset Tetap Lainnya BOS, dan keduanya menambah kolom Jumlah Modal. Baris
modal nyata umumnya berkegiatan `02.34.*` atau `05.08.*`, sehingga aturan
berbasis kode kegiatan `05.02.*` milik implementasi sebelumnya salah kolom
(dan hanya kebetulan benar untuk buku pada contoh TW I 2026). Bila kode
rekening kosong, dipakai fallback lama: kegiatan `05.02.*` atau uraian
mengandung "buku" dianggap Aset Tetap Lainnya. Transaksi di luar 01–08
tercatat `unmapped` pada payload (tidak masuk grid agar TOTAL = jumlah
baris).

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

## RPD BOS (2026-10-16)

Presentasi `rpd` (`rpd_bos`, scope bulanan/triwulan/semester) mengikuti contoh
resmi RINCIAN PENGGUNAAN DANA BOS: kop identitas + kotak "RPD", tabel
No | Kode Program | Uraian | Rincian Perhitungan (Vol/Satuan/Tarif) |
Jumlah | kolom bulan triwulan berjalan, hierarki I Program Sekolah → 8
program (01.–08.) → sub-program → kegiatan → item, footer Sub Total I/II
+ JUMLAH TOTAL, dan tanda tangan Mengetahui/Kepala Sekolah/Bendahara.
Landscape folio; Excel mengikuti struktur yang sama.

Aturan nilai: baris diambil dari snapshot revisi disahkan terakhir
(`ArkasMirrorBudgetService::revisions/snapshot`) dan hanya baris dengan
pagu tahunan > 0 serta program 01–08 yang masuk grid. Baris yang jumlah
periode terpilihnya 0 (tidak teralokasi di triwulan itu) tidak
ditampilkan, dan `has_data` hanya benar bila ada baris tampil. Kolom
bulan memakai realisasi BELANJA `arkas_mirror_kas_umum` per `ID_RAPBS`
(tautan `ID_RAPBS_PERIODE` → `rapbs_periode.ID_RAPBS`, fallback
`ID_RAPBS` langsung).

Jumlah/Vol/Satuan/Tarif mengikuti periode terpilih (2026-10-20):
`JUMLAH` dan volume dijumlahkan dari pecahan
`arkas_mirror_rapbs_periode` yang `__MONTH_NUMBER`-nya berada di rentang
bulan triwulan terpilih (atau `__QUARTER_NUMBER` cocok); satuan/tarif
diambil dari pecahan pertama yang terisi. Baris yang punya pecahan
periode terkoordinat tetapi tidak teralokasi di periode terpilih
berjumlah 0 dan tidak ditampilkan — bukan angka tahunan. Payload tahunan
`arkas_mirror_rapbs`
(`JUMLAH`, `VOLUME_TOTAL`/`VOLUME`, `SATUAN`/`UNIT`, `HARGA_SATUAN`)
hanya dipakai sebagai fallback bila baris sama sekali tidak punya
pecahan periode terkoordinat.

Implementasi: `SpjPeriodicReportPrintService::rpdData/rpdRealization/
rpdQuarterSums`, cabang `rpd` di `document.blade.php`,
`SpjPeriodicReportExcelService::writeRpd`.

Kolom per scope (2026-10-21): `rpd_bos` kini juga terdaftar pada scope
semester. Kolom periode mengikuti scope: triwulan → kolom **bulan**
(triwulan berjalan), semester → kolom **triwulan**. RPD Semester I
memakai kolom Triwulan I & II, RPD Semester II memakai kolom Triwulan
III & IV, sehingga Sub Total/jumlah baris per kelompok triwulan
(`quarters`) tersedia pada mode semester. Baris tetap ditampilkan hanya
bila jumlah periode terpilihnya tidak nol; payload mencakup `columns`
(`quarter`|`month`), `quarter_names`, dan `period_row_label`
(`SEMESTER`/`TRIWULAN`) yang dipakai baik oleh cetak maupun Excel.

Scope bulanan (2026-10-24): `rpd_bos` kini juga terdaftar pada scope
bulanan. Kolom realisasi hanya bulan terpilih (`month_names` satu bulan),
baris identitas menjadi `BULAN` dengan label nama bulan dan
`month_range` `BULAN <NAMA> <TAHUN>`. Pencocokan pecahan alokasi pada
scope bulanan memakai preseden bulan: pecahan berkoordinat bulan
(`__MONTH_NUMBER` > 0) hanya cocok pada bulannya sendiri; pecahan yang
hanya berkoordinat triwulan (bulan 0) cocok per triwulan yang memuat
bulan terpilih. Dikunci oleh `RpdBosReportTest::test_rpd_monthly_*`.

Catatan regresi (2026-10-24, temuan data nyata sekolah 10260756):
pencocokan awal memakai OR bulan/triwulan seperti scope lain, padahal
resolver selalu menurunkan `__QUARTER_NUMBER` dari bulan pecahan —
akibatnya RPD bulan 4 dan bulan 6 sama-sama menjumlah seluruh pecahan
TW II (Apr+Mei+Jun) pada kolom Jumlah sehingga hasil antarbulan tidak
konsisten. Preseden bulan di atas memperbaikinya; scope triwulan dan
semester tidak berubah. Dikunci oleh
`RpdBosReportTest::test_rpd_monthly_allocation_matches_only_its_own_month_fraction`.

Catatan regresi (2026-10-21): perulangan pecahan periode di `rpdData`
memakai nama variabel `$period` sehingga menimpa parameter scope/period;
akibatnya data nyata yang sudah punya koordinat periode (pecahan bulan)
jatuh kembali ke kolom bulan meskipun scope semester. Variabel perulangan
kini bernama `$periodRow`, dan `$totals` diinisialisasi sebelum cabang
sumber dana sehingga laporan tetap terbentuk saat sumber dana belum
terpilih. Keduanya dikunci oleh `RpdBosReportTest`
(`..._when_rows_carry_period_coordinates` dan
`..._without_fund_source_renders_empty_quarter_grid`).

## Format dengan presenter generik

Beberapa report key belum mempunyai formulir resmi khusus dan memakai
presenter data yang sudah tersedia:

- `rekap_belanja_modal_barang_jasa`, `rekap_bmd`, dan
  `rekap_belanja_dana_bos` → `account_summary` (kolom No, Kode/Nama
  Rekening, jumlah transaksi, bruto, pajak, realisasi netto);
- `lampiran_berita_acara_rekonsiliasi` → `transaction_recap` (rincian
  transaksi: tanggal, bukti, kegiatan, rekening, uraian, bruto, pajak,
  netto);
- `berita_acara_rekonsiliasi` → `statement` dengan narasi rekonsiliasi
  periode;
- `buku_pembantu_bank` → `bank_ledger` (ledger BKU sisi bank).

Untuk format-format ini, bentuk dan rumus resmi tetap RVR dan harus
mengikuti kontrak template/formula yang disepakati kemudian.

## Format K7 (Formulir BOS-K7 resmi, 2026-10-12)

Presentasi `k7` (`format_k7`, bulanan/triwulan/semester/tahunan):
"Realisasi Penggunaan Dana Tiap Jenis Anggaran" mengikuti formulir
resmi (struktur dibandingkan terhadap contoh TW II 2025): kop
identitas sekolah + kotak "Formulir Bos-K7", tabel 9 kolom (No. Kode
| Uraian Kegiatan | Jumlah | Rutin | BOS Pusat/Provinsi/Kab-Kota |
Bantuan Lain | Sumber Pendapatan Lainnya), hierarki A (penerimaan: saldo awal, rutin,
BOS, bantuan lain, sumber pendapatan lainnya, total) → B/I
(Program Sekolah: 8 program → sub-program → kegiatan dari kode
kegiatan transaksi) → II (total penggunaan) → C (sisa = I-II),
dan tanda tangan Komite Sekolah + Kepsek + Bendahara (+ tempat/
tanggal). Warna baris: header krem `#f2f1eb`, baris A/B/I abu
`#d9d9d9`, program pink `#ffd6d6`, sub-program hijau `#d4ffd4`.

Rendisi print saat ini memakai nomor kolom berurutan 1–9, lebar tabel
fixed, angka kolom dan kode rata tengah, font nominal 7px, serta
orientasi portrait kertas A4. Contoh resmi yang menjadi acuan sebelumnya
memiliki label kolom 1,2,3,4,5,8,9,10,11 dan orientasi landscape folio;
kesesuaian visual rendisi terbaru terhadap formulir resmi belum
diverifikasi dan tetap RVR.

Nominal penggunaan memakai `gross_amount` seperti A-1; kolom sumber
dana mengikuti nama fund source aktif (`k7FundColumn`, default
Pusat untuk BOSP/BOS Reguler). Transaksi tanpa kode kegiatan 01–08
dilaporkan sebagai unmapped (tidak masuk JUMLAH).
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

Scope bulanan (2026-10-24): `format_k7` juga terdaftar pada scope
bulanan; penerimaan dan penggunaan dihitung dari bulan terpilih saja
(aturan penerimaan BOS = Tahap/2 tetap hanya berlaku scope triwulan).
Dikunci oleh `FormatK7MonthlyReportTest`.

Implementasi: `SpjPeriodicReportPrintService::k7Data/k7Title/
k7PeriodQualifier/k7FundColumn/k7Receipts/k7UsageHierarchy`,
`BospRekapStandardMapper::k7Programs/k7SubProgramLabel`, cabang `k7`
di `document.blade.php` + `document-styles.blade.php`,
`SpjPeriodicReportExcelService::writeK7/styleK7Header`.

## Format BOS K-2 (2026-10-16)

Presentasi `k2` (`format_k2`, scope bulanan/triwulan/semester): "RENCANA KEGIATAN
DAN ANGGARAN SEKOLAH ( RKAS )" mengikuti formulir resmi (terverifikasi
terhadap contoh TW I 2026 SDN 316 Ranto Panjang): kop identitas
sekolah + kotak "Format BOS K-2", tabel No | Uraian | Jumlah (dalam
Rp) | Triwulan (I–IV) dengan baris "I Program Sekolah" → 1.1–1.8
(8 program resmi) → JUMLAH, dan tanda tangan Komite Sekolah + Kepala
Sekolah + Bendahara (+ tempat/tanggal). Landscape folio. Bagian
"II Belanja Lainnya"/"2.1" dihapus dari cetak/PDF dan Excel
(2026-10-20).

Data anggaran per program per triwulan dari
`RkasReportService::build('tahunan')` — baris level `program` membawa
rincian `tw` 1–4 dari periode mirror ARKAS; baris program dipetakan ke
1–8 via `BospRekapStandardMapper::a1RowForActivity()`. Bila mirror
belum punya rincian periode, total tahunan ditampilkan di kolom
triwulan berjalan dan nol di lainnya (keterbatasan tercatat di
PHPDoc `k2Data()`).

Kolom kumulatif (2026-10-20): laporan triwulan N hanya memuat anggaran
sampai triwulan N. Kolom triwulan setelahnya tetap ada namun bernilai
0 (anggaran belum dilaporkan), dan kolom "Jumlah" adalah penjumlahan
kolom yang tampil (kumulatif), bukan pagu tahunan penuh. Semua baris
program 1.1–1.8 tetap ditampilkan. Contoh: laporan TW II memuat TW I +
TW II; TW III/IV = 0.

Scope semester (2026-10-21): `format_k2` juga terdaftar pada scope
semester dan merender **dua kolom `Semester I` dan `Semester II`**
(pengganti kolom TW I–IV). Laporan Semester I hanya memuat Semester I,
sedangkan laporan Semester II bersifat kumulatif (Semester I + Semester
II) dengan kolom "Semester I" tetap menampilkan angka semester
pertama; kolom "Jumlah" adalah penjumlahan kolom yang tampil.
Pemetaan periode: Semester I = TW 1–2, Semester II = TW 3–4. Judul
identitas memakai `period_row_label` (`SEMESTER`/`TRIWULAN`) dan
`period_range` dari `k2MonthRangeFromMonths()`, mis. Semester II =
"01 Juli s.d 31 Desember <tahun>".

Scope bulanan (2026-10-24): `format_k2` juga terdaftar pada scope
bulanan. Kolom tetap TW I–IV dengan batas kumulatif triwulan yang memuat
bulan terpilih (kolom TW setelahnya 0); label periode memakai nama
bulan dan `period_range` adalah rentang bulan itu sendiri. Dikunci oleh
`FormatK2ReportTest::test_k2_monthly_*`.

Implementasi: `SpjPeriodicReportPrintService::k2Data/k2QuarterLabel/
k2SemesterLabel/k2MonthRange/k2MonthRangeFromMonths`, cabang `k2` di
`document.blade.php`, partial `report-k2-1.blade.php` (5 kolom semester /
7 kolom triwulan) dan `report-k2-2.blade.php`,
`SpjPeriodicReportExcelService::writeK2`, registrasi
`SpjPeriodicReportRegistry` scope bulanan + triwulan + semester.
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.

## SPTJM (2026-10-08)

Presentasi `sptjm_doc` (`sptjm`, semua scope): kop sekolah, judul
SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK, identitas penanda tangan
(Kepsek; NIK isian manual), redaksi resmi (tanggung jawab penuh atas
penggunaan dana + nominal penerimaan/penggunaan periode dari mirror,
kesediaan ganti rugi kerugian negara, penyimpanan bukti untuk aparat
pengawas), tempat/tanggal, dan tanda tangan tunggal Kepsek +
"Materai 10.000". Status visual/runtime: RVR sampai QA browser/PDF
aktual dijalankan.

Implementasi: `SpjPeriodicReportPrintService::sptjmData`, cabang
`sptjm_doc` di `document.blade.php`,
`SpjPeriodicReportExcelService::writeSptjm`.

## Lebar kolom persentase (2026-10-20)

Seluruh tabel cetak di `periodic-reports/partials/**` tidak lagi memakai
lebar piksel/otomatis. Setiap tabel merender `<colgroup>` dari array
persentase per kolom melalui partial bersama
`periodic-reports/partials/reports/colgroup.blade.php`
(`@include(..., ['widths' => [...]])`). Gaya tabel memakai
`table-layout: fixed`, sementara aturan `width` piksel pada
`document-styles.blade.php` (`.meta-table td:first-child`,
`.bku-identity-block`, `.signature-table td`, `.bpk-table`) dihapus atau
diubah menjadi persentase.

Array lebar per format:

- BKU/Pembantu Kas 8 kolom, Pembantu Bank 9, Pajak 12,
  `transaction_recap` 9, `account_summary` 7, dan fallback bagi rata
  untuk presentasi lain (`report-ledger-1.blade.php`);
- Rekap BOSP 15 kolom (No, Standar Nasional Pendidikan, 12 sub program,
  Jumlah), ringkasan 9 kolom, meta identitas 2/4 kolom; sejak 2026-10-21
  lebar K7A dan BPK dibaca dari
  `config('spj.periodic_report_column_widths')` (K7A juga memakai
  `identity` dan `meta`, BPK memakai `meta`/`table`/`signature`), dengan
  fallback default di partial. Angka persentase aktif mengikuti isi
  konfigurasi tersebut, bukan konstanta view;
- RPD 7 kolom dasar + jumlah kolom bulan; sisa 28% dibagi rata ke kolom
  bulan (`report-rpd-2.blade.php`);
- A1 8 kolom, K-2 7 kolom triwulan / **5 kolom semester** (No, Uraian,
  Jumlah, Semester I, Semester II), K7 9 kolom, K7B 3 kolom, K7C 2 kolom,
  BPK 6 kolom, serta tabel identitas/tanda tangan 2–3 kolom.

`$rpdColspan` pada RPD ikut dikoreksi menjadi jumlah kolom sebenarnya
(`count($rpdBaseWidths) + count($rpdMonths)`), sehingga baris nomor
1..N dan baris "PROGRAM SEKOLAH" selaras dengan 10 kolom (7 + 3 bulan).
Tidak ada perubahan pada registry, perhitungan nilai, tenant boundary,
penomoran, atau lifecycle dokumen.

## Status verifikasi (2026-10-21)

Sinkronisasi dokumentasi registry terhadap source diverifikasi dengan:

```text
SpjPeriodicReportRegistryTest (Feature + Unit),
SpjPeriodicReportPrintableTest,
SpjPeriodicReportModuleUiTest                  : 13 passed / 103 assertions
SpjPeriodicReportRegistry::packages()          : 37 slot / 18 report key
```

Perubahan scope semester (K-2 dua kolom semester + RPD kolom triwulan)
diverifikasi dengan:

```text
FormatK2ReportTest        : 9 passed / 105 assertions
RpdBosReportTest          : 14 passed / 136 assertions
```

Regression gabungan 11 kelas (RpdBosReport, BkuOfficialLedger, BosA1Report,
FormatK2Report, K7bK7cReport, SpjPeriodicReportPrintable,
SpjPeriodicReportRegistry Feature + Unit, SpjPeriodicReportModuleUi,
SpjReportLayout, WebRouteSmoke): **80 passed / 776 assertions** (dijalankan ulang setelah perbaikan kolom RPD semester).

Aturan kolom Belanja Modal A-1 (2026-10-21, dikonfirmasi pengguna):
kode rekening `5.2` = BELANJA MODAL, `5.2.02` = BELANJA MODAL PERALATAN
DAN MESIN, `5.2.05` (dan `5.2` lainnya) = BELANJA MODAL ASET TETAP
LAINNYA. Teks jabatan penanda tangan A-1 juga dikonfirmasi memakai
"Bendahara / Pemegang Kas" (bukan "Pemegang Kas Sekolah"), sehingga
assertion `BosA1ReportTest` diselaraskan dengan source.

Perubahan lebar kolom persentase diverifikasi dengan:

```text
BkuOfficialLedgerTest, BpkDinasFormatTest, BosA1ReportTest,
FormatK2ReportTest, RpdBosReportTest, K7bK7cReportTest,
RekapBudgetRealizationTest, SpjPeriodicReportPrintableTest,
SpjPeriodicReportModuleUiTest                 : 48 passed / 485 assertions
php artisan view:clear + view:cache           : Blade templates cached
```

Kontrak **source/data** yang tercatat di dokumen ini tetap mengacu pada
regression per format (`BkuOfficialLedgerTest`, `BpkDinasFormatTest`,
`BosA1ReportTest`, `FormatK2ReportTest`, `RpdBosReportTest`,
`K7bK7cReportTest`, `RekapBudgetRealizationTest`,
`SpjPeriodicReportPrintableTest`). Tampilan cetak/PDF tetap **RVR** karena
belum dibuka di browser/PDF viewer aktual pada batch dokumentasi ini.
