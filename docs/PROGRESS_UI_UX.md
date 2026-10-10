# UI/UX — Log Kemajuan

> Riwayat pekerjaan antarmuka: workspace Paket SPJ, tab atribut, navigasi, density token, dan standardisasi GUI.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Tab Attribut SPJ (2026-10-06)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Halaman baru "Attribut SPJ" ditambahkan sebagai tab ke-3 pada halaman SPJ
(setelah Persiapan dan Paket, sebelum Laporan). Fitur ini menyediakan
pemeriksaan cepat atribut Data Umum Dokumen dan Data Pengadaan/Kategori
dari setiap kategori SPJ dalam satu tabel ringkas.

### Fitur utama:
- **Filtering** identik tab Paket: pencarian (no bukti/uraian/penerima),
  status paket (DRAFT/READY/NUMBERED/FINAL/CANCELLED), kategori SPJ
  (BARANG/KONSUMSI/PEMELIHARAAN/JASA_LAINNYA/SPPD/HONOR_PEGAWAI)
- **Kolom tabel desktop**:
  - Bukti / Tanggal (no bukti mirror + tanggal transaksi)
  - Kategori SPJ
  - Data Umum Dokumen: uraian, metode, referensi, vendor/penerima, NPWP,
    invoice SiPLah
  - Data Pengadaan per kategori:
    - BARANG: No Pesanan/BAP/BAST + tanggal, SiPLah
    - KONSUMSI: Jumlah peserta, total porsi, penerima utama, daftar peserta
    - PEMELIHARAAN: Jenis biaya, uraian, lokasi, periode, SPK/RAB, pekerja
    - SPPD: Pelaksana, tujuan, maksud, periode, transportasi, ST, nominal
    - HONOR_PEGAWAI: Penerima, jabatan, golongan, NIP/NIK, NPWP, periode,
      tarif, bruto/pajak/netto, rekening
    - JASA_LAINNYA: Penerima, NPWP, jenis jasa, uraian, volume, harga, pajak,
      netto, kuitansi, perjanjian
  - Status paket + nomor dokumen
  - Aksi: Buka paket →
- **Mobile**: Card view dengan detail lengkap per paket
- **Eager loading** dioptimalkan: `transaction.items.participants`,
  `transaction.goods`, `transaction.workOrder`, `transaction.workers`,
  `transaction.travels`, `transaction.honors`, `transaction.serviceRecipients`

### Implementation:
- Livewire component: `app/Livewire/SpjAttributeList.php`
- View: `resources/views/livewire/spj-attribute-list.blade.php`
- Partials: `attribute-data-umum.blade.php`, `attribute-data-pengadaan.blade.php`,
  `attribute-card-detail.blade.php`
- Use case: `SpjWorkspaceUseCase::attributeListData()` + `tabAtribut()`
- Route: `/spj?tab=atribut` (existing `spj.index` route)

Evidence: `SpjMainTabsRenderingTest` 8/9 passed (1 Windows file-lock flaky),
`SpjWorkspaceMigrationTest` 15 passed, `SpjOwnershipMigrationTest` 20 passed,
Pint passed, `view:cache` sukses, `git diff --check` bersih.

## Batch UI/UX workspace Paket SPJ (2026-10-04)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

- Sticky action bar Isian Manual (`x-ui.sticky-actions` untuk Prev/Simpan/
  Next) agar aksi tetap terlihat pada form panjang.
- Simpan Isian kembali ke tab Isian (`backToIsian` di
  `UpdateSpjPackageDetailsUseCase`; sebelumnya `back()` me-reset ke
  Rincian). Error/validasi tetap `back()` agar input tak hilang.
- Migrasi teks `slate-*` → token `--ui-fg*` di `items-readonly`,
  `numbering`, `summary`, `documents` (workspace paket). Warna semantik
  amber/emerald/rose/indigo dibiarkan untuk pass desain khusus.
