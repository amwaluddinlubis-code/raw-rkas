# Laporan Resmi & Format BOS — Log Kemajuan

> Riwayat pekerjaan laporan resmi: BOS K7A/K7B/K7C, Format K7, SPTJM, BOS A-1, dan Bulk Preview Laporan SPJ. K7A, SPB/SP2B/SP2T, BOS K8, dan Form 1C kemudian dihapus dari registry; entri lama dipertahankan sebagai jejak historis.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Format BOS K-2 dan RPD BOS pada scope semester (2026-10-21)

Status: **SOURCE IMPLEMENTED / FOCUSED VERIFIED / BROWSER–PDF RVR**.

Atas permintaan pengguna, scope semester kini memuat `format_k2` dan
`rpd_bos` (registry bertambah dari 35 menjadi **37 slot**, tetap **18 report
key**; label K7A menjadi "Rekapitulasi Pengeluaran Dana BOS (K7A)").

- Format BOS K-2 scope semester merender **dua kolom `Semester I` dan
  `Semester II`** (Semester I hanya memuat Semester I; Semester II kumulatif
  Semester I + II, kolom "Jumlah" = jumlah kolom yang tampil). Rentang
  tanggal identitas memakai `period_range`, mis. Semester II = "01 Juli s.d
  31 Desember <tahun>".
- RPD BOS scope semester memakai **kolom triwulan**: Triwulan I & II untuk
  Semester I dan Triwulan III & IV untuk Semester II. Nilai/Vol/Satuan/Tarif
  tetap mengikuti alokasi periode terpilih.
- Perbaikan 2026-10-21: render dengan data mirror nyata (SDN 316 Ranto
  Panjang, TA 2026) menunjukkan RPD semester tetap memakai kolom bulan.
  Penyebabnya perulangan pecahan periode di `rpdData` memakai variabel
  `$period` sehingga menimpa parameter scope/period setelah baris berkoordinat
  periode diproses; variabel kini `$periodRow`. `$totals` juga diinisialisasi
  sebelum cabang sumber dana agar laporan tetap terbentuk (tanpa galat
  `rpdQuarterSums()`/`$totals`) saat sumber dana belum dipilih. Bukti render
  nyata: header RPD semester II = `Triwulan || III || IV`, semester I =
  `Triwulan || I || II`; triwulan (TW III) tetap `Bulan || Juli…September`.
- Excel (`writeK2`, `writeRpd`) dan kedua partial cetak
  (`report-k2-1/2.blade.php`, `report-rpd-1/2.blade.php`) kini
  column-agnostic: label kolom, colspan, dan lebar kolom mengikuti payload
  (`columns`, `group_label`, `period_row_label`).

Verifikasi:

```text
tests/Feature/FormatK2ReportTest.php   : 9 passed / 105 assertions
tests/Feature/RpdBosReportTest.php     : 14 passed / 136 assertions
tests/Feature/RekapBudgetRealizationTest.php : 6 passed / 64 assertions
tests/Feature/BpkDinasFormatTest.php   : 3 passed / 33 assertions
SpjPeriodicReportRegistry (Feature + Unit) + Printable + ModuleUi
  : 13 passed / 103 assertions
Regresi 11 kelas laporan periode       : 80 passed / 776 assertions
```

Aturan kolom Belanja Modal A-1 dikonfirmasi pengguna: kode rekening `5.2` =
BELANJA MODAL, `5.2.02` = BELANJA MODAL PERALATAN DAN MESIN, `5.2.05` (dan
`5.2` lainnya) = BELANJA MODAL ASET TETAP LAINNYA — sesuai implementasi
`a1ModalColumn`. Teks jabatan penanda tangan A-1 juga dikonfirmasi
"Bendahara / Pemegang Kas", dan sel nominal A-1 dikonfirmasi tanpa prefix
`Rp ` (mis. `1.000.000`, sel nol `-`), sehingga assertion `BosA1ReportTest`
diperbaiki ke bentuk terbaru.

