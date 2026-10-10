# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-10-11** (audit source refaktor helper tampilan; regression/CI masih RVR — lihat bagian audit terbaru di bawah).

> Repository canonical saat ini adalah `amwaluddinlubis-code/raw-rkas` dan menggunakan satu branch aktif: `main`.
> Branch `hardening/raw-rkas-audit` telah digabung melalui PR #1; referensi branch lama hanya dipertahankan sebagai evidence historis, bukan branch kerja aktif.

## Indeks log kemajuan per fungsi

`CURRENT_PROGRESS.md` adalah sumber kebenaran status terbaru. Riwayat per fungsi dipecah ke file berikut (2026-10-11) agar tiap file < 128KB:

| File | Isi |
| --- | --- |
| `PROGRESS_NUMBERING_LIFECYCLE.md` | Penomoran & Lifecycle Dokumen — 5 seksi |
| `PROGRESS_OFFICIAL_REPORTS.md` | Laporan Resmi & Format BOS — 4 seksi |
| `PROGRESS_QA_BROWSER.md` | QA Browser — 2 seksi |
| `PROGRESS_DOCUMENTATION.md` | Dokumentasi — 1 seksi |
| `PROGRESS_AUDIT_REALDATA.md` | Audit & Verifikasi Real-Data — 3 seksi |
| `PROGRESS_UI_UX.md` | UI/UX — 17 seksi |
| `PROGRESS_SYNCHRONIZATION.md` | Sinkronisasi ARKAS — 19 seksi |
| `PROGRESS_RKAS.md` | RKAS — 5 seksi |
| `PROGRESS_REGRESSION_CI.md` | Regression & CI — 8 seksi |
| `PROGRESS_P0_WORKSTREAMS.md` | Workstream P0 — 5 seksi |

## Status release saat ini

```text
FUNCTIONAL BASELINE : PREVIOUS VERIFIED GATE PASS / run #46
CURRENT CODE GATE   : FOCUSED VERIFICATION COMPLETE; FULL CI PENDING after Bulk Preview source change
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE / CODE GATE PASS
FINAL RELEASE       : NOT YET
```

P0 code/dependency integration gate terakhir terverifikasi pada run #46 sebelum perubahan Bulk Preview. Source change terbaru sudah mendapat focused verification lokal, tetapi belum full CI. Aplikasi belum boleh disebut final release-ready karena generated-document real-data QA, browser/operator QA, Office/PDF visual fidelity, dan installed-runtime verification masih terpisah dari deterministic CI.

---

## Livewire / TALL migration — Phase 1 + Phase 2

### Phase 1 — mutation boundary audit

Status: **SOURCE AUDIT COMPLETE**.

Audit seluruh `app/Livewire/` menemukan 27 component dan mengklasifikasikan read-only/UI-state, context mutation, serta mutation sensitif. Detail matriks berada di `LIVEWIRE_MIGRATION_PLAN.md`.

### Phase 2 — authorization hardening

Status: **IMPLEMENTED + REGRESSION PASS + FULL CODE GATE PASS / BROWSER RUNTIME RVR**.

Source hardening:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization
```

Critical-suite integration:

```text
701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

Boundary yang sudah ditutup:

- `UserManagement::{createUser,updateUser,deleteUser}` → ADMIN-only;
- `SchoolMaster::createSchool` → ADMIN-only;
- `DatabaseMaintenance::run` → ADMIN-only;
- `DatabaseResetForm::resetDatabase` → ADMIN + active-school match + exact confirmation;
- `DatabaseSchoolList::{activate,migrate}` → ADMIN-only;
- `DocumentStorageSettings::save` → OPERATOR/ADMIN sebelum reuse;
- `SchoolSelector::selectSchool` mempertahankan guard admin/own-school;
- `YearSelector::selectYear` tetap accepted context mutation.

`LivewireMutationAuthorizationTest` berada di SPJ Critical dan tetap tercakup oleh green SPJ Critical gate PR #486 serta run #46. Rule arsitektur tetap: mutation Livewire sensitif harus authorize pada request action/policy/persistent mechanism yang benar-benar berlaku, bukan hanya mengandalkan route GET halaman awal.

Status area yang dimigrasikan:

- Transaksi: `TransactionsTable` untuk daftar dan `TransactionDetailWorkspace` untuk detail, filter/read/write state Livewire dengan mutasi tetap melalui service domain;
- RKAS: `RkasBudgetWorkspace` + `RkasBudgetFilter` read-only; tabel hierarki dan ringkasan berada di dalam workspace Livewire;
- SPJ Persiapan/Paket/Laporan/Monitoring: Livewire filters/lists + SPA tab navigation; detail Paket mutation-heavy tetap server-rendered;
- Pajak: `TaxFilter` read-only;
- Pegawai: `EmployeeDirectory` read-only;
- Data Sinkronisasi: `SyncedDataNavigation` UI-state/read-only;
- Database Aktif: read-only panels + role-hardened mutation components;
- User/Master Sekolah: role-hardened mutation components;
- Penyimpanan Dokumen: dormant component sudah OPERATOR/ADMIN-hardened sebelum reuse.

Browser/operator behavior tetap RVR sampai `GUI_RUNTIME_QA.md` dijalankan pada runtime aktual.

---

## Prioritas kerja aktif

P0 integration/dependency repair dan Phase 2 authorization sudah selesai. Prioritas berikutnya:

