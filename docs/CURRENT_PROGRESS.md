# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-10-05** (browser QA focused + 2 fix mobile; SPJ report bulk preview, `raw-rkas`)

> Repository canonical saat ini adalah `amwaluddinlubis-code/raw-rkas` dan menggunakan satu branch aktif: `main`.
> Branch `hardening/raw-rkas-audit` telah digabung melalui PR #1; referensi branch lama hanya dipertahankan sebagai evidence historis, bukan branch kerja aktif.

## Browser QA focused + 2 perbaikan mobile (2026-10-05)

Status: **FUNCTIONAL PASS (focused) / RVR tersisa tercatat**.

Sesi Playwright MCP pertama (Chrome 154.0.8037.97, ADMIN,
10208183 / 2026 / BOS Reguler): ±30 rute pada 1366×768 tanpa console
error/warning (daftar rute + viewport di `GUI_RUNTIME_QA.md` §Status).
Verifikasi visual BKU TW1 via DOM + screenshot: 110 baris, 0 uraian
kosong, bunga bank/pajak bunga bernominal, tepat 1 blok tanda tangan.
Modal Pratinjau Paket muat dalam viewport + iframe `pratinjau-pdf`;
pager transaksi dan filter triwulan berfungsi.

Dua temuan mobile diperbaiki + terverifikasi (sebelum/sesudah via
pengukuran DOM):

- kartu daftar transaksi 617px pada viewport 360px (teks `truncate`
  nowrap sebagai min-content grid item) → `min-w-0` pada `article`
  (`livewire/transactions-table.blade.php`); kartu 294px, overflow hilang;
- baris Dokumen & Template paket 406px (grup 3 tombol `shrink-0`) →
  `flex-wrap` (`spj/partials/package/documents.blade.php`); overflow hilang.

