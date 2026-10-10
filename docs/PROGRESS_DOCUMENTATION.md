# Dokumentasi — Log Kemajuan

> Riwayat audit dan cleanup dokumentasi.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

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
