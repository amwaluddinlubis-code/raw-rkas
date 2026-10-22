# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-10-21** (scope semester Laporan Periode diperluas — `format_k2` dan `rpd_bos` kini tersedia di semester: K-2 merender dua kolom `Semester I`/`Semester II` dengan Semester II kumulatif, RPD memakai kolom Triwulan I–II / III–IV alih-alih kolom bulan; bug regresi kolom RPD semester ditemukan lewat render data nyata dan diperbaiki - variabel perulangan pecahan periode di `rpdData` menimpa parameter scope/period (`$period`) sehingga baris berkoordinat periode jatuh ke kolom bulan, kini `$periodRow`, dan `$totals` diinisialisasi sebelum cabang sumber dana agar laporan tetap terbentuk saat sumber dana belum terpilih; Excel `writeK2`/`writeRpd` dan partial cetak dibuat column-agnostic; registry menjadi 37 slot/18 key dengan label K7A "Rekapitulasi Pengeluaran Dana BOS (K7A)"; aturan A-1 dikonfirmasi pengguna — kolom Belanja Modal dari kode rekening (`5.2` modal, `5.2.02` Peralatan dan Mesin, `5.2.05`/`5.2` lain Aset Tetap Lainnya), jabatan penanda tangan "Bendahara / Pemegang Kas", dan sel nominal tanpa prefix `Rp ` (`1.000.000`, nol `-`); focused verification FormatK2ReportTest 9 test/105 assertions, RpdBosReportTest 14 test/136 assertions, registry+printable+moduleUi 13 test/103 assertions, regression gabungan 11 kelas 80 passed/776 assertions;aturan protected file dihapus — `dashboard.blade.php` dan `students/index.blade.php` kini mengikuti aturan umum; Jumlah/Vol/Satuan/Tarif RPD BOS kini mengikuti alokasi periode terpilih dari `arkas_mirror_rapbs_periode` dan baris berjumlah 0 tidak ditampilkan; laporan Rekapitulasi Pengeluaran Dana BOS (K7A) kini berorientasi A4 landscape agar grid 7 SNP x 12 sub program tidak terpotong dan label K7A diseragamkan di keempat scope (dikunci `RekapBudgetRealizationTest` 6 test/64 assertions dan `BpkDinasFormatTest` 3 test/33 assertions); blok K7A dan BPK dipisah menjadi partial `reports/report-rekap-bosp-1.blade.php` dan `reports/report-bpk-1.blade.php` (menggantikan `partials/bpk-dinas.blade.php`) dengan lebar kolom berupa array yang dapat dikustom lewat `config('spj.periodic_report_column_widths')` dan fallback default di partial; Format BOS K-2 kini kumulatif per triwulan — kolom TW setelah periode terpilih = 0 dan Jumlah = kumulatif, bagian II Belanja Lainnya dihapus dari cetak/Excel; lebar kolom cetak Laporan Periode menjadi array persentase via `<colgroup>` dengan focused verification 48 test / 485 assertions; placeholder workbook master SPJ v7 kini diselaraskan pada registry/resolver tanpa mengubah file XLSX sumber, validator paket pass; full CI dan browser/PDF runtime tetap RVR; kontrak `SPJ_COVER` kini mendaftarkan `NAMA_SEKOLAH`, `NPSN`, `NAMA_REKENING`, dan `PENERIMA_PENYEDIA` sebagai placeholder optional — template master v7 SD-5 (cover memuat kop, NPSN, nama rekening, dan penyedia) lolos validator 0 error/0 warning; focused verification SpjSupplementaryTemplateContractTest + DocumentTemplatePlaceholderInspectorTest 16 test/205 assertions dan SpjGeneratedDocumentRegressionTest 3 test/31 assertions).

> Repository canonical saat ini adalah `amwaluddinlubis-code/raw-rkas` dan menggunakan satu branch aktif: `main`.

Pembaruan **2026-10-23** (navbar Tahap 1 — Keluar lengket + tombol ciut): hasil audit navbar (kode + visual live) dieksekusi sebagian. (1) Sidebar `components/layouts/tailwind-app.blade.php` kini flex-column: zona brand+menu menggulir (`min-h-0 flex-1 overflow-y-auto`, padding lama dipertahankan), sedangkan form **Keluar menjadi kaki sidebar lengket** (`shrink-0`, garis atas `var(--app-sidebar-line)`) — selalu terlihat tanpa menggulir sidebar, termasuk saat grup SPJ & Laporan terbuka penuh di layar 1080p dan pada drawer mobile. (2) Tombol ciut/luaskan di topbar diberi `shrink-0` agar tidak menyusut menempel ke nama sekolah pada keadaan ciut. Temuan audit lain (grup SPJ & Laporan 9+4 item, label "Pajak Sinkronisasi", item aksi "Sinkron Semua ARKAS", duplikasi Keluar/Kelola Pengguna dengan dropdown profil) dicatat sebagai Tahap 2 — belum dieksekusi, menunggu keputusan operator. Evidence: `php artisan view:cache` bersih; `npm run build` sukses; `GuiAudit09To13SourceReadinessTest` 23 passed, `RkasBudgetUiTest` 6 passed, `ArkasFixedMirrorTest` 3 passed, `SpjMainTabsRenderingTest` 9 passed, `SpjConsumptionParticipantUiTest` 4 passed. Operator perlu `npm run build` setelah pull.

