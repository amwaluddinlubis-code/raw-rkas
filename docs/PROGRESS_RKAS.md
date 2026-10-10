# RKAS — Log Kemajuan

> Riwayat pekerjaan modul RKAS: simulasi pagu, perbandingan revisi, kertas kerja, dan laporan.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Simulasi what-if pagu RKAS (2026-10-04)

Status: **FUNCTIONAL PASS (focused)**.

Halaman baru `/penganggaran-rkas/simulasi` menampilkan simulator pagu per
kegiatan: operator mengubah total pagu usulan per kegiatan, sistem
memproyeksikan distribusi triwulan menggunakan proporsi split periode
yang ada saat ini (TW1–TW4 saat ini vs usulan vs selisih). Komposisi
tidak menulis staging/mirror — hanya aid operator sebelum mengajukan
revisi di ARKAS. Tombol "Simulasi pagu" tersedia di bar tab revisi
halaman Penganggaran RKAS.

Evidence: `tests/Unit/RkasPaguSimulationTest.php` 2 passed / 8
assertions; `RkasBudgetUiTest` + `RkasRevisionModesTest` 18/103 hijau;
route terdaftar `rkas-budget.simulate`; `view:cache`/`view:clear`
bersih; Pint passed; `git diff --check` bersih.

## Pengembangan RKAS: perbandingan revisi, freshness, dan paket laporan (2026-09-28)

Status: **FUNCTIONAL PASS / FULL REGRESSION GATE GREEN / VISUAL BROWSER RVR**.

Workspace RKAS kini memiliki halaman perbandingan dua snapshot revisi (pos
ditambah/dihapus/berubah, pagu, volume/satuan, dan delta alokasi bulanan),
indikator kesegaran mirror berdasarkan operasi sinkronisasi terakhir dan hitung
baris tabel penting, serta unduhan satu ZIP berisi beberapa scope laporan PDF
dan/atau Excel beserta manifest. Konteks tetap sekolah+tahun+sumber dana+revisi;
source ARKAS/BKU tetap read-only.

Regression mencakup comparison service/controller, paket ZIP dan validasi
periode, freshness mutakhir/lama/tabel hilang, serta kontrak UI. Regression
controller dipisah menjadi satu request per test dan diperkuat untuk jalur
`to`-only, `from` kosong yang diperlakukan sebagai omitted, revisi sama, dan
revision ID di luar konteks aktif. Source behavior tidak dilonggarkan.

