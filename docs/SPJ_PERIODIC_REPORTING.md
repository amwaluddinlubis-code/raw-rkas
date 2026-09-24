# SPJ Periodic Reporting

Terakhir diverifikasi terhadap source: **2026-09-25**

Status: **REGISTRY + DATA ENGINE + INTERNAL PRINT/PDF IMPLEMENTED / VISUAL RUNTIME RVR**

Dokumen ini adalah kontrak teknis modul **Laporan Pertanggungjawaban Periodik** pada workspace SPJ. Modul dipisahkan dari workbook/template dokumen agar daftar laporan, filter periode, dan boundary data dapat stabil walaupun template resmi masih diperbaiki.

## Prinsip

1. Semua sumber data wajib dibatasi oleh `ActiveSpjContext`: sekolah aktif, tahun anggaran aktif, dan sumber dana aktif.
2. Penentuan periode menggunakan `transaction_date` dari transaksi yang sudah berada pada konteks aktif.
3. Registry daftar laporan berada di `App\Services\SpjPeriodicReportRegistry` dan menjadi satu-satunya sumber daftar paket laporan pada UI.
4. Query/ringkasan sumber data berada di `App\UseCases\Spj\SpjPeriodicReportUseCase`.
5. Livewire `SpjPeriodicReportCenter` hanya mengelola state UI (jenis periode dan nomor periode), lalu mendelegasikan perhitungan ke use case.
6. Template/formula resmi tetap tidak didefinisikan di registry. Namun lapisan presentasi internal untuk browser print/PDF sudah diimplementasikan melalui `SpjPeriodicReportPrintService`, `PeriodicReportController`, dan view `periodic-reports/*` tanpa mengubah kontrak registry.

## Paket laporan

### Bulanan

- SPTJM
- Buku Kas Umum
- Buku Pembantu Kas
- Buku Pembantu Bank
- Buku Pembantu Pajak
- Register Penutupan Kas (K7B)
- Berita Acara Pemeriksaan Kas (K7C)
- BOS K7A
- Rekap Belanja Modal dan Belanja Barang / Jasa

Periode valid: bulan `1..12`.

### Tahap & Triwulan

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- SPB
- SP2B
- Lampiran SP2B
- SP2T
- Berita Acara Rekonsiliasi
- Lampiran Berita Acara Rekonsiliasi

Periode valid: triwulan `1..4`.

### Semester

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- SPB
- SP2B
- Lampiran SP2B
- SP2T
- Berita Acara Rekonsiliasi
- Lampiran Berita Acara Rekonsiliasi
- Rekap Belanja Barang Milik Daerah (Aset)

Periode valid: semester `1..2`.

### Tahunan

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- Rekap Barang Milik Daerah (Aset)
- BOS K8
- Rekap Belanja Dana BOS
- Form 1C
- Rekapitulasi Pengeluaran Dana BOS

Periode tahunan selalu menggunakan seluruh tahun anggaran aktif dan tidak membutuhkan nomor periode tambahan.

## Data engine

Untuk periode yang sudah valid, modul menghitung ringkasan sumber data berikut langsung dari transaksi pada konteks aktif:

- jumlah transaksi;
- nilai bruto;
- total pajak;
- nilai dibayarkan/neto;
- PPN;
- PPh 21;
- PPh 22;
- PPh 23;
- PPh 4(2);
- SSPD/Pajak Daerah.

Ringkasan ini adalah **lapisan sumber data**, bukan pengganti rumus atau bentuk resmi masing-masing laporan. Misalnya aturan kolom dan saldo Buku Kas Umum, struktur SP2B, atau klasifikasi BMD harus tetap mengikuti kontrak template/formula laporan yang disepakati kemudian.

## Integrasi UI

`resources/views/livewire/spj-report-filter.blade.php` menanam `SpjPeriodicReportCenter` di atas riwayat paket SPJ. Dengan demikian halaman `/spj?tab=laporan` mempunyai dua fungsi yang terpisah:

1. **Paket laporan periodik** — memilih Bulanan/Triwulan/Semester/Tahunan dan melihat kesiapan sumber data.
2. **Riwayat paket SPJ** — fungsi lama untuk memfilter dan mengekspor paket SPJ per transaksi.

Keduanya tidak saling menimpa query atau URL state. State paket laporan menggunakan `paket_laporan` dan `periode_laporan`, sedangkan riwayat paket tetap menggunakan `mode` dan `periode`.

## Implementasi output saat ini

Source aktif sudah menyediakan:

- `GET /laporan-periode` sebagai pusat laporan;
- `GET /laporan-periode/{scope}/{report}/cetak` untuk browser print;
- `GET /laporan-periode/{scope}/{report}/pdf` untuk PDF;
- `SpjPeriodicReportPrintService` untuk presentasi/row/column data;
- regression `SpjPeriodicReportPrintableTest` dan registry/module UI tests.

Implementasi internal ini **bukan klaim bahwa seluruh formulir resmi sudah mempunyai visual fidelity final**. Template/formula resmi, print fidelity, dan pemeriksaan browser/PDF aktual tetap mengikuti status RVR di `CURRENT_PROGRESS.md`.

## Tahap berikutnya

1. tutup visual/runtime QA browser + PDF untuk laporan yang sudah mempunyai presenter internal;
2. pasang formula/layout resmi per report key tanpa menduplikasi logika periode/tenant;
3. pertahankan registry + use case sebagai source of truth daftar laporan dan scope periode;
4. tambahkan binding template resmi hanya bila memang dibutuhkan oleh format pemerintah/sekolah.