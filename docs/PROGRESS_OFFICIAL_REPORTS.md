# Laporan Resmi & Format BOS — Log Kemajuan

> Riwayat pekerjaan laporan resmi: BOS K7A/K7B/K7C, Format K7, SPTJM, BOS A-1, dan Bulk Preview Laporan SPJ.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

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
