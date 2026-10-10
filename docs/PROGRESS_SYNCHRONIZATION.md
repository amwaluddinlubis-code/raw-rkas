# Sinkronisasi ARKAS — Log Kemajuan

> Riwayat pekerjaan sinkronisasi ARKAS: mirror kas, safe sync, reconciliation, importer, dan kesehatan mirror.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

## Relink 88 + hapus 2 SOURCE_MISSING tenant 10260756 (2026-10-04)

Status: **FUNCTIONAL PASS (focused, real tenant data) / BROWSER RVR**.

Sync ARKAS #41 (487 baris) menghapus + membuat ulang BKU: 94 transaksi
baru ACTIVE, 90 lama `SOURCE_MISSING` (status benar, bukan bug sync).
Relink rincian menemukan 0 pasangan karena mirror lama ter-prune → ditambah
fallback **sidik snapshot agregat** (`SpjSourceRelinkService::
snapshotFingerprint/lastContentSnapshot/rebuiltSnapshot/
previewSnapshotPairs`, guard unik 1:1 + tanggal + jumlah rincian sama,
refresh ulang sebelum tulis). Tier-2 **identitas tanpa penerima** (koreksi
vendor dinas, mis. RIZKY PONSEL → Naufal Fotocopy) diposisikan berurutan
dengan flag review → `requires_reconciliation`. Tier-3 **nomor pesanan Siplah
identik** (`previewOrderPairs`, guard jumlah rincian; tanggal tak diketahui
→ review). Saran `--suggest` (skor kata + bonus akun/tanggal/nominal) +
eksekusi eksplisit `--pair=OLD:NEW` tervalidasi untuk sisa yang butuh
keputusan operator. Eksekusi sempat gagal total
oleh FK gantung `spj_external_checklist_ticks.checked_by → users` (skema
lama, hanya di tenant ini; migrasi kanonis sudah "tanpa FK") → diperbaiki
migrasi repair baru `2026_10_04_000003` (rebuild tanpa FK, idempotent,
0 baris terdampak). Bug fp validasi tier-2 (identitas vs penuh) ditemukan
dan diperbaiki sebelum eksekusi penuh; sync ulang pre-execute (run #42)
me-refresh sisi baru tanpa mengubah pasangan.

Hasil: **90 tuntas, missing 0** (31 eksak + 26 koreksi vendor + 12 pesanan
Siplah + 19 manual operator via `--pair`, 88 audit `SOURCE_RELINK` + event
`SOURCE_RETURNED`, flag review untuk koreksi/vendor, paket/overlay ID lama
utuh kecuali 2 hapus paksa atas instruksi eksplisit: #183 (paket #80/0079)
dan #184 (paket #81/0080) yang dilebur ke 3 transaksi — nomor hilang tanpa
jejak CANCELLED, hanya backup file. Mirror health 34/34 ok; backup di
`storage/app/school-backups/relink-10260756-*`.

Evidence: `SpjSourceRelinkTest` 8 passed / 35+ assertions, Pint passed,
`git diff --check` bersih.

## Explorer Tabel Database Aktif dipisah pusat vs sekolah (2026-10-04)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Halaman `/pengaturan/database-aktif` kini membedakan sumber Explorer via
toggle `Sekolah Aktif` vs `Database Pusat` (`DatabaseTableExplorer::$scope`,
`$schoolCount`/`$centralCount`). `SchoolDatabaseManager::listTables /
tableSchema / tableData` menerima koneksi eksplisit `school|central`
(`resolveExplorerConnection`, koneksi arbitrer ditolak); daftar pusat
menyembunyikan `CENTRAL_DENIED_TABLES` (`users`, `sessions`,
`password_reset_tokens`, `cache*`, `jobs*`, `failed_jobs`), endpoint
`table-summary` mendukung `?scope=central` dengan deny-list yang sama.
Masking kolom sensitif tetap via `spj.database_manager_sensitive_columns`.

Evidence: `DatabaseTableExplorerScopeTest` 4 passed / 18 assertions,
Pint passed, `view:cache` sukses, `git diff --check` bersih.

## Kesegaran mirror ARKAS dipusatkan di Dashboard (2026-10-04)

Status: **FUNCTIONAL PASS (focused)**.

`<livewire:arkas-health-banner />` dihapus dari empat halaman (Penganggaran
RKAS, Transaksi, Rekonsiliasi, Data Hasil Sinkron) dan dipasang sekali di
samping "Kondisi Sistem" pada Dashboard (`dashboard-workspace`). Alasan UX:
panel yang sama tidak perlu muncul berulang tiap halaman. Evidence:
view:cache/view:clear valid, `WebRouteSmokeTest` 6/8 hijau.

## Monitor kesehatan importer lintas sekolah + audit mirror (2026-10-04)

Catatan: halaman `/pengaturan/arkas/import-monitor` beserta cluster importer
terhapus oleh `ea8c507`. Bagian ini dipertahankan untuk jejak historis; fitur
audit mirror tetap hidup di `/penganggaran-rkas/audit-mirror`.

- Halaman admin `/pengaturan/arkas/import-monitor` mengagregasi run
  importer terakhir per sekolah/tabel/tahun anggaran: status, baris
  ditulis/dihapus, umurnya, dan jumlah tabel stagnan (>7 hari), gagal,
  dan belum pernah sinkron. Read-only, membuka tiap database tenant
  hanya untuk membaca `arkas_import_runs` dan mengembalikan konteks.
- Halaman `/penganggaran-rkas/audit-mirror` merangkum jejak mirror:
  baris ditambah/dihapus/berubah antar dua pengesahan terakhir, jumlah
  periode soft-deleted, dan kandidat kas yatim. Landing kerja KPA.

## Tinjau identitas pegawai lintas feed (2026-10-04)

Status: **FUNCTIONAL PASS (focused)**.

Halaman `/pegawai/tinjau-identitas` menampilkan daftar grup pegawai yang
memiliki nama ternormalisasi sama di feed berbeda tetapi tidak memenuhi
syarat fusi kuat (`ID_REF_KODE` ber-nuptk vs nik vs dapodik pelengkap).
Setiap grup menunjukkan sumber, NUPTK/NIP/NIK, Dapodik ID, dan status
terkunci operator sehingga admin/operator dapat memutuskan gabungan atau
pemisahan di sumber ARKAS/Dapodik. Read-only; tidak mengubah `employees`.

Evidence: `tests/Feature/EmployeeIdentityReviewTest.php` 2 passed / 7
assertions (service grouping + render halaman); Pint passed;
`view:cache`/`view:clear` bersih; `git diff --check` bersih.

## Import diff preview nominal (2026-10-04) — DIHAPUS

Modul Generic Importer (route `arkas.importer*`, controller, service,
view, dan nav) telah dihapus oleh `ea8c507`; tabel staging/history tidak
dihapus tetapi subsystem importer tidak lagi tersedia. Bagian ini
dipertahankan hanya sebagai jejak sejarah; audit mirror kini diteruskan
di `/penganggaran-rkas/audit-mirror`.

## Diagnostik integritas mirror ARKAS (2026-10-04)

Status: **FUNCTIONAL PASS (focused)**.

Tugas operator tidak lagi menegakkan konsistensi mirror secara manual.
Service baru `ArkasMirrorIntegrityService::summarize($yearId, $fundSourceId)`
mengaudit 4 hal yang sebelumnya hanya ditangani diam-diam oleh fallback/
orphan shares:

- kas BELANJA yang `ID_RAPBS`-nya tidak ada di mirror RKAS (realisasi tidak
  dapat diatribusikan ke pagu);
- kas yang tautannya menunjuk RKAS tahun/dana lain — ditampilkan informatif,
  sengaja tidak masuk total konteks aktif;
- baris periode yang `ID_RAPBS`-nya tidak ada di mirror RKAS;
- revisi anggaran yang tersetujui tetapi belum memiliki baris RKAS.

Hasil muncul sebagai panel "Perlu diperiksa: konsistensi data ARKAS" pada
`/penganggaran-rkas` tepat di bawah banner Kesegaran, hanya ketika ada
temuan. Data tetap read-only; tidak ada perubahan lifecycle numbering atau
business rule.

Evidence: `tests/Feature/ArkasMirrorIntegrityTest.php` 4 passed / 15
assertions; `RkasBudgetUiTest` 6/52 dan `RkasRevisionModesTest` 12/51 tetap
hijau; Pint passed; `git diff --check` bersih. Pada data nyata 10208183
(2026, BOS): 0 kas tak ber-RKAS, 0 periode yatim, 0 anggaran kosong; 424 kas
manunjuk RKAS tahun lain — informatif, tidak dihitung pada konteks aktif.

## Cross-year reconciliation artifact quarantine (2026-09-28)

Status: **FUNCTIONAL PASS (focused) / RVR untuk visual panel**.

Temuan operator: panel Rekonsiliasi Sumber menampilkan diff "Sebelum 2024 → Sesudah 2026"
(dua transaksi berbeda). Akar masalah: kolom "Sebelum" dibangun dari agregat mirror
per NO_BUKTI tanpa filter tahun pada sync lama (filter tahun baru ada sejak `9540937`);
NO_BUKTI berulang tiap tahun (105/153 no_bukti multi-tahun di data lokal) sehingga
baseline tercampur lintas tahun. Bukan salah jodoh transaksi (matching tetap
per tahun + sumber dana).

Perbaikan (read-path, tanpa migrasi, tanpa sentuh paket/dokumen SPJ):
`SpjSourceReconciliationService` menandai event lintas tahun sebagai artefak
(dikecualikan dari `latest`/badge/hint/resolve/bulk-review), panel menampilkan
catatan + tombol tutup beraudit (`REKONSILIASI_ARTEFAK_DITUTUP`), docs di
`SYNCHRONIZATION.md` §9.

Evidence: `CrossYearArtifactQuarantineTest` 5 passed / 22 assertions; suite rekon
terkait 26 passed per-file (`Resolution` 11, `Hardening` 6, `NeedsScope` 4,
`ReconciliationList` 5). `BulkReviewReconciliationTest` gagal di environment ini
(`testing.sqlite` tanpa tabel `users`) — terbukti pra-eksis via stash di main
bersih. `view:cache` + Pint bersih.

---

## Workstream ARKAS full-mirror + refactor overlay (2026-09-19)

Tujuan: ganti proses sinkronisasi dengan mirror penuh tabel ARKAS —
`ref_*` ke database pusat, sisanya ke database sekolah — lalu refactor
overlay aplikasi agar memanggil tabel mirror (hapus kolom ganda).

### Hotfix route `/spj` — 2026-09-19

Audit runtime menemukan dua blocker pada workspace Paket SPJ: penulisan SQLite
gagal dengan `database is locked`, dan resolver pajak membaca seluruh payload
`arkas_mirror_kas_umum` pada setiap request. Perbaikan yang diterapkan:

```text
SQLITE WRITE RETRY : transaksi alur update/kategori/finalisasi/rollback mencoba ulang 3 kali
LOCK UX            : kegagalan lock dikembalikan sebagai flash error yang dapat ditindaklanjuti
PAJAK RESOLVER     : filter memakai kolom generated/indexed sx_kategori, fallback legacy dipertahankan
REGRESSION         : filter tax-child tervalidasi pada ArkasMirrorSourceValueTest
```

Verifikasi aktual: `SpjWorkspaceMigrationTest` PASS (262 assertions),
`SpjPackageManualCategoryTest` PASS (6 assertions), dan regression test filter
pajak PASS (2 assertions). Browser/runtime setelah lock dilepas tetap RVR.

### Cache baca mirror per request — 2026-09-20

`Transaction::mirrorSource()` kini memo per instance (valid selama generasi
resolver tidak berubah) dan memakai relasi `items` yang sudah eager-load bila
tersedia; `ArkasMirrorResolver` kembali singleton per request dengan counter
generasi pada `forget()` sehingga tulisan mirror tetap menginvalidasi memo.
`SchoolDatabaseManager::activate/deactivate` memanggil `forget()` setiap ganti
database sekolah. Jalur daftar (`preparationData`, `report()`) eager-load
`items:id,transaction_id,source_item_id` + `Transaction::preloadMirrorSource()`
sehingga render sel tidak lagi menembak query per sel (full scan payload PAJAK
hanya sekali per request). Nilai yang dibaca identik (cache read-path saja);
tidak ada perubahan lifecycle, numbering, sync, maupun ownership data.

Verifikasi aktual: `ArkasMirrorSourceValueTest` 7/7 PASS (31 assertions —
termasuk dua asumsi singleton/cache lama yang kini terpenuhi),
`SpjWorkspaceMigrationTest` + `SpjReportLayoutTest` + `RoutineHonorRegister`
PASS (430 assertions), `SpjSixCategoryE2eTest` PASS (310 assertions),
`SpjNumberingRollback`/`LivewireMutationAuthorization`/`SpjPackageManualCategory`
PASS, Unit suite 78/78 (+1 file lolos terpisah). `SpjQuarterAuditCommandTest`
tetap gagal seperti sebelumnya (terbukti pra-eksis via probe bind-vs-singleton;
jalur raw-PDO, tidak tersentuh perubahan ini). Full suite satu-proses di
Windows tetap tidak dapat menjadi gate (lock/disk-I/O testing.sqlite —
isu lingkungan yang sudah didokumentasikan); gate canonical tetap CI.

### Perbaikan pager /pajak — 2026-09-20

Tombol halaman tidak berpindah karena `TaxFilterService::taxData()` membaca
halaman dari query HTTP mentah, sementara pager Livewire menyimpan posisi di
state komponen (`WithPagination::getPage()`). Kini posisi diambil dari
`$this->getPage()`; `TaxController@index` juga tidak lagi menjalankan agregat
yang hasilnya tak dipakai view (view hanya me-render komponen Livewire).

Verifikasi aktual: `TaxFilterLivewire` PASS termasuk regression navigasi
halaman 2 (48 assertions), Pint passed.

### Filter Siplah + rincian pajak /pajak — 2026-09-20

Halaman pajak mendapat filter Siplah (Ya/Tidak/Semua, sebaris filter lama
tanpa baris baru) dan kolom Siplah per baris dari flag mirror. Rincian
PPN/PPh/SSPD yang selalu Rp 0 diperbaiki: Blade memakai kolom lokal yang
sudah di-drop; kini memakai `sourceValue()` mirror seperti kolom Total.

Verifikasi aktual: `TaxFilterLivewire` PASS termasuk filter Siplah dan
rincian mirror (56 assertions), Pint passed, `view:cache` PASS.

### Filter jenis pajak /pajak — 2026-09-20

Dropdown jenis pajak (PPN/PPh 21/22/23/4/SSPD) sebaris filter lama, ikut
reset + URL state + reset halaman. Nilai disaring dari `sourceValue()`
mirror per jenis.

Verifikasi aktual: `TaxFilterLivewire` PASS (61 assertions), Pint passed,
`view:cache` PASS.

### Batch risiko-kecil halaman lain — 2026-09-20

`TransactionItem::mirrorKasUmum()` kini memo per instance (cek generasi
resolver, flush saat disimpan). Preload satu baris ditambahkan pada jalur
read-only: `TransactionsTable` (statistik + halaman), `TaxFilterService`,
`SpjPeriodicReportUseCase::summary()`, `AuditReportService` — semuanya
memakai memo yang sama sehingga nilai identik. `TransactionDetailWorkspace`
sudah tercakup memo (items eager-load).

Verifikasi aktual: `TransactionsTableLivewire` + `TaxFilterLivewire` +
`TransactionPresentation` PASS (60 assertions), Periodic/Audit-selection
PASS (87 assertions), Pint passed. Saran lanjutan (belum dieksekusi):
agregat SQL laporan, cache dashboard, preflight murah, batas `perPage=all`,
indeks nama referensi, lazy-kan status Database Manager.

### Preflight murah tanpa render ganda — 2026-09-20

`SpjTemplateRenderPreflight` tidak lagi me-render workbook penuh per template
sebelum download sungguhan (biaya 2× lipat). Pemeriksaan kini: template aktif,
file source ada, sheet canonical hadir (via `listWorksheetNames` tanpa load
penuh), dan tidak ada marker tak dikenal (pindai sel sheet canonical xlsx
read-only / XML docx, dibandingkan kunci `placeholders()` + awalan baris
berulang ITEM/UPAH — pesan error sama seperti guard output). Nilai placeholder
dihitung sekali per paket. Render+validasi penuh tetap tepat sekali pada
download sungguhan. Tambahan kecil: `SpjTemplateService::sourcePath()` public
(delegasi path lama, tanpa ubah perilaku).

Verifikasi aktual: `SpjDocumentGeneratorHardening` PASS (160 assertions —
termasuk penolakan unknown-marker), IndividualDownload/MasterExport/
PlaceholderInspector/PreviewParity PASS (82 assertions), `SpjSixCategoryE2e`
PASS (310 assertions), Pint passed.

### Smoke test seluruh route web + bug rekursi provision — 2026-09-21

`tests/Feature/WebRouteSmokeTest.php` memukul ±75 URL GET (publik, workspace,
transaksi, master, laporan incl. PDF/xlsx, pengaturan) dengan fixture valid:
tidak ada HTTP ≥500 dan tidak ada proses mati.

Temuan utama (bug nyata, sudah diperbaiki): **rekursi tak berujung
`provision() ↔ activate()`** di `SchoolDatabaseManager` — bila record
`SchoolDatabase` belum ada (sekolah baru/database segar), `activate()`
memanggil `provision()` yang `updateOrCreate` lalu memanggil `activate()`
lagi pada instance yang memo relasinya masih null → 130rb+ query identik
sampai stack overflow/proses mati. Perbaikan: `setRelation()` setelah
`updateOrCreate`. Insiden ini juga menjelaskan crash suite saat fixture
bert abrakan dengan folder data nyata.

Catatan harness: `withoutMiddleware()` mematikan `ShareErrorsFromSession`
sehingga view form 500 semu — diatasi via `withViewErrors([])`; env Git Bash
merusak nilai env berawalan `/` (diatasi tanpa leading slash); 404 `/setup`
dan `/kop-surat` adalah by-design (diassert terpisah). Fixture di-seed ke
file hasil provision terisolasi (bukan `:memory:`) karena request
mengalihkan koneksi ke file. Route mutasi destruktif tidak disentuh.

Verifikasi aktual: `WebRouteSmokeTest` PASS (8 assertions), Pint passed.

### Urutan dokumen pratinjau per operator — 2026-09-20

Urutan Dokumen & Template/pratinjau/arsip paket sebelumnya selalu alfabetis
`document_type` tanpa pengaturan. Kini `document_templates.sort_order`
(migrasi `2026_09_20_000000`, backfill alfabetis agar perilaku lama bertahan)
menentukan urutan; operator (ADMIN-only) menggeser via tombol ▲▼ kompak di
kolom Tindakan daftar template (tidak menambah section/panjang halaman);
template baru antre paling akhir. `SpjPackageTemplateSelector::forPackage()`
dan katalog memakai `sort_order` terlebih dahulu.

Verifikasi aktual: `DocumentTemplateOrderTest` PASS (8 assertions: swap,
tepi, preview mengikuti urutan, ADMIN-only), Hardening 160 PASS,
`LivewireMutationAuthorization` PASS, Pint passed, `view:cache` PASS.

Audit lanjutan route `/spj/penomoran?quarter=1` menemukan scope tanggal halaman
dan submit tidak konsisten: halaman sudah memakai bulan dari `mkas.sx_tanggal`,
sedangkan submit masih memakai `transactions.transaction_date` lokal. Submit
sekarang memakai JOIN dan bulan mirror yang sama untuk pemeriksaan `notReady`
serta pemilihan paket READY/NUMBERED. Regression route berhasil menomori satu
paket dengan tanggal lokal kosong tetapi tanggal mirror berada di triwulan 1.

Audit placeholder generator menemukan resolver hierarki Program/Sub Program masih
menunjuk tabel legacy. Generator sekarang memprioritaskan rantai mirror
`kas_umum -> rapbs_periode -> rapbs -> ref_kode`, menurunkan kode parent dari
kode kegiatan secara deterministik, dan mengambil nama parent dari baris
`ref_kode` pusat. Fallback legacy dipertahankan hanya untuk data lama.

### Selesai

```text
MIRROR REGISTRY      : 30 tabel tetap (13 pusat + 17 operasional, tanpa ptk/kosong/internal)
MIRROR GUI           : halaman Mirror ARKAS (importer generik lama disembunyikan dari nav)
LIVE SYNC 10260756   : 90.919 baris mirror 1:1, 0 kolisi key
RESOLVER             : ArkasMirrorResolver (agregat BELANJA+PAJAK, chain rapbs→ref_kode, cache/request)
READ PATH            : 39 file PHP + 30 Blade + semua query SQL filter/sort/search via JOIN mirror
WRITERS              : V2 canonical ikut menulis mirror + event rekonsiliasi dari snapshot mirror
DROP FISIK           : 17 kolom transactions + 5 kolom transaction_items + trigger snapshot lama
TENANT NYATA         : 10260756 + 10208246 termigrasi; baca mirror terverifikasi di data nyata
```

### Mirror 2 tombol: referensi vs sekolah (2026-09-19)

```text
TOMBOL 1 Sinkronisasi Referensi : 13 tabel ref_* → database pusat (sekali saja)
TOMBOL 2 Sinkronisasi Sekolah   : 17 tabel operasional → database sekolah aktif
PROGRESS BAR                    : per tabel (10→95%) via endpoint status JSON, polling 2 detik
LIVE 10260756                  : refs 75.396 baris / school 14.704 baris
```

Nama "Mirror" di UI diganti "Sinkronisasi". Route lama `arkas.mirror.sync`
(gabungan) dihapus. Sekolah pertama jalankan kedua tombol; sekolah
berikutnya cukup tombol 2.

### Migrasi 3 tenant berdata ke skema baru (2026-09-19)

```text
10208183 (46 tx) : backup → migrate → backfill --backup → VERIFIED 0 mismatch
10208246 (52 tx) : backup → backfill --backup (top-up) → VERIFIED 0 mismatch
10260756 (170 tx): backup → backfill --backup (top-up) → VERIFIED 0 mismatch
```

Perintah: `spj:backfill-mirror-from-local {npsn} --backup=... [--refresh]`
(dedup shortfall pajak agar tak double-count dengan baris PAJAK sync),
verifikasi: `spj:verify-mirror-backfill {npsn} --backup=...`
(no_bukti/gross/tax/net per transaksi vs backup).
Backup tersimpan di `tenant-backup-20260919/` + `predrop-backup/`.

### Belum selesai / RVR

```text
FULL SUITE           : PASS 2026-09-19 — 0 failed / 6462 assertions
                       (DB pusat :memory:; testing.sqlite file korup di Windows — isu lingkungan)
BROWSER QA           : RVR — halaman Mirror ARKAS + daftar transaksi pasca-DROP belum dicek browser
DERIVED TABLES       : DIPERTAHANKAN sebagai read-model sync (arkas_bku_rows/rkas/activity/account)
                       untuk workspace RKAS; bukan overlay operator
```

### Kontrak yang dipertahankan

- Boundary tenant `School + Fiscal Year + Fund Source`; overlay operator tidak ditimpa sync.
- Agregat mirror identik dengan semantik V2 (dibuktikan pada data nyata 10260756).
- Kolom kunci SQL yang di-drop diganti JOIN mirror + kolom generated VIRTUAL berindeks.

### Mode revisi RKAS: persetujuan vs pengajuan (2026-09-22)

Halaman `/penganggaran-rkas` kini menampilkan dua mode revisi dari tabel
`arkas_mirror_anggaran` per tahun+sumber dana: **Persetujuan Terakhir**
(revisi `is_approve = 1` terbaru; default, sama dengan perilaku lama) dan
**Pengajuan Terakhir** (revisi terbaru menurut tanggal; bila lebih baru dari
persetujuan maka berstatus `is_aktif = 1`, `is_approve = 0` dengan badge
menunggu persetujuan). Mode pengajuan nonaktif bila identik dengan
persetujuan. Lifecycle/numbering/sync tidak tersentuh; hanya snapshot pagu
yang diganti.

Verifikasi aktual: `RkasRevisionModesTest` 4 passed (14 assertions),
`RkasBudgetUiTest` source PASS, Pint passed, `view:cache` PASS, `npm run
build` PASS. `RkasBudgetFilterTest` (5) + `RkasHierarchyTest` (4) gagal
pra-eksis: fixture legacy (`arkas_rkas_items`) vs skema mirror aktif —
tidak terkait perubahan ini. Browser/runtime QA tetap RVR.

### Koreksi split usang RKAS (2026-09-23)

Cross-check dokumen `RKAS REVISI` SDN 318 vs mirror menemukan 7 item yang
split periodenya melebihi header karena baris `soft_delete = 1` ikut
terjumlah pada tampilan triwulan/bulan (contoh: Seragam Juli ganda).
`snapshot()` kini mengabaikan split terhapus; agregat tahun tidak berubah
(header sudah benar 120.600.000). Filter yang sama juga dipasang pada loop
baris rapbs dan saran perencanaan. Resolver riwayat (cetak/audit/get-by-key)
sengaja tidak difilter agar dokumen lama tetap terbaca. Tidak ada cleanup
data — baris usang tetap di mirror, hanya tidak dihitung. Regression tercakup
`RkasRevisionModesTest::test_soft_deleted_periode_splits_are_excluded_from_snapshot`.

---

Dokumen ini adalah sumber status release utama untuk branch `main` di repository mirror `raw-rkas`. Detail gate/command verification berada di `P0_VERIFICATION_KIT.md`; prioritas berada di `DEVELOPMENT_ROADMAP.md`; kontrak bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic test/CI yang benar-benar dijalankan;
- **REAL-DATA VERIFIED**: dibuktikan pada database sekolah nyata atau isolated copy tanpa fabrikasi data;
- **RVR**: masih memerlukan real-value/runtime/operator verification;
- **DEFERRED**: sengaja tidak menjadi fokus aktif saat ini, bukan berarti PASS.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS / REAL-DATA RECONCILIATION ACTIVE.**

Contract tetap:

- ARKAS/BKU source readonly;
- operator SPJ overlay tidak dihapus oleh sync;
- source missing/returning mempertahankan identity;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- tenant boundary `School + Fiscal Year + Fund Source`.

Hardening rekonsiliasi: resolution terakhir menjadi checkpoint idempotensi. Sinkronisasi dengan `source_hash` yang sama atau snapshot item yang sama dengan event yang sudah diselesaikan tidak membuat antrean rekonsiliasi baru.

Dashboard dan halaman `/rekonsiliasi` memakai scope kebutuhan rekonsiliasi yang sama. Transaksi dengan resolution yang cocok dengan event sumber terbaru tidak lagi dihitung atau ditampilkan meskipun flag lama `requires_reconciliation` masih bernilai aktif; `SOURCE_MISSING` tetap ditampilkan sampai sumbernya kembali atau ditangani sesuai alur.

Paket `NUMBERED`/`FINAL` yang sudah pernah memiliki resolution juga dikeluarkan dari antrean rekonsiliasi agar tidak diminta ditinjau ulang setelah penomoran.

Detail transaksi selalu menampilkan penyelesaian rekonsiliasi terakhir dan riwayatnya pada seluruh status paket, termasuk `DRAFT`, `READY`, `NUMBERED`, `FINAL`, dan `CANCELLED`. Form tindakan tetap mengikuti aturan source missing serta penguncian paket bernomor/final.

Pada paket `NUMBERED`/`FINAL` yang sudah memiliki resolution, flag legacy `requires_reconciliation` tidak lagi ditampilkan sebagai status aktif pada detail transaksi; status efektif mengikuti penyelesaian yang tersimpan.

Setiap penyelesaian rekonsiliasi sekarang mengirim toast hasil keputusan ke operator, selain menyimpan flash message dan menampilkan riwayat resolution pada detail transaksi.

Isian peserta kategori `KONSUMSI` pada Paket SPJ dimuat langsung dari seluruh item transaksi beserta relasi pesertanya, sehingga isian tersimpan tetap muncul walaupun agregat relasi transaksi tidak terisi.

Kategori `JASA_LAINNYA` kini juga menyediakan pemuatan penerima dari paket jasa sebelumnya, penyalinan penerima, pengurutan A–Z/drag-and-drop, serta penyimpanan urutan lokal sebelum disimpan kembali ke paket.

---

## P0-08 — Generic ARKAS Importer (dihapus 2026-10-04)

**Status: SUBSYSTEM REMOVED.**

Modul Generic Importer sudah tidak ada; yang tersisa `ArkasStagingService`
+ `ArkasImportRowSynchronizer` sebagai infra pipeline canonical sync. Work item
P0-08 ditutup dan tidak lagi jadi prioritas. Rujukan: bagian "Hapus subsystem
importer mapping ARKAS 2026-10-04" di dokumen ini, `SYNCHRONIZATION.md`
§2.2/§18, dan `ARCHITECTURE_COMPLETE.md` §8.

---

## Route audit and mirror alignment 2026-09-19

Audit static route terhadap 132 route non-vendor menemukan ketidakkonsistenan sumber data pada laporan SPJ, validasi periode paket, dan saran perencanaan RKAS. Perbaikan yang sudah diterapkan:

- laporan periode dan ekspor honor memakai `arkas_mirror_kas_umum.sx_tanggal` untuk filter bulan/triwulan/semester;
- ringkasan laporan periode memakai `Transaction::sourceValue()` sehingga nilai bruto dan pajak mengikuti mirror;
- validasi tanggal periode paket mencoba rantai `kas_umum -> rapbs_periode -> ref_periode` mirror terlebih dahulu;
- route saran perencanaan RKAS memakai `arkas_mirror_rapbs`, `arkas_mirror_rapbs_periode`, dan `arkas_mirror_kas_umum` bila mirror tersedia;
- route referensi mendapat generated columns dan indeks untuk tahun, kode rekening, nama barang, dan id barang. Migrasi `2026_09_19_150449_add_generated_lookup_columns_to_reference_mirrors` sudah dijalankan pada database aktif.

Evidence timing dari `storage/logs/performance-2026-09-19.log` menunjukkan masalah performa nyata masih perlu RVR/browser follow-up: `transactions.index` 9--12 detik, `spj.index` maksimum 28,9 detik, `references.index` maksimum 13,7 detik, dan `database-manager.index` maksimum 9,4 detik. Penyebab terbesar yang teridentifikasi adalah scan JSON tanpa indeks pada referensi, agregasi mirror berulang pada halaman SPJ, dan route penganggaran-RKAS yang masih memakai tabel normalisasi legacy.

Update: `ArkasMirrorBudgetService` sekarang menjadi adapter utama untuk `/penganggaran-rkas` dan `RkasBudgetFilter`. Pagu, periode, realisasi, hierarki, filter program/subprogram/kegiatan, dan pencarian membaca `arkas_mirror_rapbs`, `arkas_mirror_rapbs_periode`, `arkas_mirror_kas_umum`, serta `arkas_mirror_ref_kode`. Snapshot mirror melakukan preload referensi dan cache per request. Snapshot memilih revisi RKAS terakhir dengan kontrak ARKASBridge: per tahun+sumber dana, urut `IS_AKTIF`, `IS_APPROVE`, `LAST_UPDATE`, lalu `CREATE_DATE`, kemudian hanya memakai `rapbs` yang menunjuk ke `ID_ANGGARAN` tersebut. Kontrak tampilan direvisi 2026-09-24 (permintaan operator): halaman penganggaran menampilkan tab per revisi — seluruh revisi yang disetujui (kronologis) ditambah tab pengajuan terakhir bila masih ada yang belum disetujui; default tab persetujuan terakhir; satu tab = satu snapshot `ID_ANGGARAN` (`ArkasMirrorBudgetService::revisions()`). Label tab "Pengesahan ke-N"/"Pengajuan ke-N" tanpa tanggal (tanggal di teks info + tooltip); tanggal pengesahan dari kolom `tanggal_pengesahan`, pengajuan dari `tanggal_pengajuan`. Revisi: BKU menaut ke rapbs revisi berjalan sehingga tab lama nol realisasi — ditambah fallback identitas pos (`ID_REF_KODE|rekening|uraian`, sisa proporsional pagu, direct didahulukan; total tetap pas, view terbaru tidak berubah; scope identitas lewat record anggaran agar uang tahun lain tidak bocor lintas tahun). Sejak 2026-10-04 fallback dilengkapi: kas beridentitas yang tidak tampil pada revisi ini ikut dibagi proporsional pagu (tidak dibuang), residual per bulan hanya ke baris yang memiliki bulan itu, dan konsumsi kuartal/semester menjumlahkan share bulan dalam scope — sehingga seluruh tab pengesahan menampilkan realisasi yang sama untuk kas yang sama. Volume tahunan fallback `VOLUME_TOTAL` → `VOLUME` untuk payload skema huruf kecil. Jalur tabel legacy masih ada sebagai fallback untuk database yang belum memiliki mirror; pada database dengan `arkas_mirror_rapbs`, jalur tersebut tidak dieksekusi. Regression runtime mirror tetap RVR karena database testing lokal mengalami `disk I/O`/`no such table: schools` setelah batch test sebelumnya.

## RKAS over-budget: stale kas mirror ganda pasca pengesahan ulang 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Tindak lanjut temuan "realisasi tidak mungkin melebihi pagu" (SDN 318,
10208183, BOS 2026: realisasi 150,75jt vs pagu 120,6jt). Hasil audit per
baris: 53 dari 80 baris Hammer revisi terbaru menunjukkan realisasi tepat
2x pagu — pola sistematis, bukan overspend organik. Verifikasi per nota:
111/111 nota balance (`sum(items) == TOTAL_NOTA`), tidak ada duplikat
`ID_KAS_UMUM`, seluruh kas bertanggal 2026 dan `IS_SPJ=1`.

Akar masalah: ARKAS menerbitkan ulang `ID_KAS_UMUM` saat pengesahan ulang
(2026-09-22). Sync 2026-07-01 menulis 90 baris/60,3jt (TW1+TW2); sync
2026-10-02/03 menulis 132 baris/90,45jt (TW1-TW3, ID baru, data vendor
diperkaya — 46/46 `NO_BUKTI` Juli muncul lagi di Oktober). `arkas_bku_rows`
punya cleanup (`whereNotIn(source_kas_id)` → 132 baris/90,45jt, benar),
tetapi `arkas_mirror_kas_umum` hanya upsert tanpa prune sehingga baris Juli
yang basi menumpuk: 60,3jt + 90,45jt = 150,75jt. Terkait pertanyaan
`soft_delete`: filter `SOFT_DELETE`/`IS_DELETED` sudah ada untuk
rapbs/anggaran/periode (7 titik di `ArkasMirrorBudgetService`); payload
`kas_umum` tidak memiliki key tersebut (0/1119 baris) sehingga tidak ada
yang terlewat; tabel `kas_umum_nota`/`*_pajak` tidak dikonsumsi render RKAS.

Perbaikan (`ArkasSynchronizationServiceV2::pruneStaleKasMirror`, dipanggil
di `saveBkuAndTransactions` dalam transaksi yang sama): hapus baris mirror
kas yang `ID_ANGGARAN`-nya ikut dalam fetch ini tetapi `ID_KAS_UMUM`-nya
tidak dikembalikan API. Scope ketat per himpunan anggaran fetch; tanpa
`ID_ANGGARAN` atau fetch kosong, prune dilewati (tidak bisa scope).
Regression: `SafeArkasSynchronizationTest::
test_resynced_kas_ids_prune_stale_mirror_rows_within_synced_anggaran`
(gagal tanpa fix, lulus dengan fix; baris tahun lain dipertahankan).

Repair data 10208183 (dengan backup
`spj-10208183-preprune-20261004.sqlite` di folder temp opencode):
178 baris basi / 463,6jt dihapus dari mirror (acuan silang:
seluruh 258 `source_kas_id` `arkas_bku_rows` FY2026-BOS ada di mirror).
Hasil render kedua tab kini identik dan wajar: ALL 120,6jt/90,45jt;
TW1-TW3 30,15jt/30,15jt; TW4 30,15jt/0.

Evidence: `SafeArkasSynchronizationTest` 6/54 + `RkasRevisionModesTest`
12/51 (gabungan 18/105), Pint passed, `git diff --check` bersih.

## Audit soft-delete seluruh tabel sinkronisasi ARKAS 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Audit memastikan baris yang dihapus di aplikasi ARKAS tidak dipakai lagi,
dengan satu pengecualian semantik penting: `soft_delete = 1` pada
`kas_umum_nota`/`*_pajak` muncul di seluruh 411+4 nota yang hidup
(status final/locked, bukan hapus) sehingga **dilarang difilter**.
`kas_umum` tidak membawa flag hapus. Bocor nyata ditemukan di jalur
legacy: 14 periode soft-deleted di `arkas_rkas_periods` ikut dihitung
`RkasBudgetController`/`RkasBudgetFilter`/validasi paket; write-path V2
dan Generic Importer juga tidak memfilter.

Perbaikan: filter soft-delete di write-path
(`saveRkas`/`saveRkasPeriods`, `upsertRkas`/`upsertPeriods`; mirror tetap
snapshot penuh) + helper kanonis
`ArkasMirrorResolver::onlyActivePeriods` dipakai 4 titik legacy.
Repair 10208183 (backup `spj-10208183-preperiodprune-20261004.sqlite`):
14 baris basi dihapus dari `arkas_rkas_periods`; render mirror tidak
berubah (ALL 120,6jt/90,45jt kedua tab). Semantik per domain dicatat di
`SYNCHRONIZATION.md` §8.1.

Evidence: `SafeArkasSynchronizationTest` 8 passed (termasuk 2 test baru:
prune stale mirror + write-path soft-delete + guard nota),
`RkasBudgetFilterTest` 6 passed (termasuk guard legacy
`test_legacy_period_counts_exclude_soft_deleted_rows`), Pint passed,
`git diff --check` bersih. Kedua guard write/legacy terbukti gagal tanpa
fix.

## Repair stale kas mirror SDN 316 Ranto Panjang (10208246) 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Gejala sama seperti 10208183: realisasi 324jt vs pagu 216jt (1,5x),
TW1-TW3 tepat 2x pagu (108jt vs 54jt) di ketiga tab revisi 2026-BOS
(`DTflFNheek6Zlw`, `OQAaOGDZr0qpNf`, `_N4R481jcUS6Kv`). Diagnosis:
mirror memuat 3 batch (`2026-07-01` 113 baris/108jt +
`2026-09-27` 74/54jt + `2026-10-02` 187/162jt); `arkas_bku_rows` yang
dibersihkan sync = tepat batch Oktober (187/162jt). Repair (backup
`spj-10208246-preprune-20261004.sqlite`): 321 baris basi dihapus
(BELANJA 187/162jt, PENERIMAAN_BOS 4/432jt, PAJAK 90/17,2jt, plus
SALDO_AWAL/PERGESERAN/BUNGA_BANK/PAJAK_BANK), acuan silang 323
`source_kas_id` bku FY2026-BOS seluruhnya ada di mirror. Hasil: ketiga
tab identik — ALL 216jt/162jt; TW1-TW3 54jt/54jt; TW4 54jt/0. Tidak ada
perubahan kode (prune otomatis `pruneStaleKasMirror` yang sudah rilis
mencegah terulang pada sync berikutnya).

## Perintah arkas:mirror-health + repair 10260756 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Menjawab audit "apakah ada yang harus diupdate di proses mirror":
inti proses dinyatakan sound (cakupan prune tepat, ID konsisten 22-char,
isolasi tahun via peta identitas). Satu-satunya update: perintah
deteksi dini `php artisan arkas:mirror-health [--npsn=] [--repair]`
(`ArkasMirrorHealthCommand` tipis → `ArkasMirrorHealthService` yang
testable). Default dry-run melapor per (sekolah, tahun, dana):
mirror vs `arkas_bku_rows`, baris basi, baris hilang-di-mirror, dan
baris tanpa-scope (dilapor, tak tersentuh repair). `--repair` backup
file DB otomatis ke `storage/app/arkas-mirror-backups/` dulu.

Dry-run perdana langsung menangkap kasus hidup: 10260756 TA 2026-BOS
5 baris basi / 276,39jt (PENERIMAAN_BOS ganda 138,195jt + 4 SALDO_AWAL
basi; BELANJA-nya sendiri sudah pas). Repair (backup
`20261004-094444-10260756.sqlite`): 5 baris dihapus, pasca-repair
SEHAT (361 = 361). 10208183/10208246 sehat; 10211735 belum punya kas
tersinkron.

Evidence: `ArkasMirrorHealthTest` 2 passed / 16 assertions, Pint passed,
`git diff --check` bersih.

## Explorer Tabel Pusat vs Sekolah 2026-10-04

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Explorer Tabel (`DatabaseTableExplorer`) sebelumnya hanya menampilkan
tabel database sekolah. Kini ada switcher **Sekolah Aktif / Database
Pusat**: `SchoolDatabaseManager::listTables/tableSchema/tableData`
menerima param koneksi (`school` default tak berubah;
`resolveExplorerConnection` menolak nama lain), daftar pusat
dimuat di `mount`, `openTable` memakai koneksi scope aktif, dan
`updatedScope` mereset halaman + menutup detail. Keamanan: 8 tabel
kredensial/sesi/antrean (`CENTRAL_DENIED_TABLES`: users, sessions,
password_reset_tokens, cache, cache_locks, jobs, job_batches,
failed_jobs) disembunyikan dari daftar dan ditolak di schema/data —
redaksi keyword kolom saja tidak cukup (mis. `sessions.payload`).
Guide dilengkapi entri tabel pusat. Verifikasi live: 20 tabel pusat,
0 bocor, `users` ditolak.

Evidence: `DatabaseTableExplorerScopeTest` 4/18 hijau (deny-list,
penolakan schema, switching scope, markup), Pint + `git diff --check`
bersih.

## Hapus subsystem importer mapping ARKAS 2026-10-04

Status: **FUNCTIONAL PASS (full suite) / BROWSER RVR**.

Audit membuktikan tidak ada modul lain yang memakai UI mapping importer:
satu-satunya pemakai adalah controller/job/service/test cluster itu
sendiri; pipeline kanonis (`ArkasSyncController`, `YearSelectionController`
→ `ArkasCanonicalSyncService` → V2) tidak tersentuh. Dihapus: route
`arkas.importer*` + `arkas.import-monitor`, 2 controller, 9 service/job
(`GenericImport`, guard, row synchronizer pemakai, reconciliation-service,
configuration, monitor, explorer, `DomainAdapter`, `FullSync` tanpa
pemakai), job import, model Profile/Run, 3 view, 2 blok nav, 8 file test,
dan entri phpunit.xml. `ArkasStagingService` dipertahankan sebagai infra
pipeline kanonis (preset kunci sumber di-inline; model Profile/Run dan
`ArkasImportRowSynchronizer` dikembalikan sebagai penyangga staging).
Migrasi/tabel staging tidak dihapus. Docs: `ARKAS_IMPORTER.md` dihapus,
README/matrix/`SYNCHRONIZATION` (§2.2, §18) disesuaikan.

Evidence: full suite Unit 82/82 + Feature 661+7 hijau setelah 2 sisa
referensi (`ArkasImportMonitorAndAuditTest`, `WebRouteSmokeTest`)
dibersihkan; `route:list` 8 route arkas valid; Pint + `git diff --check`
bersih. Kegagalan massal di tengah jalan (`malformed` sqlite Windows)
terbukti flaky env, hilang pada run ulang per-suite.

## GUI kesehatan mirror kas di Pusat Sinkronisasi ARKAS 2026-10-04

Status: **FUNCTIONAL PASS (focused, static + view:cache) / BROWSER RVR**.

`arkas:mirror-health` kini tampil sebagai section "Kesehatan Mirror Kas"
di `/pengaturan/arkas/mirror` (sekolah aktif): badge Sehat/Bermasalah,
tabel per (tahun, dana) mirror vs BKU + baris basi, dan tombol repair
(hanya bila ada yang basi) dengan `data-confirm` + backup otomatis.
Aksi `ArkasMirrorController::repairHealth` via route POST
`arkas.mirror.health-repair` (administrator + active-school +
throttle:3,1, validasi `confirm_sync`). Logic backup dipindah ke
`ArkasMirrorHealthService::backupActiveDatabase` agar dipakai command
dan controller. Mengikuti pola halaman: `x-page-header`,
`x-ui.table`/`x-ui.button`/`x-ui.alert`, token `--ui-*`, tanpa card
raksasa baru. Update 2026-10-04: tabel dipecah per kategori BKU
(TA, dana, kategori) karena total gabungan membingungkan; scope service
ikut dipecah (`tahun|dana|kategori`, kategori dinormalisasi uppercase)
sementara perilaku repair tidak berubah.
Filter tahun + kategori (GET, auto-submit pola importer) ditambahkan
pada section GUI; badge + tombol repair tetap memakai total seluruh
sekolah agar tidak menyesatkan saat filter aktif.

Evidence: `ArkasMirrorHealthUiTest` (route + middleware + primitive
kanonis), `php artisan view:cache` sukses, 26 test / 135 assertions
pada suite terdampak hijau, Pint + `git diff --check` bersih. Visual
browser aktual tetap RVR.