Kertas `rekapitulasi_pengeluaran_dana_bos` (K7A): atas permintaan pengguna
2026-10-21, presentasi `rekap_bosp` kini memakai **orientasi landscape**
(A4 landscape) agar grid 7 SNP × 12 sub program tidak terpotong; kontrak
dikunci `RekapBudgetRealizationTest` (payload `orientation`/`paper` +
`size: A4 landscape;` pada rute cetak). Label K7A juga diseragamkan menjadi
"Rekapitulasi Pengeluaran Dana BOS (K7A)" di keempat scope.

Pemecahan blok menjadi partial (2026-10-21, permintaan pengguna): blok K7A
dipindah ke `partials/reports/report-rekap-bosp-1.blade.php` dan blok BPK ke
`partials/reports/report-bpk-1.blade.php` (partial lama
`partials/bpk-dinas.blade.php` dihapus) sehingga `document.blade.php` hanya
memanggil `@include`. Lebar kolom kedua laporan kini **array yang dapat
dikustom** dari `config('spj.periodic_report_column_widths')`:
- `rekap_bosp`: `no`, `standar_nasional`, `sub_program`, `jumlah` (kolom
  grid), `identity`, `meta`;
- `bpk_bos`: `meta`, `table`, `signature`.

Kunci yang tidak diisi memakai default di partial, dan override diuji dengan
`config()->set()` pada `RekapBudgetRealizationTest` dan `BpkDinasFormatTest`.
Bukti render data nyata (SDN 316 Ranto Panjang, TA 2026, sumber dana 1): K7A
`orientation=landscape paper=a4` dengan colgroup `25/75`, `4/16/5.5×12/14`,
`60/40`; BPK `orientation=landscape paper=folio` dengan colgroup
`30/70`, `6/40/12/14/14/14`, `50/50`.

## BOS A-1: kolom Belanja Modal diisi dari kode rekening (2026-10-20)

Status: **SOURCE IMPLEMENTED / FOCUSED VERIFIED / BROWSER–PDF RVR**.

Rincian Belanja Modal A-1 yang sebelumnya selalu 0 kini dipetakan dari
kode rekening transaksi: `5.2.02.*` → Peralatan dan Mesin, `5.2`
lainnya (mis. `5.2.05.*` buku/aset tetap lainnya) → Aset Tetap Lainnya
BOS, keduanya menambah Jumlah Modal dan TOTAL baris. Baris honor tetap
Pegawai (sub-program 12), sisanya Barang dan Jasa. Kelas transaksi modal
data nyata umumnya berkode kegiatan `02.34.*`/`05.08.*`, sehingga aturan
kode kegiatan `05.02.*` pada implementasi sebelumnya salah kolom.
Fallback lama (kegiatan `05.02.*` atau uraian "buku") hanya dipakai bila
kode rekening kosong. Tidak ada perubahan registry, tenant boundary,
penomoran, atau lifecycle dokumen.

Implementasi: `SpjPeriodicReportPrintService::bosA1Data` +
`a1ModalColumn`.

Verifikasi:

```text
php artisan test --compact tests/Feature/BosA1ReportTest.php
  : 5 passed / 52 assertions (termasuk test baru
    test_bos_a1_splits_belanja_modal_by_account_code: 5.2.02 -> mesin,
    5.2.05 -> aset tetap lainnya, Jumlah Modal 3.500.000, grand 6.500.000)
Regresi 9 kelas laporan periode
  (RpdBosReport, BkuOfficialLedger, BosA1Report, FormatK2Report,
   K7bK7cReport, SpjPeriodicReportPrintable, SpjPeriodicReportRegistry,
   SpjPeriodicReportModuleUi, SpjReportLayout)
  : 61 passed / 654 assertions
vendor/bin/pint --dirty --format agent  : passed
```