Pembaruan **2026-10-23** (pemecahan `spj/index` Tahap 2 — skrip pratinjau menjadi modul JS): `<script>` inline 108 baris di kaki `resources/views/spj/index.blade.php` (modal pratinjau template + hitungan pilihan massal maks. 20 + pratinjau massal) diekstrak **persis** ke modul baru `resources/js/spj-template-preview.js` (isi terverifikasi identik setelah dedentasi) dan didaftarkan di `resources/js/bootstrap.js` mengikuti pola modul SPJ lain; delegasi penuh di `document` dipertahankan agar modal tetap berfungsi pasca navigasi SPA. Index kini **185 baris** (dari 412 sebelum Tahap 1). Test diselaraskan ke rumah baru perilaku: asersi JS di `SpjMainTabsRenderingTest` & `SpjReportLayoutTest` kini membaca modul JS; sekalian diperbaiki 3 kegagalan lama sisa Tahap 1 di `SpjReportLayoutTest` (tag Livewire monitoring, route bulk-finalize, token permukaan, dan pemakaian `SpjDisplay::typeLabel` kini dibaca dari partial `monitoring`/`category-card`). Evidence: `node --check` bersih; isi modul identik dengan skrip lama; `php artisan view:cache` bersih; `npm run build` sukses dan string "Pratinjau Massal" ada di bundle; 70 test hijau (MainTabs 9, ReportLayout 15, PreviewPdf 10, ActiveContractsGuard 4, GuiAudit 23, ConsumptionParticipantUi 4, PersonInCharge 2, PackageNavigationButtons 3); `pint --dirty` lulus. Operator perlu `npm run build` setelah pull.

Pembaruan **2026-10-23** (navbar Tahap 2 — penataan grup & label): (1) Urutan grup sidebar kini Dashboard, Asisten, **SPJ & Laporan**, Keuangan, Referensi, Data & Integrasi, Administrasi — grup inti naik tepat di bawah Asisten. (2) Di dalam SPJ & Laporan ditambah caption sub-grup kecil: **Laporan** (di atas blok laporan dinamis) dan **Konfigurasi** (di atas Template Dokumen/Format Penomoran, tampil untuk operator+). (3) Label menu "Pajak Sinkronisasi" dipersingkat menjadi **Pajak**; tooltip Asisten diselaraskan dengan labelnya. (4) Item aksi **Sinkron Semua ARKAS** diberi pemisah garis atas + jarak di dalam grup Data & Integrasi agar terbaca sebagai aksi, bukan halaman. (5) Duplikasi **Manajemen User di dropdown profil dihapus** (tetap ada di grup Administrasi); dropdown kini hanya identitas + Keluar. (6) **Nama sekolah di topbar menjadi tautan ke halaman Ganti Sekolah** (`schools.select`). Menu "Tahun Anggaran" DIPERTAHANKAN: halaman tujuannya adalah penentu konteks kerja lengkap (sekolah + tahun + sumber dana), bukan sekadar duplikat pemilih tahun topbar. Evidence: `php artisan view:cache` bersih; `npm run build` sukses; 47 test hijau (`GuiAudit09To13SourceReadinessTest` 23, `RkasBudgetUiTest` 6, `ArkasFixedMirrorTest` 3, `SpjMainTabsRenderingTest` 9, `SpjConsumptionParticipantUiTest` 4, `SpjPersonInChargeTest` 2). Operator perlu `npm run build` setelah pull.

Pembaruan **2026-10-22** (Tahap 1 pemecahan `spj/index.blade.php` menjadi partial): tiga blok inline diekstrak tanpa mengubah markup yang dirender — (1) `spj/partials/package/category-card.blade.php` (kartu kategori SPJ: select + konteks sebaris + petunjuk), (2) `spj/partials/package/manual-form.blade.php` (persiapan data `@php` + form Isian Manual lengkap: kunci isian, fieldset, kartu kategori via include, Data Umum + fieldset per kategori, sticky actions Prev/Simpan/Next), (3) `spj/partials/monitoring.blade.php` (seluruh isi tab Monitoring: ringkasan tindak lanjut, Bulk Final, Penomoran Triwulan, Status periode, antrean transaksi tertunda). `spj/index.blade.php` menyusut 412 → 293 baris (kerangka halaman + pembungkus tab + include). `SpjPackageNavigationButtonsTest` disesuaikan: hitungan `package_tab=isian` kini membaca partial manual-form (maksud asersi dipertahankan) + asersi include manual-form. Sisa inline terbesar berikutnya: `<script>` pratinjau template ±108 baris di kaki index (kandidat modul `resources/js`, tahap terpisah). Evidence: `php artisan view:cache` bersih; `SpjMainTabsRenderingTest` 9 passed, `SpjPackageNavigationButtonsTest` 3 passed, `GuiAudit09To13SourceReadinessTest` 23 passed, `SpjConsumptionParticipantUiTest` 4 passed, `SpjPersonInChargeTest` 2 passed.

Pembaruan **2026-10-22** (konteks kategori dirender server-side — tindak lanjut grid Isian Manual): inspeksi browser terhadap aplikasi live (paket 66) membuktikan panel Bahan tetap jatuh sebagai blok terpisah selebar kolom kategori tepat di bawah select Kategori (tepi kanan kedua kontrol sejajar; paragraf petunjuk masih utuh di DOM) — konteks kategori yang dibuat runtime oleh `spj-package-manual-category.js` tidak pernah menempati kolom grid di samping kategori pada halaman live. Perbaikan struktural: konteks kategori kini dirender langsung oleh Blade di dalam grid kategori (`spj/index.blade.php`) sebagai `lg:col-span-3` — panel BARANG berisi radio SiPLah/Non SiPLah (keadaan awal mengikuti `is_siplah`/`payment_method`, Non SiPLah nonaktif untuk sumber SiPLah) dan panel PEMELIHARAAN berisi `data-spj-maintenance-quick-slot`; paragraf petunjuk dipindah ke bawah grid. Di `spj-package-manual-category.js`, `bindCategoryContext()` mengikat konteks bawaan Blade (listener radio UI-only, seed `lastNonSiplahPayment`, dispatch `spj:category-context-ready` untuk strip nomor otomatis; dijaga flag dataset agar tidak ganda), sedangkan jalur pembuatan DOM lama menjadi fallback dengan pencarian grid/bidang via `closest('[data-spj-category-grid]')`/`closest('[data-spj-category-field]')`. Grid kategori (`lg:grid-cols-4`, bidang `lg:col-span-1`) dipertahankan persis seperti sebelumnya. Evidence: `php artisan view:cache` bersih; `node --check` lolos; `npm run build` sukses dan CSS hasil build memuat `lg:col-span-3`; `SpjMainTabsRenderingTest` 9 passed, `SpjConsumptionParticipantUiTest` 4 passed, `SpjPersonInChargeTest` 2 passed. Operator tetap perlu `npm run build` setelah pull.