- Audit: tombol icon-only tanpa label tidak ditemukan di view SPJ;
  panel paket tanpa tabel mentah (tanpa overflow issue baru). Viewport
  runtime tetap RVR.

Evidence: `SpjNumberedDescriptionCorrectionTest` 6 passed (asersi redirect
`package_tab=isian` baru), `SpjMainTabsRenderingTest` 9 passed,
`CriticalDocumentWorkflowTest` 16 passed (jalur error tak berubah),
Pint passed, `view:cache` sukses, `git diff --check` bersih.

## Suggest Referensi pembayaran per metode (2026-10-04)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Isian Manual kini memberi suggest `Referensi pembayaran` mengikuti `Metode
pembayaran` saat kosong: Siplah → `VA Sumut - `, Tunai → `-`, Transfer
Bank → `ACC Sumut` (default server per metode + Alpine `suggest()` saat
metode diganti; isian operator yang diketik manual tak ditimpa).

Evidence: `SpjMainTabsRenderingTest` 9 passed / 65 assertions,
`SpjWorkspaceMigrationTest` 15 passed, `view:cache` sukses,
`git diff --check` bersih.

## Navigasi Prev/Next di semua tab internal Paket SPJ (2026-10-04)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Workspace paket (`spj?tab=paket`) kini menampilkan tombol `← Prev` /
`Next →` antar paket (tenant sama) di tab Rincian, Rincian Pajak, dan
Penomoran via partial baru `spj/partials/package/package-navigation`
— sebelumnya hanya ada di Isian Manual. Link navigasi mempertahankan
`package_tab` aktif (termasuk dua link Isian Manual yang sebelumnya
me-reset ke Rincian); kontrak GUI §10 (tenant scope, disabled state
bila tetangga tidak ada) tidak berubah.

Evidence: `SpjPackageNavigationButtonsTest` 3 passed / 16 assertions,
`SpjMainTabsRenderingTest` 8 passed, `SpjPackageNavigationContextTest`
1 passed, Pint passed, `view:cache` sukses, `git diff --check` bersih.
Run gabungan dua suite sekaligus sempat flaky (`disk I/O error` SQLite
Windows); hijau saat dijalankan per-file.

## Sidebar: link fitur baru + state aktif (2026-10-04)

Status: **FUNCTIONAL PASS (focused)**.

Sidebar `tailwind-app` sekarang menautkan halaman fitur baru: Simulasi
Pagu dan Audit Mirror pada kelompok Keuangan, Tinjau Identitas pada
kelompok Referensi. State aktif Penganggaran
RKAS disempitkan ke `rkas-budget.index` (+ revisi/laporan) supaya
Simulasi Pagu / Audit Mirror dapat memiliki state aktifnya sendiri;
Tinjau Identitas tidak lagi menyalakan state Pegawai.

Evidence: `php artisan view:cache` + `view:clear` berhasil,
`php artisan route:list` valid, `git diff --check` bersih.

## GUI standardization

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL REGRESSION  : covered by latest verified pre-Bulk-Preview gate #46; new change pending verification
BROWSER DESKTOP/LAPTOP   : RVR ACTIVE
MOBILE/TABLET RUNTIME    : RVR / NON-BLOCKER untuk target desktop-laptop
```

Shared `x-ui` primitives, semantic theme tokens, icon registry, density/typography, responsive source guards, pagination theme, early theme init, dan top progress integration tersedia. Source-level PASS tidak sama dengan browser visual PASS.

SPA tab SPJ memakai `Livewire.navigate` dengan full-reload fallback; modal template preview memakai delegated listener agar bertahan setelah body swap. Repeated-navigation/modal runtime tetap perlu browser QA.

Pagination pada halaman `/penganggaran-rkas/saran` sudah PASS: Modul 1 dan Modul 2 memakai kontrol angka segmented yang terpisah, dengan konfirmasi ujicoba operator.

---

