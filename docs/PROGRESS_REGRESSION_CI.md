# Regression & CI — Log Kemajuan

> Riwayat perbaikan regression, hardening, checkpoint CI, dan audit commit.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

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


## Audit mendalam commit terbaru dan tindak lanjut (2026-10-11)

Status: **SOURCE FIX APPLIED / REGRESSION + CI RVR**.

Audit rangkaian refaktor terbaru pada branch \`main\` meninjau commit helper tampilan, ekstraksi pembaca DOCX, sentralisasi eager-load paket, dan penghapusan method duplikat. Temuan yang dapat dibuktikan dari source:

1. \`SpjTemplateService\` masih memiliki \`match\` kategori lokal yang menduplikasi pemetaan \`SpjDisplay::typeLabel()\`. Pemetaan lokal itu diganti dengan helper kanonis agar kategori lama \`JASA_HONORARIUM\` tetap mendapat label yang sama dan daftar label tidak bercabang.
2. Ditambahkan \`tests/Unit/SpjDisplayTest.php\` untuk mengunci format Rupiah (termasuk pembulatan dan nilai negatif) serta label kategori canonical/legacy dan fallback kategori yang belum dikenal.
3. Tanggal audit README diperbarui menjadi 2026-10-11, dengan peringatan eksplisit bahwa audit source tidak berarti regression suite atau CI telah lulus.

Verifikasi GitHub: combined commit status untuk commit awal audit mengembalikan daftar status kosong, dan pencarian workflow terkait commit tidak menemukan run yang dapat dipastikan sebagai gate CI untuk HEAD terbaru. Ini **bukan** bukti CI gagal maupun lulus.

RVR wajib sebelum menyatakan selesai:
- Jalankan \`php artisan test --compact tests/Unit/SpjDisplayTest.php\` dan suite terkait template/export.
- Jalankan Pint, \`php artisan view:cache --no-interaction\`, dan \`git diff --check\`.
- Periksa workflow Actions yang berjalan pada HEAD setelah perubahan audit ini dan catat hasil setiap job.
- Jalankan suite penuh dan \`php artisan spj:verify\` jika lingkungan serta koneksi tenant yang dibutuhkan tersedia.

Tidak ada klaim bahwa pengujian lokal telah dijalankan dalam sesi audit ini. Browser QA tetap ditunda sampai pengguna melakukan uji UI sendiri.
