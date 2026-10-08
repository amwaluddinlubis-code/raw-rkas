# GUI Runtime QA — Desktop, Laptop, Mobile, dan Tablet

Terakhir diperbarui: **2026-10-05**

Dokumen ini adalah checklist runtime untuk menutup **GUI-AUDIT-12** dan **GUI-AUDIT-13**. Source-level regression dan CI tidak boleh dipakai sebagai pengganti verifikasi visual/runtime di browser.

## Status saat ini

```text
GUI-AUDIT-12 source readiness : PASS pada gate run #46 / e4ba5cf;
                                + focused re-verification 2026-10-05
                                (SPJ Critical 335/2.494, GUI readiness
                                20/154, build, theme:qa, density:qa)
GUI-AUDIT-12 browser runtime  : FUNCTIONAL PASS (focused, 2026-10-05,
                                Chrome 154, SDN 318/2026/BOS Reguler) /
                                RVR tersisa untuk: modal Pratinjau Massal
                                + batas 20 paket (butuh paket NUMBERED,
                                data aktif 0 bernomor), eksekusi penomoran
                                triwulan, eksekusi sinkronisasi, output
                                biner PDF/Excel, dan kontras tema gelap
GUI-AUDIT-13 source readiness : PASS pada gate run #46 / e4ba5cf (sama)
GUI-AUDIT-13 mobile/tablet     : FUNCTIONAL PASS (focused, viewport
                                375/768/1024; 4 temuan diperbaiki
                                termasuk overflow topbar 375px) /
                                RVR tersisa untuk dokumen cetak folio
                                pada layar kecil (scroll halaman, non-blocking:
                                jalur cetak/PDF resmi via desktop)
```

Sesi evidence 2026-10-05 (Playwright MCP, Chrome 154.0.8037.97,
role ADMIN, 10208183 / 2026 / BOS Reguler, commit kerja
`SpjPeriodicReportPrintService` + `document.blade.php` + 2 Blade mobile):

- Desktop 1366×768 PASS tanpa console error/warning: `/`,
  `/transaksi` (+pager hal. 2, filter triwulan), `/transaksi/93`,
  `/spj?tab=paket`, `&package_id=48` (+tab Isian, modal Pratinjau
  muat dalam viewport + iframe pratinjau-pdf, tombol Tutup ada),
  `/spj/penomoran?quarter=1`, `/spj?tab=laporan` (empty state benar,
  0 bernomor), `/spj?tab=monitoring`, `/penganggaran-rkas`,
  `/pajak`, `/rekonsiliasi` (empty state), `/spj/rekap-triwulan`,
  `/laporan-periode`, `/referensi`, `/siswa`, `/pegawai`,
  `/pegawai/tinjau-identitas` (read-only), `/data-sinkron`,
  `/pengaturan/arkas/mirror`, `/laporan-audit`,
  `/pengaturan/database-aktif`, `/pengaturan/user`,
  `/pengaturan/database-reset` (tombol Reset/Batal ada, TIDAK
  dieksekusi), `/pengaturan/backup`, `/pengaturan/template-dokumen`,
  `/pengaturan/format-penomoran`, `/spj/penomoran/koreksi`,
  `/penganggaran-rkas/{saran,simulasi,audit-mirror,perbandingan-revisi}`,
  `/pengaturan/dapodik`, `/pengaturan/impersonate`.
- `/laporan-periode/triwulan/bku/cetak?periode_laporan=1` PASS di
  1366 dan 1920: 110 baris, 0 uraian kosong, bunga bank/pajak bunga
  bernominal, tepat 1 blok tanda tangan.
- Tablet 768×1024: kartu mobile, tanpa overflow. Tablet 1024×768:
  tabel desktop kembali, tanpa overflow (breakpoint `lg` benar).
- Mobile 375×812: dashboard + siswa tanpa overflow; 2 temuan
  diperbaiki dan terverifikasi (kartu transaksi `min-w-0`,
  baris dokumen paket `flex-wrap`).
- Reset database, penomoran massal, dan sinkronisasi TIDAK dieksekusi
  (mutasi destruktif/berat); freshness WARNING (>24 jam) tercatat
  informatif, bukan blocker.