1. **Generated-document real-data/operator QA** untuk Paket nyata yang tersedia;
2. **browser/operator QA desktop-laptop** berdasarkan `GUI_RUNTIME_QA.md`, khususnya repeated `Livewire.navigate`, SPA tab SPJ, modal preview, pagination, dropdown, dan filter URL state;
3. **Office/PDF visual-output QA** untuk individual template, master terbaru, XLSX/PDF hasil generate, print area/page break/header/footer;
4. lanjutkan JASA_LAINNYA multi-penerima dan PEMELIHARAAN bahan+upah pada output nyata bila ditemukan mismatch;
5. setelah operator/runtime flow stabil, baru pertimbangkan kandidat migrasi Livewire read-only berikutnya seperti Rekonsiliasi.

---

## Open verification / release blockers

Code/dependency integration gate **bukan lagi blocker**. Blocker/verifikasi tersisa:

- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML/template nyata pada browser aktual masih RVR;
- browser/operator desktop-laptop QA masih RVR;
- official-template print/layout/output QA masih RVR;
- installed-runtime checks masih DEFERRED;
- mobile/tablet runtime QA tetap RVR/non-blocker untuk target desktop-laptop.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data agar test/audit PASS.
2. Jangan memakai deterministic fixture sebagai bukti real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source/runtime change setelah gate `e4ba5cf...` (workflow run #46) membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary pada action request.
7. GUI source PASS tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan `operator flow -> temukan bug nyata -> perbaiki -> focused regression bila perlu`.

---

## Authenticated route performance sweep + searchable-select fix 2026-09-25

Status: **FUNCTIONAL FIX PASS / SHELL RUNTIME EVIDENCE / BROWSER RUNTIME RVR**.

Perbaikan yang diterapkan:

- komponen `x-ui.searchable-select` memakai directive `@entangle(...).live` canonical ketika berada di dalam Livewire, menggantikan ekspresi string `$wire.entangle(...)` yang menghasilkan error browser `$wire is not defined` pada tab Paket SPJ;
- import `DB` ganda pada model transaksi dibersihkan sehingga bootstrap route transaksi tidak lagi berhenti pada fatal error duplicate import.

Evidence aktual:

- 38 route GET statis authenticated diuji dari shell; 34 HTTP 200, 3 redirect normal, dan route `/setup` expected 404 karena user sudah ada;
- route inti authenticated setelah perbaikan: `/` rata-rata 1,43 detik, `/spj` 141 ms, `/transaksi` 1,77 detik, dan `/penganggaran-rkas` 342 ms pada tiga request sequential per route;
- route GET statis paling lambat pada sweep: template dokumen 5,65 detik, importer 5,02 detik, SPJ 4,80 detik, transaksi 4,23 detik, database manager 3,78 detik, dan dashboard 3,35 detik;
- Blade cache berhasil, Vite build berhasil 3,30 detik, dan focused regression 30 test / 341 assertion lulus;
- `git diff --check` bersih.

Route dinamis yang memerlukan ID nyata dan route mutasi POST/PUT/DELETE tidak dijalankan pada sweep ini. Pengujian tersebut memerlukan fixture/flow khusus agar tidak mengubah data operator. Browser visual dan interaksi Livewire aktual tetap RVR sampai checklist runtime browser dijalankan.

## Query performance tahap 1 — 2026-09-25

Diagnosis: disk mentah cepat (5 query = 3 ms) dan indeks events/rekonsiliasi sudah
memadai; kelambatan `spj.index` 3–18 detik berasal dari latensi per query
sesaat (lock/WAL saat burst tulis sync; `busy_timeout=5000` membuat baca
menunggu) dikali volume query per halaman. Perbaikan:

- checkpoint WAL 4 DB sekolah + `PRAGMA wal_checkpoint(PASSIVE)` otomatis
  pasca-sync sukses (`ArkasSynchronizationServiceV2`);
- `scopeNeedsReconciliation`: agregasi event terbaru sekali (`GROUP BY`) lalu
  di-join, bukan subquery `MAX` per baris — hasil identik (dikunci test);
- kolom generated + indeks: `sx_id_anggaran` pada rapbs mirror (snapshot
  memfilter `ID_ANGGARAN` di database), `sx_satuan`/`sx_harga_barang`/
  `sx_batas_atas` + indeks komposit `(sx_tahun, sx_nama_barang, sx_satuan)`
  pada acuan harga pusat (GROUP BY 68 ribu baris 127 ms → 72 ms, INDEX SEARCH);
- temuan sampingan: migrasi lookup 150449 tercatat jalan padahal tabel referensi
  dibuat runtime sesudahnya sehingga kolomnya tak pernah terpasang; migrasi
  baru melengkapi yang hilang (guard `hasTable`/`hasColumn`).

Evidence aktual: scope rewrite ekuivalen pada salinan DB nyata (45 = 45);
`TransactionNeedsReconciliationScopeTest` 4/4, `ReferenceModuleTest` 6/6,
suite rekonsiliasi/budget terkait hijau individual. Browser/runtime tetap RVR.

Hierarki RKAS memprioritaskan relasi `arkas_mirror_rapbs.ID_REF_KODE` ke `arkas_mirror_ref_kode.ID_REF_KODE`; kode dan nama kegiatan diambil dari referensi tersebut, sedangkan `KODE_PROGRAM`, `NAMA_PROGRAM`, `KODE_SUB_PROGRAM`, dan `NAMA_SUB_PROGRAM` diambil dari payload RAPBS lalu memakai referensi parent sebagai fallback. `ID_LEVEL_KODE` ikut dibawa sebagai metadata level referensi.

Filter periode RKAS mirror sekarang menormalisasi `ID_PERIODE` melalui `arkas_mirror_ref_periode` terlebih dahulu. Nama/metadata bulan, triwulan, dan semester dari referensi menjadi dasar agregasi pagu, volume, realisasi, serta jumlah opsi filter; fallback numerik hanya digunakan bila referensi periode belum tersedia.
