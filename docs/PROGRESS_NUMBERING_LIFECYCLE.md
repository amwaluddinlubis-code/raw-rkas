# Penomoran & Lifecycle Dokumen — Log Kemajuan

> Riwayat pekerjaan penomoran SPJ, registry penomoran canonical, filter periode, koreksi R1/R6, dan lifecycle dokumen DRAFT → READY → NUMBERED → FINAL.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Perbaikan R1 + R6 + 2 temuan QA (2026-10-08)

Status: **FUNCTIONAL PASS (focused)**.

1. **R1** — `synchronizeDerived()` menulis `activity_references` /
   `business_partners` tanpa `fund_source_id` sehingga data sumber dana
   berbeda saling menimpa. Migrasi
   `2026_10_08_112311_add_fund_source_id_to_reference_tables` menambah kolom
   `fund_source_id` (nullable, FK restrict) ke kedua tabel + unique index
   baru `[fiscal_year_id, fund_source_id, activity_code]` dan
   `[fund_source_id, name, npwp]`. Kunci tulis `updateOrInsert` kini
   menyertakan `fund_source_id`.
2. **R6** — `rebuildSequences()` memakai `reset_period` saat ini; migrasi
   `2026_10_08_112353` menambah `spj_documents.numbering_period_key` yang
   diisi saat `assign()`.
3. **QA** — `lang/id/validation.php` dilengkapi dan `APP_NAME` diubah dari
   `Laravel` menjadi `SPJ BOSP Web`.

## Perbaikan cluster penomoran + lifecycle dokumen (2026-10-08)

Status: **FUNCTIONAL PASS (focused)**.

Patch `patch-spj-bosp` 29 commit diterapkan via `git am` (seluruh isi zip
kecuali 0011 docs: batch pertama 0001–0006, 0008–0010, 0012, 0014–0026,
0028–0030, disusul 0007, 0013, 0017, 0027, 0030 setelah basisnya tersedia;
hanya 0011 docs yang diterapkan manual karena konteksnya bergeser). Sorotan:

1. **K2** — race duplikat sequence saat `fund_source_id` NULL (SQLite
   menganggap NULL distinct): sentinel `0`
   (`SpjDocumentNumberService::NULL_FUND_SOURCE_SENTINEL`) + migrasi
   backfill + kolom NOT NULL.
2. **S3** — race insert-pertama sequence → retry idempoten
   (`allocateSequenceNumber` menangkap duplicate-key 23000).
3. **T1** — paket CANCELLED terminal dikecualikan dari blocker
   penomoran/penutupan triwulan; `finalizePackage` tetap NUMBERED-only.
4. **S2** — `replaceDocument` (cancel + assign + sync) satu transaksi
   `school`; kegagalan assign me-rollback cancel.
5. **S4/R5** — ubah urutan rincian diblokir pada paket NUMBERED;
   rollback membersihkan arsip folder dokumen.
6. Penguatan lain: otorisasi mutasi Livewire K1, relink fingerprint T2,
   safe-sync S1/S6/S7, guard PDF T3, arsip XLSX S8, audit S5,
   reconcile R2/R4/R8 (dengan penyesuaian 2 test), hapus V1 R15,
   path traversal R10, password min-12 R11, audit impersonasi R12,
   kop surat R13, warning document_date R14, a11y explorer, Pint R15/30.

Regression yang dijalankan di sini: `DocumentNumberingWorkflowTest`
15 passed / 63 assertions, `SpjCancelledNumberingBlockerTest` 4 passed,
`SpjDocumentReplaceAtomicityTest` 2 passed (ditambah `RefreshDatabase`
agar deterministik — test patch gagal isolasi tanpa itu),
`SpjNumberingRollbackTest` 5 passed, `SpjSourceRelinkTest` 13 passed
(fixture disesuaikan ke skema pasca-drop kolom duplikat),
`LivewireMutationAuthorizationTest` 6 passed,
`SpjSourceReconciliationResolutionTest` 11 passed; Pint passed;
`view:cache` + `git diff --check` bersih.

RVR tersisa: `php artisan migrate` untuk migrasi sentinel di DB
sekolah nyata dan `php artisan spj:verify` penuh.

## Unifikasi filter tab Persiapan + Paket ikut pola Atribut (2026-10-07)

Status: **FUNCTIONAL PASS (focused + browser)**.

