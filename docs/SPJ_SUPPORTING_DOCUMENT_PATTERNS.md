# SPJ BOSP — Pola Lampiran Bukti Dukung Operasional (Referensi Poster 10 Pola)

Terakhir diperbarui: **2026-09-23**

Status: **ACTIVE REFERENCE / RVR** — dokumen referensi operator, bukan klaim implementasi.

Dokumen ini mentranskripsikan poster “LAMPIRAN BUKTI DUKUNG SPJ — LENGKAP – SAH – AKURAT – DAPAT DIPERTANGGUNGJAWABKAN / Kunci Lulus Dari Pemeriksaan” menjadi referensi checklist eksternal. Tidak mengubah kontrak bisnis di `SPJ_DESIGN_DECISIONS.md`, lifecycle/numbering, sync, maupun tenant boundary.

Aturan canonical yang tetap berlaku:

```text
Kategori SPJ: BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, SPPD, HONOR_PEGAWAI
SiPlah adalah channel, bukan kategori.
Boundary tenant: School + Fiscal Year + Fund Source
Dokumen yang di-generate aplikasi: SpjDocumentTypeRegistry + SpjDocumentRequirementService
Dokumen eksternal di bawah ini: checklist manual operator (sebagian tidak dapat divalidasi otomatis)
```

---

## 1. KKG

1. Kwitansi
2. Undangan KKG
3. SPT (di TT Kepsek) dan SPPD (di TT Tujuan)
4. Daftar Terima Uang (kalau lebih dari 1 orang)
5. Daftar Hadir
6. Notulen
7. Foto Kegiatan KKG

## 2. Rapat K3S

1. Kwitansi
2. Undangan
3. SPT (di TT Kepsek) dan SPPD (di TT Tujuan)
4. Daftar Hadir
5. Notulen
6. Foto Kegiatan

## 3. Honor GTT/PTT

1. Kwitansi
2. SK GTT/PTT
3. SK Pembagian Tugas
4. Daftar/Tanda Terima (lebih dari 1 orang)

## 4. Perjalanan Dinas

1. Kwitansi
2. SPT (di TT Kepsek) dan SPPD (di TT Tujuan)
3. Daftar/Tanda Terima (bila lebih dari 1 orang)
4. Laporan Hasil Perjalanan
5. Foto Kegiatan

## 5. Belanja SiPlah

1. Kwitansi
2. Bukti Transfer
3. Surat Pesanan
4. Faktur
5. Invoice
6. Berita Acara Serah Terima Barang
7. Bukti Setor Pajak
8. Foto Barang

## 6. Makan Minum / Rapat

1. Kwitansi
2. Undangan Rapat
3. Nota Pesanan
4. Daftar Hadir
5. Notulen
6. Dokumentasi/Foto Kegiatan
7. Bukti Setor Pajak PPh 23 2% dan Pajak Restoran 10%

## 7. Honor Narasumber

1. Kwitansi
2. Undangan
3. SK Penetapan
4. Daftar Terima (kalau lebih dari 1 orang)
5. Nama Narasumber

## 8. Pengadaan / Penggandaan

1. Kwitansi
2. Nota Pesanan
3. Nota Pembayaran
4. Berita Acara Serah Terima Barang dari penyedia
5. Foto Barang
6. Bukti Setor Pajak PPN jika belanja di atas 2 Jt

## 9. Ekstrakurikuler

1. Kwitansi
2. SK Pembina
3. Jadwal
4. Daftar Hadir Pembina
5. Foto Kegiatan

## 10. Jasa Tukang dll

1. Kwitansi
2. Daftar Terima Uang (lebih dari 1 orang)
3. Daftar Hadir
4. Foto Kegiatan

---

## Catatan penting dari poster

- Perbaikan: dokumentasikan foto **sebelum** dan **sesudah** perbaikan.
- Penggunaan materai Rp10.000 untuk belanja di atas Rp5 Jt.
- Pastikan kecocokan antara **nomor**, **nilai uang di BKU**, dan **kwitansi**.
- Prinsip: transparan, akuntabel, tertib administrasi, tuntas pertanggungjawaban.

## Pemetaan ke kategori canonical (acuan implementasi, bukan kategori baru)

| Pola poster | Kategori canonical | Catatan |
|---|---|---|
| 1 KKG, 2 Rapat K3S, 6 Makan Minum/Rapat | `KONSUMSI` (+ `SPPD` bila ada SPT/SPPD) | Daftar hadir/notulen/foto = eksternal manual |
| 3 Honor GTT/PTT, 7 Honor Narasumber | `HONOR_PEGAWAI` | SK + daftar terima = eksternal manual |
| 4 Perjalanan Dinas | `SPPD` | SPT/SPPD + laporan hasil = eksternal manual |
| 5 Belanja SiPlah, 8 Pengadaan/Penggandaan | `BARANG` | Channel `SIPLAH` vs non-SIPLAH mengikuti `SpjProcurementPolicyService`; bukan kategori baru |
| 9 Ekstrakurikuler | `JASA_LAINNYA` / `HONOR_PEGAWAI` | Tergantung pembina dihonor atau jasa kegiatan |
| 10 Jasa Tukang dll | `PEMELIHARAAN` (+ `JASA_LAINNYA` bila jasa umum) | RAB/SPK/daftar pekerja tetap dari service canonical |

## Status implementasi

- **RVR**: pola di atas belum diimplementasikan sebagai checklist/validasi otomatis di aplikasi. `SpjDocumentRequirementService` saat ini hanya mencakup dokumen yang di-generate aplikasi (A2, rincian, pesanan, BAP/BAST, RAB/SPK, travel, honor, peserta).
- Aturan ambang (materai >Rp5 Jt, PPN >Rp2 Jt, PPh23 2% + pajak restoran 10%) belum menjadi validasi otomatis.
- Usulan tanpa mengubah lifecycle: tambah layer checklist `SOURCE_EXTERNAL` centang manual + pengingat ambang otomatis. Perubahan apa pun mengikuti kontrak numbering/sync/tenant yang aktif.