Pembaruan **2026-10-22** (grid formulir Isian Manual satu baris): dua perbaikan tata letak atas temuan operator pada paket 66. (1) Kartu Kategori SPJ: grid `lg:grid-cols-4` + bidang kategori `lg:col-span-1` kini dirender langsung di Blade (`spj/index.blade.php`), tidak lagi bergantung pada mutasi kelas runtime `spj-package-manual-category.js` — select Kategori dan panel konteks di sampingnya (radio SiPLah/Non SiPLah untuk BARANG, select tautan Bahan/Upah untuk PEMELIHARAAN) tampil satu baris pada layar lebar; JS tetap menemukan konteks yang sama dan petunjuk kategori tetap dihapus JS seperti sebelumnya. (2) Penanggung Jawab Kegiatan pada `partials/package/common.blade.php`: pemilih pencarian nama pegawai dan field NIP PJ kini satu baris (nama fleksibel, NIP `sm:w-72` di kanan; bertumpuk hanya di layar sempit), dropdown hasil pencarian tetap menempel pada field nama. Evidence: `php artisan view:cache` bersih; `SpjConsumptionParticipantUiTest` 4 passed, `SpjMainTabsRenderingTest` 9 passed, `SpjPersonInChargeTest` 2 passed. Operator perlu `npm run build` setelah pull agar kelas grid baru masuk bundle CSS.

Pembaruan **2026-10-22** (penyelarasan kontrak daftar hadir/upah dengan master SD-5-FIX-2): pemeriksaan dua sheet baru pada master unggahan operator (`TPL_DAFTAR_HADIR_PEMELIHARAAN`, `TPL_DAFTAR_PENERIMA_UPAH`) menemukan desain sheet sudah sesuai mesin (kolom contoh `HARI_TANGGAL`/`HARI_NAMA`, baris `UPAH_*`, semua sel string bersih), tetapi kontrak awal keliru di dua titik dan satu marker sheet tidak dikenal. Kontrak `DAFTAR_HADIR_PEMELIHARAAN` diselaraskan ke pola dokumen pemeliharaan (SPK/RAB berkunci nomor SPK): `required` kini `NOMOR_SPK` + `TANGGAL_SPK` (menggantikan `NOMOR_SPJ`, yang turun menjadi `optional`). Kontrak `DAFTAR_PENERIMA_UPAH` menerima `DESA`, `NOMOR_RAB`, `TANGGAL_RAB`, `TANGGAL_DOKUMEN`, dan `NAMA_PENERIMA` sebagai `optional`. Marker `{{NAMA_PENERIMA_UTAMA}}` pada sheet tidak dikenal engine — nama kanonisnya `{{NAMA_PENERIMA}}` (sama-sama `effective_receipt_recipient_name`); perbaikan sisi template ada pada salinan master FIX, berkas asli operator tidak diubah. Evidence: `SpjTemplateValidator::validateFile` atas master asli — hadir 0 error/0 warning setelah penyelarasan kontrak, penerima tinggal 1 error marker tak dikenal; atas salinan FIX kedua sheet 0 error/0 warning. Test baru di `SpjSupplementaryTemplateContractTest` mengunci kontrak kedua tipe + set marker persis master.

