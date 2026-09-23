# SPJ BOSP — Pola Lampiran Bukti Dukung Operasional (Referensi Poster 10 Pola)

Terakhir diperbarui: **2026-09-23**

Status: **ACTIVE REFERENCE / FASE 1 IMPLEMENTED** — checklist manual tersedia di
halaman checklist paket; tetap tidak memblokir penomoran.

## Status implementasi (fase 1 — 2026-09-23)

- Halaman `spj.checklist` menampilkan kartu “Bukti Dukung Eksternal” per pola
  (pilihan pola via `?pola=`, default saran dari kategori + channel SiPlah).
- Centang manual per item eksternal, tersimpan per paket
  (`spj_external_checklist_ticks`, migrasi school `2026_09_23_000001`).
- Guard: hanya OPERATOR/ADMIN, hanya paket DRAFT/READY, tenant
  `School + Fiscal Year + Fund Source`, setiap toggle tercatat audit
  (`SPJ_PACKAGE / CHECKLIST_EKSTERNAL`). Tidak menyentuh validator
  penomoran, lifecycle, sync, maupun registry.
- Item GENERATED (Kwitansi A2) hanya informatif mengikuti status A2.
- RVR / belum: aturan ambang otomatis (materai >Rp5 Jt, PPN >Rp2 Jt,
  PPh23 2% + pajak restoran 10%), cetak checklist pendamping.

## Pemetaan ke kategori canonical (acuan implementasi, bukan kategori baru)

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

## Belum dikerjakan (fase berikutnya)

- Aturan ambang otomatis: materai >Rp5 Jt, PPN >Rp2 Jt, PPh23 2% + pajak
  restoran 10% belum menjadi validasi otomatis.
- Cetak checklist pendamping (`SPJ_CHECKLIST`) belum memuat pola eksternal.
- Perubahan apa pun mengikuti kontrak numbering/sync/tenant yang aktif.
