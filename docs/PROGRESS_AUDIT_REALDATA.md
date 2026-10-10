# Audit & Verifikasi Real-Data — Log Kemajuan

> Riwayat audit real-data tenant nyata, kepatuhan coding, dan referensi pola bukti dukung.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Audit real-data 3 tenant nyata (2026-10-05)

Status: **REAL-DATA VERIFIED (read-only) / BLOCKER DATA: TIDAK ADA**.

Audit dijalankan **read-only** terhadap tiga tenant produksi pada
`SPJ_DATA_PATH` aktif (`.env:82` → `school-databases-arkas-mirror`):
**10208183** (SDN 318 Bangun Saroha), **10208246** (SDN 316 Ranto Panjang),
**10260756** (SMP Negeri 2 Ranto Baek). Enam tenant lain di folder tersebut
(`10211735`, `10215538`, `10255901`, `10271767`, `10277611`, `00000001`) adalah
tenant uji coba dan **tidak** dipakai sebagai bukti.

Semua pemeriksaan dilakukan pada salinan file di `%TEMP%`; tidak ada write,
reprovision, migrate, maupun `--repair` terhadap file tenant asli.

### Skema dan integritas

```text
NPSN       migrasi  integrity  fk_check  sort_order  checklist_ticks
10208183      70/70      ok       0 bersih     ADA      8 baris, FK -> spj_packages.id
10208246      69/70      ok       0 bersih     ADA      0 baris, FK -> spj_packages.id
10260756      70/70      ok       0 bersih     ADA      0 baris, FK -> spj_packages.id
```

Temuan:

- **Tidak ada gap migrasi yang merusak fitur.** 10208246 tertinggal satu
  migrasi (`2026_10_04_000003_drop_dangling_users_fk_from_checklist_ticks`),
  tetapi migrasi itu hanya rebuild tabel bila FK `checked_by → users` masih
  ada. Audit `PRAGMA foreign_key_list` pada 10208246 menunjukkan FK yang ada
  **hanya** `spj_package_id → spj_packages.id`, dan tabel berisi 0 baris.
  Jadi migrasi tersebut akan menjadi no-op; tidak ada risiko data.
- **17 tabel mirror aktif di ketiga tenant**, dan kolom yang didokumentasikan
  sebagai sudah di-drop memang hilang di data nyata:
  `transactions.{gross_amount,tax_total,net_amount}` dan
  `transaction_items.{description,quantity,unit,unit_price,amount}` **tidak ada**.
  Ini mengonfirmasi koreksi dokumentasi pada batch sebelumnya bukan sekadar
  klaim — nilai source benar-benar hanya tersedia via mirror + `sourceValue()`.
- `transaction_items.sort_order` ada di ketiganya, sehingga
  `Transaction::items()` (`COALESCE(NULLIF(sort_order,0), id)`) aman. Query
  ekuivalen dijalankan langsung dan berhasil pada ketiga tenant.

### Kesehatan mirror kas (`arkas:mirror-health`, dry-run)

Dijalankan tanpa `--repair` untuk seluruh sekolah:

```text
baris dengan stale > 0        : 0
baris hilang-di-mirror > 0    : 0
kategori tanpa scope           : 0
```

Untuk (tahun, dana) yang punya kas, `mirror` dan `bku` cocok pada n dan sum —
contoh 10260756 TA 2026 fund 1: `SALDO_AWAL 20/483.682.500`,
`PENERIMAAN_BOS 2/276.390.000`, `BELANJA 219/207.292.500`,
`PAJAK 96/21.179.232`, seluruhnya `stale n=0`, `hilang n=0`. Regresi overuse
pagu yang dicatat 2026-10-04 (realisasi 2x pagu pada 10208183 dan 10208246)
tidak terulang.

### Readiness penomoran

```text
NPSN       transaksi  READY  DRAFT  tw READY per triwulan (dari sx_tanggal mirror)
10208183        65      46       0  FY3/dana1: TW1=23, TW2=23
10208246        74      24      50  FY3/dana1: TW1=24
10260756       198      94       0  FY5/dana1: TW2=66, TW3=28
```

Semua paket READY punya `sx_tanggal` mirror (0 tanpa tanggal), jadi scope
triwulan pada `/spj/penomoran` dapat ditentukan tanpa fallback. PAJAK turunan
dan dokumen: `document_number_formats` terisi 30/35/55 baris.

Distribusi kategori READY (tanpa fabrikasi):