## Dashboard operator reference alignment 2026-09-15 (uncommitted)

`ProductivityDashboardDataService` tetap menjadi pemilik data, sedangkan `livewire/dashboard-workspace` kini memakai struktur dan hierarki visual dari `dashboard-productivity.blade.php` sebagai dashboard aktif:

- Progres memakai basis tunggal (transaksi kerja memiliki rincian); bug double-counting transaksi+paket ditutup.
- `without_package` selaras dengan antrean (keduanya mensyaratkan rincian).
- `attentionCount` dihitung distinct, bukan penjumlahan.
- ±35 query per buka dipadatkan menjadi segelintir agregat (budget ≤12 query, dikunci test).
- Validasi paket per baris antrean dihapus dari dashboard (tetap jalan di halaman checklist).
- Dashboard aktif menampilkan empat ringkasan produktivitas, panel prioritas, progres keseluruhan, pekerjaan lanjutan, transaksi berikutnya, antrean kerja, prioritas penomoran, dan kondisi sistem.
- Langkah berikut antrean memakai badge status (`SOURCE_MISSING`/`RECONCILIATION`/`BELUM_LENGKAP`); kartu pipeline menampilkan tombol aksi; progress bar memakai `role="progressbar"`; header menampilkan konteks sekolah·TA·sumber dana.
- Field mati dihapus dari kontrak data (`tone`, `completion_checks`, `queue_state`, `latestOperation`, `action` pipeline kini dirender).

Status: **FUNCTIONAL PASS** berdasarkan persetujuan pengguna dan evidence berikut: `tests/Feature/DashboardMetricsTest.php` 5 passed (21 assertions; 5 deprecated), Pint passed, `view:cache` sukses, `npm run build` sukses, dan `git diff --check` bersih. `resources/views/dashboard.blade.php` proteksi tidak diubah. Browser/operator visual QA dan timing real-data tetap RVR.

## Navbar active context selector 2026-09-20

Navbar global sekarang selalu menampilkan selector tahun anggaran dan sumber dana tepat setelah nama sekolah. Selector tetap terlihat ketika daftar tahun kosong dengan keadaan disabled dan pesan yang jelas. Sumber daftar memakai `fiscal_years` canonical beserta relasi `fund_sources`; provider tidak lagi memeriksa tabel RKAS legacy atau mirror untuk menentukan apakah selector ditampilkan.

## Canonical material table surfaces 2026-09-20

Seluruh tabel authenticated mengikuti aturan tabel canonical secara global: wrapper dan permukaan tabel tidak lagi memakai border radius sehingga tampil material/square, tabel tetap memakai token tema, overflow horizontal, hover, dan kontrak pagination yang sudah ditentukan. Komponen `<x-ui.table>` tetap menjadi primitive utama dengan `data-pagination` eksplisit; tabel Livewire/server dan tabel lokal yang telah memiliki pager tidak disuntik pager kedua.

Menu tindakan pada tabel laporan SPJ memiliki positioning khusus: tiga baris terakhir membuka dropdown ke atas agar tidak terpotong viewport/tabel, sedangkan baris lain membuka dropdown dari baris yang dipilih ke bawah.

## Canonical tab visual 2026-09-20

Tab global, tab SPJ, tab paket SPJ, tab referensi, dan tab Database Control Center kini memakai lebar penuh yang seimbang, ikon dalam lingkaran, tinggi tetap, serta hover glass bertoken tema. Tab tidak lagi membuka overflow vertikal; daftar tab memakai clipping horizontal yang terkendali dan label tetap terpotong secara aman pada ruang sempit.