Bukti data nyata (mirror sekolah 10208246, 2026): transaksi belanja
berekening `5.2.02.*` = Rp 48.080.000 dan `5.2.05.*` = Rp 50.820.000,
sebelumnya sebagian besar jatuh ke Barang dan Jasa.

## Format BOS K-2 kumulatif per triwulan (2026-10-20)

Status: **SOURCE IMPLEMENTED / FOCUSED VERIFIED / BROWSER–PDF RVR**.

Laporan K-2 triwulan N kini hanya memuat anggaran sampai triwulan N:
kolom TW setelah periode terpilih tetap dirender namun bernilai 0, dan
kolom Jumlah memakai penjumlahan kolom yang tampil (kumulatif), bukan
pagu tahunan penuh. Semua baris program 1.1–1.8 tetap ditampilkan.
Bila mirror belum punya rincian periode, total tahunan tetap jatuh ke
kolom triwulan berjalan. Bagian "II Belanja Lainnya"/"2.1" dihapus dari
tampilan cetak dan export Excel (perubahan view oleh operator,
dikonfirmasi 2026-10-20).

Implementasi: `SpjPeriodicReportPrintService::k2Data` (zero-kan tw >
quarter, Jumlah = sum tw tampil) dan `SpjPeriodicReportExcelService::
writeK2` (baris II/2.1 dihapus).

Verifikasi:

```text
php artisan test --compact tests/Feature/FormatK2ReportTest.php
  : 5 passed / 58 assertions (termasuk test baru
    test_k2_shows_cumulative_quarters_until_selected_period)
vendor/bin/pint --dirty --format agent  : passed
```

Browser/PDF viewer belum dijalankan dan tetap **RVR**.

## RPD BOS: Jumlah/Vol/Satuan/Tarif mengikuti periode terpilih (2026-10-20)

Status: **SOURCE IMPLEMENTED / FOCUSED VERIFIED / BROWSER–PDF RVR**.

`SpjPeriodicReportPrintService::rpdData` kini menurunkan kolom Jumlah,
Vol, Satuan, dan Tarif Harga dari pecahan `arkas_mirror_rapbs_periode`
pada snapshot (`periods`) yang `__MONTH_NUMBER`-nya berada di rentang
bulan triwulan terpilih atau `__QUARTER_NUMBER`-nya cocok. `JUMLAH` dan
volume dijumlahkan; satuan/tarif memakai pecahan pertama yang terisi.
Baris yang punya pecahan periode terkoordinat tetapi tidak teralokasi
di periode terpilih berjumlah 0 dan **tidak ditampilkan** (bukan angka
tahunan); `has_data` hanya benar bila ada baris tampil. Payload
tahunan `arkas_mirror_rapbs` hanya dipakai sebagai fallback saat baris
tidak punya pecahan periode terkoordinat sama sekali (mis. baris SPPD
tanpa split di data nyata). Agregat sub/kegiatan/program, Sub Total,
dan JUMLAH TOTAL ikut menjumlahkan angka periode ini. Excel memakai
payload `rpd` yang sama sehingga konsisten.

Tidak ada perubahan registry, realisasi bulanan, tenant boundary,
penomoran, atau lifecycle dokumen.

Verifikasi:

```text
php artisan test --compact tests/Feature/RpdBosReportTest.php
  : 8 passed / 90 assertions (termasuk test baru
    test_rpd_volume_unit_and_unit_price_follow_selected_period yang juga
    memeriksa baris berjumlah 0 tidak tampil dan Jumlah per periode)
Regresi 9 kelas laporan periode
  (RpdBosReport, BkuOfficialLedger, BosA1Report, FormatK2Report,
   K7bK7cReport, SpjPeriodicReportPrintable, SpjPeriodicReportRegistry,
   SpjPeriodicReportModuleUi, SpjReportLayout)
  : 59 passed / 636 assertions
vendor/bin/pint --dirty --format agent  : passed
```

