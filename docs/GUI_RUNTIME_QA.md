# GUI Runtime QA — Desktop, Laptop, Mobile, dan Tablet

Terakhir diperbarui: **2026-10-05**

Dokumen ini adalah checklist runtime untuk menutup **GUI-AUDIT-12** dan **GUI-AUDIT-13**. Source-level regression dan CI tidak boleh dipakai sebagai pengganti verifikasi visual/runtime di browser.

## Status saat ini

```text
GUI-AUDIT-12 source readiness : PASS pada gate run #46 / e4ba5cf;
                                HEAD sekarang sudah beberapa commit
                                setelah gate itu sehingga perlu
                                focused re-verification
GUI-AUDIT-12 browser runtime  : FUNCTIONAL PASS (focused, 2026-10-05,
                                Chrome 154, SDN 318/2026/BOS Reguler) /
                                RVR tersisa untuk: modal Pratinjau Massal
                                + batas 20 paket (butuh paket NUMBERED,
                                data aktif 0 bernomor), eksekusi penomoran
                                triwulan, eksekusi sinkronisasi, output
                                biner PDF/Excel, dan kontras tema gelap
GUI-AUDIT-13 source readiness : PASS pada gate run #46 / e4ba5cf (sama)
GUI-AUDIT-13 mobile/tablet     : FUNCTIONAL PASS (focused, viewport
                                375/768/1024, 2 temuan diperbaiki) /
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
