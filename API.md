# API / Route Reference

Diverifikasi ulang terhadap output `php artisan route:list --except-vendor --json` pada **2026-10-05** (`raw-rkas`, branch `main`). Aplikasi tetap memakai session-authenticated web routes dan **tidak memiliki `routes/api.php`**.

Regenerasi:

```bash
php artisan route:list --except-vendor
php artisan route:list --path=<prefix> -v --no-interaction
```

Isi tabel di bawah dihasilkan dari `route:list` aktual, bukan dari catatan manual. Angka total route dapat berubah setiap kali route ditambah atau dihapus; jangan mengutip angka ini sebagai kontrak. Route yang tidak tercantum berarti route tersebut tidak ada — bila fitur menambahkan route, dokumen ini wajib diperbarui sesuai `docs/DOCUMENTATION_MAINTENANCE.md` §3.

## Middleware

Alias terdaftar di `bootstrap/app.php`:

| Alias | Class | Peran |
|---|---|---|
| `auth` | `Illuminate\Auth\Middleware\Authenticate` | Wajib login |
| `guest` | `Illuminate\Auth\Middleware\RedirectIfAuthenticated` | Sudah login diarahkan ke dashboard |
| `active-school` | `App\Http\Middleware\EnsureActiveSchool` | Sekolah aktif harus dipilih |
| `active-year` | `App\Http\Middleware\EnsureActiveFiscalYear` | Tahun anggaran aktif harus dipilih |
| `spj-active-context` | `App\Http\Middleware\EnsureSpjActiveContext` | Konteks sekolah + tahun + sumber dana aktif |
| `operator-or-administrator` | `App\Http\Middleware\EnsureOperatorOrAdministrator` | OPERATOR atau ADMIN |
| `administrator` | `App\Http\Middleware\EnsureAdministrator` | ADMIN saja |
| `throttle:N,M` | `Illuminate\Routing\Middleware\ThrottleRequests` | Rate limit N request per M menit |

Peran aplikasi: **VIEWER** read-only, **OPERATOR** workflow operasional, **ADMINISTRATOR** lifecycle/maintenance/sensitive action.

Grup `web` dan `MeasureRequestPerformance` sengaja tidak ditampilkan pada tabel karena berlaku ke seluruh route. Route yang tidak memakai `auth` maupun `guest` ditandai `(tanpa middleware)` — perhatikan `/setup` (bootstrap awal).

> Route Generic ARKAS Importer (`arkas.importer*`) dan `arkas.import-monitor` dihapus pada 2026-10-04 dan tidak lagi terdaftar. Audit mirror ARKAS berada di `/penganggaran-rkas/audit-mirror`.

## Route aplikasi

### Auth, Publik, dan Asisten (8)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/asisten` | asisten.index | auth |
| GET | `/asisten/status/{token}` | asisten.status | auth |
| POST | `/asisten/tanya` | asisten.ask | auth, throttle:5,1 |
| POST | `/keluar` | logout | auth |
| GET | `/masuk` | login | guest |
| POST | `/masuk` | login.store | guest, throttle:5,1 |
| GET | `/setup` | setup | (tanpa middleware) |
| POST | `/setup` | setup.store | throttle:6,1 |

### Dashboard, Referensi, dan Laporan (7)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/laporan-audit` | audit-reports.index | auth, active-school, active-year, spj-active-context |
| GET | `/laporan-audit/unduh/{format}` | audit-reports.export | auth, active-school, active-year, spj-active-context |
| GET | `/laporan-periode` | spj.periodic-reports.index | auth, active-school, active-year, spj-active-context |
| GET | `/laporan-periode/{scope}/{report}/cetak` | spj.periodic-reports.print | auth, active-school, active-year, spj-active-context |
| GET | `/laporan-periode/{scope}/{report}/excel` | spj.periodic-reports.excel | auth, active-school, active-year, spj-active-context |
| GET | `/laporan-periode/{scope}/{report}/pdf` | spj.periodic-reports.pdf | auth, active-school, active-year, spj-active-context |
| GET | `/referensi` | references.index | auth, active-school, active-year, spj-active-context |