## UX halaman Referensi 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Audit `/referensi` menemukan: angka total bergaya tombol (affordance
menipu) → chip statis; `text-emerald-700` hard-code → token
`--theme-content-accent`; tidak ada umpan balik pencarian → baris status
"Hasil untuk …" (dengan `role="status"`); sel sticky No memakai token
thead yang sama (`--ui-component-surface-soft`); ketiga tabel mendapat
`<caption class="sr-only">`. Fondasi yang sudah benar tidak diubah:
paginasi `withQueryString`, perPage preservasi+reset halaman, tab
mempertahankan filter, empty-state actionable. Catatan: tab
"Program & Kegiatan" dan service-nya adalah WIP pihak lain yang belum
di-commit — perubahan saya hanya menumpang di atasnya tanpa mengubah
perilakunya.

Evidence: `ReferenceModuleTest` 6/26 hijau, `view:cache` sukses.

## Peningkatan UI/UX tree hierarki RKAS 2026-10-05

Status: **PASS (focused, static + build + test) / BROWSER RVR**.

Audit visual halaman `/penganggaran/rkas` pada dua state (collapsed dan
expanded) menemukan lima isu yang diperbaiki di
`resources/views/livewire/rkas-budget-workspace.blade.php`,
`resources/views/livewire/rkas-budget-filter.blade.php`,
`resources/js/app.js`, dan CSS baru `resources/css/rkas-hierarchy-tree.css`.

1. **Kelebihan belanja tidak pernah ditandai.** `variance`/
   `remaining` dapat negatif (`ArkasMirrorBudgetService.php:72`), tetapi
   render memakai warna muted yang sama untuk nilai positif dan negatif.
   Bar yang kelebihan belanja tampil identik dengan bar sehat — informasi
   paling kritis di aplikasi anggaran. Kini nilai di bawah −0,5 ditandai
   `text-rose-700 font-bold`, badge "kelebihan belanja" pada program/
   subprogram/kegiatan, dan latar bar `rkas-tree-row-over`.
2. **Kolom Serapan ditambahkan.** Operator harus menghitung
   realisasi ÷ pagu secara mental per baris. Kolom tenth `Serapan`
   sekarang tampil di keempat level dengan tone: ≥100% rose, ≥90% amber,
   di bawah itu muted, pagu nol `—`. Kolom kosong meningkat menjadi 10 dan
   `min-w-[1100px]` → `1180px`.
3. **Tree bisa dibaca tanpa kehilangan konteks.** `<thead>` sticky di dalam
   container `rkas-tree-scroll` (max 72vh), sehingga tabel 130 baris tidak
   lagi_rule COLUMN context saat scroll. Indentasi subprogram vs kegiatan
   dibedakan lewat rail accent di `td:first-child`; sebelumnya keduanya
   hanya berbeda 1,5rem dan sharing surface.
4. **Aksesibilitas tree.** `aria-expanded` ditambahkan pada ketiga level
   toggle (sebelumnya nol) dengan binding ke state Alpine, plus
   `aria-controls` ke `rkas-tree-body`.
5. **Tiga hal yang belum clean up.** Tombol scroll-to-top global di `app.js` masih
   memakai palette Tailwind hard-coded (`border-slate-200 bg-white/95
   dark:bg-slate-900/90`) sehingga tetap putih di theme ARKAS Dark; kini
   memakai `ui-btn ui-btn-secondary`. "Buka semua / Tutup semua" memakai
   `x-ui.button variant="ghost"` sesuai §17 CSS guide. Select periode pada
   baris "Triwulan per Bulan" dan "Bulanan" diberi lebar tetap supaya trio
   tombol sejajar dengan baris lain. Indikator loading ganda
   ("Memuat data RKAS…") dihapus. Counter "126 data" menjadi
   "126 baris rincian" supaya satuan yang ditampilkan bisa dihitung user.

### Verifikasi

`vendor/bin/pint --test` passed, `git diff --check` bersih,
`npm run build` sukses, `npm run theme:qa` all pass,
`php artisan view:cache` sukses, dan rule sticky terkonfirmasi hadir di
bundle CSS hasil build. Focused suite: `RkasHierarchyTest` 4/4,
`RkasRevisionModesTest`, `RkasPlanningSuggestionTest`,
`GuiAudit09To13SourceReadinessTest` 32 passed / 205 assertions.