Pembaruan **2026-10-22** (Penanggung Jawab Kegiatan + daftar hadir/upah pemeliharaan): (1) Dua tipe dokumen pendukung pemeliharaan terdaftar di `SpjDocumentTypeRegistry` (total 15 tipe): `DAFTAR_HADIR_PEMELIHARAAN` (sheet `TPL_DAFTAR_HADIR_PEMELIHARAAN`) dan `DAFTAR_PENERIMA_UPAH` (sheet `TPL_DAFTAR_PENERIMA_UPAH`), khusus kategori PEMELIHARAAN dengan kontrak placeholder mengikuti pola dokumen konsumsi; berkas template pendamping lolos `SpjTemplateValidator` 0 error/0 warning untuk kedua tipe. (2) Ekspansi kolom hari dinamis XLSX: marker struktural `HARI_TANGGAL`/`HARI_NAMA` pada satu kolom contoh dikembangkan `SpjTemplateService::expandExcelDayColumns()` menjadi satu kolom per hari masa pelaksanaan SPK (inklusif, maksimum 92 hari) pada ketiga jalur render (unduh, `filledSpreadsheet`, kanonis Extended); tanpa tanggal pelaksanaan, marker dibersihkan dan grid contoh dipertahankan satu kolom. (3) Penanggung Jawab Kegiatan per paket: kolom snapshot `person_in_charge_name`/`person_in_charge_nip`/`person_in_charge_employee_id` pada `transactions` (migrasi school `2026_10_22_134004`), pemilih pencarian dari daftar pegawai aktif (`SpjWorkspaceUseCase::participantRoster()`, diteruskan ke view sebagai `employeeRoster`) pada Data Umum Dokumen formulir Isian Manual, penyimpanan lewat `UpdateSpjPackageDetailsUseCase`, dan placeholder `NAMA_PENANGGUNG_JAWAB`/`NIP_PENANGGUNG_JAWAB` kini terisi dari snapshot tersebut (sebelumnya selalu fallback kosong). Evidence: test baru `SpjDayColumnExpansionTest` 3 passed (periode 6 hari — tanggal dan nama hari terisi, judul merge `E13:J13`, kolom Jumlah Hari/Tanda Tangan bergeser, border sel paraf terkopi ke kolom baru dan baris pekerja sisipan, area cetak melebar; periode 1 hari; tanpa tanggal) dan `SpjPersonInChargeTest` 2 passed (simpan dari roster menjangkau placeholder; pengosongan kembali ke fallback); regresi terkait per-file hijau: `SpjSupplementaryTemplateContractTest` 12, `SpjGeneratedDocumentRegressionTest` 3, `SpjConsumptionParticipantUiTest` 4, `DocumentTemplatePlaceholderInspectorTest` 4, `SpjDateChronologyValidationTest` 2, `SpjSixCategoryE2eTest` 6, `SpjTemplateWorkbookPreservationTest` 7; pint bersih; `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` diselaraskan (seksi Penanggung Jawab Kegiatan + ekspansi kolom hari). Tindak lanjut operator: migrasi school baru berjalan lewat mekanisme migrasi tenant seperti biasa; unggah/impor template daftar hadir & penerima upah sebagai template aktif PEMELIHARAAN agar kedua dokumen muncul pada paket.

Pembaruan **2026-10-22** (hitungan antrean Persiapan satu query): hitungan antrean kerja tab Persiapan `/spj` di `SpjWorkspaceUseCase::preparationData()` kini dihitung dalam SATU query agregat bersyarat (`workQueueCounts()`), menggantikan 6 query COUNT terpisah per status yang berjalan pada setiap muat halaman dan setiap interaksi Livewire. Kondisi tiap status meniru persis `SpjWorkflowFilterService::apply()` — status perhatian adalah baris sumber bermasalah (`requires_reconciliation`/`SOURCE_MISSING`), dan status lain selalu mengecualikan baris tersebut; alias `needs_details` tetap mengikuti `attention`. Evidence: test baru `test_work_queue_counts_are_computed_in_one_aggregate_query` (paritas angka 7 transaksi lintas semua status + tepat satu query agregat; gagal di kode lama), `SpjPreparationFilterTest` 4 passed, `SpjTabFiltersLivewireTest` 7 passed, `SpjMainTabsRenderingTest` 9 passed, `SpjPackagePeriodFilterTest` 6 passed (per-file; run gabungan multi-file di sandbox gagal QueryException identik — artefak lingkungan yang sudah terkonfirmasi berulang), pint bersih. Dua item rencana performa lain dinilai ulang berbasis bukti dan TIDAK dikerjakan: muatan ganda halaman Penomoran (route tidak pernah muncul di `performance.log` data asli — tidak ada bukti lambat) dan pencarian LIKE payload mirror (sudah terlingkup kunci sumber terindeks; tidak ada slow query terkait di log).

Pembaruan **2026-10-22** (cache pratinjau PDF): masa simpan cache PDF pratinjau di `SpjDocumentUseCase` diperpanjang dari 10 menit menjadi 7 hari (konstanta `PREVIEW_PDF_CACHE_DAYS`), berlaku untuk pratinjau tunggal (`previewPackagePdf`) dan pratinjau massal (`previewPackages`). Aman diperpanjang karena kunci cache adalah sidik jari seluruh isi render (`packagePreviewCacheKey`: paket, transaksi beserta seluruh relasinya, template, sekolah, dan profil sekolah) — perubahan data apa pun menghasilkan kunci baru dan render ulang otomatis; TTL hanya membersihkan entri yatim yang tidak akan terpanggil lagi. Latar: analisis `performance.log` data asli 22 Okt 2026 mencatat pratinjau PDF sebagai route terberat (5–30 detik per render; satu paket dirender 7× dalam sehari dengan rata-rata 21 detik akibat TTL 10 menit). Evidence: test regresi baru `test_preview_package_cache_survives_beyond_ten_minutes` (gagal di kode lama, lulus setelah perubahan), `SpjPreviewCacheFingerprintTest` 4 passed/12 assertions, `SpjPreviewPdfTest` 10 passed/38 assertions, `SpjPreviewExcelParityTest` 1 passed/18 assertions, pint bersih. Konteks lingkungan: aplikasi operator kini dilayani Laravel Herd (migrasi dari `php artisan serve` yang satu-ulir); efek gabungan terverifikasi di PC operator (waktu server halaman login 3.257 ms → 20–36 ms).
Pembaruan **2026-10-22** (batas waktu render dokumen): fatal `Maximum execution time of 30 seconds exceeded` pada `SpjTemplateService.php:799` (titik akhir akumulasi pipeline, bukan operasi zip itu sendiri) diperbaiki dengan `set_time_limit` khusus endpoint render dokumen di `SpjDocumentUseCase` (unduh/preview PDF & Excel paket, unduh/preview PDF template, preview massal). Batas diangkat ke 180 detik (konfigurabel `SPJ_DOCUMENT_RENDER_TIME_LIMIT`, default `config('spj.document_render_time_limit')`) karena `php.ini` `max_execution_time=30` lebih pendek dari timeout proses LibreOffice (120s) di `SpjSpreadsheetPdfConverter` — request PHP mati sebelum konversi selesai. Evidence: pint bersih; `SpjPreviewPdfTest` + `SpjPreviewExcelParityTest` + `SpjDocumentGeneratorHardeningTest` 21 test/236 assertions hijau.

