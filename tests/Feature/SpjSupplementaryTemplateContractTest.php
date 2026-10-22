<?php

namespace Tests\Feature;

use App\Services\ExtendedSpjTemplateService;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\Services\SpjTemplateValidator;
use App\UseCases\Spj\ExtendedSpjReportUseCase;
use App\UseCases\Spj\SpjReportUseCase;
use Tests\TestCase;

class SpjSupplementaryTemplateContractTest extends TestCase
{
    public function test_consumption_support_documents_are_registered_for_consumption_only(): void
    {
        $attendance = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_HADIR_KONSUMSI);
        $recipients = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI);

        $this->assertSame('TPL_DAFTAR_HADIR_KONSUMSI', $attendance['sheet']);
        $this->assertSame(['KONSUMSI'], $attendance['applicable_categories']);
        $this->assertContains('KONSUMSI_NAMA', $attendance['required']);

        $this->assertSame('TPL_DAFTAR_PENERIMA_KONSUMSI', $recipients['sheet']);
        $this->assertSame(['KONSUMSI'], $recipients['applicable_categories']);
        $this->assertContains('TOTAL_KONSUMSI', $recipients['required']);
    }

    public function test_consumption_placeholders_are_exposed_in_runtime_catalog(): void
    {
        $groups = app(SpjTemplateService::class)::placeholderGroups();

        $this->assertArrayHasKey('Konsumsi & kegiatan', $groups);
        $this->assertContains('TANGGAL_KEGIATAN', $groups['Konsumsi & kegiatan']);
        $this->assertContains('NAMA_ACARA', $groups['Konsumsi & kegiatan']);
        $this->assertContains('TANGGAL_ACARA', $groups['Konsumsi & kegiatan']);
        $this->assertContains('TEMPAT_ACARA', $groups['Konsumsi & kegiatan']);
        $this->assertContains('KONSUMSI_NAMA', $groups['Konsumsi & kegiatan']);
        $this->assertContains('TOTAL_KONSUMSI', $groups['Konsumsi & kegiatan']);
    }

    public function test_consumption_placeholders_are_known_by_template_validator(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI);
        $markers = array_values(array_unique(array_merge(
            $definition['required'],
            $definition['optional'],
            $definition['repeat_required'],
            $definition['repeat_optional'],
            $definition['image'],
        )));

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI,
            $markers,
        );

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertNotContains('UNKNOWN_PLACEHOLDER', $errorCodes);
    }

    public function test_maintenance_support_documents_are_registered_for_maintenance_only(): void
    {
        $attendance = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_HADIR_PEMELIHARAAN);
        $recipients = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_UPAH);

        $this->assertSame('TPL_DAFTAR_HADIR_PEMELIHARAAN', $attendance['sheet']);
        $this->assertSame(['PEMELIHARAAN'], $attendance['applicable_categories']);
        // Daftar hadir pemeliharaan berkunci nomor SPK seperti SPK/RAB, bukan nomor SPJ.
        $this->assertContains('NOMOR_SPK', $attendance['required']);
        $this->assertContains('TANGGAL_SPK', $attendance['required']);
        $this->assertNotContains('NOMOR_SPJ', $attendance['required']);
        $this->assertContains('HARI_TANGGAL', $attendance['optional']);
        $this->assertContains('HARI_NAMA', $attendance['optional']);

        $this->assertSame('TPL_DAFTAR_PENERIMA_UPAH', $recipients['sheet']);
        $this->assertSame(['PEMELIHARAAN'], $recipients['applicable_categories']);
        $this->assertContains('NOMOR_SPJ', $recipients['required']);
        $this->assertContains('NILAI_UPAH', $recipients['required']);
        foreach (['DESA', 'NOMOR_RAB', 'TANGGAL_RAB', 'TANGGAL_DOKUMEN', 'NAMA_PENERIMA'] as $marker) {
            $this->assertContains($marker, $recipients['optional']);
        }
    }

    public function test_master_maintenance_support_markers_validate_without_errors_or_warnings(): void
    {
        $validator = app(SpjTemplateValidator::class);

        $attendance = $validator->validateMarkers(SpjDocumentTypeRegistry::DAFTAR_HADIR_PEMELIHARAAN, [
            'HARI_NAMA', 'HARI_TANGGAL', 'LOKASI_PEKERJAAN', 'NAMA_KEPALA_SEKOLAH',
            'NAMA_PENANGGUNG_JAWAB', 'NIP_KEPALA_SEKOLAH', 'NIP_PENANGGUNG_JAWAB',
            'NOMOR_SPK', 'TANGGAL_MULAI', 'TANGGAL_SELESAI', 'TANGGAL_SPK',
            'UPAH_HARI', 'UPAH_NAMA', 'UPAH_NO', 'UPAH_PEKERJAAN', 'URAIAN_PEKERJAAN',
        ]);
        $this->assertSame([], $attendance['errors']);
        $this->assertSame([], $attendance['warnings']);

        $recipients = $validator->validateMarkers(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_UPAH, [
            'DESA', 'KOP_SURAT', 'LOKASI_PEKERJAAN', 'NAMA_BENDAHARA_BOSP', 'NAMA_KEPALA_SEKOLAH',
            'NAMA_PENERIMA', 'NILAI_UPAH_TERBILANG', 'NILAI_UPAH', 'NIP_BENDAHARA_BOSP',
            'NIP_KEPALA_SEKOLAH', 'NOMOR_RAB', 'NOMOR_SPJ', 'NOMOR_SPK', 'TANGGAL_DOKUMEN',
            'TANGGAL_MULAI', 'TANGGAL_RAB', 'TANGGAL_SELESAI', 'TANGGAL_SPK',
            'TANGGAL_TANDA_TANGAN', 'UPAH_HARI', 'UPAH_JUMLAH', 'UPAH_NAMA', 'UPAH_NO',
            'UPAH_PEKERJAAN', 'UPAH_TARIF_HARI', 'URAIAN_PEKERJAAN',
        ]);
        $this->assertSame([], $recipients['errors']);
        $this->assertSame([], $recipients['warnings']);
    }

    public function test_revised_master_workbook_metadata_and_unimplemented_design_sheets_are_technical(): void
    {
        $technical = SpjDocumentTypeRegistry::technicalSheets();

        $this->assertContains('TEMPLATE_REGISTER', $technical);
        $this->assertContains('REGULASI_REFERENSI', $technical);
        $this->assertContains('BACKEND_TEMPLATE_RULES', $technical);
        $this->assertContains('TPL_DAFTAR_PENERIMAAN_HONOR', $technical);
        $this->assertContains('TPL_SPPD', $technical);
    }

    public function test_runtime_resolves_extended_template_and_report_services(): void
    {
        $this->assertInstanceOf(ExtendedSpjTemplateService::class, app(SpjTemplateService::class));
        $this->assertInstanceOf(ExtendedSpjReportUseCase::class, app(SpjReportUseCase::class));
    }

    public function test_bap_points_to_revised_inspection_and_acceptance_sheet(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::BAP);

        $this->assertSame('TPL_BA_PEMERIKSAAN_PENERIMAAN', $definition['sheet']);
        $this->assertContains('NAMA_PENGURUS_BARANG', $definition['required']);
        $this->assertContains('NIP_PENGURUS_BARANG', $definition['required']);
        $this->assertContains('NOMOR_BAP', $definition['required']);
        $this->assertContains('TANGGAL_BAP', $definition['required']);
        $this->assertContains('NAMA_SEKOLAH', $definition['required']);
        $this->assertNotContains('NOMOR_DOKUMEN', $definition['required']);
        $this->assertNotContains('TANGGAL_DOKUMEN', $definition['required']);
        $this->assertNotContains('TEMPAT_PENYERAHAN', $definition['required']);
        $this->assertNotContains('NAMA_BENDAHARA_BOSP', $definition['required']);
        $this->assertNotContains('NIP_BENDAHARA_BOSP', $definition['required']);
    }

    public function test_surat_pesanan_does_not_require_delivery_location(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SURAT_PESANAN);

        $this->assertNotContains('TEMPAT_PENYERAHAN', $definition['required']);

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::SURAT_PESANAN,
            array_merge($definition['required'], $definition['repeat_required']),
        );

        $missingRequired = collect($result['errors'])
            ->where('code', 'MISSING_REQUIRED')
            ->flatMap(fn (array $error): array => $error['markers'] ?? [])
            ->all();

        $this->assertNotContains('TEMPAT_PENYERAHAN', $missingRequired);
    }

    public function test_bast_uses_document_specific_number_placeholder(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::BAST);

        $this->assertContains('NOMOR_BAST', $definition['required']);
        $this->assertContains('KECAMATAN', $definition['required']);
        $this->assertContains('NAMA_PENGURUS_BARANG', $definition['optional']);
        $this->assertContains('NIP_PENGURUS_BARANG', $definition['optional']);
        $this->assertNotContains('NAMA_PENGURUS_BARANG', $definition['required']);
        $this->assertNotContains('NIP_PENGURUS_BARANG', $definition['required']);
        $this->assertNotContains('NOMOR_DOKUMEN', $definition['required']);
        $this->assertNotContains('TEMPAT_PENYERAHAN', $definition['required']);
        $this->assertNotContains('NAMA_BENDAHARA_BOSP', $definition['required']);
        $this->assertNotContains('NIP_BENDAHARA_BOSP', $definition['required']);

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::BAST,
            ['NOMOR_BAST'],
        );

        $this->assertNotContains('UNKNOWN_PLACEHOLDER', collect($result['errors'])->pluck('code')->all());
    }

    public function test_bap_placeholders_are_known_by_template_validator(): void
    {
        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::BAP,
            ['NOMOR_BAP', 'TANGGAL_BAP', 'NAMA_SEKOLAH'],
        );

        $this->assertNotContains('UNKNOWN_PLACEHOLDER', collect($result['errors'])->pluck('code')->all());
    }

    public function test_revised_master_contracts_match_rab_spk_and_consumption_sheets(): void
    {
        $rab = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::RAB_PEMELIHARAAN);
        $recipients = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI);

        $this->assertContains('JENIS_RAB', $rab['required']);
        $this->assertContains('TOTAL_RAB', $rab['required']);
        $this->assertNotContains('UPAH_NO', $rab['repeat_required']);
        $this->assertContains('NAMA_REKENING', SpjTemplateService::placeholderGroups()['Transaksi & pembayaran']);
        $this->assertContains('TANGGAL_KEGIATAN', $recipients['required']);
        $this->assertContains('KONSUMSI_HARGA_PORSI', $recipients['optional']);
        $this->assertContains('KONSUMSI_JUMLAH', $recipients['optional']);
        $this->assertNotContains('NAMA_ACARA', $recipients['required']);
    }

    public function test_existing_master_workbook_placeholders_are_registered_per_canonical_sheet(): void
    {
        $compatibilityMarkers = [
            SpjDocumentTypeRegistry::SPJ_COVER => [
                'KODE_PROGRAM', 'NAMA_PROGRAM', 'KODE_SUB_PROGRAM', 'NAMA_SUB_PROGRAM', 'URAIAN_TRANSAKSI',
            ],
            SpjDocumentTypeRegistry::SPJ_CHECKLIST => ['KOP_SURAT'],
            SpjDocumentTypeRegistry::KUITANSI_A2 => [
                'KODE_PROGRAM', 'NAMA_PROGRAM', 'KODE_SUB_PROGRAM', 'NAMA_SUB_PROGRAM',
                'NAMA_KEGIATAN', 'SUMBER_DANA_PERIODE', 'KOP_SURAT',
            ],
            SpjDocumentTypeRegistry::RINCIAN_BELANJA => [
                'KOP_SURAT', 'NAMA_BENDAHARA_BOSP', 'NAMA_KEPALA_SEKOLAH', 'NIP_BENDAHARA_BOSP',
                'NIP_KEPALA_SEKOLAH', 'SUMBER_DANA_PERIODE', 'TANGGAL_DOKUMEN', 'URAIAN_TRANSAKSI',
            ],
            SpjDocumentTypeRegistry::REKAP_PAJAK => [
                'KOP_SURAT', 'NAMA_BENDAHARA_BOSP', 'NAMA_KEPALA_SEKOLAH', 'NAMA_PENERIMA',
                'NIP_BENDAHARA_BOSP', 'NIP_KEPALA_SEKOLAH', 'REFERENSI_BAYAR', 'SUMBER_DANA_PERIODE',
            ],
            SpjDocumentTypeRegistry::SURAT_PESANAN => [
                'DESA', 'KODE_PROGRAM', 'KODE_SUB_PROGRAM', 'NAMA_PROGRAM', 'NAMA_SEKOLAH',
                'NAMA_SUB_PROGRAM', 'SUMBER_DANA', 'TANGGAL_BAP',
            ],
            SpjDocumentTypeRegistry::BAP => [
                'NAMA_PENERIMA_KUITANSI', 'HARI_BAP', 'TANGGAL_BAP_HURUP',
                'BULAN_BAP_HURUP', 'TAHUN_BAP_HURUP',
            ],
            SpjDocumentTypeRegistry::BAST => [
                'ALAMAT_SEKOLAH', 'NAMA_PENERIMA_KUITANSI', 'NAMA_SEKOLAH', 'NOMOR_BAP',
                'TANGGAL_BAP', 'HARI_BAST', 'TANGGAL_BAST_HURUP', 'BULAN_BAST_HURUP',
                'TAHUN_BAST_HURUP',
            ],
            SpjDocumentTypeRegistry::INVOICE => [
                'KODE_KEGIATAN', 'KODE_REKENING', 'NAMA_PENERIMA_KUITANSI', 'NAMA_SEKOLAH',
            ],
            SpjDocumentTypeRegistry::RAB_PEMELIHARAAN => [
                'KECAMATAN', 'KODE_PROGRAM', 'KODE_REKENING', 'KODE_SUB_PROGRAM',
                'NAMA_PROGRAM', 'NAMA_SUB_PROGRAM',
            ],
            SpjDocumentTypeRegistry::SPK_PEMELIHARAAN => [
                'KECAMATAN', 'KODE_KEGIATAN', 'KODE_PROGRAM', 'KODE_REKENING', 'KODE_SUB_PROGRAM',
                'NAMA_KEGIATAN', 'NAMA_SUB_PROGRAM', 'TANGGAL_TANDA_TANGAN',
            ],
            SpjDocumentTypeRegistry::DAFTAR_HADIR_KONSUMSI => [
                'KODE_PROGRAM', 'KODE_SUB_PROGRAM', 'NAMA_KEGIATAN', 'NAMA_PENGURUS_BARANG',
                'NAMA_PROGRAM', 'NAMA_SUB_PROGRAM', 'NIP_PENGURUS_BARANG',
            ],
            SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI => [
                'NAMA_ACARA', 'NAMA_KEPALA_SEKOLAH', 'NAMA_PENGURUS_BARANG',
                'NIP_KEPALA_SEKOLAH', 'NIP_PENGURUS_BARANG',
            ],
        ];

        foreach ($compatibilityMarkers as $documentType => $markers) {
            $definition = SpjDocumentTypeRegistry::definition($documentType);
            $registeredMarkers = SpjDocumentTypeRegistry::placeholdersFor($documentType);

            foreach ($markers as $marker) {
                $this->assertContains($marker, $registeredMarkers, "{$documentType} must register {$marker}.");
            }

            $result = app(SpjTemplateValidator::class)->validateMarkers(
                $documentType,
                array_merge($definition['required'], $definition['repeat_required'], $markers),
            );

            $this->assertSame([], $result['errors'], "{$documentType} must accept its workbook markers.");
            $this->assertSame([], $result['warnings'], "{$documentType} must not report registered markers as unexpected.");
        }
    }

    public function test_legacy_written_bap_and_bast_dates_are_exposed_in_runtime_catalog(): void
    {
        $groups = SpjTemplateService::placeholderGroups();

        foreach ([
            'HARI_BAP', 'TANGGAL_BAP_HURUP', 'BULAN_BAP_HURUP', 'TAHUN_BAP_HURUP',
            'HARI_BAST', 'TANGGAL_BAST_HURUP', 'BULAN_BAST_HURUP', 'TAHUN_BAST_HURUP',
        ] as $marker) {
            $this->assertContains($marker, $groups['Pesanan & pekerjaan']);
        }
    }
}