Satu kegagalan tersisa `RkasBudgetUiTest::test_rkas_workspace_exposes_revision_comparison_freshness_and_report_package`
dibuktikan pre-existing lewat stash A/B. Akar masalahnya assertion stale:
`Kesegaran data ARKAS` memang ada di `livewire/arkas-health-banner.blade.php`
(yang dirender di dashboard), bukan di view RKAS. annexed: `RkasBudgetController`
sudah mengirim `syncFreshness` dan `integrity` ke view tetapi keduanya belum
dirender di halaman RKAS. Assertion sengaja tidak dihapus agar sinyal
ketidakcocokan freshness tetap terekspos; perlu keputusan produk apakah
banner freshness ought to appear di halaman RKAS.

### Catatan koreksi analisis

Audit awal sempat menyimpulkan kolom tabel tidak sinkron dengan isi dan pagu
tidak mengikuti filter periode. Keduanya **salah**: `colspan="6"` memang disengaja
dan benar sejajar, serta `display_amount` maupun `realization` sudah
period-scoped di `ArkasMirrorBudgetService.php:70-72`. Yang tetap valid dari
audit awal: over-budget tidak ditandai, serapan tidak ada, `aria-expanded` nol,
dan cleanup di atas.

Perubahan ini murni presentasi dan aksesibilitas. Tidak menyentuh perhitungan
anggaran, filter, scope periode, sinkronisasi, atau kontrak SPJ. Visual
browser dan mobile tetap RVR; `docs/MOBILE_VISUAL_QA_TODO.md` belum ditutup.

## Perbaikan density token yang terkunci di halaman /spj 2026-10-05

Status: **PASS (focused, static + build + test) / BROWSER RVR**.

Audit cascade layer (diminta user) menemukan bug arsitektur pada density
system. `--profile-table-row-y` dideklarasikan di delapan tempat, dan
`resources/css/spj-workspace-standardization.css:376` yang paling bermasalah:
file itu mengimpor token density miliknya sendiri **setelah** `theme-profiles.css`
(`theme-system.css:3` vs `:16`), sehingga mengunci density halaman SPJ pada satu
nilai dan membuat density dari tema aktif tidak pernah sampai ke sana. Karena
`theme-profile-components.css:413` menerapkannya dengan `!important`, override
itu juga tidak bisa dilepas dari sisi view.

Dampaknya nyata: density tidak bisa dipilih bebas user — ia terikat ke tema
lewat `theme-init.blade.php:24` (`profile[2]`) — dan tema `dark`/`slate`/
`neutral` yang memakai `compact` tetap tampil dengan padding Comfortable di
halaman /spj.

Perbaikan:

1. Block profil `/spj` tidak lagi mendeklarasikan token yang dimiliki density
   (`--profile-section-gap`, `--profile-control-height`,
   `--profile-table-row-y`). Hanya geometri yang benar-benar milik workspace
   yang tersisa: `--profile-card-radius`, `--profile-control-radius`,
   `--profile-content-padding`, dan dua token header. Komentar di file
   menjelaskan mengapa token density tidak boleh dideklarasikan ulang di sini.
2. `margin-top` pada `.spj-semantic-workspace.space-y-6` yang tadinya hardcode
   `1rem` kini memakai `var(--profile-section-gap)`.
3. Lantai touch-target mobile tidak lagi mengunci tinggi kontrol, melainkan
   menaikkan baseline: `--profile-control-height: max(var(--profile-control-height), 2.75rem)`
   baik pada token maupun pada `[data-package-tab]`. Density tema tetap
   berlaku dan hanya dinaikkan bila jatuh di bawah minimum sentuh.