Cek data nyata (school 10208246): "Biaya Paket Data" volume tahunan 28
paket, alokasi Triwulan I = 6 paket — rendisi kini memakai 6 untuk
Triwulan I. Total alokasi periode 81–83 (Triwulan I)
Rp 716.945.400 dan 87–89 (Triwulan III) Rp 793.793.000, dibanding pagu
tahunan Rp 2.725.108.000. Dari 1.459 baris RKAS, 598 baris punya
alokasi Triwulan I; sisanya (teralokasi di triwulan lain) kini tidak
tampil di RPD Triwulan I. Baris SPPD tanpa split periode tetap memakai
nilai tahunan. Browser/PDF viewer belum dijalankan dan tetap **RVR**.

## Lebar kolom cetak Laporan Periode menjadi persentase (2026-10-20)

Status: **SOURCE IMPLEMENTED / FOCUSED VERIFIED / BROWSER–PDF RVR**.

Semua tabel cetak `resources/views/periodic-reports/partials/**`
sekarang memakai `<colgroup>` berisi lebar persentase per kolom melalui
partial bersama `reports/colgroup.blade.php`. `document-styles.blade.php`
memakai `table-layout: fixed` dan tidak lagi mendefinisikan lebar piksel
untuk `.meta-table td:first-child`, `.bku-identity-block`,
`.signature-table td`, serta `.bpk-table`.

Lebar didefinisikan sebagai array per format: BKU/Pembantu Kas 8 kolom,
Pembantu Bank 9, Pajak 12, `transaction_recap` 9, `account_summary` 7,
Rekap BOSP 15, ringkasan 9, A1 8, K-2 7, K7 9, K7B 3, K7C 2, BPK 6, dan
tabel identitas/tanda tangan 2–3 kolom. RPD memakai 7 kolom dasar dan
membagi sisa 28% rata ke kolom bulan. `$rpdColspan` dikoreksi menjadi
jumlah kolom sebenarnya sehingga baris nomor 1..N dan "PROGRAM SEKOLAH"
selaras.

Tidak ada perubahan registry, nilai, tenant boundary, penomoran, atau
lifecycle dokumen. `BkuOfficialLedgerTest` disesuaikan agar ikut membaca
partial `colgroup.blade.php` saat memeriksa keberadaan `<colgroup>`.

Verifikasi:

```text
BkuOfficialLedgerTest, BpkDinasFormatTest, BosA1ReportTest,
FormatK2ReportTest, RpdBosReportTest, K7bK7cReportTest,
RekapBudgetRealizationTest, SpjPeriodicReportPrintableTest,
SpjPeriodicReportModuleUiTest                 : 48 passed / 485 assertions
php artisan view:clear + view:cache           : Blade templates cached
```

Browser/PDF viewer aktual (lebar kolom, page break, hasil cetak) belum
dijalankan dan tetap **RVR**.

## Sinkronisasi registry & dokumentasi laporan periode (2026-10-20)

Status: **DOCUMENTATION SYNC / SOURCE VERIFIED (registry) / BROWSER RVR**.

Commit `c893e3f` menghapus `bos_k7a`, `spb`, `sp2b`, `lampiran_sp2b`, dan
`sp2t` dari registry/presenter, lalu commit `130c47d` menghapus `bos_k8`
dan `form_1c`. Dokumentasi belum mengikuti perubahan tersebut. Registry
aktual sekarang berisi **35 slot** (bulanan 10, triwulan 10, semester 8,
tahunan 7) dari **18 report key** — angka ini adalah keadaan 2026-10-20 dan
sudah menjadi **historis** sejak scope semester memuat K-2, RPD, dan K7A
pada 2026-10-21 (**37 slot** / 18 report key; lihat entri terbaru di atas).