Bar filter Persiapan (select Bulan + Triwulan ganda) dan Paket
dimigrasi ke pola Atribut: segmen Bulan/Triwulan/Semester/Semua +
satu dropdown periode (sembunyi saat Semua) + searchable
Status/Kategori + kontrol Baris + Bersihkan, 1 baris responsif.
`SpjPreparationFilter` kini memakai `mode/periode` (+semester,
sebelumnya tak ada) dengan pemetaan URL lama `?month=/?
quarter=` di `mount()`; `preparationData` + `preparationFilterRules`
mengikuti. Tanpa JS baru — enhancement dropdown daisy + Livewire
yang sudah ada terpakai ulang.

Regression: `SpjPreparationFilterTest` 3 passed,
`SpjPackagePeriodFilterTest` 6 passed, `SpjTabFiltersLivewireTest`
7 passed (sebelumnya 7 failed pra-eksis: fixture `sort_order`
dilengkapi + ekspektasi diselaraskan API baru), Pint passed,
`view:cache` + `git diff --check` bersih. Browser: Persiapan TW1
23 transaksi, legacy `?month=6` → mode bulan Juni benar, Paket TW1
23 paket, Atribut TW1 23 — tanpa console error.

Perubahan lanjutan pada sesi yang sama (commit `28ba6cf`):

- Menu **Rekap Triwulan** pindah dari bar filter Persiapan ke
  sidebar di bawah **Laporan SPJ** (aktif saat rute
  `spj.quarter-recap`); tombol di filter dihapus.
- Radius trigger dropdown select disamakan ke token kontrol form
  (pengecualian aturan blanket `main button` di `human-ui.css`
  untuk `.ui-searchable-select-trigger`).
- Tinggi tombol segmen filter periode disamakan ke token tinggi
  kontrol (`--profile-control-height`).
- Popup kalender input tanggal Indonesia ditambatkan ke kotak field
  (input native menutupi wrapper, bukan 1px) agar tampil tepat di
  bawah/atas field; dropdown daisy di-portal ke body dengan
  penempatan dinamis + handler resize/scroll.
- Evidence: `SidebarRoleVisibilityTest` 3 passed,
  `GuiAudit09To13SourceReadinessTest` 23 passed, browser: menu
  sidebar aktif benar, radius/tinggi seragam 8px/44px terukur,
  popup kalender sejajar field (screenshot).

## Filter periode tab Paket/Atribut 500 + state Bulan/TW Persiapan (2026-10-06)

Status: **FUNCTIONAL PASS (focused) / RVR tersisa pra-eksis**.

Temuan browser QA pada `/spj?tab=paket`: memilih periode Triwulan 1
memicu `livewire/update` 500 (`no such column:
transactions.transaction_date`). Akar: `SpjWorkspaceUseCase::
packageListData`/`attributeListData` memfilter strftime pada kolom
yang sudah di-drop pasca-refactor mirror (plus format `%q`/`%s`
yang tidak dikenal SQLite — TW/semester takkan pernah cocok).
Perbaikan: helper `applyPackagePeriodScope()` via JOIN mirror +
rentang bulan (`whereMirrorDate`, pola sama seperti
`SpjPeriodicReportUseCase`), nilai liar diabaikan. Terverifikasi
browser: TW1 Paket 23 paket + Atribut 23 tanpa console error;
Persiapan TW1 23 transaksi; Laporan TW1 empty-state benar.

Temuan kedua: filter Persiapan menampilkan Bulan=Juni +
Triwulan=TW1 bersamaan (data ikut Bulan, backend month-precedence
memang deliberate). Catatan 2026-10-07: pendekatan
`updatedMonth/updatedQuarter` tersebut sudah digantikan migrasi
`mode/periode` satu-scope (entri 2026-10-07 di atas) —
`SpjTabFiltersLivewireTest` yang tadinya 7 failed kini 7 passed
(fixture `sort_order` dilengkapi + ekspektasi diselaraskan API baru).

Regression saat itu: `SpjPackagePeriodFilterTest` baru 4 passed /
13 assertions, `SpjPreparationFilterTest` 3 passed, Pint passed,
`view:cache` + `git diff --check` bersih.

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL PASS / REGISTRY CANONICAL.**

Source of truth:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Gate PR #486 dan run #46 mempertahankan regression suite yang mencakup first numbering, cancel/reserved sequence, tail rollback, quarter dependency, fund-source scope, NUMBERED description carve-out, FINAL lock, dan registry-based consumers.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---