Pembaruan **2026-10-22** (perbaikan Saran RKAS): prioritas sumber data `RkasPlanningSuggestionService::build()` dibalik — jalur normalisasi (`arkas_rkas_items` + `arkas_bku_rows`, terpelihara per tahun + sumber dana oleh `ArkasSynchronizationServiceV2::saveRkas` dan dipakai juga oleh workspace RKAS) kini menjadi jalur utama bila tabel normalisasi terisi untuk sumber dana terkait; jalur mirror mentah turun menjadi fallback hanya untuk database yang belum pernah dinormalisasi. Akar masalah yang diperbaiki: mirror adalah snapshot mentah (di data asli SDN 316: 1.461 baris tanpa tahun/sumber dana, akumulasi ±12 lapis salinan lintas revisi) sehingga halaman Saran menampilkan total Rp 2,68 miliar vs pagu sah Rp 216 juta, dengan kelompok tanpa kode menyaru sebagai "Biaya Paket Data". `DiagnoseRkasSaranCommand` diselaraskan (label jalur mengikuti prioritas baru). Evidence: test regresi baru `test_mirror_accumulation_does_not_override_normalized_totals` (gagal di kode lama dengan 120 jt vs 15 jt; lulus setelah perbaikan), seluruh test Rkas 110 passed/637 assertions, pint bersih. Verifikasi angka akhir tetap di PC operator: setelah pull, halaman Saran harus menampilkan total pagu Rp 216.000.000 dan `spj:diagnose-rkas-saran` melaporkan jalur LEGASI.

Pembaruan **2026-10-22** (alat diagnosis Saran): perintah read-only baru `spj:diagnose-rkas-saran` (`app/Console/Commands/DiagnoseRkasSaranCommand.php`) untuk menelusuri anomali pagu Saran Perencanaan RKAS yang ditemukan pada QA browser data asli (Modul 2 "Biaya Paket Data" Rp 2.570.260.000 vs total RKAS Rp 216.000.000). Perintah menampilkan per kegiatan: total versi Saran, jumlah baris mirror yang terjumlah, baris terbesar, dan total versi sadar-revisi sebagai pembanding. Dugaan utama dari penelusuran kode: `RkasPlanningSuggestionService::mirrorRkasRows()` menjumlahkan semua baris `arkas_mirror_rapbs` tanpa filter revisi (dan baris revisi lama tidak pernah dibuang sinkronisasi), sementara `ArkasMirrorBudgetService` menyaring ke revisi terpilih — perbaikan logika Saran BELUM dilakukan, menunggu hasil diagnosis pada data asli operator (`php artisan spj:diagnose-rkas-saran --school=10208246 --year=2026`).

Pembaruan **2026-10-22** (A2 — alert & flash): bug prop `x-ui.alert` di `schools/settings` diperbaiki — 4 pemanggilan memakai `variant=` (tidak dikenal komponen; semua alert tampil gaya info) diganti `type=` (`success`/`danger`/`warning`). Flash mentah diganti komponen baku `x-ui.alert`: `dapodik/index` (sukses/error/validasi) dan `livewire/user-management` (sukses + `@error('form')`). Evidence: test yang tersentuh lulus per-file (`UserManagementTest` 5, `TallProfileMasterTest`, layout Dapodik, mounting settings); run gabungan multi-file di sandbox malam ini gagal QueryException identik (7 failed/8 passed) pada HEAD bersih maupun dengan perubahan — terkonfirmasi gangguan lingkungan VM, bukan perubahan ini.

Pembaruan **2026-10-22** (kontrak Surat Pesanan): `ALAMAT_REKANAN` diturunkan dari `required` ke `optional` pada kontrak `SURAT_PESANAN` di `SpjDocumentTypeRegistry` — menyusul penggabungan alamat, template Surat Pesanan cukup memakai `ALAMAT_PENYEDIA`; template lama yang masih memakai `ALAMAT_REKANAN` tetap sah karena penanda tetap dikenal dan terisi alias. Di dokumen lain (BAP/BAST/Invoice) penanda ini memang sudah `optional`.

Pembaruan **2026-10-22** (alamat penyedia): `ALAMAT_PENYEDIA` kini menjadi alamat penyedia kanonis hasil gabungan di `SpjTemplateService::placeholders()` — diutamakan `siplah_merchant_address` transaksi (atau `merchant_address` respons SiPlah), dan bila kosong jatuh ke alamat master `business_partners` yang sebelumnya hanya dirender oleh `ALAMAT_REKANAN`. `ALAMAT_REKANAN` dipertahankan sebagai alias nilai yang sama agar template lama tidak kosong; `SIPLAH_ALAMAT_PENYEDIA` tidak berubah (murni alamat SiPlah). Konsekuensi template: blok alamat yang menumpuk baris `ALAMAT_REKANAN` + `ALAMAT_PENYEDIA` kini mencetak alamat yang sama dua kali — template cukup memakai `ALAMAT_PENYEDIA`. Dokumentasi ownership di `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` diselaraskan.