Dokumen yang diselaraskan: `SPJ_PERIODIC_REPORTING.md` (daftar paket per
scope + key, catatan key yang dihapus, peta presentasi),
`PERIODIC_REPORT_MODULE.md` (hapus panduan K7A yang sudah tidak ada,
tambah panduan RPD BOS dan format presenter generik), dan
`docs/README.md` (angka slot/key). Tidak ada perubahan source, registry,
business rule, tenant boundary, numbering, atau lifecycle.

Verifikasi yang dijalankan:

```text
php artisan test --compact tests/Feature/SpjPeriodicReportRegistryTest.php \
  tests/Unit/SpjPeriodicReportRegistryTest.php
hasil: 5 passed / 35 assertions
```

Browser/PDF runtime tidak dijalankan pada batch dokumentasi ini dan tetap
RVR. Angka slot/key pada entri historis di bawah (mis. 47 slot/23 key)
adalah keadaan saat entri tersebut ditulis, bukan status registry sekarang.

## Penyesuaian layout cetak Format K7 (2026-10-19)

Rendisi print K7 diperbarui dengan layout tabel fixed-width, font nominal
7px, alignment tengah untuk kolom angka/kode, label kolom berurutan 1–9,
identitas Kabupaten/Provinsi berprefix `Kab.`/`Prov.`, serta orientasi
portrait A4. Panduan format mencatat bahwa nomor kolom dan orientasi ini
berbeda dari contoh resmi yang menjadi acuan; visual fidelity terbaru
belum diverifikasi dan tetap RVR.

Focused verification: `php artisan test --compact
tests/Feature/SpjPeriodicReportPrintableTest.php` — 5 passed / 47
assertions; Pint, `npm run build`, dan `php artisan view:cache
--no-interaction` berhasil. Full CI dan browser/PDF aktual belum
dijalankan.

## RPD BOS baru (2026-10-16)

Laporan "RINCIAN PENGGUNAAN DANA BOS (RPD BOS)" ditambahkan ke scope
triwulan (`rpd_bos`), mengikuti contoh resmi TW I 2026 SDN 316 Ranto
Panjang: kop identitas + kotak "RPD", tabel No | Kode Program | Uraian |
Rincian Perhitungan (Vol | Satuan | Tarif Harga) | Jumlah | Bulan
(Januari/Februari/Maret), hierarki I PROGRAM SEKOLAH → 8 program → sub →
kegiatan → item, footer Sub Total I/II + JUMLAH TOTAL, TTD
Mengetahui/Kepala/Bendahara, landscape folio; Excel mengikuti. Kolom
Jumlah = pagu RKAS; kolom bulan = realisasi BELANJA kas umum per
ID_RAPBS; Vol/Satuan/Tarif dari mirror rapbs.

Regression: `RpdBosReportTest` 7 passed / 77 assertions; Pint passed.
Browser/PDF aktual RVR.

## Format BOS K-2 baru (2026-10-16)

Laporan "RENCANA KEGIATAN DAN ANGGARAN SEKOLAH ( RKAS )" Format BOS
K-2 ditambahkan ke scope triwulan (`format_k2`), mengikuti contoh
resmi TW I 2026 SDN 316 Ranto Panjang: kop identitas + kotak "Format
BOS K-2", tabel No | Uraian | Jumlah (dalam Rp) | Triwulan (I–IV),
baris "I Program Sekolah" → 1.1–1.8 → "II Belanja Lainnya" → "2.1" →
JUMLAH, TTD Komite/Kepsek/Bendahara, landscape folio; Excel
mengikuti. Data anggaran per program per triwulan dari
`RkasReportService::build('tahunan')` (rincian `tw` mirror ARKAS);
tanpa rincian periode, total tahunan tampil di kolom triwulan
berjalan.

Regression: `FormatK2ReportTest` 4 passed / 50 assertions (struktur
data 8 program + total, fallback tanpa data, print route, excel);
K7aFormatK7Sptjm 13 passed; Pint passed. Browser/PDF aktual RVR.

## Format K7 ditulis ulang ke formulir resmi (2026-10-12)