Evidence source-readiness terakhir: lihat angka gate di `P0_VERIFICATION_KIT.md` §1 (tidak disalin ke sini agar tidak divergen).

## Sesi QA audit GUI 2026-10-05 (kedua): seragam halaman dan kompatibilitas form

Playwright MCP, Chrome, role ADMIN, 10208183 / 2026 / BOS Reguler. Lintas:
`/masuk`, `/pilih-sekolah`, `/pilih-tahun`, `/`, `/transaksi`, `/transaksi/93`,
`/spj?tab=paket` (+ `package_id=48` + `package_tab=isian`), `/penganggaran-rkas`,
`/pajak`, `/spj/penomoran`, `/pengaturan/template-dokumen`, `/rekonsiliasi`,
`/laporan-periode` (+ `?periode_laporan=1`), `/laporan-periode/bulan/bku/cetak`.
Viewport 1366x768 dan 375x812; tema `light` dan `arkas_dark_v2`.

**0 console error** di seluruh rute yang diuji (dicek via `browser_console_messages`).

### Yang sudah seragam (tidak perlu tindakan)

- Breadcrumb global konsisten: manusiawi, bordered, sticky di bawah topbar,
  offset mengikuti tinggi topbar nyata (topbar 61px, breadcrumb mulai y=93).
- Palette header/stats konsisten antar halaman (Light Modern): surface
  putih/`--ui-surface-soft`, border `--ui-line`, aksen teal
  `--theme-content-accent` = `#134e4a`.
- Kontras token inti **LULUS AA** pada kedua tema:

  ```text
  pasangan                                    light    arkas_dark_v2
  --ui-fg on --ui-surface-base               10.35     15.85
  --ui-fg-muted on --ui-surface-base          4.76      7.41
  --theme-action-fg on --theme-action-bg      7.17      7.15
  --theme-content-accent on --ui-surface-base 9.48      8.37
  ```

- Density token benar-benar terpakai: `--profile-table-row-y` untuk padding sel,
  `--profile-control-height` untuk tinggi kontrol (44px realized). Nol
  hardcoded padding tabel di halaman yang diuji.
- Tab datar sesuai kontrak: radius 0, tinggi 52px seragam, indikator accent.
- Tap target: **nol** elemen interaktif di bawah 32px tinggi / 24px lebar pada
  1366 dan pada `/spj?tab=paket`.
- Focus ring: 26 elemen focusable di `/spj/penomoran`, **nol** tanpa outline.
- `/laporan-periode/bulan/bku/cetak?periode_laporan=1` PASS: 110 baris, 0 uraian
  kosong, bunga bank + pajak bunga bernominal, tepat 1 blok tanda tangan,
  disclaimer sumber data terbaca.

### Temuan dan perbaikannya (SEMUA SUDAH DIPERBAIKAN)

Ketiga temuan di bawah ditemukan pada sesi ini, diperbaiki, lalu diverifikasi
ulang di browser. Guard source-level ditambahkan untuk masing-masing.

**T1 (tinggi) — SUDAH DIPERBAIKAN: tombol "Ke atas" menutupi kontrol.**
`#app-scroll-to-top` sebelumnya memakai `fixed bottom-5 left-1/2 z-40` dengan
label teks sehingga area 93x44 px menutupi baris aksi tengah bawah. Bukti
sebelum perbaikan di `/laporan-periode?periode_laporan=1` (1366x768):

```text
tombol   : x=629 y=704 w=93 h=44
terkunci : link "Cetak" (baris Buku Kas Umum)
overlap  : 2828 px2 dari 44px target sentuh
elementFromPoint() pada titik tengah tombol -> SPAN "Ke atas", bukan link
```

Link `Cetak` masih ter-*reachable* lewat klik terarah, tetapi klik mouse pada
area tombunya tidak mengenai link. Ini berdampak negatif nyata bagi operator.

Perbaikan: tombol dipindahkan ke pojok kanan, ditumpuk di atas tombol asisten
(`fixed bottom-[5.5rem] right-5`), dan diubah menjadi ikon bulat 48×48 tanpa
label teks. Bukti sesudah perbaikan pada halaman yang sama:

```text
tombol      : x=1283 y=632 w=48 h=48 (grid, place-items-center)
kontrol yang tertutup: 0
elementFromPoint di area tombol: tidak ada kontrol ter blokir
```

