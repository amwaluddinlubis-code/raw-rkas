# SPJ Periodic Reporting

Terakhir diverifikasi terhadap source: **2026-10-21** (`raw-rkas`, branch `main`)

Status: **REGISTRY + DATA ENGINE + INTERNAL PRINT/PDF IMPLEMENTED / VISUAL RUNTIME RVR**

Dokumen ini adalah kontrak teknis modul **Laporan Pertanggungjawaban Periodik** pada workspace SPJ. Modul dipisahkan dari workbook/template dokumen agar daftar laporan, filter periode, dan boundary data dapat stabil walaupun template resmi masih diperbaiki.

Panduan fitur per-format laporan (aturan nilai, mapping sel, batasan presenter) ada di `PERIODIC_REPORT_MODULE.md`.

## Prinsip

1. Semua sumber data wajib dibatasi oleh `ActiveSpjContext`: sekolah aktif, tahun anggaran aktif, dan sumber dana aktif.
2. Penentuan periode menggunakan `transaction_date` dari transaksi yang sudah berada pada konteks aktif.
3. Registry daftar laporan berada di `App\Services\SpjPeriodicReportRegistry` dan menjadi satu-satunya sumber daftar paket laporan pada UI.
4. Query/ringkasan sumber data berada di `App\UseCases\Spj\SpjPeriodicReportUseCase`.
5. Livewire `SpjPeriodicReportCenter` hanya mengelola state UI (jenis periode dan nomor periode), lalu mendelegasikan perhitungan ke use case.
6. Template/formula resmi tetap tidak didefinisikan di registry. Namun lapisan presentasi internal untuk browser print/PDF sudah diimplementasikan melalui `SpjPeriodicReportPrintService`, `PeriodicReportController`, dan view `periodic-reports/*` tanpa mengubah kontrak registry.

## Paket laporan

Daftar di bawah adalah cerminan langsung dari
`SpjPeriodicReportRegistry::packages()`: **41 slot** (bulanan 14 + triwulan 10
+ semester 10 + tahunan 7) dari **18 report key** berbeda. Bila daftar ini
berbeda dengan registry, registry yang benar.

### Bulanan

- SPTJM (`sptjm`)
- Buku Kas Umum (`bku`)
- Buku Pembantu Kas (`buku_pembantu_kas`)
- Buku Pembantu Bank (`buku_pembantu_bank`)
- Buku Pembantu Pajak (`buku_pembantu_pajak`)
- Register Penutupan Kas (K7B) (`k7b`)
- Berita Acara Pemeriksaan Kas (K7C) (`k7c`)
- Rekap Belanja Modal dan Belanja Barang / Jasa (`rekap_belanja_modal_barang_jasa`)
- Format K7 (`format_k7`)
- Format BOS K-2 (`format_k2`)
- RPD BOS (`rpd_bos`)
- Rekapitulasi Pengeluaran Dana BOS (K7A) (`rekapitulasi_pengeluaran_dana_bos`)
- Format BOS A-1 (`bos_a1`)
- Laporan BPK Format BOS (`bpk_bos`)

Periode valid: bulan `1..12`. Catatan scope bulanan (2026-10-24): K7 dan
A-1 memakai transaksi bulan terpilih; K-2 tetap menampilkan kolom TW I–IV
dengan batas kumulatif triwulan yang memuat bulan terpilih dan label
periode nama bulan; RPD memakai satu kolom realisasi bulan terpilih,
baris identitas `BULAN`, dan alokasi pecahan cocok per bulan (atau per
triwulan induk bila pecahan hanya terkoordinat per triwulan).

### Tahap & Triwulan

- SPTJM (`sptjm`)
- Format K7 (`format_k7`)
- Format BOS K-2 (`format_k2`)
- RPD BOS (`rpd_bos`)
- Rekapitulasi Pengeluaran Dana BOS (K7A) (`rekapitulasi_pengeluaran_dana_bos`)
- Buku Kas Umum (BKU) (`bku`)
- Berita Acara Rekonsiliasi (`berita_acara_rekonsiliasi`)
- Lampiran Berita Acara Rekonsiliasi (`lampiran_berita_acara_rekonsiliasi`)
- Laporan BPK Format BOS (`bpk_bos`)
- Format BOS A-1 (`bos_a1`)

Periode valid: triwulan `1..4`.

### Semester

- SPTJM (`sptjm`)
- Format K7 (`format_k7`)
- Format BOS K-2 (`format_k2`)
- RPD BOS (`rpd_bos`)
- Rekapitulasi Pengeluaran Dana BOS (K7A) (`rekapitulasi_pengeluaran_dana_bos`)
- Buku Kas Umum (BKU) (`bku`)
- Berita Acara Rekonsiliasi (`berita_acara_rekonsiliasi`)
- Lampiran Berita Acara Rekonsiliasi (`lampiran_berita_acara_rekonsiliasi`)
- Rekap Belanja Barang Milik Daerah (Aset) (`rekap_bmd`)
- Laporan BPK Format BOS (`bpk_bos`)

Periode valid: semester `1..2`. K-2 pada scope ini merender kolom
`Semester I`/`Semester II`, sedangkan RPD merender kolom triwulan
(I–II untuk Semester I, III–IV untuk Semester II) — lihat
`PERIODIC_REPORT_MODULE.md`.

### Tahunan