Format K7 lama (tabel per jenis anggaran 5.1.01/5.1.02/5.2 per bulan)
diganti total mengikuti Formulir BOS-K7 resmi — contoh TW II 2025 dari
pengguna: kop identitas + kotak Formulir Bos-K7, tabel 9 kolom bernomor
1,2,3,4,5,8,9,10,11, hierarki A → B/I (8 program → sub → kegiatan) →
II → C (sisa), warna baris resmi, TTD Komite/Kepsek/Bendahara,
landscape folio; Excel mengikuti struktur yang sama.

Temuan saat pengerjaan: `SpjPeriodicReportUseCase::applyPeriod` join
mirror tanpa select eksplisit sehingga kolom `id` mirror menimpa `id`
transaksi pada model (relasi items + agregat mirror bergeser bila
urutan id kedua tabel tidak selaras — termask di test A1 lama karena
id kebetulan selaras). Diperbaiki dengan `select('transactions.*')`.

Regression: `K7aFormatK7SptjmReportTest` 8 passed / 105 assertions
(data + struktur resmi, print route, excel); BkuOfficialLedger,
BosA1, BpkDinas, K7bK7c, RekapBudgetRealization,
SpjPeriodicReportPrintable 32 passed; Pint passed. Browser/PDF
aktual tetap RVR.

## BOS K7A + Format K7 + SPTJM resmi (2026-10-08)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR, AKURASI PER-KOMPONEN K7A RVR**.

Tahap 2–4 perbaikan bertahap K7 & SPTJM. `bos_k7a` keluar dari
`activity_summary` generik menjadi presenter 12 komponen ARKAS resmi
(2 baris SMK ditandai) dengan pemetaan kata kunci NAMA_KEGIATAN
(`BospRekapStandardMapper::k7aComponentForActivity`) — mirror terbukti
tidak membawa field komponen (audit read-only payload tenant 10208183)
— plus rekonsiliasi `total + unmapped = belanja` yang selalu eksplisit.
`format_k7` menjadi tabel realisasi per jenis anggaran (prefix rekening
5.1.01/5.1.02/5.2) per bulan scope + JUMLAH + blok Lampiran + TTD
3 pihak. `sptjm` menjadi dokumen redaksi resmi dengan nominal
penerimaan/penggunaan periode + TTD tunggal Kepsek + materai. Tidak ada
perubahan registry/scope/tenant/numbering/sync.

Regression: `K7aFormatK7SptjmReportTest` 8 passed / 71 assertions
(mapper, data + rekonsiliasi, print route K7A/K7/SPTJM, excel),
Printable + guard presenter baru, K7bK7c 5 passed, BosA1 4 passed,
filter PeriodicReport 13 passed, BkuOfficialLedger 12 passed; Pint
passed; `view:cache` + `git diff --check` bersih. Browser/PDF aktual
tetap RVR; per-komponen K7A menunggu pembanding keluaran resmi.

## Register Penutupan Kas K7B + Berita Acara K7C resmi (2026-10-07)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Tahap 1 dari perbaikan bertahap K7 & SPTJM. `k7b`/`k7c` (scope bulanan)
keluar dari fallback `statement` generik menjadi presenter resmi mengikuti
formulir BOS-K7B/K7C: D/K/A dari ledger BKU periode yang sama, rincian
pecahan sebagai isian manual, Perbedaan (A-B), dan tanda tangan
Bendahara + Kepsek. Nomor SK pada K7C memakai placeholder manual karena
tidak tersedia di skema. Tidak ada perubahan registry/scope/tenant/
numbering/sync; nilai tetap dari mirror via `bkuLedgerRows`.

Regression: `K7bK7cReportTest` 5 passed / 45 assertions (data, print
route K7B+K7C, excel), Printable 5 passed, BosA1 4 passed,
filter PeriodicReport 13 passed, BkuOfficialLedger 12 passed; Pint
passed; `view:cache` + `git diff --check` bersih. Browser/PDF aktual
tetap RVR.