```text
NPSN       BARANG  KONSUMSI  PEMELIHARAAN  JASA_LAINNYA  HONOR_PEGAWAI  SPPD
10208183        32         2             2             6             4  0
10208246        17         1             1             1             4  0
10260756        64         6             2             5            17  0
```

SPPD tetap 0 di ketiga tenant. Ini konsisten dengan kontrak: jangan membuat
SPPD fiktif untuk sixth-category coverage. Lima kategori lain punya data nyata
representatif di sini.

Status source bersih di ketiganya: seluruh transaksi `ACTIVE`,
`requires_reconciliation = 0`, `source_missing_since = 0`. Tidak ada antrean
rekonsiliasi yang perlu operator tangani.

### Konsekuensi untuk prioritas

Tidak ada blocker data. Prioritas P1 yang tersisa murni soal output dan
runtime, bukan perbaikan data:

1. Generated-document real-data QA pada paket READY di atas (5 kategori × 3
   tenant) — termasuk penomoran sungguhan pada salinan terisolasi.
2. Browser/operator QA desktop-laptop sesuai `GUI_RUNTIME_QA.md`.
3. Office/PDF visual QA untuk XLSX/PDF hasil generate.

## Referensi pola bukti dukung operasional (poster 10 pola) — 2026-09-23

Status: **FASE 1 IMPLEMENTED / AMBANG DETEKSI NON-BLOCKING / RVR visual**.

> **Koreksi status 2026-10-05.** Entri ini sebelumnya menyatakan "belum
> implementasi, tanpa perubahan source" dan "aturan ambang belum menjadi
> validasi otomatis". Keduanya sudah usang: checklist eksternal beserta
> centang per item sudah hidup di halaman `spj.checklist` dengan guard
> OPERATOR/ADMIN + DRAFT/READY + tenant boundary + audit, dan ketiga ambang
> poster (materai >Rp5 Jt, PPN >Rp2 Jt, PPh23 2% + pajak restoran 10%) sudah
> dideteksi otomatis oleh `SpjOperatorHintService`. Yang belum ada adalah
> menjadikan ambang itu **gate pemblokir**, dan cetak checklist pendamping.
> Deteksi sekarang informatif dan tidak menghentikan numbering.

Poster “Lampiran Bukti Dukung SPJ” ditranskripsikan ke `docs/SPJ_SUPPORTING_DOCUMENT_PATTERNS.md`
(10 pola: KKG, Rapat K3S, Honor GTT/PTT, Perjalanan Dinas, Belanja SiPlah,
Makan Minum/Rapat, Honor Narsumber, Pengadaan/Penggandaan, Ekstrakurikuler,
Jasa Tukang dll + catatan materai >Rp5 Jt dan kecocokan Nomor/Nilai BKU-Kwitansi).
Pemetaan ke 6 kategori canonical + channel SiPlah ada di dokumen tersebut.

---

## Kepatuhan aturan coding TALL/Laravel 13 (audit + perbaikan bertahap) 2026-10-05

Status: **PASS (focused verification) / BROWSER RVR**.

Audit kepatuhan terhadap `AGENTS.md` + `.ai/rules/index.md` +
`docs/CSS_USAGE_GUIDE.md` menemukan dan memperbaiki pelanggaran berikut.

### Diperbaiki

1. **Pint gate**: `RkasRevisionComparisonController`,
   `ArkasMirrorFreshnessService`, `RkasRevisionComparisonService`,
   `TransactionItemOrderingTest` kini style-clean. `vendor/bin/pint --test`
   sekarang `passed` untuk seluruh repo.
2. **Merge conflict yang sudah ada sebelumnya** di
   `app/Services/RkasReportExcelService.php` (sisa `stash@{0} autostash`
   pop gagal) di-resolve dengan instruksi user explicit: sisi
   "Updated upstream" dipertahankan, yaitu private `setCellValue()` yang
   memakai `setCellValueExplicit(..., DataType::TYPE_STRING)` untuk nilai
   string. Sisi "Stashed changes" kosong sehingga tidak ada kode hilang.
   Konsekuensi: kolom angka/karakter pada XLSX RKAS tidak lagi dikonversi
   Excel menjadi formula/tanggal. File ini sempat memblokir Pint dan
   `git diff --check` di seluruh repo.