Pembaruan **2026-10-22** (kop): jangkar gambar kop (`KOP_SURAT`) pada output XLSX tidak lagi terkunci di `A1`. `SpjTemplateService::fillExcelLetterhead()` kini menentukan rentang kop berurutan dari `printArea` worksheet, merge cell marker kop, lalu rentang terisi sheet (kolom marker s.d. kolom terisi terakhir); gambar di-anchor pada kolom awal rentang tersebut dan lebarnya mengikuti rentang yang sama, menggantikan fallback lebar tetap 610px yang membuat kop template tanpa `printArea` tampil sempit dan menempel kiri. Template yang isinya mulai di kolom B kini menempatkan kop tepat selebar blok isinya. Evidence: `SpjLetterheadAutofitTest` 5 test/15 assertions (2 test baru: fallback merge dan fallback rentang terisi), regresi tetangga `SpjTemplateScalarFallbackTest`, `SpjSupplementaryTemplateContractTest`, `SpjTemplateExcelHeaderFooterTest`, `SpjTemplateHtmlPreviewTest`, `SpjUnresolvedPlaceholderGuardTest`, `SpjPreviewPdfTest`, `SpjPreviewExcelParityTest` hijau (35 test), pint bersih. Dokumentasi perilaku kop di `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` diselaraskan.
> Branch `hardening/raw-rkas-audit` telah digabung melalui PR #1; referensi branch lama hanya dipertahankan sebagai evidence historis, bukan branch kerja aktif.

## Indeks log kemajuan per fungsi

`CURRENT_PROGRESS.md` adalah sumber kebenaran status terbaru. Riwayat per fungsi dipecah ke file berikut (2026-10-11) agar tiap file < 128KB:

| File | Isi |
| --- | --- |
| `PROGRESS_NUMBERING_LIFECYCLE.md` | Penomoran & Lifecycle Dokumen — 5 seksi |
| `PROGRESS_OFFICIAL_REPORTS.md` | Laporan Resmi & Format BOS — 4 seksi |
| `PROGRESS_QA_BROWSER.md` | QA Browser — 2 seksi |
| `PROGRESS_DOCUMENTATION.md` | Dokumentasi — 1 seksi |
| `PROGRESS_AUDIT_REALDATA.md` | Audit & Verifikasi Real-Data — 3 seksi |
| `PROGRESS_UI_UX.md` | UI/UX — 17 seksi |
| `PROGRESS_SYNCHRONIZATION.md` | Sinkronisasi ARKAS — 19 seksi |
| `PROGRESS_RKAS.md` | RKAS — 5 seksi |
| `PROGRESS_REGRESSION_CI.md` | Regression & CI — 8 seksi |
| `PROGRESS_P0_WORKSTREAMS.md` | Workstream P0 — 5 seksi |

## Status release saat ini

```text
FUNCTIONAL BASELINE : PREVIOUS VERIFIED GATE PASS / run #46
CURRENT CODE GATE   : FOCUSED VERIFICATION COMPLETE; FULL CI PENDING after Bulk Preview and Format K7 presentation changes
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE / CODE GATE PASS
FINAL RELEASE       : NOT YET
```

P0 code/dependency integration gate terakhir terverifikasi pada run #46 sebelum perubahan Bulk Preview. Perubahan Bulk Preview, presentasi Format K7, dan alignment registry placeholder master v7 mendapat focused verification lokal tetapi belum full CI; browser/PDF aktual tetap RVR. Aplikasi belum boleh disebut final release-ready karena generated-document real-data QA, browser/operator QA, Office/PDF visual fidelity, dan installed-runtime verification masih terpisah dari deterministic CI.

## Template master v7 — registry placeholder alignment 2026-10-21

Status: **SOURCE UPDATED / FOCUSED VERIFICATION PASS / OFFICE VISUAL QA RVR**.

Registry `SpjDocumentTypeRegistry` dan katalog/resolver `SpjTemplateService`
kini menerima marker yang sudah digunakan pada sheet canonical master
`MASTER-TEMPLATE-SPJ v7_SD.xlsx` tanpa mengubah workbook sumber. Marker tanggal
tertulis BAP/BAST (`HARI_*`, `TANGGAL_*_HURUP`, `BULAN_*_HURUP`,
`TAHUN_*_HURUP`) diisi dari tanggal berita acara terkait. Marker existing
lainnya dideklarasikan per sheet sebagai optional/image; item yang tidak
terdapat pada workbook tidak lagi dipaksakan sebagai required untuk sheet
tersebut.

Evidence aktual:

```text
SpjTemplatePackageImporter::validatePackage pada master v7
  : valid=true, 0 errors, 0 warnings
Focused regression lima file template
  : 38 passed
vendor/bin/pint --dirty --format agent
  : passed
```

Validasi paket tersebut memeriksa nama sheet dan kontrak marker, bukan visual
Excel/LibreOffice, hasil cetak, atau data nyata; pemeriksaan Office/PDF tetap
RVR. Perubahan source ini juga belum tercakup full CI setelah workflow run #46.

## XLSX gradient fill pada pembacaan dan ekspor PDF — 2026-10-21

Status: **READER STYLE PRESERVATION / EXCEL COM ROUTE IMPLEMENTED / EXCEL COM END-TO-END VERIFIED / FOCUSED REGRESSION PASS / WORKER PACKAGE PDF RVR**.

`SpjTemplateService` kini menambahkan `type="linear"` yang tidak tertulis pada
`gradientFill` OOXML dalam salinan XLSX sementara sebelum PhpSpreadsheet
membaca template. Workbook sumber tidak dimodifikasi. Pembandingan pada
`MASTER-TEMPLATE-SPJ v7_SD.xlsx` menunjukkan PhpSpreadsheet sebelumnya
membaca empat fill gradient Invoice sebagai `none`; setelah normalisasi fill
terbaca dan bertahan dalam round-trip XLSX.

Batas renderer terverifikasi secara lokal: PDF native Microsoft Excel
mempertahankan gradient Invoice, sedangkan LibreOffice Calc 26.2 meratakannya
menjadi warna solid. PDF paket yang diberikan dibuat oleh LibreOffice Calc.
HTML preview dan fallback Dompdf juga belum memiliki jaminan gradient.

