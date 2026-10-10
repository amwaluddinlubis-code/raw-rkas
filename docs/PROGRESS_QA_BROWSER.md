# QA Browser — Log Kemajuan

> Riwayat sesi QA browser (desktop/mobile) dan temuan perbaikannya. Checklist canonical ada di GUI_RUNTIME_QA.md.
>
> Dipecah dari `CURRENT_PROGRESS.md` pada 2026-10-11 agar tiap file di bawah batas 128KB konektor GitHub.
> Urutan kronologis terbalik (terbaru di atas), sesuai file induk. Status terbaru selalu di `CURRENT_PROGRESS.md`.

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
