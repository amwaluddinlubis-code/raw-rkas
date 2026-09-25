# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-25** (repository audit + authenticated route performance sweep, `raw-rkas`)

> Repository canonical saat ini adalah `amwaluddinlubis-code/raw-rkas` dan menggunakan satu branch aktif: `main`.
> Branch `hardening/raw-rkas-audit` telah digabung melalui PR #1; referensi branch lama hanya dipertahankan sebagai evidence historis, bukan branch kerja aktif.

---

## Repository + documentation audit (2026-09-25)

Status: **AUDIT COMPLETE / DOCS SYNCHRONIZED / CURRENT AUDIT SOURCE GATE GREEN**.

Evidence GitHub untuk source commit audit `2b854f1b9f6a7f7501a3803acbaacb2035cd85f3`, yang kemudian digabung ke `main` melalui PR #1:

```text
WORKFLOW              : SPJ Critical Verification
RUN                   : 36112716405 (#15)
ARTIFACT GUARD        : PASS
COMPOSER CHECKS       : PASS
FRONTEND BUILD        : PASS
BLADE COMPILE         : PASS
CHECKLIST PHP LINT    : PASS
SPJ CRITICAL          : PASS / 329 tests / 2,568 assertions
FULL UNIT             : PASS / 79 tests / 281 assertions
FULL FEATURE          : PASS / 563 tests / 4,005 assertions
RESULT                : SUCCESS
```

Regression yang menahan run sebelumnya berada di `resources/views/spj/checklist.blade.php`: directive inline `@php(...)` menghasilkan PHP terkompilasi yang tidak tertutup dan baru gagal pada token `else`. Assignment `$fixUrl` kini memakai blok `@php ... @endphp`; artifact diagnostic run #15 membuktikan `php -l` pada hasil compile tidak lagi menemukan syntax error, dan `WebRouteSmokeTest` kembali lewat sebagai bagian Full Feature suite. Historical green gate tetap dipertahankan sebagai baseline lama, sedangkan run #15 menjadi evidence source gate audit sebelum konsolidasi ke `main`. Audit dokumentasi juga menemukan dan memperbaiki referensi branch lama, inventaris Livewire yang sangat stale, route Filament yang sudah tidak berlaku, status checklist bukti dukung fase 1, dan kontrak laporan periodik yang tertinggal dari implementasi print/PDF.

## Repository containment hardening (2026-09-24)

Status: **SOURCE HARDENING APPLIED / ARTIFACT GUARD PASS / CURRENT SOURCE GATE GREEN**.

Audit repository menemukan dump ARKAS/BKU dan backup archive terlacak di bawah
`public/`, serta archive migration dan shortcut lokal Windows yang tidak
merupakan source aplikasi. Hardening yang diterapkan:

- dump SQL dan backup archive dikeluarkan dari current tree;
- archive migration dan shortcut lokal dikeluarkan dari current tree;
- `.gitignore` menolak pola artefak tersebut agar tidak masuk kembali;
- workflow `SPJ Critical Verification` mencakup push/PR ke `main` dan menolak
  private/backup artifacts yang terlacak.

Riwayat Git masih memuat artefak pada commit awal. History rewrite dan rotasi
token/kredensial, bila diperlukan setelah pemeriksaan pemilik data, belum
dijalankan. Verifikasi lokal yang tersedia: artifact guard PASS, theme QA PASS,
JavaScript syntax PASS, JSON metadata parse PASS, dan `git diff --check` PASS.
GitHub Actions terbaru pada PR branch audit sekarang hijau sampai Full Feature suite; lihat evidence run #15 pada audit 2026-09-25 di atas.

---

## Focused regression repair after audit (2026-09-24)

Audit pada `raw-rkas` menemukan dan memperbaiki beberapa regression lokal:

- partial rekonsiliasi kini mendefinisikan status paket terkunci sebelum dipakai
  oleh Blade;
- fallback RKAS legacy dipakai bila tabel mirror tersedia tetapi belum berisi
  baris anggaran yang usable;
- `ID_PERIODE` numerik tidak lagi dianggap sebagai nomor bulan tanpa konfirmasi
  dari referensi periode canonical;
