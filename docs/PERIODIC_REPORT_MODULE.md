# Modul Laporan Periode

Dokumen ini adalah panduan fitur laporan periode (`/laporan-periode`): generator
internal APP-SPJ untuk cetak browser dan PDF, terpisah dari template Paket SPJ.

## Buku Pembantu Pajak (dimodelkan dari BKP-PAJAK ARKAS)

Presentasi `tax` (`buku_pembantu_pajak`, scope bulanan) mengikuti contoh resmi
`BKP-PAJAK` ARKAS dengan adaptasi data operator:

- **Header ala BKU**: blok identitas sekolah (NPSN, nama, alamat, desa,
  kecamatan, kab/kota, provinsi) + blok meta (sumber dana, tahun, periode,
  tanggal periode, jumlah transaksi), judul `BUKU PEMBANTU PAJAK` dengan
  subjudul `BKU - PAJAK`, serta blok tanda tangan `Menyetujui` Kepala Sekolah
  dan Bendahara. Tidak ada paragraf penutup saldo seperti BKU.
- **Uraian dari operator**: kolom Uraian memakai `payment_description` paket
  operator, fallback ke uraian sumber ARKAS/BKU bila kosong.
- **Kolom Siplah**: `Ya`/`Tidak` dari flag mirror `is_siplah` per transaksi.
- Nilai PPN/PPh/SSPD mengikuti agregat mirror `sourceValue()` yang sama dengan
  halaman `/pajak` (termasuk aturan dedup bayangan backfill), sehingga angka
  laporan selalu konsisten dengan layar.

Implementasi: `SpjPeriodicReportPrintService::taxColumns/taxRows` +
`periodic-reports/partials/document.blade.php` (cabang `tax`).
Status visual/runtime: RVR sampai QA browser/PDF aktual dijalankan.