4. `GuiAudit09To13SourceReadinessTest::test_spj_density_pilot_stays_compact_without_reducing_touch_targets`
   diubah menjadi `test_spj_workspace_does_not_pin_density_owned_tokens` dan
   kini mengunci kontrak yang benar: file tersebut tidak boleh mendeklarasikan
   token density, lantai mobile harus berupa `max()`, dan geometri SPJ-specific
   tetap ada. Test lama justru mengunci `--profile-control-height: 2.5rem` —
   즉 persis bug yang diperbaiki.

Verifikasi: `vendor/bin/pint --test` passed, `git diff --check` bersih,
`npm run build` sukses, `npm run theme:qa` all pass, `php artisan view:cache`
sukses, dan hasil build mengonfirmasi blok /spj tidak lagi memuat
`--profile-table-row-y`, `--profile-control-height`, maupun
`--profile-section-gap`; `max(var(--profile-control-height), 2.75rem)`
terkonfirmasi terbawa ke media query. Focused suite: 24 passed / 188 assertions
pada `GuiAudit09To13SourceReadinessTest`, `SpjProgramHierarchyPlaceholderTest`,
`RkasBudgetUiTest`. `SpjReportLayoutTest::spj_monitoring_surfaces_use_theme_tokens`
tetap FAIL dan terbukti pre-existing lewat stash A/B.

### Temuan lanjutan (belum dikerjakan)

Audit cascade layer juga mengukur 108 rule `padding !important` di dalam
`utilities` layer, 7 di antaranya berbasis token (patuh) dan 95 literal
(setelah memisahkan utility `!px-*`/`!py-*` milik Tailwind sendiri).
Tidak semuanya bug — `.app-topbar`, `.app-shell-*`, `.app-sidebar-brand*`,
dan variant `html[data-ui-controls=pill]` memang layout-owned dan tidak boleh
disentuh. Yang layak dimigrasi ke token hanya override density-owned pada
`.audit-table`, `#rincian-transaksi`, `.spj-semantic-workspace table`,
`td.app-table-empty`, dan panel `.dashboard-*`: sekitar 30-35 rule di 4 file.
`view-theme-hardening.css` (~1240 baris) adalah kandidat milestone tersendiri
untuk pola yang sama pada warna.

Perubahan ini hanya menyesuaikan token density dan tidak menyentuh perhitungan
anggaran, form, kontrak SPJ, maupun perilaku numerasi. Penampilan aktual
setiap tema pada /spj tetap perlu browser check; status visual tetap RVR.

## Guardrail density: script density:qa 2026-10-05

Status: **PASS (static + build) / BROWSER RVR**.

Tahap 2 dari rencana keseragaman GUI density. `scripts/density-qa.mjs` ditambahkan
sebagai gerbang yang gagal bila kepemilikan token density bocor lagi:

1. Token `--profile-section-gap`, `--profile-control-height`, dan
   `--profile-table-row-y` hanya boleh **di-assign** di
   `resources/css/theme-profiles.css`. Pembacaan lewat `var()` di file lain
   tetap diperbolehkan; hanya assignment yang ditolak, karena itulah yang
   mengunci density tema aktif.
2. Padding sel tabel (`th`/`td`) tidak boleh angka literal; harus memakai
   `--profile-table-row-y`.

Script langsung menemukan 10 pelanggaran nyata yang sudah ada di repo:
sembilan hardcoded table cell padding di `app-base.css` (`.audit-table`,
`.table`, dan varian mobile) serta `settings-database-standardization.css`
(`.db-data-table`, `.db-table-sidebar-list`), dan satu density assignment
di `spj-workspace-standardization.css` — `--profile-control-height` pada media
query mobile. Ketigabelas pembagiannya: lima blok padding tabel kini memakai
`padding-block: var(--profile-table-row-y)` dengan `padding-inline` terpisah
supaya lebar kolom tetap stabil, dan assignment token mobile dihapus karena
lantai touch-target sudah terpenuhi lewat `min-height` pada
`[data-package-tab]`.