Area tertutup berkurang dari 2828 px2 menjadi 0, dan tombol tidak lagi berada
di kolom aksi. Guard: `test_scroll_to_top_button_does_not_overlay_the_center_action_column`.

**T2 (sedang) — SUDAH DIPERBAIKAN: asosiasi label ke kontrol.**
Di `/spj?tab=paket&package_id=48&package_tab=isian` seluruh kontrol terukur
sebelum perbaikan:

```text
total kontrol (tanpa radio/hidden)      : 17
label dengan for= yang cocok              : 0
label membungkus kontrol (implicit)       : 0
aria-label / aria-labelledby              : 0
klik label -> fokus pindah ke kontrol    : tidak
```

Artinya 17 field (Kategori SPJ, Uraian pembayaran, Metode pembayaran, Referensi
pembayaran, Penyedia, NPWP, Tanggal-tanggal, dan seterusnya) **tidak punya
asosiasi label dengan kontrol**. Label terlihat secara visual, tetapi klik
label tidak memfokuskan input dan screen reader tidak membacakan kontrol
berikutnya. Audit repo awal: **45 `<label>` tanpa `for=` pada 18 file**.

Perbaikan dilakukan dua arah:

1. **Field eksplisit** di `spj/partials/package/common.blade.php` — 7 field
   kini memakai id berawalan `spj-common-{transactionId}-` dan `label for=`
   yang cocok (payment-description, payment-method, payment-reference,
   vendor-name, vendor-owner, vendor-npwp, receipt-recipient).
2. **Perbaikan generik pada primitive `x-ui.field`** — bila pemanggil tidak
   mengoper `:for`, primitive membuat id sendiri dan menaruh marker
   `data-ui-field-bind-id`; `bindGeneratedFieldIds()` di `resources/js/app.js`
   mengikat id itu ke kontrol submit yang benar. Detail penting: widget tanggal
   Indonesia membuat input cermin (type=text, tanpa `name`) lebih dulu, jadi
   binder memilih kontrol **ber-`name`**, bukan "yang pertama" — jika tidak,
   id terikat ke input mati dan tanggal tetap terputus.
3. **Empat label yang benar-benar terputus** (tidak membungkus kontrol)
   diperbaiki langsung: `spj/index.blade.php` (Kategori SPJ),
   `components/page-table-per-page.blade.php` (selector baris per halaman),
   `livewire/school-selector.blade.php`, `livewire/database-school-list.blade.php`.

Bukti sesudah perbaikan pada halaman yang sama:

```text
total kontrol : 17
tanpa asosiasi: 0
klik label memfokuskan kontrol: 6/6 kontrol yang diuji (uraian, metode,
  referensi, penerima utama, tanggal pesanan, kategori SPJ)
```

Sweep 14 rute representative (`/`, `/transaksi`, `/penganggaran-rkas`,
`/pajak`, `/rekonsiliasi`, `/laporan-periode`, `/pegawai`,
`/pegawai/tambah/baru`, `/siswa`, `/referensi`,
`/pengaturan/template-dokumen`, `/pengaturan/format-penomoran`,
`/spj/penomoran`, `/pengaturan/database-aktif`): **0 kontrol tanpa asosiasi
label di semua rute**. Guard:
`test_every_visible_label_is_associated_with_a_control`,
`test_package_form_fields_expose_ids_matching_their_labels`,
`test_ui_field_component_can_bind_generated_label_to_its_control`.

Susulan 2026-10-08: label pencarian explorer
(`livewire/database-table-explorer`) memakai `for` + `id` eksplisit
(temuan GuiAudit09To13 yang sama kelasnya dengan T2).

**T3 (rendah) — SUDAH DIPERBAIKAN: dekorasi header meluber pada mobile.**
`.page-header-decoration-top` sebelumnya memakai `right: -4rem; width: 14rem`
sehingga pada 375px tepi kanan dekorasi mencapai 403px vs viewport 360px. Tidak
merusak scroll (`docScrollW` = 360 = viewport dan parent sudah
`overflow: hidden`), jadi murni kosmetik.