- fixture upload template menjalankan migration `sort_order` terbaru;
- fixture quarter audit memakai NPSN terisolasi agar tidak memilih database
  managed nyata dari environment;
- focused regression mencakup template upload, safe sync reconciliation, RKAS
  scoped realization, pre-numbering, quarter audit, dan Livewire authorization.

Evidence aktual pada environment Windows/PHP 8.5.5:

```text
FOCUSED REGRESSION : PASS / 148 assertions / 0 deprecated / 9.02s
SPJ CRITICAL       : PASS / 329 tests / 2,568 assertions / 0 deprecated / 141.07s
PHP SYNTAX         : PASS / 487 files
THEME QA           : PASS
GIT DIFF CHECK     : PASS
```

Gate SPJ Critical lokal sudah PASS pada PHP 8.5.5 tanpa deprecation. Full
CI/PHP 8.3 tetap menjadi gate canonical; konstanta PDO MySQL deprecated sudah
ditangani dengan fallback kompatibel untuk runtime PHP 8.3 sampai 8.5.

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

## Checkpoint terbaru

### Latest successful canonical code gate

```text
LATEST SUCCESSFUL CODE HEAD: ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
LATEST SUCCESSFUL CODE GATE: CI #486 / run 34853857969 / SUCCESS
WORKFLOW                   : SPJ Critical Verification
COMPOSER VALIDATE          : PASS
LOCKED PLATFORM CHECK      : PASS pada PHP 8.3
COMPOSER INSTALL           : PASS dari committed lock
REPOSITORY PINT            : PASS pada gate #486
FRONTEND BUILD             : PASS
BLADE COMPILE              : PASS
SPJ CRITICAL               : PASS
FULL UNIT                  : PASS
FULL FEATURE               : PASS
```

CI #486 adalah gate sukses canonical terbaru untuk code head `ba8fa0b...`. Gate ini membuktikan dependency lock dapat di-install secara deterministik pada PHP 8.3, lalu frontend build, Blade compile, SPJ Critical, full Unit, dan full Feature semuanya selesai sukses.

Commit dokumentasi setelah `ba8fa0b...` tidak menggantikan code gate tersebut selama tidak mengubah source/runtime yang digate.

### P0 dependency-platform repair — CI #483 → #486

CI #483 pada head TALL migration `a4dd3954...` gagal sebelum test pada langkah `composer install`. Log membuktikan `composer.lock` mengunci sejumlah Symfony 8.x yang membutuhkan PHP `>=8.4`, sedangkan project mendeklarasikan PHP `^8.3` dan workflow canonical berjalan pada PHP 8.3.

Perbaikan dilakukan tanpa menaikkan minimum PHP project dan tanpa mengubah business rule:

1. `composer.json` menambahkan Composer platform floor `config.platform.php = 8.3.0` agar dependency resolution dari workstation PHP 8.4+ tetap kompatibel dengan minimum runtime project.
2. CI repair #485 me-resolve dependency Symfony pada PHP 8.3, memverifikasi `composer install`, build, Blade, SPJ Critical, Unit, dan Feature, lalu hanya setelah seluruh gate PASS menyimpan `composer.lock` hasil repair.
3. Workflow kemudian dikembalikan ke mode read-only/deterministik: tidak ada `composer update` di gate normal.
4. Gate normal menambahkan `composer validate --strict` dan `composer check-platform-reqs --lock` sebelum `composer install` agar drift platform lock terdeteksi lebih awal.
5. CI #486 pada `ba8fa0b...` membuktikan gate normal tersebut SUCCESS.

Commit terkait:

```text
7b5615c4b98222f145a3ba0e18b409abb2b1e20d
fix: constrain dependency resolution to PHP 8.3

d3c786d841d431c4d78cf2441f9a1e358115afa6
fix: keep dependency lock compatible with PHP 8.3

ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
ci: enforce deterministic PHP 8.3 dependency gate
```

### Integration repair #478 → #480 — historical baseline

Dua failure SPJ Critical #478 sudah ditutup pada commit:

```text
b61cdc621539cb6fc62dd17efc22da16a9c2a14c
test: close SPJ critical integration regressions
```

Perbaikannya hanya menyentuh regression test:

1. `SpjNumberingRollbackTest` tidak lagi memanggil method controller dengan signature internal lama; lifecycle NUMBERED/FINAL diuji melalui route HTTP canonical.
2. `SpjWorkspaceMigrationTest` diselaraskan dengan contract NUMBERED canonical: field substansi seperti `vendor_name` yang dikirim melalui manual package update tidak disimpan, category switch tetap ditolak, sedangkan `payment_description`/`item_description` tetap carve-out yang diizinkan.

CI #479 membuktikan dua failure tersebut selesai: SPJ Critical dan Unit PASS. Full Feature kemudian membuka satu stale source-contract assertion pada `TransactionNumberedItemDescriptionUiTest`, yang masih mencari implementasi inline lama meskipun controller sekarang mendelegasikan normalisasi payment description ke `SpjDescriptionService`. Assertion tersebut diselaraskan pada:

```text
887d0219142d634e6a85b6672d3bffb02b5b1584
test: align description UI contract with service delegation
```

CI #480 adalah historical green baseline sebelum Laravel 13/TALL migration. Ia telah disupersede sebagai current canonical code gate oleh CI #486.

### Laravel 13 upgrade — local verification 2026-09-14

`composer.json` dinaikkan: `php ^8.3`, `laravel/framework ^13.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`, `branch-alias 13.x-dev`. Pada tahap upgrade awal, resolve lokal PHP 8.4 sempat memilih dependency Symfony 8 dan Filament masih ada sebelum TALL cleanup berikutnya.

Verifikasi lokal yang benar-benar dijalankan pada head upgrade (PHP 8.4.0):

```text
SPJ Critical : 288 PASS / 2244 assertions
FULL UNIT    : 60 PASS / 206 assertions
FULL FEATURE : 412 PASS / 2980 assertions
npm run build: PASS (vite v6.4.3, ~3s)
view:cache   : PASS
pint --dirty : passed
git diff --check: OK
```

Selisih +1 test vs gate #480 berasal dari commit `5fa98ed` (satu head di depan gate), bukan dari upgrade framework. Tidak ada business rule, lifecycle, numbering, sync, tenant ownership, atau authorization contract yang diubah. Remote deterministic compatibility Laravel 13/TALL pada PHP 8.3 sekarang dibuktikan oleh CI #486.

### TALL migration — Filament + Sail removal 2026-09-14

Audit membuktikan seluruh surface Filament adalah dead code: `RkasTable` orphan tanpa konsumen, `RkasBudgetTable` hanya dipakai view `rkas-budget/filament.blade.php` yang juga orphan (controller aktif me-render `rkas-budget.index` yang native), dan tidak ada test yang menyentuh class/view Filament. `laravel/sail` tidak dipakai (tanpa compose file/CI, dev memakai herd-lite + `artisan serve`).

Dihapus: `filament/*` + `laravel/sail` dari `composer.json` (termasuk script `filament:upgrade`), 2 komponen + 3 view orphan, directive `@filamentStyles/@filamentScripts`, 5 CSS `@import` Filament, selector `.fi-*` basi, dan aset `public/js/filament`. Tidak ada paket baru — stack aplikasi memakai Laravel + Livewire + Alpine + Tailwind; workspace RKAS kini dimount melalui `RkasBudgetWorkspace` dengan controller sebagai adapter data read-only.

Verifikasi lokal pasca-removal (PHP 8.4.0): SPJ Critical 288 PASS / 2244 assertions, Unit 60/206, Feature 412/2980 (identik dengan baseline L13), `npm run build` PASS (CSS 932KB → 425KB), `view:cache` PASS, `pint --dirty` passed. Remote deterministic gate pasca-removal sekarang PASS pada CI #486.

---

## Status release saat ini

```text
FUNCTIONAL BASELINE : PASS pada ba8fa0b... / CI #486
CURRENT CODE GATE   : GREEN / CI #486
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE / CODE GATE PASS
FINAL RELEASE       : NOT YET
```

P0 code/dependency integration gate sudah hijau pada current canonical code head. Aplikasi belum boleh disebut final release-ready karena generated-document real-data QA, browser/operator QA, Office/PDF visual fidelity, dan installed-runtime verification masih terpisah dari deterministic CI.

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