### Transaksi (8)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/transaksi` | transactions.index | auth, active-school, active-year, spj-active-context |
| GET | `/transaksi/rekomendasi-vendor` | transactions.vendor-recommendation | auth, active-school, active-year, spj-active-context |
| GET | `/transaksi/{transactionId}` | transactions.show | auth, active-school, active-year, spj-active-context |
| GET | `/transaksi/{transactionId}/pemeliharaan/transaksi-terkait` | transactions.maintenance-links.show | auth, active-school, active-year, spj-active-context |
| PUT | `/transaksi/{transactionId}/pemeliharaan/transaksi-terkait` | transactions.maintenance-links.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/transaksi/{transactionId}/rekonsiliasi-sumber/selesaikan` | transactions.source-reconciliation.resolve | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/transaksi/{transactionId}/siapkan-spj` | transactions.prepare-spj | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/transaksi/{transactionId}/uraian-spj` | transactions.spj-descriptions.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |

### SPJ (40)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/spj` | spj.index | auth, active-school, active-year, spj-active-context |
| POST | `/spj/bulk-final` | spj.bulk-finalize | auth, active-school, active-year, spj-active-context, operator-or-administrator, administrator |
| POST | `/spj/dokumen/{documentId}/batal` | spj.documents.cancel | auth, active-school, active-year, spj-active-context, operator-or-administrator, administrator |
| POST | `/spj/dokumen/{documentId}/final` | spj.documents.finalize | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/dokumen/{documentId}/ganti` | spj.documents.replace | auth, active-school, active-year, spj-active-context, operator-or-administrator, administrator |
| GET | `/spj/laporan/honor/pilih` | spj.honor-payments.select | auth, active-school, active-year, spj-active-context |
| GET|POST|HEAD | `/spj/laporan/honor/susun` | spj.honor-payments.compose | auth, active-school, active-year, spj-active-context |
| GET | `/spj/laporan/honor/{format}` | spj.honor-payments.export | auth, active-school, active-year, spj-active-context |
| GET | `/spj/laporan/jasa/pilih` | spj.service-recipients.select | auth, active-school, active-year, spj-active-context |
| GET|POST|HEAD | `/spj/laporan/jasa/susun` | spj.service-recipients.compose | auth, active-school, active-year, spj-active-context |
| GET | `/spj/laporan/jasa/{format}` | spj.service-recipients.export | auth, active-school, active-year, spj-active-context |
| POST | `/spj/laporan/pratinjau-bulk` | spj.preview-packages | auth, active-school, active-year, spj-active-context |
| DELETE | `/spj/paket/catatan/{noteId}` | spj.operator-notes.destroy | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/spj/paket/{packageId}` | spj.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/paket/{packageId}/catatan` | spj.operator-notes.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/spj/paket/{packageId}/checklist` | spj.checklist | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/checklist-eksternal` | spj.external-checklist.toggle | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/paket/{packageId}/dokumen/{documentType}/nomor` | spj.documents.assign-number | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/paket/{packageId}/nomor` | spj.assign-number | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/spj/paket/{packageId}/pratinjau` | spj.preview-package | auth, active-school, active-year, spj-active-context |
| GET | `/spj/paket/{packageId}/pratinjau-excel` | spj.preview-package-excel | auth, active-school, active-year, spj-active-context |
| GET | `/spj/paket/{packageId}/pratinjau-pdf` | spj.preview-package-pdf | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/siap` | spj.ready | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/spj/paket/{packageId}/template/{templateId}/pratinjau` | spj.preview-template | auth, active-school, active-year, spj-active-context |
| GET | `/spj/paket/{packageId}/template/{templateId}/pratinjau-pdf` | spj.preview-template-pdf | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/template/{templateId}/unduh` | spj.download-template | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/template/{templateId}/unduh-pdf` | spj.download-template-pdf | auth, active-school, active-year, spj-active-context |
| GET|POST|HEAD | `/spj/paket/{packageId}/unduh` | spj.download | auth, active-school, active-year, spj-active-context |
| GET|POST|HEAD | `/spj/paket/{packageId}/unduh-excel` | spj.download-package-excel | auth, active-school, active-year, spj-active-context |
| GET | `/spj/penomoran` | spj.numbering-workflow | auth, active-school, active-year, spj-active-context |
| POST | `/spj/penomoran-triwulan` | spj.quarter-numbering | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/penomoran-triwulan/batal` | spj.quarter-numbering.cancel | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/spj/penomoran/koreksi` | spj.numbering-correction | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/penomoran/rollback` | spj.numbering.rollback | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/spj/rekap-triwulan` | spj.quarter-recap | auth, active-school, active-year, spj-active-context |
| POST | `/spj/transaksi/{transactionId}/pembayaran` | spj.payments.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/transaksi/{transactionId}/penerimaan` | spj.receipts.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| DELETE | `/spj/transaksi/{transactionId}/penerimaan/{receiptId}` | spj.receipts.destroy | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/triwulan/{periodId}/buka` | spj.quarter-reopen | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/tutup-triwulan` | spj.quarter-close | auth, active-school, active-year, spj-active-context, administrator |

### Pajak & Rekonsiliasi (3)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/pajak` | taxes.index | auth, active-school, active-year, spj-active-context |
| GET | `/rekonsiliasi` | reconciliation.index | auth, active-school, active-year, spj-active-context |
| POST | `/rekonsiliasi/tinjau-massal` | reconciliation.bulk-review | auth, active-school, active-year, spj-active-context, operator-or-administrator |