Perbaikan: blok `@media(max-width:639px)` baru pada
`token-native-components.css` mengecilkan dan menarik kedua dekorasi ke dalam
bound header (`right: -1rem; top: -3.5rem; 10rem` untuk atas, `left: 24%;
bottom: -3.5rem; 9rem` untuk bawah). Guard:
`test_page_header_decoration_stays_inside_header_on_small_screens`.

### Temuan tambahan dari sesi perbaikan (juga sudah diperbaiki)

**T4: grup topbar kanan tidak bisa wrap pada 375px.** Scanner overflow pada
375x812 menemukan `docScrollW` = 430px vs `clientWidth` = 360px, dengan
`div.flex.items-center.gap-2` (tema + profil) memakai `flex-wrap: nowrap`
sehingga mendorong dokumen. Perbaikan: grup tersebut kini
`flex min-w-0 flex-wrap items-center justify-end gap-2`, dan span nama user
mendapat `min-w-0`. Bukti sesudah perbaikan: `docScrollW` = 360 = viewport,
scanner overflow = 0 pada `/spj?tab=paket`. Guard:
`test_topbar_action_group_can_wrap_on_narrow_viewports`.

**T5: lima tombol "Tutup panel" tidak dapat dibedakan pengguna screen reader.**
Ternyata kelimanya memang lima panel berbeda (batas upload, Import Paket
Template, Tambah/Ganti Template, Hasil Validasi, Template yang Tersedia), jadi
bukan aksi yang terduplikasi. Cacatnya ada di accessible name: dua tombol
memakai `aria-label="Buka atau tutup panel"` yang menimpa teks dinamis sehingga
state terbuka/tertutup hilang dan nama aksesibel tidak lagi memuat label yang
terlihat (WCAG 2.5.3 Label in Name); tiga tombol lain hanya reductions `Tutup
panel` tanpa nama panel. consequence: pengguna SR mendengar "Tutup panel" lima
kali tanpa tahu tombol mana menutup bagian apa. Tidak satu pun tombol punya
`aria-controls`.

Perbaikan: primitive baru `resources/views/components/ui/panel-toggle.blade.php`
mengganti kelima tombol. Accessible name sekarang `<state> <judul panel>` dan
setiap tombol menunjuk body-nya lewat `aria-controls` dengan id yang dijamin
ada. `x-ui.form-section` ikut diperbaiki: prop `panelId` (default
`ui-form-section-<slug judul>`) dipasang pada body, sehingga primitive yang
dipakai halaman lain otomatis benar tanpa edit per halaman.

Catatan proses: versi pertama memakai ternary di dalam backtick
(`` `open ? 'Tutup panel' : 'Buka panel' ` ``). Alpine mengevaluasi template
literal itu sebagai teks biasa, sehingga `aria-label` berisi literal
`open ? 'Tutup panel' : 'Buka panel' Import Paket Template`. Ketahuan karena
probe DOM membaca `aria-label` mentah, bukan karena error. Diperbaiki ke
`(open ? 'Tutup panel ' : 'Buka panel ') + 'Import Paket Template'`.

Bukti sesudah perbaikan (probe DOM, 1366x768):

```text
Tutup panel Status batas unggah server      expanded=true  target ada
Tutup panel Import Paket Template           expanded=true  target ada
Tutup panel Tambah atau Ganti Satu Template expanded=true  target ada
Tutup panel Hasil Validasi Template         expanded=true  target ada
Tutup panel Template yang Tersedia          expanded=true  target ada
```

Uji interaksi pada `ui-form-section-import-paket-template`: nama berubah
`Tutup panel` -> `Buka panel`, `aria-expanded` `true` -> `false`, body
`sembunyi` -> lalu `true` lagi saat diklik ulang. Sweep 5 rute
(`/pengaturan/template-dokumen`, `/sekolah/setting`, `/employees`, `/users`,
`/siswa`): 0 tombol dengan nama generik, 0 target `aria-controls` menggantung.
Guard: `test_panel_toggle_names_the_panel_it_collapses` dan
`test_collapsible_panels_expose_an_aria_controls_target`.