`LivewireMutationAuthorizationTest` berada di SPJ Critical dan tetap tercakup oleh current green SPJ Critical gate #486. Rule arsitektur tetap: mutation Livewire sensitif harus authorize pada request action/policy/persistent mechanism yang benar-benar berlaku, bukan hanya mengandalkan route GET halaman awal.

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

## GUI standardization

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL REGRESSION  : COVERED oleh green gate #486 untuk current canonical code head
BROWSER DESKTOP/LAPTOP   : RVR ACTIVE
MOBILE/TABLET RUNTIME    : RVR / NON-BLOCKER untuk target desktop-laptop
```

Shared `x-ui` primitives, semantic theme tokens, icon registry, density/typography, responsive source guards, pagination theme, early theme init, dan top progress integration tersedia. Source-level PASS tidak sama dengan browser visual PASS.

SPA tab SPJ memakai `Livewire.navigate` dengan full-reload fallback; modal template preview memakai delegated listener agar bertahan setelah body swap. Repeated-navigation/modal runtime tetap perlu browser QA.

Pagination pada halaman `/penganggaran-rkas/saran` sudah PASS: Modul 1 dan Modul 2 memakai kontrol angka segmented yang terpisah, dengan konfirmasi ujicoba operator.

---

## P0-01 — Six-category SPJ end-to-end

```text
FUNCTIONAL SIX-CATEGORY : PASS pada current green gate
REAL-DATA BASELINE      : VERIFIED untuk scope yang tersedia
GENERATED-DOCUMENT DATA : ACTIVE / RVR
INSTALLED-RUNTIME       : DEFERRED
```

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Baseline real-data 2026 yang sudah terdokumentasi:

```text
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
```

Distribusi Paket 2026: BARANG 41, HONOR_PEGAWAI 12, JASA_LAINNYA 9, KONSUMSI 2, PEMELIHARAAN 2, SPPD 0. SPPD nyata tersedia pada data 2025; jangan fabrikasi SPPD 2026 untuk coverage.

---

## P0-02 — Document generator / template

**Status: FUNCTIONAL CODE GATE PASS / REAL-DATA OUTPUT QA ACTIVE / OFFICIAL-TEMPLATE VISUAL RVR.**

Current green gate mencakup source/regression untuk:

- individual template XLSX true single-sheet;
- canonical XLSX HTML preview;
- placeholder inspector;
- master template recomposition;
- preview/download tidak menerbitkan nomor;
- source/master tersimpan tidak dimutasi saat individual download;
- master parsial ditolak;
- DOCX individual;
- validator/template load path terbaru;
- generated document validation;
- `SpjSpreadsheetPdfWriter`/report-related test coverage yang berada dalam Unit/Feature suite.

Optimasi validator XLSX membaca daftar sheet lebih dahulu dan memuat hanya sheet canonical `readDataOnly` untuk individual validation. Angka developer sekitar 33 detik → 0,7 detik **bukan canonical browser/performance evidence** dan tetap memerlukan runtime profiling bila ingin dipromosikan sebagai performance claim.

Sisa utama: buka output/template nyata di Microsoft Excel/LibreOffice/PDF viewer dan verifikasi repair prompt, drawing, formula/reference, defined names, print area, page break, header/footer, serta hasil cetak.

---

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL PASS / REGISTRY CANONICAL.**

Source of truth:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Gate #486 mempertahankan regression suite yang mencakup first numbering, cancel/reserved sequence, tail rollback, quarter dependency, fund-source scope, NUMBERED description carve-out, FINAL lock, dan registry-based consumers.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

```text
HTTP/ROUTE AUTH BASELINE       : PASS
LIVEWIRE MUTATION BOUNDARY     : HARDENED
NEGATIVE ROLE REGRESSION       : PASS di current SPJ Critical gate
OVERALL CURRENT CODE GATE      : GREEN / CI #486
RUNTIME/BROWSER VERIFICATION   : RVR
```

Mutation user/role, school provisioning, database maintenance/reset/activation, dan document-storage setting harus tetap mengulang authorization pada boundary Livewire yang dieksekusi.

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

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Livewire Phase 2 hanya menambah role enforcement dan tidak memindahkan scope logic ke Blade/Alpine.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / LIVEWIRE RESET ROLE HARDENING COMPLETE / INSTALLED-RUNTIME DEFERRED.**

`DatabaseResetForm` memerlukan ADMIN, active-school match, dan exact confirmation sebelum reset service dipanggil.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / OPERATOR DATA TEST ACTIVE.**

Importer stateful tidak menjadi target migrasi Livewire opportunistic.

---

## Referensi pola bukti dukung operasional (poster 10 pola) — 2026-09-23

Status: **ACTIVE REFERENCE / RVR** — belum implementasi, tanpa perubahan source.

Poster “Lampiran Bukti Dukung SPJ” ditranskripsikan ke `docs/SPJ_SUPPORTING_DOCUMENT_PATTERNS.md`
(10 pola: KKG, Rapat K3S, Honor GTT/PTT, Perjalanan Dinas, Belanja SiPlah,
Makan Minum/Rapat, Honor Narasumber, Pengadaan/Penggandaan, Ekstrakurikuler,
Jasa Tukang dll + catatan materai >Rp5 Jt dan kecocokan Nomor/Nilai BKU-Kwitansi).
Pemetaan ke 6 kategori canonical + channel SiPlah ada di dokumen tersebut.
Aturan ambang (materai, PPN >Rp2 Jt, PPh23 2% + pajak restoran 10%) belum menjadi
validasi otomatis. Implementasi checklist `SOURCE_EXTERNAL` masih usulan.

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
5. Setiap source/runtime change setelah gate `ba8fa0b...` membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary pada action request.
7. GUI source PASS tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan `operator flow -> temukan bug nyata -> perbaiki -> focused regression bila perlu`.

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

## Route audit and mirror alignment 2026-09-19

Audit static route terhadap 132 route non-vendor menemukan ketidakkonsistenan sumber data pada laporan SPJ, validasi periode paket, dan saran perencanaan RKAS. Perbaikan yang sudah diterapkan:

- laporan periode dan ekspor honor memakai `arkas_mirror_kas_umum.sx_tanggal` untuk filter bulan/triwulan/semester;
- ringkasan laporan periode memakai `Transaction::sourceValue()` sehingga nilai bruto dan pajak mengikuti mirror;
- validasi tanggal periode paket mencoba rantai `kas_umum -> rapbs_periode -> ref_periode` mirror terlebih dahulu;
- route saran perencanaan RKAS memakai `arkas_mirror_rapbs`, `arkas_mirror_rapbs_periode`, dan `arkas_mirror_kas_umum` bila mirror tersedia;
- route referensi mendapat generated columns dan indeks untuk tahun, kode rekening, nama barang, dan id barang. Migrasi `2026_09_19_150449_add_generated_lookup_columns_to_reference_mirrors` sudah dijalankan pada database aktif.

Evidence timing dari `storage/logs/performance-2026-09-19.log` menunjukkan masalah performa nyata masih perlu RVR/browser follow-up: `transactions.index` 9--12 detik, `spj.index` maksimum 28,9 detik, `references.index` maksimum 13,7 detik, dan `database-manager.index` maksimum 9,4 detik. Penyebab terbesar yang teridentifikasi adalah scan JSON tanpa indeks pada referensi, agregasi mirror berulang pada halaman SPJ, dan route penganggaran-RKAS yang masih memakai tabel normalisasi legacy.

Update: `ArkasMirrorBudgetService` sekarang menjadi adapter utama untuk `/penganggaran-rkas` dan `RkasBudgetFilter`. Pagu, periode, realisasi, hierarki, filter program/subprogram/kegiatan, dan pencarian membaca `arkas_mirror_rapbs`, `arkas_mirror_rapbs_periode`, `arkas_mirror_kas_umum`, serta `arkas_mirror_ref_kode`. Snapshot mirror melakukan preload referensi dan cache per request. Snapshot memilih revisi RKAS terakhir dengan kontrak ARKASBridge: per tahun+sumber dana, urut `IS_AKTIF`, `IS_APPROVE`, `LAST_UPDATE`, lalu `CREATE_DATE`, kemudian hanya memakai `rapbs` yang menunjuk ke `ID_ANGGARAN` tersebut. Kontrak tampilan direvisi 2026-09-24 (permintaan operator): halaman penganggaran menampilkan tab per revisi — seluruh revisi yang disetujui (kronologis) ditambah tab pengajuan terakhir bila masih ada yang belum disetujui; default tab persetujuan terakhir; satu tab = satu snapshot `ID_ANGGARAN` (`ArkasMirrorBudgetService::revisions()`). Label tab "Pengesahan ke-N"/"Pengajuan ke-N" tanpa tanggal (tanggal di teks info + tooltip); tanggal pengesahan dari kolom `tanggal_pengesahan`, pengajuan dari `tanggal_pengajuan`. Revisi: BKU menaut ke rapbs revisi berjalan sehingga tab lama nol realisasi — ditambah fallback identitas pos (`ID_REF_KODE|rekening|uraian`, sisa proporsional pagu, direct didahulukan; total tetap pas, view terbaru tidak berubah; scope identitas lewat record anggaran agar uang tahun lain tidak bocor lintas tahun). Volume tahunan fallback `VOLUME_TOTAL` → `VOLUME` untuk payload skema huruf kecil. Jalur tabel legacy masih ada sebagai fallback untuk database yang belum memiliki mirror; pada database dengan `arkas_mirror_rapbs`, jalur tersebut tidak dieksekusi. Regression runtime mirror tetap RVR karena database testing lokal mengalami `disk I/O`/`no such table: schools` setelah batch test sebelumnya.

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

Hierarki RKAS memprioritaskan relasi `arkas_mirror_rapbs.ID_REF_KODE` ke `arkas_mirror_ref_kode.ID_REF_KODE`; kode dan nama kegiatan diambil dari referensi tersebut, sedangkan `KODE_PROGRAM`, `NAMA_PROGRAM`, `KODE_SUB_PROGRAM`, dan `NAMA_SUB_PROGRAM` diambil dari payload RAPBS lalu memakai referensi parent sebagai fallback. `ID_LEVEL_KODE` ikut dibawa sebagai metadata level referensi.

Filter periode RKAS mirror sekarang menormalisasi `ID_PERIODE` melalui `arkas_mirror_ref_periode` terlebih dahulu. Nama/metadata bulan, triwulan, dan semester dari referensi menjadi dasar agregasi pagu, volume, realisasi, serta jumlah opsi filter; fallback numerik hanya digunakan bila referensi periode belum tersedia.

## Navbar active context selector 2026-09-20

Navbar global sekarang selalu menampilkan selector tahun anggaran dan sumber dana tepat setelah nama sekolah. Selector tetap terlihat ketika daftar tahun kosong dengan keadaan disabled dan pesan yang jelas. Sumber daftar memakai `fiscal_years` canonical beserta relasi `fund_sources`; provider tidak lagi memeriksa tabel RKAS legacy atau mirror untuk menentukan apakah selector ditampilkan.

## Canonical material table surfaces 2026-09-20

Seluruh tabel authenticated mengikuti aturan tabel canonical secara global: wrapper dan permukaan tabel tidak lagi memakai border radius sehingga tampil material/square, tabel tetap memakai token tema, overflow horizontal, hover, dan kontrak pagination yang sudah ditentukan. Komponen `<x-ui.table>` tetap menjadi primitive utama dengan `data-pagination` eksplisit; tabel Livewire/server dan tabel lokal yang telah memiliki pager tidak disuntik pager kedua.

Menu tindakan pada tabel laporan SPJ memiliki positioning khusus: tiga baris terakhir membuka dropdown ke atas agar tidak terpotong viewport/tabel, sedangkan baris lain membuka dropdown dari baris yang dipilih ke bawah.

## Canonical tab visual 2026-09-20

Tab global, tab SPJ, tab paket SPJ, tab referensi, dan tab Database Control Center kini memakai lebar penuh yang seimbang, ikon dalam lingkaran, tinggi tetap, serta hover glass bertoken tema. Tab tidak lagi membuka overflow vertikal; daftar tab memakai clipping horizontal yang terkendali dan label tetap terpotong secara aman pada ruang sempit.