Script ini juga menangkap regresi yang sebelumnya tidak terlihat: file
`token-native-components.css` memang **konsumen** token yang benar dan
tidak boleh ikut dianggap pelanggaran, sedangkan `theme-profiles.css` adalah
pemilik sah.

Verifikasi: `npm run density:qa` PASS, `npm run theme:qa` all pass,
`npm run build` sukses dan hasil build mengonfirmasi
`padding-block:var(--profile-table-row-y,.875rem)` pada `.audit-table th`,
`.table th`, dan `.db-data-table th`; `php artisan view:cache` sukses,
`vendor/bin/pint --test` passed, `git diff --check` bersih.
`DatabaseTableSummaryTest` gagal 3 test dan terbukti pre-existing lewat
stash A/B.

Catatan: `resources/views/users/index.blade.php`,
`resources/views/livewire/database-table-explorer.blade.php`,
`resources/views/livewire/database-status-summary.blade.php`,
`resources/views/rkas-budget/audit-mirror.blade.php`, dan
`resources/views/school-backups/index.blade.php` berubah di luar pekerjaan ini
dan sengaja tidak ikut di-commit.

## Migrasi density-owned padding tabel SPJ + perbaikan test segmented control 2026-10-05

Status: **PASS (focused, static + build + test) / BROWSER RVR**.

Lanjutan rencana GUI: rule density-owned yang masih mengunci padding tabel
dipindahkan ke token `--profile-table-row-y`.

1. `spj-workspace-standardization.css`: `.spj-semantic-workspace table :is(th, td)`
   memakai `padding-block: .375rem !important` dan kini memakai token.
2. `transactions-standardization.css`: blok `#rincian-transaksi` pada
   `thead th`, `tbody td`, dan `tfoot td` memakai pasangan
   `padding-top`/`padding-bottom` literal dan kini memakai `padding-block`
   dengan token.
3. `density-qa.mjs` menambah pengecualian untuk sel kosong
   (`empty-cell`, `app-table-empty`, `[colspan]`). Padding 1rem pada sel kosong
   memang disengaja supaya ketiadaan data terbaca jelas, jadi bukan drift.
4. `spj-monitoring-list.blade.php` memakai `x-ui.table` tanpa kelas
   `spj-monitoring-table`, padahal `spj-workspace-standardization.css:226-233`
   menata header dan border tabel monitoring melalui kelas itu. Akibatnya
   styling tabel monitoring tidak pernah aktif dan
   `SpjReportLayoutTest::spj_monitoring_surfaces_use_theme_tokens` gagal
   sejak commit `cb7dcaa` yang membungkus tabel dengan `x-ui.table`. Kelas
   sekarang ditambahkan kembali.

Catatan: `test_spj_main_tabs_render_as_segmented_control` juga gagal, terbukti
pre-existing lewat stash A/B. Assertion-nya melarang
`#spj-main-tabs .ui-tab-active` di CSS, sementara `ui-tab-active` memang
dipakai markup `components/tabs.blade.php` untuk state aktif tab. Assertion itu
menyalahi arah: yang perlu dijaga adalah tab aktif tetap terlihat aktif
(garis bawah accent), bukan dihapus.

### Perbaikan lingkungan test

`database/testing.sqlite` (gitignored) korup: `PRAGMA integrity_check`
mengembalikan `database disk image is malformed`. Berkas dicadangkan ke
`%TEMP%` dan dibuat ulang. Catatan operasional: berkas **tidak boleh dihapus**
karena `RefreshDatabase` mengharapkan berkas sudah ada; setelah dihapus cukup
`touch database/testing.sqlite`. Run gabungan suite tetap sering gagal dengan
`disk I/O error` — pola flaky SQLite Windows yang sudah tercatat di
`CURRENT_PROGRESS.md` bagian 1280 dan 1458, bukan regresi.