**Koreksi atas klaim sesi sebelumnya:** dugaan bahwa tombol unduh laporan
RKAS pada `/penganggaran-rkas` tidak sejajar adalah **salah**. Pengukuran
`getBoundingClientRect` menunjukkan inset tombol dari tepi kanan kartu 17px
pada kelima baris (select 152px = spacer 152px). Yang tampak seperti zigzag
hanya efek grid 2 kolom dengan lima kartu, bukan defect. Tidak ada perubahan
source untuk temuan itu.

### RVR yang masih terbuka

Tidak berubah dari sesi sebelumnya: modal Pratinjau Massal + batas 20 paket
(butuh paket NUMBERED; data aktif masih 0 bernomor), eksekusi penomoran
triwulan, eksekusi sinkronisasi, output biner PDF/Excel, kontras seluruh 29
profil tema (hanya 2 profil diuji), dan dokumen cetak folio di layar kecil.

Guard terkait:

```text
tests/Feature/GuiAudit09To13SourceReadinessTest.php
```

Guard tersebut membuktikan kontrak source responsive/table/icon tertentu tetap ada. Guard **tidak** membuktikan layout benar-benar tampil tanpa overlap, clipping, overflow viewport, atau masalah interaksi pada browser nyata.

## 1. Aturan evidence runtime

Setiap sesi QA minimal mencatat:

```text
Commit SHA:
Browser + versi:
Viewport:
Role user:
Sekolah aktif:
Tahun anggaran:
Sumber dana:
Route:
Status: PASS / BLOCKED / RVR
Catatan:
Screenshot/reference bila ada:
```

Jangan memberi status PASS berdasarkan inspeksi Blade saja.

## 2. Matrix viewport desktop/laptop — GUI-AUDIT-12

Wajib diuji minimal:

| Viewport | Target |
| --- | --- |
| 1366 × 768 | laptop umum |
| 1440 × 900 | laptop/desktop medium |
| 1920 × 1080 | desktop lebar |

Pemeriksaan umum:

- header dan breadcrumb sticky tidak saling menutupi;
- page header/action tetap terbaca;
- form tidak terpotong;
- tombol utama/secondary/danger terlihat dan dapat diklik;
- modal muat dalam viewport dan dapat discroll bila tinggi;
- dropdown/menu tidak terpotong container;
- tabel lebar scroll horizontal hanya di container tabel, bukan seluruh halaman;
- tidak ada pagination/filter ganda;
- sticky action tidak menutup field terakhir;
- focus/hover/disabled state jelas;
- pada tab Laporan SPJ, gunakan pencarian dan filter periode, tandai beberapa baris, lalu gunakan Pratinjau Massal; pastikan PDF gabungan tampil dalam iframe/modal yang sama dengan pratinjau individu, toolbar menunjukkan jumlah halaman gabungan, urutan paket benar, modal dapat discroll, dan Cetak menghasilkan dokumen yang sama;
- set jumlah baris ke 25 atau lebih, pilih lebih dari 20 paket dan pastikan pesan batas muncul; satu pilihan harus menghasilkan satu paket;
- theme token tidak menghasilkan foreground/background berkontras buruk;
- icon canonical sejajar dengan label dan tidak menggeser row height berlebihan.

## 3. Matrix mobile/tablet — GUI-AUDIT-13

Minimum usability diuji pada:

| Viewport | Target |
| --- | --- |
| 375 × 812 | mobile portrait |
| 768 × 1024 | tablet portrait |
| 1024 × 768 | tablet landscape |

Pemeriksaan umum:

- tidak ada horizontal viewport overflow kecuali container tabel yang memang scrollable;
- daftar yang menyediakan desktop table + mobile card benar-benar berpindah pada breakpoint yang sesuai;
- action penting tetap dapat ditemukan tanpa hover;
- tab/navigation dapat dibaca atau discroll tanpa memotong label;
- modal tidak keluar viewport;
- form field, textarea, select, radio, dan checkbox dapat digunakan dengan sentuhan;
- label dan validation message tidak tertutup;
- text panjang/path/database identifier wrap atau scroll secara aman;
- tombol tidak bertumpuk atau terpotong;
- sticky header/action tidak menghabiskan terlalu banyak tinggi layar.

Mobile/tablet masih non-blocker untuk target release desktop/laptop saat ini, tetapi minimum usability tetap harus dicatat dan blocker serius tidak boleh diabaikan.