- SPTJM (`sptjm`)
- Format K7 (`format_k7`)
- Buku Kas Umum (BKU) (`bku`)
- Rekap Barang Milik Daerah (Aset) (`rekap_bmd`)
- Rekap Belanja Dana BOS (`rekap_belanja_dana_bos`)
- Rekapitulasi Pengeluaran Dana BOS (K7A) (`rekapitulasi_pengeluaran_dana_bos`)
- Laporan BPK Format BOS (`bpk_bos`)

Periode tahunan selalu menggunakan seluruh tahun anggaran aktif dan tidak membutuhkan nomor periode tambahan.

Report key berikut sudah dihapus dari registry dan tidak boleh ditampilkan atau
ditambahkan kembali tanpa perubahan kontrak: `bos_k7a`, `spb`, `sp2b`,
`lampiran_sp2b`, `sp2t` (commit `c893e3f`), serta `bos_k8` dan `form_1c`
(commit `130c47d`).

## Data engine

Untuk periode yang sudah valid, modul menghitung ringkasan sumber data berikut langsung dari transaksi pada konteks aktif:

- jumlah transaksi;
- nilai bruto;
- total pajak;
- nilai dibayarkan/neto;
- PPN;
- PPh 21;
- PPh 22;
- PPh 23;
- PPh 4(2);
- SSPD/Pajak Daerah.

Ringkasan ini adalah **lapisan sumber data**, bukan pengganti rumus atau bentuk resmi masing-masing laporan. Misalnya aturan kolom dan saldo Buku Kas Umum, struktur laporan rekap, atau klasifikasi BMD harus tetap mengikuti kontrak template/formula laporan yang disepakati kemudian.

## Presentasi cetak

`SpjPeriodicReportPrintService::presentation()` memetakan report key ke
presentasi berikut:

- `bku_ledger` — BKU;
- `cash_ledger` — Buku Pembantu Kas;
- `bank_ledger` — Buku Pembantu Bank;
- `tax` — Buku Pembantu Pajak;
- `rekap_bosp` — Rekapitulasi Pengeluaran Dana BOS;
- `bpk_bos` — Laporan BPK Format BOS;
- `bos_a1` — Format BOS A-1;
- `k7b`/`k7c` — Register Penutupan Kas dan Berita Acara Pemeriksaan Kas;
- `k7` — Format K7;
- `k2` — Format BOS K-2;
- `rpd` — RPD BOS;
- `sptjm_doc` — SPTJM;
- `account_summary` — Rekap Belanja Modal/Barang Jasa, Rekap BMD, dan Rekap
  Belanja Dana BOS;
- `transaction_recap` — Lampiran Berita Acara Rekonsiliasi;
- `statement` — fallback, termasuk Berita Acara Rekonsiliasi.

Detail aturan nilai per presentasi berada di `PERIODIC_REPORT_MODULE.md`.

## Integrasi UI

`resources/views/livewire/spj-report-filter.blade.php` menanam `SpjPeriodicReportCenter` di atas riwayat paket SPJ. Dengan demikian halaman `/spj?tab=laporan` mempunyai dua fungsi yang terpisah:

1. **Paket laporan periodik** — memilih Bulanan/Triwulan/Semester/Tahunan dan melihat kesiapan sumber data.
2. **Riwayat paket SPJ** — fungsi lama untuk memfilter dan mengekspor paket SPJ per transaksi.

Keduanya tidak saling menimpa query atau URL state. State paket laporan menggunakan `paket_laporan` dan `periode_laporan`, sedangkan riwayat paket tetap menggunakan `mode` dan `periode`.

## Implementasi output saat ini

Source aktif sudah menyediakan:

- `GET /laporan-periode` sebagai pusat laporan;
- `GET /laporan-periode/{scope}/{report}/cetak` untuk browser print;
- `GET /laporan-periode/{scope}/{report}/pdf` untuk PDF;
- `GET /laporan-periode/{scope}/{report}/excel` untuk export Excel;
- `SpjPeriodicReportPrintService` untuk presentasi/row/column data;
- regression `SpjPeriodicReportPrintableTest`, `SpjPeriodicReportRegistryTest`,
  `BkuOfficialLedgerTest`, `BpkDinasFormatTest`, `BosA1ReportTest`,
  `FormatK2ReportTest`, `RpdBosReportTest`, `K7bK7cReportTest`, dan
  `RekapBudgetRealizationTest`.

Daftar lengkap route modul ini beserta middleware-nya berada di `API.md` root
(bagian "Dashboard, Referensi, dan Laporan").

Implementasi internal ini **bukan klaim bahwa seluruh formulir resmi sudah mempunyai visual fidelity final**. Template/formula resmi, print fidelity, dan pemeriksaan browser/PDF aktual tetap mengikuti status RVR di `CURRENT_PROGRESS.md`.

## Tahap berikutnya

1. tutup visual/runtime QA browser + PDF untuk laporan yang sudah mempunyai presenter internal;
2. pasang formula/layout resmi per report key tanpa menduplikasi logika periode/tenant;
3. pertahankan registry + use case sebagai source of truth daftar laporan dan scope periode;
4. tutup QA workbook Excel untuk seluruh report key dan validasi format khusus
   instansi;
5. tambahkan binding template resmi hanya bila memang dibutuhkan oleh format pemerintah/sekolah.

Laporan BPK Format BOS sudah memiliki presenter internal berbasis mirror kas
umum. Pemetaan kategori rekening tetap perlu divalidasi dengan kode rekening
yang digunakan sekolah sebelum dokumen dijadikan format final instansi.