## Generator Format BOS A-1 (2026-10-07)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Laporan Periode triwulan bertambah `bos_a1` ("Format BOS A-1"): grid 8
program resmi × (Belanja Pegawai | Barang dan Jasa | 3 kolom Belanja Modal
| TOTAL) + baris TOTAL, sel nol sebagai `Rp -`, kop Format BOS A-1,
identitas desa/kecamatan, tanda tangan Menyetujui Kepsek + Pemegang Kas
Sekolah (tempat + tanggal akhir periode + NIP). Orientasi landscape folio;
Excel menulis grid + baris TOTAL.

Aturan grid terverifikasi terhadap keluaran resmi TW IV 2024 (seluruh angka
cocok per baris, total 56.055.000): baris = segmen pertama kode kegiatan
01–08; kolom Pegawai = sub-program 12 honorarium; selainnya Barang dan
Jasa (termasuk baris berkode rekening `5.2.*` — aturan prefix rekening
ditolak bukti). Kolom modal dipertahankan nol + RVR: seluruh sampel resmi
bernilai nol sehingga belum ada aturan populasi terverifikasi. Registry
triwulan 12→13 slot (47 total, 23 key); kontrak teknis, indeks, dan
regression count diselaraskan.

> **Disuperseded 2026-10-20**: kolom modal kini diisi dari kode rekening
> (`5.2.02.*` → Peralatan dan Mesin, `5.2` lain → Aset Tetap Lainnya BOS);
> lihat entri "BOS A-1: kolom Belanja Modal diisi dari kode rekening".

Regression: `BosA1ReportTest` 4 passed / 40 assertions (data, print
route, excel), registry Feature+Unit, Printable, RekapBudgetRealization,
BkuOfficialLedger, ModuleUi hijau; Pint passed; `view:cache` +
`git diff --check` bersih. Browser/PDF aktual tetap RVR.

## Bulk Preview paket pada Laporan SPJ — 2026-09-29

Status: **SOURCE IMPLEMENTED / PHP TEST + BROWSER RUNTIME RVR**.

Tab Laporan SPJ menyediakan pencarian, filter periode, checkbox per baris,
pilih semua pada halaman aktif, dan tombol **Pratinjau Massal**. Paket terpilih
dikirim melalui POST dan dikembalikan sebagai satu PDF inline pada iframe modal
yang sama dengan preview dokumen tunggal. Pipeline workbook/PDF-nya sama dengan
preview individu; perbedaannya hanya jumlah halaman, dengan batas maksimal 20
paket dan urutan baris yang dipilih tetap dipertahankan.

Use case memeriksa sekolah, tahun anggaran, dan sumber dana aktif untuk setiap
paket serta memakai template XLSX aktif sesuai paket. Preview tetap read-only
dan tidak menerbitkan nomor SPJ. Regression source/layout telah diperbarui.
Focused verification terbaru: Pint pada file PHP tersentuh, lint PHP, Blade
cache, `SpjReportLayoutTest` 15 test / 188 assertions, dan `git diff --check`
berhasil. Browser modal, jumlah halaman pada PDF nyata, dan hasil cetak masih
**RVR**.

Tahap 2 memperjelas seleksi baris dengan penghitung langsung `x dari 20`,
status instruksi saat belum ada pilihan, dan pesan jumlah paket yang harus
dibatalkan saat melewati batas. Tombol Pratinjau Massal nonaktif ketika belum ada
pilihan atau pilihan melebihi 20; pilih-semua tetap memperbarui penghitung.
Regression source/layout diperluas. Verifikasi PHPUnit dan browser tetap RVR.

Latest full verified code gate tetap run #46 pada `e4ba5cf`; perubahan ini sudah
melewati focused verification lokal, tetapi belum tercakup full CI. Browser dan
operator runtime tetap RVR.

---