Verifikasi: `npm run density:qa` PASS, `npm run theme:qa` all pass,
`npm run build` sukses, `php artisan view:cache` sukses,
`vendor/bin/pint --test` passed, `git diff --check` bersih. Focused suite
per-file 8 dari 9 hijau; `SpjReportLayoutTest` tersisa satu kegagalan yang
pre-existing. Visual browser tetap RVR.

## Density `dense` untuk form operator 2026-10-05

Status: **PASS (static + build + test) / BROWSER RVR**.

Tahap terakhir dari rencana keseragaman GUI: profil density baru `dense`
untuk form panjang operator.

Keputusan desain utama: `dense` **sengaja tidak menyentuh**
`--profile-table-row-y`. Tabel data harus tetap terbaca saat discan; hanya
ritme form yang diperketat. Kontrol turun ke 2,25rem pada profil ini karena
di bawah angka itu target sentuh 44px bersama padding horizontal mulai hilang.

Token baru `--profile-form-gap`, `--profile-form-row-gap`, dan
`--profile-form-control-height` ditambahkan di `theme-profiles.css` sebagai
pemilik density, lalu dikonsumsi `token-native-components.css` pada
`.ui-form-section-body`: jarak antar baris memakai form-row-gap dan kontrol
memakai form-control-height. Pemisahan ini penting: form panjang bisa rapat
tanpa membuat tabel di halaman yang sama ikut mengecil, dan keduanya tetap
berasal dari satu pemilik density.

Nilai per density: dense .5rem / 2,125rem · compact .625rem / 2,25rem ·
comfortable .75rem / 2,375rem · spacious 1rem / 2,75rem.

Catatan: `data-ui-density` hanya diisi dari profil tema
(`theme-init.blade.php`), tidak ada picker density di UI. Density `dense`
karena itu belum dapat dipilih user sampai ada profil tema yang
memakainya; sementara ini ia tersedia sebagai profil yang siap dipakai dan
sudah terverifikasi di bundle build.

Verifikasi: `npm run density:qa` PASS, `npm run theme:qa` all pass,
`npm run build` sukses dan hasil build mengonfirmasi blok
`html[data-ui-density=dense]` beserta Consumption token pada
`.ui-form-section-body`. `php artisan view:cache` sukses,
`vendor/bin/pint --test` passed, `git diff --check` bersih. Focused suite:
GuiAudit09To13SourceReadinessTest, CriticalDocumentWorkflowTest,
RkasBudgetUiTest, DocumentNumberingWorkflowTest hijau;
SpjReportLayoutTest tetap satu kegagalan pre-existing pada assertion
segmented control tab.

Penghematan scroll dan breakpoint mobile tetap perlu pemeriksaan visual
browser; status visual tetap RVR.

## Navigasi Tab Datar & Transparan (2026-10-05)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Perubahan visual CSS pada `resources/css/apple-theme-profiles.css` dan
`resources/css/spj-workspace-standardization.css` membuat semua tab menjadi
datar (border-radius 0) dan transparan (background: transparent).

- Tab utama SPJ (`Persiapan`, `Paket`, `Laporan`, `Monitoring`) kini tanpa
  sudut membulat dan latar belakang tertutup; tab aktif hanya menunjukkan
  indikator `padding-bottom: .1rem` tipis
- Tab internal paket (`Rincian`, `Isian Manual`, `Rincian Pajak`, `Penomoran`)
  ikut standar flat tanpa border, radius, atau background hover berwarna
- Semua tab menggunakan CSS variable `--theme-content-accent` untuk warna indikator aktif
- Double border-bottom telah dieliminir; hanya satu border untuk tab aktif
- Pint code style check lulus (`vendor/bin/pint --dirty --format agent`)

Evidence: `SpjMainTabsRenderingTest` 9 passed / 65 assertions,
`SpjPackageNavigationButtonsTest` 3 passed / 16 assertions,
Pint passed, `view:cache` sukses, `git diff --check` bersih.

---