Prototype ekspor read-only dengan Microsoft Excel 16.0 COM pada master template
mempertahankan gradient Invoice. PDF Excel dan LibreOffice sama-sama memiliki
13 halaman dengan urutan/ukuran kertas yang cocok; ukuran A4 berbeda maksimum
0,1 pt. Dua pita gradient Invoice pada PDF Excel terenkode sebagai JPEG
674x53 dan 678x53 dengan masing-masing 157 dan 160 warna unik, membentuk
transisi kuning ke krem. File template sumber tidak berubah.

`SpjExcelComPdfRenderer` kini menyediakan jalur XLSX native berbasis PowerShell
dan Excel COM. Pilihan konfigurasi `SPJ_XLSX_PDF_RENDERER` adalah `libreoffice`
(default, menjaga perilaku lama), `auto`, atau `excel_com`. Jalur Excel membuka
workbook read-only, menonaktifkan macro dan update link, menserialisasi akses
melalui lock worker, membatasi waktu proses, serta mengembalikan error secara
eksplisit jika mode `excel_com` gagal atau tidak tersedia. Pengujian seleksi
renderer dan kegagalan eksplisit lulus dalam focused suite 10 test.

Mesin pengembangan kini mengatur `SPJ_XLSX_PDF_RENDERER=auto` dan Excel COM
terdaftar (Microsoft Excel 2024). Konversi XLSX berisi `gradientFill` ke PDF
diverifikasi end-to-end: PDF dihasilkan oleh Microsoft Excel (Producer:
"Microsoft Excel 2024"), bukan LibreOffice, sehingga gradient cell dipertahankan.

Belum ada verifikasi end-to-end melalui aplikasi di akun service worker khusus,
XLSX paket terisi, concurrency antarmesin, atau pemulihan proses Excel yang
timeout. Deploy yang menginginkan gradient harus mengatur
`SPJ_XLSX_PDF_RENDERER=auto` (atau `excel_com`) pada worker Windows khusus
berlisensi Office. Sampai PDF paket aplikasi dibuat dan diperiksa pada worker
tersebut, hasil gradient/pagination paket tetap RVR; konfigurasi default
LibreOffice tetap meratakan gradient.

---

## Livewire / TALL migration — Phase 1 + Phase 2

### Phase 1 — mutation boundary audit

Status: **SOURCE AUDIT COMPLETE**.

Audit seluruh `app/Livewire/` menemukan 27 component dan mengklasifikasikan read-only/UI-state, context mutation, serta mutation sensitif. Detail matriks berada di `LIVEWIRE_MIGRATION_PLAN.md`.

### Phase 2 — authorization hardening

Status: **IMPLEMENTED + REGRESSION PASS + FULL CODE GATE PASS / BROWSER RUNTIME RVR**.

Source hardening:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization
```

Critical-suite integration:

```text
701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

Boundary yang sudah ditutup:

- `UserManagement::{createUser,updateUser,deleteUser}` → ADMIN-only;
- `SchoolMaster::createSchool` → ADMIN-only;
- `DatabaseMaintenance::run` → ADMIN-only;
- `DatabaseResetForm::resetDatabase` → ADMIN + active-school match + exact confirmation;
- `DatabaseSchoolList::{activate,migrate}` → ADMIN-only;
- `DocumentStorageSettings::save` → OPERATOR/ADMIN sebelum reuse;
- `SchoolSelector::selectSchool` mempertahankan guard admin/own-school;
- `YearSelector::selectYear` tetap accepted context mutation.

`LivewireMutationAuthorizationTest` berada di SPJ Critical dan tetap tercakup oleh green SPJ Critical gate PR #486 serta run #46. Rule arsitektur tetap: mutation Livewire sensitif harus authorize pada request action/policy/persistent mechanism yang benar-benar berlaku, bukan hanya mengandalkan route GET halaman awal.

Status area yang dimigrasikan:

- Transaksi: `TransactionsTable` untuk daftar dan `TransactionDetailWorkspace` untuk detail, filter/read/write state Livewire dengan mutasi tetap melalui service domain;
- RKAS: `RkasBudgetWorkspace` + `RkasBudgetFilter` read-only; tabel hierarki dan ringkasan berada di dalam workspace Livewire;
- SPJ Persiapan/Paket/Laporan/Monitoring: Livewire filters/lists + SPA tab navigation; detail Paket mutation-heavy tetap server-rendered;
- Pajak: `TaxFilter` read-only;
- Pegawai: `EmployeeDirectory` read-only;
- Referensi: `ReferenceDirectory` read-only katalog rekening, acuan harga, dan program/kegiatan ARKAS via Livewire 3 (implemented 2026-10-21);
- Data Sinkronisasi: `SyncedDataNavigation` UI-state/read-only;
- Database Aktif: read-only panels + role-hardened mutation components;
- User/Master Sekolah: role-hardened mutation components;
- Penyimpanan Dokumen: dormant component sudah OPERATOR/ADMIN-hardened sebelum reuse.

Browser/operator behavior tetap RVR sampai `GUI_RUNTIME_QA.md` dijalankan pada runtime aktual.

---

## Prioritas kerja aktif

P0 integration/dependency repair dan Phase 2 authorization sudah selesai. Prioritas berikutnya:

1. **Generated-document real-data/operator QA** untuk Paket nyata yang tersedia;
2. **browser/operator QA desktop-laptop** berdasarkan `GUI_RUNTIME_QA.md`, khususnya repeated `Livewire.navigate`, SPA tab SPJ, modal preview, pagination, dropdown, dan filter URL state;
3. **Office/PDF visual-output QA** untuk individual template, master terbaru, XLSX/PDF hasil generate, print area/page break/header/footer;
4. lanjutkan JASA_LAINNYA multi-penerima dan PEMELIHARAAN bahan+upah pada output nyata bila ditemukan mismatch;
5. setelah operator/runtime flow stabil, baru pertimbangkan kandidat migrasi Livewire read-only berikutnya seperti Rekonsiliasi.

---

## Open verification / release blockers

Code/dependency integration gate **bukan lagi blocker**. Blocker/verifikasi tersisa:

- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML/template nyata pada browser aktual masih RVR;
- browser/operator desktop-laptop QA masih RVR;
- official-template print/layout/output QA masih RVR;
- installed-runtime checks masih DEFERRED;
- mobile/tablet runtime QA tetap RVR/non-blocker untuk target desktop-laptop.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data agar test/audit PASS.
2. Jangan memakai deterministic fixture sebagai bukti real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source/runtime change setelah gate `e4ba5cf...` (workflow run #46) membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary pada action request.
7. GUI source PASS tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan `operator flow -> temukan bug nyata -> perbaiki -> focused regression bila perlu`.

---

## Authenticated route performance sweep + searchable-select fix 2026-09-25

Status: **FUNCTIONAL FIX PASS / SHELL RUNTIME EVIDENCE / BROWSER RUNTIME RVR**.

Perbaikan yang diterapkan:

- komponen `x-ui.searchable-select` memakai directive `@entangle(...).live` canonical ketika berada di dalam Livewire, menggantikan ekspresi string `$wire.entangle(...)` yang menghasilkan error browser `$wire is not defined` pada tab Paket SPJ;
- import `DB` ganda pada model transaksi dibersihkan sehingga bootstrap route transaksi tidak lagi berhenti pada fatal error duplicate import.

Evidence aktual:

- 38 route GET statis authenticated diuji dari shell; 34 HTTP 200, 3 redirect normal, dan route `/setup` expected 404 karena user sudah ada;
- route inti authenticated setelah perbaikan: `/` rata-rata 1,43 detik, `/spj` 141 ms, `/transaksi` 1,77 detik, dan `/penganggaran-rkas` 342 ms pada tiga request sequential per route;
- route GET statis paling lambat pada sweep: template dokumen 5,65 detik, importer 5,02 detik, SPJ 4,80 detik, transaksi 4,23 detik, database manager 3,78 detik, dan dashboard 3,35 detik;
- Blade cache berhasil, Vite build berhasil 3,30 detik, dan focused regression 30 test / 341 assertion lulus;
- `git diff --check` bersih.

Route dinamis yang memerlukan ID nyata dan route mutasi POST/PUT/DELETE tidak dijalankan pada sweep ini. Pengujian tersebut memerlukan fixture/flow khusus agar tidak mengubah data operator. Browser visual dan interaksi Livewire aktual tetap RVR sampai checklist runtime browser dijalankan.

## Query performance tahap 1 — 2026-09-25

Diagnosis: disk mentah cepat (5 query = 3 ms) dan indeks events/rekonsiliasi sudah
memadai; kelambatan `spj.index` 3–18 detik berasal dari latensi per query
sesaat (lock/WAL saat burst tulis sync; `busy_timeout=5000` membuat baca
menunggu) dikali volume query per halaman. Perbaikan:

- checkpoint WAL 4 DB sekolah + `PRAGMA wal_checkpoint(PASSIVE)` otomatis
  pasca-sync sukses (`ArkasSynchronizationServiceV2`);
- `scopeNeedsReconciliation`: agregasi event terbaru sekali (`GROUP BY`) lalu
  di-join, bukan subquery `MAX` per baris — hasil identik (dikunci test);
- kolom generated + indeks: `sx_id_anggaran` pada rapbs mirror (snapshot
  memfilter `ID_ANGGARAN` di database), `sx_satuan`/`sx_harga_barang`/
  `sx_batas_atas` + indeks komposit `(sx_tahun, sx_nama_barang, sx_satuan)`
  pada acuan harga pusat (GROUP BY 68 ribu baris 127 ms → 72 ms, INDEX SEARCH);
- temuan sampingan: migrasi lookup 150449 tercatat jalan padahal tabel referensi
  dibuat runtime sesudahnya sehingga kolomnya tak pernah terpasang; migrasi
  baru melengkapi yang hilang (guard `hasTable`/`hasColumn`).

Evidence aktual: scope rewrite ekuivalen pada salinan DB nyata (45 = 45);
`TransactionNeedsReconciliationScopeTest` 4/4, `ReferenceModuleTest` 6/6,
suite rekonsiliasi/budget terkait hijau individual. Browser/runtime tetap RVR.

Hierarki RKAS memprioritaskan relasi `arkas_mirror_rapbs.ID_REF_KODE` ke `arkas_mirror_ref_kode.ID_REF_KODE`; kode dan nama kegiatan diambil dari referensi tersebut, sedangkan `KODE_PROGRAM`, `NAMA_PROGRAM`, `KODE_SUB_PROGRAM`, dan `NAMA_SUB_PROGRAM` diambil dari payload RAPBS lalu memakai referensi parent sebagai fallback. `ID_LEVEL_KODE` ikut dibawa sebagai metadata level referensi.

Filter periode RKAS mirror sekarang menormalisasi `ID_PERIODE` melalui `arkas_mirror_ref_periode` terlebih dahulu. Nama/metadata bulan, triwulan, dan semester dari referensi menjadi dasar agregasi pagu, volume, realisasi, serta jumlah opsi filter; fallback numerik hanya digunakan bila referensi periode belum tersedia.