3. **Fixture test usang**: `TransactionsTableLivewireTest::prepareSchoolConnection`
   tidak membuat kolom `sort_order` pada `transaction_items`, padahal
   `Transaction::items()` mengurutkan `COALESCE(NULLIF(sort_order, 0), id)`
   sejak commit `058d771`. Tiga test gagal `no such column: sort_order`
   sebelum perbaikan ini; semuanya hijau sesudahnya.
4. **Livewire 2 computed property → `#[Computed]`** (Livewire 3):
   `TransactionsTable::filteredStats/statuses/transactions` dan
   `ReconciliationList::summary/transactions`. `RkasBudgetSimulator::simulation`
   sudah menjadi pola kanon. Tidak ada lagi `get*Property()` di `app/Livewire`.
5. **`protected $casts` → method `casts()`** pada
   `app/Models/ArkasRkasItem.php`, mengikutikonvensi 40+ model lain.
6. **Return type eksplisit**: `SpjController` (13 method download/preview/export),
   `SpjReportUseCase::exportHonorPayments`, `ExtendedSpjReportUseCase::exportServiceRecipients`
   (+ override `exportHonorPayments` yang wajib sinkron agar covariant),
   middleware `EnsureActiveFiscalYear`/`EnsureActiveSchool`/`EnsureAdministrator`,
   `DocumentTemplateController::downloadStored/downloadMaster/sample`,
   `SchoolConfigurationController::letterhead`,
   `EmployeeController::honorsFor`, dan
   `ArkasMirrorBudgetService::hierarchyOptions`.
7. **`render(): View`** pada 9 komponen Livewire Database*/Rkas
   (`DatabaseDiagnostics`, `DatabaseMaintenance`, `DatabaseManagerAlerts`,
   `DatabaseManagerTabs`, `DatabaseOverview`, `DatabaseResetForm`,
   `DatabaseSchoolList`, `DatabaseStatusSummary`, `RkasBudgetSimulator`).
8. **Hard-coded color → semantic token** pada 5 view Livewire area
   authenticated: `rkas-planning-suggestion-tables` (21 hit),
   `spj-report-filter` (17), `tax-filter` (15), `transactions-table` (7),
   `reconciliation-list` (6). `text-slate-*`/`text-indigo-*`/`bg-indigo-50*`/
   `hover:bg-slate-*`/`border-slate-300` diganti `--ui-fg*`, `--ui-surface-*`,
   `--ui-line*`, `--theme-content-accent`, `--theme-action-*`. Sesuai
   `CSS_USAGE_GUIDE.md` §15 dan §17. Hierarchy teks dijaga agar tidak
   mendatar: strong→`--ui-fg-strong`, normal→`--ui-fg`, muted→`--ui-fg-muted`.

### Tetap dikerjakan terpisah (di luar scope audit ini)

- 32 view lain masih punya hit hard-coded color (`users/index`,
  `impersonation/index`, `school-backups/index`, `arkas/settings`,
  `employees/form`, `spj/*`, `spj-documents/template-preview`, dst).
  Semuanya masih ditoleransi `view-theme-hardening.css`, tetapi
  bertentangan dengan §15 dan tidak boleh ditambahkan pada kode baru.
- 116 `<input>`/`<select>`/`<textarea>` mentah vs primitive
  `x-ui.input/select/textarea` yang sudah lengkap. Contoh paling menonjol:
  `resources/views/transactions/partials/detail/source-reconciliation.blade.php:113-173`.

### Evidence

`vendor/bin/pint --test --format agent` → `passed` (seluruh repo, sebelumnya
gagal). `git diff --check` bersih. `npm run build` sukses,
`npm run theme:qa` "All representative theme checks passed",
`php artisan view:cache` sukses. Focused suite hijau: 33 test/130 assertions
(Rkas report + item ordering + transactions table + reconciliation +
workflow filter), 24 test/213 assertions (GUI readiness + authorization +
middleware), 27 test/244 assertions (termasuk SpjReportLayoutTest).
Dua kegagalan `SpjReportLayoutTest::spj_monitoring_surfaces_use_theme_tokens`
dan `RkasBudgetUiTest::test_rkas_workspace_exposes_revision_comparison_freshness_and_report_package`
sudah dibuktikan pre-existing lewat stash A/B: tetap FAIL pada working tree
tanpa perubahan batch ini. Visual browser tetap RVR.

Perubahan ini tidak mengubah business rule, tenant boundary, lifecycle
numbering, maupun kontrak sync; semua verifikasi difokuskan pada
signature/style/markup.