### Penganggaran RKAS (10)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/penganggaran-rkas` | rkas-budget.index | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/audit-mirror` | rkas-budget.audit | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/laporan/paket/unduh` | rkas-reports.package | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/laporan/{scope}/excel` | rkas-reports.excel | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/laporan/{scope}/pdf` | rkas-reports.pdf | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/laporan/{scope}/preview` | rkas-reports.preview | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/perbandingan-revisi` | rkas-budget.revisions.compare | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/saran` | rkas-planning.index | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/saran/unduh/{modul}` | rkas-planning.export | auth, active-school, active-year, spj-active-context |
| GET | `/penganggaran-rkas/simulasi` | rkas-budget.simulate | auth, active-school, active-year, spj-active-context |

### Siswa & Pegawai (19)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/pegawai` | employees.index | auth, active-school, active-year, spj-active-context |
| POST | `/pegawai` | employees.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| DELETE | `/pegawai/sk/{certificateId}` | employees.certificates.destroy | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/pegawai/sk/{certificateId}` | employees.certificates.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/pegawai/sk/{certificateId}/unduh` | employees.certificates.download | auth, active-school, active-year, spj-active-context |
| GET | `/pegawai/tambah/baru` | employees.create | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/pegawai/tinjau-identitas` | employees.identity-review | auth, active-school, active-year, spj-active-context |
| DELETE | `/pegawai/{employeeId}` | employees.destroy | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/pegawai/{employeeId}` | employees.show | auth, active-school, active-year, spj-active-context |
| PUT | `/pegawai/{employeeId}` | employees.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/pegawai/{employeeId}/sk` | employees.certificates.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/pegawai/{employeeId}/ubah` | employees.edit | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/siswa` | students.index | auth, active-school, active-year, spj-active-context |
| POST | `/siswa` | students.store | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/siswa/tambah/baru` | students.create | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| DELETE | `/siswa/{studentId}` | students.destroy | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/siswa/{studentId}` | students.show | auth, active-school, active-year, spj-active-context |
| PUT | `/siswa/{studentId}` | students.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/siswa/{studentId}/ubah` | students.edit | auth, active-school, active-year, spj-active-context, operator-or-administrator |

### Sinkronisasi Data (3)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/data-sinkron` | synced-data.index | auth, active-school, active-year, spj-active-context |
| GET | `/data-sinkron/{type}` | synced-data.show | auth, active-school, active-year, spj-active-context |
| POST | `/sinkronisasi/arkas` | arkas.sync | auth, active-school, active-year, spj-active-context, operator-or-administrator, throttle:3,1 |