## 4. Route minimum yang harus diperiksa

### Core operator

```text
/                                     (dashboard; route name `dashboard`)
/transaksi atau route daftar transaksi aktif
/transaksi/{transactionId}
/spj?tab=paket
/spj/penomoran
/spj/penomoran/koreksi
/penganggaran-rkas (scope tahun + triwulan/semester, filter lanjutan, tabel rincian)
/penganggaran-rkas/saran
/penganggaran-rkas/simulasi
/penganggaran-rkas/perbandingan-revisi
/pajak
/rekonsiliasi
/spj/rekap-triwulan
/laporan-periode (+ /laporan-periode/{scope}/{report}/{cetak|excel|pdf})
/referensi
```

Khusus Paket SPJ:

- tab utama Persiapan/Paket/Laporan/Monitoring memakai icon canonical;
- toolbar Semua Paket / Sebelumnya / Setelahnya / Lihat Transaksi tetap usable;
- sub-tab Rincian / Isian Manual / Rincian Pajak / Penomoran tidak overflow;
- `NUMBERED` masih mengizinkan koreksi `item_description` melalui Detail Transaksi;
- `FINAL` tetap terkunci;
- Data Umum desktop tetap dua kolom sesuai kontrak;
- tabel kategori non-BARANG tetap compact dan hanya mempunyai satu pager.

### Master dan sinkronisasi

```text
Master Siswa index/form/show (/siswa)
Master Pegawai index/form/show (/pegawai)
Tinjau Identitas Pegawai (/pegawai/tinjau-identitas) — read-only
Data Hasil Sinkron (/data-sinkron)
Integrasi Dapodik (/pengaturan/dapodik)
Sinkronisasi ARKAS (`Pengaturan → Sinkronisasi Data ARKAS`) / Audit Mirror RKAS
```

Verifikasi khusus:

- table/card responsive fallback Siswa dan Pegawai;
- masking identitas tetap benar;
- filter/per-page tidak pecah pada width kecil;
- tabel Data Hasil Sinkron tidak mendorong seluruh viewport secara horizontal;
- `/pegawai/tinjau-identitas` menampilkan grup nama ternormalisasi sama tanpa
  menulis ke `employees`, dan tetap read-only di viewport sempit.

### Admin/settings

```text
Laporan Audit
Template Dokumen
Format Penomoran
Database Manager
Reset Database Sekolah
Backup & Pemulihan
Pengguna & Hak Akses
Impersonation
```

Reset Database wajib diperiksa untuk:

- danger zone tetap jelas;
- warning icon canonical tampil;
- konfirmasi reset tidak terpotong;
- tombol Reset / Batal / Backup & Pemulihan tetap terlihat;
- semantic danger tidak terganggu oleh theme.

## 5. Kriteria penutupan GUI-AUDIT-12

GUI-AUDIT-12 hanya boleh menjadi **PASS** bila:

1. seluruh viewport desktop minimum sudah diuji pada runtime lokal/installed build;
2. route core operator tidak mempunyai blocker visual/interaksi;
3. issue yang ditemukan sudah diperbaiki atau secara eksplisit dicatat sebagai non-blocking dengan alasan;
4. evidence commit/browser/viewport dicatat;
5. `docs/CURRENT_PROGRESS.md` diperbarui berdasarkan evidence nyata.

## 6. Kriteria penutupan GUI-AUDIT-13

GUI-AUDIT-13 hanya boleh menjadi **PASS** bila:

1. tiga viewport mobile/tablet minimum sudah diuji;
2. core flow dapat dinavigasi tanpa clipping/overflow fatal;
3. input dan action penting dapat digunakan tanpa hover-only interaction;
4. blocker responsive telah diperbaiki atau dicatat dengan keputusan release yang eksplisit;
5. evidence runtime dicatat.

## 7. Hubungan dengan CI

CI menjaga kontrak source, build, Blade compile, dan regression. CI tidak dapat membuktikan persepsi visual atau usability browser pada aplikasi lokal. Karena itu status canonical adalah:

```text
source-readiness PASS + runtime belum diuji = RVR
source-readiness PASS + runtime evidence lengkap tanpa blocker = PASS
```

Jangan mengubah aturan ini hanya untuk menutup checklist lebih cepat.