Regression: `GuiAudit09To13SourceReadinessTest` 14 passed (1 test
kontrak baru), Pint passed, `view:cache` sukses, `git diff --check`
bersih. RVR tersisa (eksplisit, bukan PASS): modal Pratinjau Massal +
batas 20 paket, eksekusi penomoran, eksekusi sinkronisasi/reset,
output biner PDF/Excel, tema gelap, dan cetak folio pada layar kecil.
>
> **Cara membaca identifier gate.** Dokumentasi ini memakai dua scheme yang
> berbeda dan keduanya pernah ditulis "CI #", sehingga mudah tertukar:
> `run #NN` = nomor run workflow "SPJ Critical Verification" (mis. run #46,
> run ID `36515608353`), sedangkan `PR #NNN` = nomor pull request (mis.
> #478–#480, #483–#486). Gate kanonik saat ini adalah **run #46** pada
> `e4ba5cf`; PR #486 adalah gate dependency-platform PHP 8.3 yang lebih
> lama dan bersifat historis.
>
> **Catatan verifikasi hash (2026-10-05).** Sejumlah commit yang dikutip pada
> blok evidence historis di bawah tidak ada pada repository mirror ini
> (`git cat-file -t` → unknown revision): `ba8fa0b`, `a4dd3954`, `7b5615c4`,
> `d3c786d8`, `5fa98ed`, `3c7be408`, `701c7364`, `b61cdc62`, `887d0219`.
> Hash tersebut **dipertahankan sebagai jejak historis** dan tidak
> diverifikasi ulang, tetapi tidak boleh diutip sebagai evidence aktif tanpa
> konfirmasi terhadap repository canonical. Hash yang terkonfirmasi ada antara
> lain `e4ba5cf`, `1705f5d`, `14cf825`, `a22f7d5`, `b07d764`, `9540937`,
> `058d771`, `ea8c507`, `ac75b5b`, `ffa049a4`, `24a19333`.

## Cleanup dokumentasi usang + view orphan importer (2026-10-05)

Status: **FUNCTIONAL PASS (focused, docs + view cleanup) / BROWSER RVR**.

Audit dokumentasi terhadap source menemukan tiga kelas staleness yang ditutup
dalam satu batch. Tidak ada business rule, lifecycle, numbering, tenant
boundary, sync contract, atau kontrak GUI yang diubah.

### 1. Generic ARKAS Importer yang sudah dihapus masih didokumentasikan aktif

`route:list` tidak lagi memuat `arkas.importer*` maupun
`arkas.import-monitor` (subsystem dihapus 2026-10-04 pada bagian "Hapus
subsystem importer mapping ARKAS" di dokumen ini). Namun 12 file dokumentasi
masih menganggapnya hidup:

- `ARCHITECTURE_COMPLETE.md` §8 masih memuat pipeline
  `ArkasImporterController → ... → ArkasDomainAdapter` dan mengklaim status
  **READY FOR OPERATOR DATA TEST**; §2 masih menyebut "preset mapping importer
  yang berlaku lintas sekolah"; §21 masih menaruh importer pada daftar area RVR;
  §22 menautkan `docs/ARKAS_IMPORTER.md` yang sudah dihapus;
- `DEVELOPMENT_ROADMAP.md` dan `CURRENT_PROGRESS.md` P0-08 masih terbuka;
- `P0_VERIFICATION_KIT.md` §3 masih menyebut "ARKAS importer" sebagai coverage
  SPJ Critical yang aktif;
- `GUI_RUNTIME_QA.md` checklist dan `LIVEWIRE_MIGRATION_PLAN.md` §8 masih
  menominasikan importer sebagai target migrasi;
- `USER_SCENARIOS.md` §3 masih mendokumentasikan "Mode Sederhana"/"Mode
  Lanjutan" + mapping manual yang tidak ada lagi;
- `README.md`, `ARCHITECTURE.md` (root), dan `API.md` (root) menautkan
  `ARKAS_IMPORTER.md` / route `arkas.importer*` yang sudah tidak ada.

Semua ditulis ulang sebagai penanda penghapusan dengan rujukan ke `SYNCHRONIZATION.md`
§2.2/§18. Yang **tetap hidup** dan kini dijelaskan eksplisit:
`ArkasStagingService` + `ArkasImportRowSynchronizer` + `ArkasImportProfile`/`Run`
dipakai `ArkasCanonicalSyncService:17` sebagai infra pipeline canonical sync,
bukan sebagai workspace operator. Daftar dokumen acuan `ARCHITECTURE_COMPLETE.md`
§22 juga dilengkapi dengan dokumen aktif yang sebelumnya hilang
(`SYNCHRONIZATION.md`, `UI_ICON_MIGRATION.md`, `LIVEWIRE_MIGRATION_PLAN.md`,
`TEMPLATE_MASTER_WORKFLOW.md`, `PERIODIC_REPORT_MODULE.md`,
`SPJ_SUPPORTING_DOCUMENT_PATTERNS.md`).

`API.md` kehilangan 4 route `arkas.importer*`, diganti baris `arkas.mirror.*`
(`mirror`, `mirror/status`, `mirror/sync-refs`, `mirror/sync-school`,
`mirror/health-repair`) yang terverifikasi pada `route:list`.

### 2. Identifier gate ambigu (`run #NN` vs `PR #NNN`)

Dokumentasi mencampur dua scheme dan keduanya ditulis "CI #", sehingga `#46`
(run workflow) dan `#486` (nomor PR) terbaca sebagai objek yang sama though
keduanya hijau. Pemisahan scheme kini dijelaskan di header
`CURRENT_PROGRESS.md`, `DEVELOPMENT_ROADMAP.md` §P0-00,
`P0_VERIFICATION_KIT.md` §1, dan `docs/README.md`. Gate kanonik ditegaskan
sebagai **run #46** pada `e4ba5cf`.

Satu aturan actionable ikut dikoreksi: `CURRENT_PROGRESS.md` §"Aturan evidence"
menyebut `ba8fa0b...` sebagai gate acuan, padahal itu head PR #486 yang
historis; diganti `e4ba5cf...` (run #46).

Branch mati `hardening/raw-rkas-audit` (sudah digabung via PR #1) dihapus dari
header `LIVEWIRE_MIGRATION_PLAN.md`, `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`,
`TEMPLATE_MASTER_WORKFLOW.md`, dan `UI_ICON_MIGRATION.md`.

### 3. Hash commit yang tidak ada di mirror ini

`git cat-file -t` membuktikan 9 hash yang dikutip pada blok evidence historis
tidak ada pada repository mirror `raw-rkas`: `ba8fa0b`, `a4dd3954`, `7b5615c4`,
`d3c786d8`, `5fa98ed`, `3c7be408`, `701c7364`, `b61cdc62`, `887d0219`. Sesuai
keputusan pengguna, hash tersebut **dipertahankan sebagai jejak historis**
(tidak dihapus) dan diberi catatan eksplisit di `CURRENT_PROGRESS.md` serta
`P0_VERIFICATION_KIT.md` bahwa blok itu belum terverifikasi terhadap mirror dan
tidak boleh dinaikkan menjadi evidence aktif tanpa konfirmasi ke repository
canonical. Hash yang terkonfirmasi ada antara lain `e4ba5cf`, `1705f5d`,
`14cf825`, `a22f7d5`, `b07d764`, `9540937`, `058d771`, `ea8c507`, `ac75b5b`,
`ffa049a4`, `24a19333`.

### 4. Klaim slot Laporan Periode salah hitung

`docs/README.md` mengklaim "empat kelompok/**39 slot** laporan periode".
Pengukuran terhadap `app/Services/SpjPeriodicReportRegistry::packages()` pada
HEAD ini — dan pada `e4ba5cf` juga, sehingga klaim itu tidak pernah benar —
menghasilkan **46 slot** (11 bulanan + 12 triwulan + 13 semester + 10 tahunan)
dari **22 report key** berbeda. Angka pada indeks diperbaiki menjadi "46 slot
(22 report key)". `SPJ_PERIODIC_REPORTING.md`, yang sebelumnya tidak terindeks
di `docs/README.md`, kini ditambahkan sebagai kontrak teknis modul tersebut.

### 5. Audit isi dokumentasi lama terhadap source — 16 defect

Sepuluh dokumen yang bertanggal 2026-09-11 s.d. 2026-09-29 diaudit claim per
claim terhadap source. Enam benar-benar bersih; empat memuat klaim yang
bertentangan dengan kode.

#### 5.1 `SPJ_DESIGN_DECISIONS.md` / `NUMBERING_CORRECTION_AND_ROLLBACK.md`

- **Kolom yang sudah di-drop masih didokumentasikan sebagai kolom.**
  `transactions.gross_amount`, `tax_total`, `net_amount` serta
  `transaction_items.{description,quantity,unit,unit_price,amount}` di-drop oleh
  migrasi school `2026_09_19_000004_drop_duplicate_source_columns`. Nilai
  canonical kini dibaca dari mirror lewat `Transaction::sourceValue()` /
  `TransactionItem::sourceValue()`. §5 dan §4.1 diberi catatan bentuk
  penyimpanan; blok "Ownership item" disederhanakan.
- **Roster KONSUMSI/SPPD.** Auto-fill roster hanya terpasang untuk `KONSUMSI`
  (`SpjWorkspaceUseCase` memuatnya pada kondisi `spj_category === 'KONSUMSI'`,
  dikonsumsi hanya `konsumsi.blade.php`). SPPD memakai `spj_travels` tanpa
  roster. Klaim "KONSUMSI/SPPD" yang sama ada di 4 dokumen lain
  (`ARCHITECTURE_COMPLETE.md`, `SYNCHRONIZATION.md`, `USER_SCENARIOS.md`,
  `docs/README.md`) — semuanya dikoreksi ke scope `KONSUMSI`.
- **Kapasitas `operational_audit_logs`.** Tabel itu **tidak punya**
  `fund_source_id` maupun kolom sekolah; `OperationalAuditService::record()`
  hanya menulis `fiscal_year_id`, `entity_type`, `entity_id`, `action`,
  `description`, `user_id`, timestamp. Klaim "fund source" dan "sequence
  sebelum/sesudah" pada §17.6 dan §9 dikoreksi menjadi kapasitas aktual.
- **Rollback belum registry-driven.** `SpjNumberingRollbackUseCase` memakai
  daftar kolom hardcoded, bukan `number_target` dari registry; registry-driven
  cleanup baru ada pada jalur cancel individual. Dicatat tanpa mengubah aturan.
- **Nilai status legacy.** `DICETAK` masih di-whitelist
  `SpjNumberingOrderService`, `BERNOMOR` masih dibaca sebagai alias NUMBERED di
  `TransactionsTable`, dan `spj_packages.status` adalah `string(30)` tanpa enum
  constraint. Ditambahkan catatan pada §16.
- **Letak penyimpanan field Paket.** Field operator (`spj_category`,
  `payment_*`, `receipt_recipient_name`, metadata SiPLah) berada di
  `transactions`, bukan di `spj_packages`; §4.3 diberi catatan agar tidak dibaca
  sebagai klaim nama kolom.

Registry numbering sendiri **terverifikasi akurat** — 7 kode bernomor, seluruh
`event_date_rule`, `number_target`, `applicable_categories`, dan `scope_rule`
cocok dengan source, termasuk aturan `TAHAP:n`.

#### 5.2 `CSS_USAGE_GUIDE.md`

- **Cascade tail tidak lengkap.** `theme-system.css` mengimpor 39 file;
  tabel §13 hanya mencantumkan 22. Tiga file yang tidak tercatat adalah
  `apple-theme-profiles.css`, `public-context-standardization.css`, dan
  `header-button-unification.css` — yang terakhir justru **lapisan paling
  akhir** dan diandalkan `GUI_STANDARDIZATION.md` §93, sehingga kedua dokumen
  sebelumnya saling bertentangan. Tabel §13 ditulis ulang menjadi daftar
  lengkap bergrp (entry/base, theme system, feature area, lapisan akhir).
- **`--theme-action-hover-bg`/`-fg` bukan token canonical.** Keduanya tidak
  punya definisi global di `:root` maupun `theme-accessibility.css`; hanya
  `arkas-theme-profiles.css` dan `apple-theme-profiles.css` yang
  mendeklarasikannya. Semua consumer menulis fallback. Dicatat eksplisit.

#### 5.3 `GUI_STANDARDIZATION.md` §20.1 — pilot density salah

Dokumen mencantumkan `control height desktop: 40px`, `touch 44px`
workspace-wide, dan `table row vertical: 10px`. Ketiganya tidak sesuai source:

```text
40px  : tidak ada di mana pun untuk --profile-control-height; nilai global
        2.5rem di human-ui.css hanya untuk main button/.ui-btn, bukan kontrol
        form. spj-workspace-standardization.css sogar memuat komentar bahwa
        file itu SENGAJA tidak mendeklarasikan token density.
44px  : hanya di-scope ke [data-package-tab] pada max-width 1023px
10px  : bukan nilai pilot, melainkan nilai profil `compact`;
        default --profile-table-row-y adalah .875rem
```

Tabel diganti memakai token density, dengan catatan bahwa regression lama
justru mengunci nilai 40px yang kemudian dihapus sebagai bug. Regression
penggantinya sudah ada: `test_spj_workspace_does_not_pin_density_owned_tokens`.

#### 5.4 `SPJ_SUPPORTING_DOCUMENT_PATTERNS.md` — ambang SUDAH diimplementasi

Dokumen menyatakan ambang materai/PPN/PPh23 "belum menjadi validasi otomatis".
Namun: ketiganya sudah dideteksi di `SpjOperatorHintService` (materai
`gross > Rp5 Jt`; PPN `gross > Rp2 Jt` tanpa PPN pada BARANG/JASA_LAINNYA/
PEMELIHARAAN; pajak konsumsi KONSUMSI tanpa PPh 23), dirender di
`spj.checklist`, dan tercakup `SpjOperatorHelperTest`. Yang belum ada adalah
menjadikan ambang itu **gate pemblokir**. Dokumentasi (dan entri yang sama di
dokumen ini) dikoreksi: "deteksi otomatis non-blocking, gate masih usulan".

#### 5.5 Route dan parameter yang salah

- `GUI_RUNTIME_QA.md` menulis `/dashboard` yang **tidak ada**; dashboard adalah
  `GET /`. Ditambahkan juga route yang belum tercakup: `/pajak`,
  `/rekonsiliasi`, `/referensi`, `/laporan-periode`, `/spj/rekap-triwulan`,
  `/penganggaran-rkas/{saran,simulasi,perbandingan-revisi}`, dan
  `/pegawai/tinjau-identitas`.
- Status "GUI-AUDIT-12/13 source readiness: PASS" kini disebut sebagai PASS
  **pada gate run #46 / `e4ba5cf`** — HEAD sudah beberapa commit setelah gate itu.
- `MOBILE_VISUAL_QA_TODO.md`: parameter route `/transaksi/{id}` → `{transactionId}`;
  tombol `+ Peserta manual` → label aktual `Ambil Pegawai` + `＋ Peserta`.
- `DOCUMENTATION_MAINTENANCE.md` §3: baris GUI tidak memuat `GUI_RUNTIME_QA.md`
  padahal file itu checklist penutupan yang diwajibkan `GUI_STANDARDIZATION.md`;
  ditambahkan, plus baris route untuk `API.md`, dan daftar dokumen aktif lain
  yang selama ini tidak ada di matriks.

#### 5.6 Dokumen yang terverifikasi bersih

`SIPLAH_MVP_PLAN.md` (seluruh klaim class/column/route/policy cocok dengan
source), `UI_ICON_MIGRATION.md` (18 nama icon, urutan import, dedup contract),
dan `P0_01_SOURCE_AUDIT.md` (signature `spj:audit-quarter`, jaminan read-only
`PRAGMA query_only`, kedua regression test). Klaim `SPJ_DESIGN_DECISIONS.md` §10
tentang identity pegawai juga seluruhnya cocok dengan `EmployeeIdentityService`.

### 6. Teks korup dan view orphan

Tujuh baris di dokumen ini mengandung karakter CJK yang tersisip di tengah
kalimat Indonesia sehingga menjadi tidak terbaca. Contohnya judul butir
"Tiga... clean up" pada bagian cleanup RKAS, frasa "efforts sebelumnya ...
select" pada perbaikan lebar blok unduh, "dan ... oleh test RkasReportTest"
pada payload freshness, "hanya ... token density" pada catatan koreksi
analisis, "rencana GUI ..." pada guardrail density, "Ketigabelas ..." pada
paragraf density, serta satu kata async berbahasa asing pada kalimat
"Script ini juga menangkap regresi". Karakter tersebut dihapus dan
kalimatnya dipulihkan ke bahasa Indonesia sesuai konteks sekitarnya, tanpa
mengubah makna teknis yang dilaporkan.

`resources/views/arkas/partials/importer-flash-messages.blade.php` dihapus:
sisa partial Generic Importer yang tidak lagi dirender view mana pun
(`mirror.blade.php` dan `settings.blade.php` tidak punya `@include`
sama sekali) dan masih memakai palet hard-coded `rose-300/50` +
`emerald-300/50` yang bertentangan dengan `CSS_USAGE_GUIDE.md` §15. Folder
`resources/views/arkas/partials/` ikut terhapus karena menjadi kosong.

### 7. Regenerasi `API.md`

Status: **FUNCTIONAL PASS (regenerasi dari route:list aktual)**.

`API.md` sebelumnya bertanggal 2026-09-25, tidak lengkap, dan memuat 4 route
`arkas.importer*` yang sudah dihapus. Sekarang dokumen ini **dihasilkan langsung**
dari `php artisan route:list --except-vendor --json`:

```text
route terdaftar        : 154
route aplikasi         : 150   (4 route boost/up/storage Closure dikecualikan)
baris tabel            : 150   (verifikasi balik URI per route: 0 hilang)
route infra eksplisit  : 4     (/up, /_boost/browser-logs, /storage/{path} x2)
```

Struktur baru: tabel alias middleware dengan class asalnya, catatan grup `web`
yang dihilangkan, remark route tanpa middleware (`/setup`,
`GET /asisten/status/{token}`), lalu 150 route dikelompokkan per area operator
(auth/publik, dashboard & laporan, transaksi, SPJ, pajak & rekonsiliasi,
anggaran RKAS, siswa & pegawai, sinkronisasi data, pengaturan, dashboard root).

Tabel middleware dibaca dari middleware aktual tiap route, bukan dari
asumsi per prefix, sehingga `administrator` yang menempel pada
`/pengaturan/template-dokumen` dan `dapodik` kini tercatat benar. Klaim "jumlah
route" sengaja tidak dijadikan kontrak di dalam dokumen.

### 8. Documentation Impact Review — perubahan Laporan Periode yang belum tercatat

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

Perubahan berikut sudah ada di working tree tetapi belum punya entri status:

```text
app/Services/SpjPeriodicReportPrintService.php   +273 / -19
tests/Feature/BkuOfficialLedgerTest.php           +111
docs/PERIODIC_REPORT_MODULE.md                    +22
```

Isi perubahan: dedup baris pajak bayangan `MIG-T-*` hasil
`spj:backfill-mirror-from-local` terhadap baris PAJAK kanonis (kunci sama
dengan dedup `ArkasMirrorResolver::transactionSource`), shortfall murni ditulis
ulang sebagai "Terima … (data lokal)"; normalisasi `REK_BKU` varian `Sisa`
lewat `bkuBaseRek()`; `Bunga Bank` dihitung sebagai penerimaan sisi bank dan
`Pajak Bunga` sebagai pengeluaran sisi bank (sebelumnya `0/0` walau mirror
membawa nominal); fallback uraian `URAIAN` → `URAIAN_PAJAK` → `REK_BKU`; dan
parent grup memakai payment/uraian non-kosong pertama.

Verifikasi yang benar-benar dijalankan pada batch ini:

```text
BkuOfficialLedgerTest         : 12 passed / 124 assertions
php artisan test --filter=PeriodicReport : 13 passed / 148 assertions
```

Impact review terhadap matriks `DOCUMENTATION_MAINTENANCE.md` §3:

- Status/evidence → entri ini (selesai).
- Template/generator/placeholder → `PERIODIC_REPORT_MODULE.md` sudah punya
  bagian "Dedup bayangan backfill pajak + fallback uraian BKU" dari perubahan
  tersebut; ditambah tanggal header dan blok "Status verifikasi" yang menyebut
  hasil test di atas (selesai).
- Kontrak teknis modul → `SPJ_PERIODIC_REPORTING.md` dilengkapi route Excel
  yang sebelumnya tidak tercatat dan rujukan test `BkuOfficialLedgerTest`
  (selesai).
- Route → `API.md` sudah memuat `/laporan-periode/{scope}/{report}/excel`
  (selesai lewat regenerasi).
- Tidak menyentuh business rule, tenant boundary, numbering, atau lifecycle;
  nilai selalu dibaca dari mirror dengan `sourceValue()` seperti lapisan lain.
  Tampilan cetak/PDF tetap **RVR** — belum dibuka di browser/PDF viewer.

### Evidence keseluruhan

Dokumentasi + view orphan + regenerasi `API.md`:

```text
git diff --check                 : BERSIH
php artisan view:clear + cache   : sukses
route:list                       : tidak ada arkas.importer*/import-monitor
grep karakter CJK (docs/ + root) : bersih
grep ARKAS_IMPORTER.md           : 0 tautan aktif tersisa
verifikasi balik API.md per URI  : 150/150 route tercakup
```

Perubahan source yang sudah ada di working tree (Lapangan Periode + tab datar +
`users/index.blade.php`) diverifikasi ulang pada batch commit:

```text
SPJ Critical suite      : 328 passed / 2.454 assertions
GuiAudit09To13 readiness: 13 passed / 114 assertions
SpjReportLayoutTest     : 15 passed / 199 assertions
WebRouteSmokeTest       : 6 passed / 8 assertions
Bku/Bpk/PeriodicReport  : 19 passed / 196 assertions
Repository Pint         : passed
npm run theme:qa        : All representative theme checks passed
npm run density:qa      : All density ownership checks passed
npm run build           : sukses
php artisan view:cache  : sukses
```

`DatabaseTableSummaryTest` gagal 3 test dengan `database is locked`, dan
kegagalan itu terbukti **pre-existing** melalui stash A/B: gagal identik pada
working tree bersih tanpa perubahan batch ini. Termasuk pola flaky SQLite
Windows yang sudah tercatat di dokumen ini.

Bagian dokumentasi tidak menghasilkan functional gate baru. Semua status
visual/browser tetap **RVR** — tidak ada halaman yang dibuka di browser atau PDF
viewer pada batch ini.

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

## Sesi QA browser audit GUI + perbaikan (2026-10-05)

Status: **FUNCTIONAL PASS (focused, findings Fixed) / BROWSER RVR**.

QA Playwright (Chrome, ADMIN, 10208183 / 2026 / BOS Reguler) lintas 14 rute pada
1366x768 dan 375x812, tema `light` + `arkas_dark_v2`. Temuan sudah
diperbaiki, diverifikasi ulang di browser, dan dikunci regression test.
Detail per temuan ada di `GUI_RUNTIME_QA.md` §"Sesi QA audit GUI 2026-10-05
(kedua)".

### Yang terverifikasi seragam

- Breadcrumb global konsisten dan sticky mengikuti tinggi topbar nyata.
- Kontras token inti LULUS AA pada kedua tema: `--ui-fg-muted on
  --ui-surface-base` 4.76 (light) / 7.41 (dark); `--theme-action-fg on
  --theme-action-bg` 7.15 (dark).
- Density token terpakai benar (padding sel via `--profile-table-row-y`, tinggi
  kontrol via `--profile-control-height` = 44px realized). Nol hardcoded padding
  tabel di halaman yang diuji.
- Tap target: nol elemen interaktif < 32px tinggi di 1366 dan `/spj?tab=paket`.
- Focus ring: 26 elemen focusable di `/spj/penomoran`, nol tanpa outline.
- `/laporan-periode/bulan/bku/cetak?periode_laporan=1` PASS: 110 baris, 0 uraian
  kosong, bunga bank + pajak bunga bernominal, tepat 1 blok tanda tangan.

### Temuan dan perbaikannya (semua sudah diperbaiki)

```text
T1 (tinggi)  SUDAH DIPERBAIKAN : tombol scroll-to-top memblokir kontrol.
              Dipindah dari fixed bottom-5 left-1/2 ke bottom-[5.5rem]
              right-5 (ditumpuk di atas tombol asisten) dan diubah jadi ikon
              bulat 48x48 tanpa label teks. Area tertutup pada
              /laporan-periode: 2828 px2 -> 0 kontrol terblokir.
T2 (sedang)  SUDAH DIPERBAIKAN : 17 kontrol Isian Manual Paket tanpa
              asosiasi label. (1) common.blade.php kini memakai id
              spj-common-{transactionId}-* dengan label for= yang cocok;
              (2) primitive x-ui.field membuat id sendiri bila :for tidak
              dioper, dan bindGeneratedFieldIds() di app.js mengikatkan ke
              kontrol submit yang ber-name (bukan input cermin widget
              tanggal); (3) 4 label yang benar-benar terputus diperbaiki
              langsung di spj/index, page-table-per-page, school-selector,
              database-school-list.
              Bukti: 0 kontrol tanpa asosiasi pada 17 kontrol isian manual,
              dan 0 pada sweep 14 rute representative.
T3 (rendah)  SUDAH DIPERBAIKAN : dekorasi header meluber 43px di mobile.
              Blok @media(max-width:639px) baru menarik kedua dekorasi ke
              dalam bound header.
T4 (tambahan) SUDAH DIPERBAIKAN : grup topbar kanan (tema + profil) tidak
              wrap pada 375px sehingga docScrollW 430px vs 360px. Grup kini
              flex min-w-0 flex-wrap items-center justify-end gap-2 dan
              span nama user mendapat min-w-0. Bukti sesudah: docScrollW
              360 = viewport, scanner overflow 0.
```

Tidak ada perubahan business rule, lifecycle, numbering, tenant boundary,
ataukan kontrak sync. Perbaikan murni presentation, aksesibilitas, dan
interaksi.

Guard baru di `tests/Feature/GuiAudit09To13SourceReadinessTest.php` (6 test,
semua terbukti gagal tanpa fix lewat stash A/B):
`test_scroll_to_top_button_does_not_overlay_the_center_action_column`,
`test_ui_field_component_can_bind_generated_label_to_its_control`,
`test_topbar_action_group_can_wrap_on_narrow_viewports`,
`test_page_header_decoration_stays_inside_header_on_small_screens`,
`test_every_visible_label_is_associated_with_a_control`,
`test_package_form_fields_expose_ids_matching_their_labels`.

```text
GuiAudit09To13SourceReadinessTest : 20 passed / 154 assertions
SpjReportLayoutTest + MainTabs +
  PackageNavigationButtons          : 27 passed / 280 assertions
SPJ Critical suite                : 335 passed / 2.494 assertions
Repository Pint                   : passed
npm run theme:qa                  : All representative theme checks passed
npm run density:qa                : All density ownership checks passed
npm run build                     : sukses
php artisan view:cache            : sukses
git diff --check                  : bersih
```

### Batch GUI 2026-10-05 (ketiga): nama aksesibel panel yang dapat dibuka/tutup

Temuan: lima tombol "Tutup panel" pada `/pengaturan/template-dokumen` tidak
dapat dibedakan pengguna screen reader. Kelimanya memang lima panel berbeda
(batas upload, Import Paket Template, Tambah/Ganti Template, Hasil Validasi,
Template yang Tersedia), jadi ini bukan aksi terduplikasi. Dua cacat nyata:

1. `aria-label="Buka atau tutup panel"` pada `x-ui.form-section` menimpa teks
   dinamis, sehingga state terbuka/tertutup hilang dari accessible name dan
   nama aksesibel tidak lagi memuat label yang terlihat (WCAG 2.5.3).
2. Tidak satu pun tombol menyebut panel yang dikontrolnya, dan tidak ada
   `aria-controls`, sehingga target-nya tidak dapat diverifikasi.

```text
T5 (sedang) SUDAH DIPERBAIKAN : primitive baru x-ui.panel-toggle dipakai
              kelima panel. Nama aksesibel sekarang "<state> <judul panel>"
              (mis. "Tutup panel Import Paket Template") dan setiap tombol
              menunjuk body-nya lewat aria-controls dengan id yang dijamin
              ada. x-ui.form-section memasang id body dari prop panelId
              (default ui-form-section-<slug>), sehingga pemakaian di halaman
              lain ikut benar tanpa edit per halaman.
              Bukti DOM: 5/5 nama unik + aria-controls target ada; uji klik
              pada satu panel mengubah nama (Tutup->Buka), aria-expanded
              (true->false), dan visibilitas body. Sweep 5 rute: 0 nama
              generik, 0 target aria-controls menggantung.
```

Catatan proses: implementasi pertama menaruh ternary Alpine di dalam backtick
sehingga `aria-label` berisi literal `open ? 'Tutup panel' : 'Buka panel' ...`.
Ketahuan lewat probe DOM yang membaca atribut mentah, bukan lewat error;
diperbaiki ke bentuk konkatenasi di luar template literal.

Koreksi atas klaim batch sebelumnya: dugaan bahwa tombol unduh laporan RKAS
pada `/penganggaran-rkas` tidak sejajar adalah **salah**. Pengukuran
`getBoundingClientRect` menunjukkan inset tombol dari tepi kanan kartu 17px
pada kelima baris (select 152px = spacer 152px). Penampilan zigzag hanya efek
grid 2 kolom dengan lima kartu. Tidak ada perubahan source untuk temuan itu.

Guard baru (2 test, terbukti gagal tanpa fix lewat A/B):
`test_panel_toggle_names_the_panel_it_collapses`,
`test_collapsible_panels_expose_an_aria_controls_target`.

```text
GuiAudit09To13SourceReadinessTest +
  SpjReportSidebarNavigationTest   : 25 passed / 202 assertions
DocumentTemplate Theme/Architecture/Order : 8 passed / 53 assertions
WebRouteSmokeTest (6 method, satu per satu) : all passed
  settings 46.53s, public/workspace 31.61s,
  transaction/master 44.29s, report 16.71s,
  by-design 0.79s, first-activation 0.82s
Repository Pint                   : passed
npm run theme:qa                  : All representative theme checks passed
npm run density:qa                : All density ownership checks passed
npm run build                     : sukses
php artisan view:cache            : sukses
```

`WebRouteSmokeTest` sebagai satu file sebelumnya timeout setelah 900s.
Dijalankan per-method dan keenamnya hijau; totalnya ~140s. Kegagalan itu
slow-suite, bukan cacat source.

### RVR tetap terbuka

Modal Pratinjau Massal + batas 20 paket (butuh paket NUMBERED; data aktif 0
bernomor), eksekusi penomoran triwulan, eksekusi sinkronisasi, output biner
PDF/Excel, kontras 29 profil tema (hanya 2 diuji), dan dokumen cetak folio di
layar kecil.

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

## Hapus endpoint rekap umum spj.export (2026-10-04)

Status: **FUNCTIONAL PASS (focused) / BROWSER RVR**.

`GET /spj/unduh/{format}` (`spj.export` → `SpjController@export` →
`SpjReportUseCase::export`) tidak tertaut di view/service mana pun —
halaman susun honor/jasa memakai `spj.honor-payments.export` /
`spj.service-recipients.export`. Dihapus: route, controller method,
`SpjReportUseCase::export` + helper khusus `addRealizationSheet`
(`report()`/`reportData()` tetap dipakai tab Laporan/Monitoring),
entri smoke test, dan baris `API.md` (header count 27→26).

Evidence: `WebRouteSmokeTest` 6 passed, `SpjReportLayoutTest` +
`SpjSupplementaryTemplateContractTest` 25 passed / 245 assertions,
Pint passed, `route:list` tak lagi memuat `/spj/unduh`, `git diff --check`
bersih.

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

## Main regression recovery after staged-rendering feature (2026-09-28)

Status: **FUNCTIONAL PASS / HISTORICAL GREEN GATE**.

Perubahan fitur besar `9540937d1098f954784d0971fa4f92edb9691b3f` memperluas staged goods receipt (`TAHAP:n`), template rendering, vendor memory, dan beberapa jalur rekonsiliasi. Empat run push berturut-turut (#27–#30) kemudian gagal. Audit 2026-09-28 menutup blocker secara bertahap tanpa mengubah lifecycle/numbering contract:

- `47368a2c31d73bebdf04ee8bb8ece880ee2efc15` — override test template diselaraskan dengan signature baru `?GoodsReceipt $receipt = null`; fatal `Premature end of PHP process` tertutup.
- `217a69f858d0a5f03f12cdd51286b356b2cd4404` — fallback `auth()` di use case rekonsiliasi dihapus, regression assertion tanggal dibuat tahan whitespace tanpa melonggarkan batas tanggal canonical, dan form Siswa kembali memakai theme token.
- `49cd5ae759bc5713cd7023adc4999ca4ac5d9e2e` — expected test SiPLah diselaraskan dengan wording source yang memang berubah menjadi `invoice nomor ...`.
- `14cf825eea79da48b98423469d8e2746839a0bcb` — realisasi RKAS lintas revisi dipulihkan. Query baris tampilan tetap boleh difilter ke revisi terpilih, tetapi indeks identitas RAPBS untuk fallback realisasi kembali membaca lintas revisi lalu menerapkan scope tahun+sumber dana canonical. Tiga regression `RkasRevisionModesTest` tetap dipertahankan sebagai guard.

Evidence GitHub untuk code HEAD `main@14cf825eea79da48b98423469d8e2746839a0bcb`
(sebelum enam commit yang membawa main ke `1705f5d`):

```text
WORKFLOW              : SPJ Critical Verification
RUN                   : 36349827238 (#34)
ARTIFACT GUARD        : PASS
COMPOSER VALIDATE     : PASS
LOCKED PLATFORM CHECK : PASS / PHP 8.3
COMPOSER INSTALL      : PASS
REPOSITORY PINT       : PASS
FRONTEND BUILD        : PASS
BLADE COMPILE         : PASS
CHECKLIST PHP LINT    : PASS
SPJ CRITICAL          : PASS / 339 tests / 2,605 assertions
FULL UNIT             : PASS / 79 tests / 281 assertions
FULL FEATURE          : PASS / 611 tests / 4,276 assertions
RESULT                : SUCCESS
```

Deterministic source/regression gate kembali hijau. Browser/operator visual-runtime untuk perubahan UI 2026-09-27 tetap **RVR**; CI ini tidak dipakai sebagai klaim browser QA.

## Current main audit + SK file regression hardening (2026-09-25)

Status: **AUDIT COMPLETE / VERIFIED IN GATE #25 (HISTORICAL)**.

Audit dimulai dari `main@89e1866d7b9fecc6197ad71ec9674c6fa6a3d614` (`feat: tab SK kanonis + upload unduh pindaian SK`). Baseline commit tersebut sudah hijau pada CI run #20, tetapi audit source menemukan gap data-integrity yang belum dibuktikan regression:

- upload hanya memeriksa ekstensi, sehingga file berkonten salah yang menyamar sebagai PDF/JPG/PNG belum ditolak berdasarkan MIME server;
- penggantian pindaian menghapus file lama sebelum file baru pasti tersimpan, sehingga storage failure dapat meninggalkan metadata/file tidak konsisten;
- nama file hanya unik sampai detik dan berisiko collision untuk kind+nama file yang sama;
- penghapusan pegawai meng-cascade row `employee_certificates`, tetapi file pindaian fisik berpotensi menjadi orphan;
- test upload awal memakai `withoutMiddleware()` dan belum mengunci boundary route mutation vs read.

Hardening ditutup pada:

```text
ffa049a4589dd8fc650906eccc1796a3c6b5f60a
fix: harden employee SK file regression

24a19333889ddf73614ad7a2a69dd6b7f12a768f
fix: clean up employee SK files on delete
```

Kontrak setelah hardening: validasi file memakai ekstensi + MIME server, file pengganti disimpan sebelum metadata DB diubah dan file lama baru dibuang setelah update DB sukses, nama file mendapat suffix random untuk mencegah collision, serta delete SK/pegawai membersihkan file setelah delete DB berhasil. Regression baru mencakup MIME spoof, replace sukses, replace gagal, boundary route, dan cleanup file saat pegawai dihapus.

Evidence GitHub untuk source HEAD `main@ac75b5bed646be70a4a2688512f75fbc6b55c46a`:

```text
WORKFLOW              : SPJ Critical Verification
RUN                   : 36120237766 (#25)
ARTIFACT GUARD        : PASS
COMPOSER CHECKS       : PASS
REPOSITORY PINT       : PASS / 495 files
FRONTEND BUILD        : PASS
BLADE COMPILE         : PASS
CHECKLIST PHP LINT    : PASS
SPJ CRITICAL          : PASS / 330 tests / 2,570 assertions
FULL UNIT             : PASS / 79 tests / 281 assertions
FULL FEATURE          : PASS / 569 tests / 4,051 assertions
EMPLOYEE CERTIFICATE  : PASS
QUARTER AUDIT         : PASS
RESULT                : SUCCESS
```

Debt test/style pra-eksis juga ditutup tanpa mengubah business rule: coverage schema tenant tidak lengkap dipindahkan dari `TmpAuditDebugTest` ke `SpjQuarterAuditCommandTest` dengan assertion nyata, file debug sementara dihapus, dan issue Pint pada `VerifyMirrorBackfill.php` serta `tests/bootstrap.php` dibersihkan. Run #25 membuktikan Pint 495 file bersih dan seluruh regression gate tetap hijau. Browser/operator runtime untuk UI tab/upload SK tetap **RVR**; CI membuktikan functional/regression gate, bukan visual/runtime operator evidence.

## Repository containment hardening (2026-09-24)

Status: **SOURCE HARDENING APPLIED / ARTIFACT GUARD PASS / VERIFIED IN GATE #25 (HISTORICAL)**.

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

### Latest successful canonical code gate before Bulk Preview

```text
LATEST SUCCESSFUL CODE HEAD: e4ba5cf869f554cee9f0b9d230e437f1aee676ae
LATEST SUCCESSFUL CODE GATE: run 36515608353 (#46) / SUCCESS
WORKFLOW                   : SPJ Critical Verification
COMPOSER VALIDATE          : PASS
LOCKED PLATFORM CHECK      : PASS pada PHP 8.3
COMPOSER INSTALL           : PASS dari committed lock
REPOSITORY PINT            : PASS
FRONTEND BUILD             : PASS
BLADE COMPILE              : PASS
CHECKLIST PHP LINT         : PASS
SPJ CRITICAL               : PASS / 339 tests / 2,605 assertions
FULL UNIT                  : PASS / 79 tests / 281 assertions
FULL FEATURE               : PASS / 642 tests / 4,417 assertions
```

Run #46 adalah code gate terakhir yang terverifikasi untuk source `main` sebelum perubahan Bulk Preview. Ia menutup failure `RkasReportTest` pada run #42–#45 dengan mengisolasi skenario HTTP revision-comparison dan menambah guard input tanpa mengubah business rule. Source change Bulk Preview belum tercakup pada run tersebut; focused verification lokal terbaru tercatat pada bagian status aktif di atas.

### P0 dependency-platform repair — CI #483 → #486

CI #483 pada head TALL migration `a4dd3954...` gagal sebelum test pada langkah `composer install`. Log membuktikan `composer.lock` mengunci sejumlah Symfony 8.x yang membutuhkan PHP `>=8.4`, sedangkan project mendeklarasikan PHP `^8.3` dan workflow canonical berjalan pada PHP 8.3.

Perbaikan dilakukan tanpa menaikkan minimum PHP project dan tanpa mengubah business rule:

1. `composer.json` menambahkan Composer platform floor `config.platform.php = 8.3.0` agar dependency resolution dari workstation PHP 8.4+ tetap kompatibel dengan minimum runtime project.
2. CI repair #485 me-resolve dependency Symfony pada PHP 8.3, memverifikasi `composer install`, build, Blade, SPJ Critical, Unit, dan Feature, lalu hanya setelah seluruh gate PASS menyimpan `composer.lock` hasil repair.
3. Workflow kemudian dikembalikan ke mode read-only/deterministik: tidak ada `composer update` di gate normal.
4. Gate normal menambahkan `composer validate --strict` dan `composer check-platform-reqs --lock` sebelum `composer install` agar drift platform lock terdeteksi lebih awal.
5. PR #486 pada `ba8fa0b...` membuktikan gate normal tersebut SUCCESS.

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

CI #480 adalah historical green baseline sebelum Laravel 13/TALL migration. Ia telah disupersede sebagai current canonical code gate oleh PR #486, lalu oleh workflow run #46.

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

Selisih +1 test vs gate #480 berasal dari commit `5fa98ed` (satu head di depan gate), bukan dari upgrade framework. Tidak ada business rule, lifecycle, numbering, sync, tenant ownership, atau authorization contract yang diubah. Remote deterministic compatibility Laravel 13/TALL pada PHP 8.3 sekarang dibuktikan oleh PR #486.

### TALL migration — Filament + Sail removal 2026-09-14

Audit membuktikan seluruh surface Filament adalah dead code: `RkasTable` orphan tanpa konsumen, `RkasBudgetTable` hanya dipakai view `rkas-budget/filament.blade.php` yang juga orphan (controller aktif me-render `rkas-budget.index` yang native), dan tidak ada test yang menyentuh class/view Filament. `laravel/sail` tidak dipakai (tanpa compose file/CI, dev memakai herd-lite + `artisan serve`).

Dihapus: `filament/*` + `laravel/sail` dari `composer.json` (termasuk script `filament:upgrade`), 2 komponen + 3 view orphan, directive `@filamentStyles/@filamentScripts`, 5 CSS `@import` Filament, selector `.fi-*` basi, dan aset `public/js/filament`. Tidak ada paket baru — stack aplikasi memakai Laravel + Livewire + Alpine + Tailwind; workspace RKAS kini dimount melalui `RkasBudgetWorkspace` dengan controller sebagai adapter data read-only.

Verifikasi lokal pasca-removal (PHP 8.4.0): SPJ Critical 288 PASS / 2244 assertions, Unit 60/206, Feature 412/2980 (identik dengan baseline L13), `npm run build` PASS (CSS 932KB → 425KB), `view:cache` PASS, `pint --dirty` passed. Remote deterministic gate pasca-removal sekarang PASS pada PR #486.

---

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

Baseline real-data yang sudah terdokumentasi (snapshot 2026-09-11, tenant 10260756):

```text
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
```

> **Diperbarui 2026-10-05.** Baseline di atas adalah snapshot lama tenant
> 10260756 dan sekarang **tidak lagi akurat**: audit read-only terhadap tiga
> tenant nyata pada `SPJ_DATA_PATH` aktif menunjukkan angka berbeda dan
> `document_number_formats` sudah terisi. Lihat entri "Audit real-data 3 tenant
> nyata (2026-10-05)" di atas untuk angka terkini. Distribusi paket READY saat
> itu: BARANG 32/17/64, HONOR_PEGAWAI 4/4/17, JASA_LAINNYA 6/1/5, KONSUMSI
> 2/1/6, PEMELIHARAAN 2/1/2, SPPD 0 untuk 10208183/10208246/10260756.

SPPD tetap 0 di ketiga tenant nyata. Jangan fabrikasi SPPD untuk coverage.

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

Gate PR #486 dan run #46 mempertahankan regression suite yang mencakup first numbering, cancel/reserved sequence, tail rollback, quarter dependency, fund-source scope, NUMBERED description carve-out, FINAL lock, dan registry-based consumers.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

```text
HTTP/ROUTE AUTH BASELINE       : PASS
LIVEWIRE MUTATION BOUNDARY     : HARDENED
NEGATIVE ROLE REGRESSION       : PASS di current SPJ Critical gate
LAST VERIFIED CODE GATE        : GREEN / workflow run #46; predates Bulk Preview source change
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

## P0-08 — Generic ARKAS Importer (dihapus 2026-10-04)

**Status: SUBSYSTEM REMOVED.**

Modul Generic Importer sudah tidak ada; yang tersisa `ArkasStagingService`
+ `ArkasImportRowSynchronizer` sebagai infra pipeline canonical sync. Work item
P0-08 ditutup dan tidak lagi jadi prioritas. Rujukan: bagian "Hapus subsystem
importer mapping ARKAS 2026-10-04" di dokumen ini, `SYNCHRONIZATION.md`
§2.2/§18, dan `ARCHITECTURE_COMPLETE.md` §8.

---

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

Update: `ArkasMirrorBudgetService` sekarang menjadi adapter utama untuk `/penganggaran-rkas` dan `RkasBudgetFilter`. Pagu, periode, realisasi, hierarki, filter program/subprogram/kegiatan, dan pencarian membaca `arkas_mirror_rapbs`, `arkas_mirror_rapbs_periode`, `arkas_mirror_kas_umum`, serta `arkas_mirror_ref_kode`. Snapshot mirror melakukan preload referensi dan cache per request. Snapshot memilih revisi RKAS terakhir dengan kontrak ARKASBridge: per tahun+sumber dana, urut `IS_AKTIF`, `IS_APPROVE`, `LAST_UPDATE`, lalu `CREATE_DATE`, kemudian hanya memakai `rapbs` yang menunjuk ke `ID_ANGGARAN` tersebut. Kontrak tampilan direvisi 2026-09-24 (permintaan operator): halaman penganggaran menampilkan tab per revisi — seluruh revisi yang disetujui (kronologis) ditambah tab pengajuan terakhir bila masih ada yang belum disetujui; default tab persetujuan terakhir; satu tab = satu snapshot `ID_ANGGARAN` (`ArkasMirrorBudgetService::revisions()`). Label tab "Pengesahan ke-N"/"Pengajuan ke-N" tanpa tanggal (tanggal di teks info + tooltip); tanggal pengesahan dari kolom `tanggal_pengesahan`, pengajuan dari `tanggal_pengajuan`. Revisi: BKU menaut ke rapbs revisi berjalan sehingga tab lama nol realisasi — ditambah fallback identitas pos (`ID_REF_KODE|rekening|uraian`, sisa proporsional pagu, direct didahulukan; total tetap pas, view terbaru tidak berubah; scope identitas lewat record anggaran agar uang tahun lain tidak bocor lintas tahun). Sejak 2026-10-04 fallback dilengkapi: kas beridentitas yang tidak tampil pada revisi ini ikut dibagi proporsional pagu (tidak dibuang), residual per bulan hanya ke baris yang memiliki bulan itu, dan konsumsi kuartal/semester menjumlahkan share bulan dalam scope — sehingga seluruh tab pengesahan menampilkan realisasi yang sama untuk kas yang sama. Volume tahunan fallback `VOLUME_TOTAL` → `VOLUME` untuk payload skema huruf kecil. Jalur tabel legacy masih ada sebagai fallback untuk database yang belum memiliki mirror; pada database dengan `arkas_mirror_rapbs`, jalur tersebut tidak dieksekusi. Regression runtime mirror tetap RVR karena database testing lokal mengalami `disk I/O`/`no such table: schools` setelah batch test sebelumnya.

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

## Navbar active context selector 2026-09-20

Navbar global sekarang selalu menampilkan selector tahun anggaran dan sumber dana tepat setelah nama sekolah. Selector tetap terlihat ketika daftar tahun kosong dengan keadaan disabled dan pesan yang jelas. Sumber daftar memakai `fiscal_years` canonical beserta relasi `fund_sources`; provider tidak lagi memeriksa tabel RKAS legacy atau mirror untuk menentukan apakah selector ditampilkan.

## Canonical material table surfaces 2026-09-20

Seluruh tabel authenticated mengikuti aturan tabel canonical secara global: wrapper dan permukaan tabel tidak lagi memakai border radius sehingga tampil material/square, tabel tetap memakai token tema, overflow horizontal, hover, dan kontrak pagination yang sudah ditentukan. Komponen `<x-ui.table>` tetap menjadi primitive utama dengan `data-pagination` eksplisit; tabel Livewire/server dan tabel lokal yang telah memiliki pager tidak disuntik pager kedua.

Menu tindakan pada tabel laporan SPJ memiliki positioning khusus: tiga baris terakhir membuka dropdown ke atas agar tidak terpotong viewport/tabel, sedangkan baris lain membuka dropdown dari baris yang dipilih ke bawah.

## Canonical tab visual 2026-09-20

Tab global, tab SPJ, tab paket SPJ, tab referensi, dan tab Database Control Center kini memakai lebar penuh yang seimbang, ikon dalam lingkaran, tinggi tetap, serta hover glass bertoken tema. Tab tidak lagi membuka overflow vertikal; daftar tab memakai clipping horizontal yang terkendali dan label tetap terpotong secara aman pada ruang sempit.

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

## Self-healing database test + perbaikan assertion tab aktif 2026-10-05

Status: **PASS (focused, test) / BROWSER RVR**.

Dua perbaikan dari audit kekurangan aplikasi.

1. **`tests/bootstrap.php` sekarang menyiapkan `database/testing.sqlite` sendiri.**
   Dua kegagalan yang berulang sudah tercatat di `CURRENT_PROGRESS.md`: berkas
   hilang membuat setiap test gagal dengan "Database file at path ... does not
   exist", dan berkas korup memicu "database disk image is malformed" atau
   "file is not a database". Guard lama hanya menolak jalan bila
   `bootstrap/cache/config.php` aktif dan tidak menyentuh berkas test.
   Sekarang bootstrap membuat berkas bila belum ada dan mengosongkan isinya bila
   `PRAGMA integrity_check` bukan `ok`, sehingga skema dibangun ulang oleh
   `RefreshDatabase`. Perubahan isi ditulis di tempat, bukan rename, karena PDO
   sempat membuka handle dan pada Windows berkas sqlite tidak bisa di-rename
   selama masih dirujuk proses; percobaan rename justru meninggalkan berkas
   `testing.sqlite.rebuild-*` dan membuat suite rusak.
   Error mode PDO dipaksa exception karena tanpa itu query
   `PRAGMA integrity_check` mengembalikan `false` tanpa error untuk
   "file is not a database" sehingga korup tidak terdeteksi.
   Halaman produksi `database/database.sqlite` tidak pernah disentuh; jalur ini
   hanya aktif lewat `phpunit.xml` yang memaksa `DB_DATABASE`.
2. **`SpjReportLayoutTest::test_spj_main_tabs_render_as_segmented_control`**
   assertion-nya salah arah: melarang `.ui-tab-active` di CSS, padahal
   `components/tabs.blade.php` memakai kelas itu untuk menandai tab terpilih.
   Assertion sekarang memeriksa keberadaan penanda aktif pada markup dan pada
   `ui-generalization.css`, plus keberadaan contracted `.ui-tabs-list` dan
   `.ui-tab` pada `spj-workspace-standardization.css`.

Verifikasi: `SpjReportLayoutTest` 15 passed / 199 assertions hijau penuh untuk
pertama kalinya di HEAD ini. Skenario bootstrap diuji langsung: berkas dihapus
lalu suite hijau; berkas diisi `CORRUPT-GARBAGE` lalu suite memulih dengan
NOTICE dan hijau. Tujuh test file lain tetap hijau. `vendor/bin/pint --test`
passed, `git diff --check` bersih.