Evidence canonical: source `main@e4ba5cf869f554cee9f0b9d230e437f1aee676ae`,
workflow `SPJ Critical Verification` run `36515608353` (#46) **SUCCESS**:
Repository Pint PASS, frontend build PASS, Blade compile PASS, SPJ Critical
339 tests / 2.605 assertions PASS, Full Unit 79 / 281 PASS, dan Full Feature
642 / 4.417 PASS. Visual browser/operator tetap RVR.

---

## Laporan Kertas Kerja RKAS per pengesahan (2026-09-28)

Status: **FUNCTIONAL PASS (focused) / RVR untuk visual PDF/Excel di browser**.

Halaman Penganggaran RKAS kini mengunduh Kertas Kerja ala ARKAS (PDF via Dompdf
landscape + XLSX via PhpSpreadsheet) dalam 5 bentuk: Tahunan (kolom 6 sumber
dana × Operasi/Modal), Tahap (Tahap 1 = TW 1+2, Tahap 2 = TW 3+4), Triwulan
(kolom TW 1–4), Triwulan per Bulan (pilih TW I–IV dengan tiga kolom bulan),
Bulanan (filter bulan + Volume/Satuan/Tarif). Setiap laporan
menghormati tab revisi aktif (`revisi`) sehingga pengesahan terdahulu dapat
digenerate ulang dari snapshot mirror per ID anggaran; kop sekolah +
penandatangan diambil dari profil, kolom dana mengikuti konteks dana aktif.

Evidence: `RkasReportTest` 10 passed / 50 assertions (termasuk render `%PDF`
kelima scope + pratinjau HTML/modal + unduhan route PDF/Excel + 404/422 guard); suite RKAS terkait
hijau (`RevisionModes` 10, `BudgetFilter` 5, `BudgetUi` 4, `ReportLayout` 14,
`RouteSmoke` 6). `view:cache` + Pint bersih. Batasan: butuh data mirror
(422 bila belum sync); alokasi periode revisi lama mengikuti split periode
mutakhir bila ARKAS mengubah split antar revisi.
Header laporan dan header kolom diulang saat cetak, footer memuat nomor halaman
X dari Y, kolom Satuan diperlebar, dan nama Komite Sekolah dibaca dari
`school_profiles.committee_name` (diisi melalui Pengaturan Sekolah).
Tanda tangan Komite, Kepala Sekolah, dan Bendahara dapat diunggah secara
opsional dari Pengaturan Sekolah; PDF/pratinjau/Excel mengabaikan gambar yang
kosong atau tidak tersedia dan tetap mencetak nama, garis, serta NIP.

### Audit lanjutan `main@1705f5d` (2026-09-29)

Audit read-through menemukan bahwa ekspor Excel meninggalkan file kosong dari
`tempnam()` dan menulis string source/profil memakai binder otomatis, sehingga
teks yang diawali `=` dapat dianggap formula. Perbaikan lokal menjaga semua
string sebagai teks literal dan menghapus file sementara awal maupun file
parsial saat penulisan gagal. Regression ditambahkan untuk formula-literal dan
file sementara yatim.

Penggantian gambar tanda tangan kini menyimpan gambar baru terlebih dahulu,
memperbarui path profil dalam transaksi tenant, lalu menghapus gambar lama.
Jika penyimpanan gambar atau update profil gagal, file baru yang sudah
ditahapkan dibersihkan dan gambar lama tetap ada.

Status perubahan audit report: **FUNCTIONAL PASS / FULL REGRESSION GATE GREEN**.
GitHub Actions #39 (`36486277572`) pada `b07d764` mengungkap local `<style>`
yang melanggar guard tema. Fix pertama memindahkan sebagian tombol ke kelas
canonical; gate #40 (`36487670910`) menangkap selector class lama yang masih
tertinggal pada kelompok Tahunan/Tahap/Triwulan dan Bulanan, serta assertion
baru yang terlalu luas. Semua kelompok tombol laporan kini memakai semantic
theme classes; regression mengunci kelas itu, spacing iframe, dan tidak adanya
atribut class lokal lama. GitHub Actions #41 (`36488216251`) pada source
`a22f7d5` **SUCCESS**: Pint, frontend, Blade, checklist lint, SPJ Critical
(339 / 2.605 assertions), Full Unit (79 / 281 assertions), dan Full Feature
(630 / 4.370 assertions) lulus. PHP/Composer tidak tersedia lokal, tetapi gate
CI menjalankan ulang seluruh suite; visual PDF/Excel tetap RVR.

---

## RKAS revision-tab realization parity 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Temuan operator (SDN 318 Bangun Saroha, NPSN 10208183, BOS 2026): tab
Pengesahan ke-1 dan ke-2 menampilkan realisasi berbeda (ALL 146,5jt vs
150,75jt; TW1 56,97jt vs 60,3jt; TW4 13,44jt vs 0), padahal kas tahun+sumber
dana yang mendasarinya sama. Pagu sudah sama (120,6jt / 30,15jt per triwulan).

Akar masalah di `ArkasMirrorBudgetService` (tanpa hardcode, tanpa ubah lifecycle):

- fallback identitas pos bersifat lossy: kas untuk baris yang diganti pada
  revisi lain (identitas `ID_REF_KODE|rekening|uraian` tidak tampil pada tab
  ini — mis. bola/net/peluit → meja/kursi) dibuang dari tab lama;
- share fallback per bulan dibagi dengan bobot pagu tahun ke semua baris
  seidentitas sehingga bocor ke bulan/triwulan lain;
- jalur konsumsi kuartal/semester memakai bulan perwakilan `periods[0]`
  untuk baris satu-periode, sehingga share bulan yang sama terhitung pada
  dua triwulan (jumlah render kuartal 162,55jt > total tahun 150,75jt).

Perbaikan: kas "yatim" (identitas dalam scope tahun+sumber dana yang tidak
tampil pada revisi ini) ikut dibagi proporsional pagu; residual per bulan
hanya dibagi ke baris yang memiliki bulan itu (bobot pagu bulan, pelebaran
triwulan lalu tahun bila tidak ada baris yang cocok, tanpa ganda); konsumsi
kuartal/semester selalu menjumlahkan share bulan-bulan dalam scope yang
diminta. View revisi terbaru tidak berubah (direct didahulukan, fallback nol
bila semua kas tertaut langsung).

Hasil pada data nyata 10208183 (BOS 2026): kedua tab identik —
ALL 120,6jt/150,75jt; TW1 30,15jt/60,3jt; TW2 30,15jt/60,3jt;
TW3 30,15jt/30,15jt; TW4 30,15jt/0; semester dan bulan juga sama per
realisasi (pagu bulanan boleh berbeda karena split rencana memang berubah).
Isolasi tahun+sumber dana dipertahankan (uang 2024/2025 tidak bocor).

Evidence: `RkasRevisionModesTest` 12 passed / 51 assertions (termasuk test
baru `test_revision_tabs_show_same_budget_and_realization_when_lines_change`
yang gagal pada source lama dan lulus pada source baru), `RkasBudgetFilterTest`
5/23, `RkasScopedRealizationTest` + `RkasBudgetUiTest` + `RkasReportTest`
29/148, Pint passed, `git diff --check` bersih. Catatan lingkungan: file
`database/testing.sqlite` lokal rusak (`malformed`) sehingga suite tidak
jalan; dihapus dan dibuat ulang otomatis oleh test run — data uji tidak
terpengaruh. Browser/operator visual tetap RVR.

## Perbaikan melebar blok Unduh Laporan RKAS + buang payload freshness usang 2026-10-05

Status: **PASS (focused, static + build + test) / BROWSER RVR**.

Dua perbaikan terpisah pada halaman `/penganggaran/rkas`.

1. **Baris "Triwulan per Bulan" dan "Bulanan" melebar melewati card.** Select periode
   memakai kelas `ui-select` yang didefinisikan `width: 100%` pada
   `token-native-components.css:110`. Aturan itu unlayered sehingga menang atas
   utility Tailwind yang ber-layer, jadi efforts sebelumnya memberikan select
   `w-[9.5rem]` tidak berlaku dan select tetap memakan seluruh sisa ruang, mendorong trio
   tombol keluar tepi kanan card. Setiap baris kini memakai grid eksplisit
   `grid-cols-[minmax(0,1fr)_auto]` pada mobile dan
   `md:grid-cols-[minmax(0,1fr)_9.5rem_auto]` pada desktop: label fleksibel,
   select 9,5rem pas, trio tombol auto. Baris tanpa select mendapat placeholder
   `hidden md:block` sehingga kelima baris saling sejajar. Di mobile select
   turun ke baris penuh sendiri.
2. **Payload freshness dan integrity yang tidak terpakai dibuang.**
   `RkasBudgetController::renderData` mengirim `syncFreshness` dan `integrity`
   ke view pada dua jalur return, tetapi keduanya tidak pernah dirender di
   halaman RKAS; header freshness tetap hidup di
   `livewire/arkas-health-banner.blade.php` untuk dashboard. Atas instruksi
   user, payload usang dibuang beserta import
   `ArkasMirrorFreshnessService` dan `ArkasMirrorIntegrityService` dari
   controller. Kedua service tidak dihapus karena masih dipakai banner dashboard
   dan masih tercakup oleh test RkasReportTest serta ArkasMirrorIntegrityTest.
   Assertion stale `Kesegaran data ARKAS` pada
   `RkasBudgetUiTest::test_rkas_workspace_exposes_revision_comparison_freshness_and_report_package`
   ikut dibuang dan method di-rename menjadi
   `test_rkas_workspace_exposes_revision_comparison_and_report_package`.

### Verifikasi

`vendor/bin/pint --test` passed, `git diff --check` bersih,
`php artisan view:cache` sukses, `npm run build` sukses, `npm run theme:qa`
all pass, dan rule grid terkonfirmasi hadir pada bundle CSS hasil build sebagai
`grid-template-columns:minmax(0,1fr) 9.5rem auto`. Focused suite: 56 passed /
267 assertions pada RkasBudgetUiTest, RkasHierarchyTest,
RkasRevisionModesTest, RkasPlanningSuggestionTest, RkasBudgetFilterTest,
RkasReportTest, ArkasMirrorIntegrityTest; ditambah 19 passed / 157 assertions
pada RkasScopedRealizationTest, GuiAudit09To13SourceReadinessTest,
ArkasMirrorHealthTest, ArkasMirrorHealthUiTest. `RkasBudgetUiTest` kini hijau
penuh untuk pertama kalinya pada HEAD ini.

Tidak ada perubahan perhitungan anggaran, filter, scope periode, atau kontrak
SPJ. Lebar card dan breakpoint mobile tetap perlu pemeriksaan visual browser;
status mobile tetap RVR.