### Pengaturan, Sekolah, dan Konteks (51)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/pengaturan/arkas` | arkas.settings | auth, administrator |
| POST | `/pengaturan/arkas` | arkas.settings.store | auth, administrator |
| GET | `/pengaturan/arkas/mirror` | arkas.mirror | auth, administrator, active-school |
| POST | `/pengaturan/arkas/mirror/health-repair` | arkas.mirror.health-repair | auth, administrator, active-school, throttle:3,1 |
| GET | `/pengaturan/arkas/mirror/status` | arkas.mirror.status | auth, administrator, active-school |
| POST | `/pengaturan/arkas/mirror/sync-refs` | arkas.mirror.sync-refs | auth, administrator, active-school, throttle:3,1 |
| POST | `/pengaturan/arkas/mirror/sync-school` | arkas.mirror.sync-school | auth, administrator, active-school, throttle:3,1 |
| GET | `/pengaturan/backup` | school-backups.index | auth, administrator |
| POST | `/pengaturan/backup` | school-backups.store | auth, administrator |
| POST | `/pengaturan/backup/{backupId}/pulihkan` | school-backups.restore | auth, administrator |
| GET | `/pengaturan/dapodik` | dapodik.index | auth, active-school, active-year, spj-active-context, administrator |
| PUT | `/pengaturan/dapodik` | dapodik.store | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/pengaturan/dapodik/sinkron` | dapodik.sync | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/pengaturan/dapodik/tes` | dapodik.test | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/pengaturan/database-aktif` | database-manager.index | auth, administrator |
| GET | `/pengaturan/database-aktif/tabel/{table}` | database-manager.table-summary | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/activate` | database-manager.activate | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/checkpoint` | database-manager.checkpoint | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/integrity` | database-manager.integrity | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/migrate` | database-manager.migrate | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/provision` | database-manager.provision | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/reset` | database-manager.reset | auth, administrator |
| POST | `/pengaturan/database-aktif/{schoolId}/vacuum` | database-manager.vacuum | auth, administrator |
| GET | `/pengaturan/database-reset` | database-manager.reset-form | auth, administrator |
| PUT | `/pengaturan/dokumen/penyimpanan` | documents.storage.update | auth, operator-or-administrator |
| GET | `/pengaturan/format-penomoran` | document-number-formats.index | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/pengaturan/format-penomoran/{documentType}` | document-number-formats.update | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET | `/pengaturan/impersonate` | impersonation.index | auth, administrator |
| POST | `/pengaturan/impersonate/selesai` | impersonation.stop | auth |
| POST | `/pengaturan/impersonate/{userId}` | impersonation.start | auth, administrator |
| GET | `/pengaturan/sekolah` | schools.settings | auth, operator-or-administrator |
| POST | `/pengaturan/sekolah` | schools.store | auth, administrator |
| GET | `/pengaturan/sekolah/kop-surat` | schools.letterhead | auth, operator-or-administrator |
| PUT | `/pengaturan/sekolah/profil` | schools.profile.update | auth, operator-or-administrator |
| POST | `/pengaturan/tahun` | years.store | auth, administrator |
| GET | `/pengaturan/template-dokumen` | document-templates.index | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/pengaturan/template-dokumen` | document-templates.store | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/pengaturan/template-dokumen/contoh/{format}` | document-templates.sample | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/pengaturan/template-dokumen/master/unduh` | document-templates.master.download | auth, active-school, active-year, spj-active-context, administrator |
| DELETE | `/pengaturan/template-dokumen/{templateId}` | document-templates.destroy | auth, active-school, active-year, spj-active-context, administrator |
| PUT | `/pengaturan/template-dokumen/{templateId}/pemetaan` | document-templates.mapping.update | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/pengaturan/template-dokumen/{templateId}/unduh` | document-templates.download | auth, active-school, active-year, spj-active-context, administrator |
| GET | `/pengaturan/user` | users.index | auth, administrator |
| POST | `/pengaturan/user` | users.store | auth, administrator |
| DELETE | `/pengaturan/user/{userId}` | users.destroy | auth, administrator |
| PUT | `/pengaturan/user/{userId}` | users.update | auth, administrator |
| GET | `/pilih-sekolah` | schools.select | auth |
| POST | `/pilih-sekolah` | schools.activate | auth |
| GET | `/pilih-tahun` | years.select | auth, active-school |
| POST | `/pilih-tahun` | years.activate | auth, active-school |
| POST | `/pilih-tahun/sinkronisasi` | years.synchronize | auth, active-school, operator-or-administrator, throttle:3,1 |

### Dashboard (1)

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/` | dashboard | auth, active-school, active-year, spj-active-context |

## Route yang sengaja tidak dicantumkan

Empat route infra/framework terdaftar pada `route:list` tetapi tidak relevan
untuk referensi API aplikasi:

| URI | Name | Alasan |
|---|---|---|
| `/up` | (generated health check) | Health check Laravel |
| `/_boost/browser-logs` | `boost.browser-logs` | Endpoint Laravel Boost, Closure |
| `/storage/{path}` | `storage.local`, `storage.local.upload` | Penyajian file lokal, Closure |

Jumlah route aplikasi yang didokumentasikan: **150**.
