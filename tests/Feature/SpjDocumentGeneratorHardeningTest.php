<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjMaintenanceDocumentContextService;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjTemplateRenderPreflight;
use App\Services\SpjTemplateService;
use App\Services\SpjUnresolvedPlaceholderGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;
use ZipArchive;

class SpjDocumentGeneratorHardeningTest extends TestCase
{
    use SeedsArkasMirror;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['code' => 'BOSP', 'name' => 'BOSP']);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Sekolah Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_generated_xlsx_is_openable_resolves_core_fields_and_does_not_allocate_numbers(): void
    {
        $package = $this->package('BARANG', 1);
        $template = $this->xlsxTemplate([
            'A1' => '{{NAMA_SEKOLAH}}',
            'A2' => '{{NOMOR_DOKUMEN}}',
            'A3' => '{{NOMOR_BUKTI}}',
            'A4' => '{{NILAI_BRUTO}}',
            'A6' => '{{ITEM_NO}}',
            'B6' => '{{ITEM_URAIAN}}',
            'C6' => '{{ITEM_VOLUME}}',
            'D6' => '{{ITEM_SATUAN}}',
            'E6' => '{{ITEM_HARGA_SATUAN}}',
            'F6' => '{{ITEM_JUMLAH}}',
        ]);
        $school = $this->school();
        $documentsBefore = DB::connection('school')->table('spj_documents')->count();
        $sequencesBefore = DB::connection('school')->table('document_number_sequences')->count();

        $response = app(SpjTemplateService::class)->download($template, $package, $school);
        $path = $response->getFile()->getPathname();

        try {
            $generated = IOFactory::load($path);
            $sheet = $generated->getSheetByName('TPL_RINCIAN');

            $this->assertNotNull($sheet);
            $this->assertSame('SD Negeri Generator Uji', $sheet->getCell('A1')->getValue());
            $this->assertSame('001/SPJ/2026', $sheet->getCell('A2')->getValue());
            $this->assertSame('BKU-001', $sheet->getCell('A3')->getValue());
            $this->assertSame(1000, $sheet->getCell('A4')->getValue());
            $this->assertMatchesRegularExpression('/^\d+$/', (string) $sheet->getCell('A4')->getValue());
            $this->assertSame('1', (string) $sheet->getCell('A6')->getValue());
            $this->assertSame('Barang generator uji', $sheet->getCell('B6')->getValue());
            $this->assertSame([], app(SpjUnresolvedPlaceholderGuard::class)->findInFile('RINCIAN_BELANJA', $path, 'xlsx'));
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->assertSame($documentsBefore, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame($sequencesBefore, DB::connection('school')->table('document_number_sequences')->count());
    }

    public function test_generated_docx_is_openable_and_has_no_unresolved_markers(): void
    {
        $package = $this->package('BARANG', 2);
        $template = $this->docxTemplate();
        $response = app(SpjTemplateService::class)->download($template, $package, $this->school());
        $path = $response->getFile()->getPathname();

        try {
            $this->assertGreaterThan(0, filesize($path));
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $documentXml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertStringContainsString('SD Negeri Generator Uji', $documentXml);
            $this->assertStringContainsString('002/SPJ/2026', $documentXml);
            $this->assertSame([], app(SpjUnresolvedPlaceholderGuard::class)->findInFile('SPJ_CHECKLIST', $path, 'docx'));
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_rab_item_kind_is_bahan_for_material_rows_and_upah_for_labor_rows(): void
    {
        $package = $this->package('PEMELIHARAAN', 21);
        $school = $this->school();
        $template = $this->xlsxTemplate([
            'A1' => '{{ITEM_NO}}',
            'B1' => '{{JENIS_RAB}}',
            'C1' => '{{ITEM_URAIAN}}',
        ], 'RAB_PEMELIHARAAN', 'TPL_RAB_PEMELIHARAAN');

        $response = app(SpjTemplateService::class)
            ->downloadPackageExcel(collect([$template]), $package, $school);
        $path = $response->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheetByName('TPL_RAB_PEMELIHARAAN');
            $this->assertNotNull($sheet);
            $this->assertSame('Bahan', (string) $sheet->getCell('B1')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        // Cabang UPAH: rincian milik transaksi tenaga kerja.
        $service = app(SpjTemplateService::class);
        $method = new \ReflectionMethod($service, 'rabItemKind');
        $transaction = $package->transaction;
        $transaction->setAttribute('maintenance_labor_source_id', $transaction->getKey());
        $item = $transaction->items->firstOrFail();
        $this->assertSame('UPAH', $method->invoke($service, $package, $item));
    }

    public function test_rab_lists_material_and_labor_items_with_kind_labels(): void
    {
        $material = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'RAB-MAT-1',
            'no_bukti' => 'BKU-RAB-M',
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja bahan',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'BARANG',
            'source_key' => hash('sha256', 'RAB-MAT-1'),
        ]);
        $this->mirrorItem($material, [
            'source_item_id' => 'RAB-MAT-ITEM-1',
            'description' => 'Semen',
            'item_description' => 'Semen',
            'quantity' => 2,
            'unit' => 'zak',
            'unit_price' => 500,
            'amount' => 1000,
        ]);

        $labor = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'RAB-LAB-1',
            'no_bukti' => 'BKU-RAB-L',
            'transaction_date' => '2026-01-11',
            'description' => 'Upah kerja',
            'gross_amount' => 500,
            'tax_total' => 0,
            'net_amount' => 500,
            'spj_category' => 'PEMELIHARAAN',
            'source_key' => hash('sha256', 'RAB-LAB-1'),
        ]);
        $this->mirrorItem($labor, [
            'source_item_id' => 'RAB-LAB-ITEM-1',
            'description' => 'Upah tukang',
            'item_description' => 'Upah tukang',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 500,
            'amount' => 500,
        ]);
        $labor->update(['maintenance_material_transaction_id' => $material->id]);

        $package = $labor->spjPackage()->create([
            'document_number' => 'RAB/SPJ/2026',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
        app(SpjMaintenanceDocumentContextService::class)->apply($package);

        $values = app(SpjTemplateService::class)->placeholders($package, $this->school());
        $this->assertSame('1000', $values['NILAI_BAHAN']);
        $this->assertSame('500', $values['NILAI_UPAH']);
        $this->assertSame('1500', $values['NILAI_PEKERJAAN']);
        $this->assertNotSame('', trim($values['NILAI_BAHAN_TERBILANG']));
        $this->assertNotSame('', trim($values['NILAI_UPAH_TERBILANG']));
        $this->assertNotSame('', trim($values['NILAI_PEKERJAAN_TERBILANG']));

        $template = $this->xlsxTemplate([
            'A1' => '{{ITEM_NO}}',
            'B1' => '{{JENIS_RAB}}',
            'C1' => '{{ITEM_URAIAN}}',
        ], 'RAB_PEMELIHARAAN', 'TPL_RAB_PEMELIHARAAN');

        $response = app(SpjTemplateService::class)
            ->downloadPackageExcel(collect([$template]), $package, $this->school());
        $path = $response->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheetByName('TPL_RAB_PEMELIHARAAN');
            $this->assertNotNull($sheet);
            $kinds = [];
            foreach ([1, 2] as $row) {
                $kinds[] = (string) $sheet->getCell('B'.$row)->getValue();
            }
            sort($kinds);
            $this->assertSame(['Bahan', 'UPAH'], $kinds);
            $workbook->disconnectWorksheets();
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_labor_package_scalar_facts_come_from_own_transaction_not_material(): void
    {
        $material = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'KUIT-MAT-1',
            'no_bukti' => 'BKU-KUIT-M',
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja bahan',
            'gross_amount' => 3770000,
            'tax_total' => 373608,
            'net_amount' => 3396392,
            'spj_category' => 'BARANG',
            'source_key' => hash('sha256', 'KUIT-MAT-1'),
        ]);
        $this->mirrorItem($material, [
            'source_item_id' => 'KUIT-MAT-ITEM-1',
            'description' => 'Semen',
            'item_description' => 'Semen',
            'quantity' => 1,
            'unit' => 'zak',
            'unit_price' => 3770000,
            'amount' => 3770000,
        ]);

        $labor = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'KUIT-LAB-1',
            'no_bukti' => 'BKU-KUIT-L',
            'transaction_date' => '2026-01-11',
            'description' => 'Upah kerja',
            'gross_amount' => 1680000,
            'tax_total' => 0,
            'net_amount' => 1680000,
            'spj_category' => 'PEMELIHARAAN',
            'source_key' => hash('sha256', 'KUIT-LAB-1'),
        ]);
        $this->mirrorItem($labor, [
            'source_item_id' => 'KUIT-LAB-ITEM-1',
            'description' => 'Upah tukang',
            'item_description' => 'Upah tukang',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 1680000,
            'amount' => 1680000,
        ]);
        $labor->update(['maintenance_material_transaction_id' => $material->id]);

        $package = $labor->spjPackage()->create([
            'document_number' => 'KUIT/SPJ/2026',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
        app(SpjMaintenanceDocumentContextService::class)->apply($package);

        // Rincian boleh meminjam milik material, tetapi fakta skalar paket
        // (kuitansi) tetap milik transaksi pemeliharaan sendiri.
        $this->assertSame('BKU-KUIT-L', $package->transaction->sourceValue('no_bukti'));
        $this->assertEquals(1680000, $package->transaction->sourceValue('gross_amount'));
        $this->assertCount(1, $package->transaction->items);
    }

    public function test_package_multitemplate_excel_and_pdf_are_real_outputs_without_numbering_side_effects(): void
    {
        $package = $this->package('BARANG', 3);
        $school = $this->school();
        $templates = collect([
            $this->xlsxTemplate([
                'A1' => 'Rincian {{NOMOR_DOKUMEN}}',
                'A2' => '{{NAMA_SEKOLAH}}',
            ]),
            $this->xlsxTemplate([
                'A1' => 'Checklist {{NOMOR_DOKUMEN}}',
                'A2' => '{{NAMA_KEPALA_SEKOLAH}}',
            ], 'SPJ_CHECKLIST', 'TPL_CHECKLIST_SPJ'),
        ]);
        $documentsBefore = DB::connection('school')->table('spj_documents')->count();
        $sequencesBefore = DB::connection('school')->table('document_number_sequences')->count();

        app(SpjTemplateRenderPreflight::class)->assertAllRenderable($templates, $package, $school);
        $excelResponse = app(SpjTemplateService::class)->downloadPackageExcel($templates, $package, $school);
        $excelPath = $excelResponse->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($excelPath);
            $this->assertSame(['TPL_RINCIAN', 'TPL_CHECKLIST_SPJ'], $workbook->getSheetNames());
            $this->assertSame('Rincian 003/SPJ/2026', $workbook->getSheetByName('TPL_RINCIAN')?->getCell('A1')->getValue());
            $this->assertSame('Checklist 003/SPJ/2026', $workbook->getSheetByName('TPL_CHECKLIST_SPJ')?->getCell('A1')->getValue());
        } finally {
            if (is_file($excelPath)) {
                @unlink($excelPath);
            }
        }

        $pdfResponse = app(SpjTemplateService::class)->downloadPackagePdf($templates, $package, $school);
        $pdf = (string) $pdfResponse->getContent();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        $this->assertSame($documentsBefore, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame($sequencesBefore, DB::connection('school')->table('document_number_sequences')->count());
    }

    public function test_generated_xlsx_and_package_select_only_canonical_sheets_from_multi_sheet_master_source(): void
    {
        $package = $this->package('BARANG', 5);
        $school = $this->school();

        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', 'Rincian {{NOMOR_DOKUMEN}}');
        $book->createSheet()->setTitle('TPL_CHECKLIST_SPJ')->setCellValue('A1', 'Checklist {{NOMOR_DOKUMEN}}');
        $book->createSheet()->setTitle('MAP_PLACEHOLDER')->setCellValue('A1', 'Sheet teknis');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/master-generator-'.uniqid().'.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        $rincian = new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'RINCIAN_BELANJA',
            'name' => 'Rincian dari Master',
            'format' => 'xlsx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
        $checklist = new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'SPJ_CHECKLIST',
            'name' => 'Checklist dari Master',
            'format' => 'xlsx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);

        $service = app(SpjTemplateService::class);
        $singleResponse = $service->download($rincian, $package, $school);
        $singlePath = $singleResponse->getFile()->getPathname();

        try {
            $singleWorkbook = IOFactory::load($singlePath);
            $this->assertSame(['TPL_RINCIAN'], $singleWorkbook->getSheetNames());
            $this->assertSame('Rincian 005/SPJ/2026', $singleWorkbook->getActiveSheet()->getCell('A1')->getValue());
        } finally {
            if (is_file($singlePath)) {
                @unlink($singlePath);
            }
        }

        $packageResponse = $service->downloadPackageExcel(collect([$rincian, $checklist]), $package, $school);
        $packagePath = $packageResponse->getFile()->getPathname();

        try {
            $packageWorkbook = IOFactory::load($packagePath);
            $this->assertSame(['TPL_RINCIAN', 'TPL_CHECKLIST_SPJ'], $packageWorkbook->getSheetNames());
            $this->assertSame('Rincian 005/SPJ/2026', $packageWorkbook->getSheetByName('TPL_RINCIAN')?->getCell('A1')->getValue());
            $this->assertSame('Checklist 005/SPJ/2026', $packageWorkbook->getSheetByName('TPL_CHECKLIST_SPJ')?->getCell('A1')->getValue());
        } finally {
            if (is_file($packagePath)) {
                @unlink($packagePath);
            }
        }
    }

    public function test_package_output_selects_only_spreadsheet_templates_mapped_to_its_category(): void
    {
        $package = $this->package('HONOR_PEGAWAI', 6);
        $school = $this->school();

        $included = $this->xlsxTemplate([
            'A1' => 'Rincian {{NOMOR_DOKUMEN}}',
        ]);
        $included->applicable_categories = ['HONOR_PEGAWAI'];
        $included->save();

        $excluded = $this->xlsxTemplate([
            'A1' => 'Checklist {{NOMOR_DOKUMEN}}',
        ], 'SPJ_CHECKLIST', 'TPL_CHECKLIST_SPJ');
        $excluded->applicable_categories = ['BARANG'];
        $excluded->save();

        DocumentTemplate::query()->create([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'SPJ_COVER',
            'name' => 'Cover Word',
            'format' => 'docx',
            'file_path' => 'document-templates/cover-word.docx',
            'applicable_categories' => ['HONOR_PEGAWAI'],
            'is_active' => true,
        ]);

        $templates = app(SpjPackageTemplateSelector::class)->spreadsheetsForPackage($package);

        $this->assertSame(['RINCIAN_BELANJA'], $templates->pluck('document_type')->all());

        $response = app(SpjTemplateService::class)->downloadPackageExcel($templates, $package, $school);
        $path = $response->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($path);

            $this->assertSame(['TPL_RINCIAN'], $workbook->getSheetNames());
            $this->assertSame('Rincian 006/SPJ/2026', $workbook->getActiveSheet()->getCell('A1')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_preflight_rejects_unresolved_placeholder_before_preview_or_package_output(): void
    {
        $package = $this->package('BARANG', 4);
        $template = $this->xlsxTemplate([
            'A1' => '{{NOMOR_DOKUMEN}}',
            'A2' => '{{UNKNOWN_RELEASE_MARKER}}',
        ]);

        try {
            app(SpjTemplateRenderPreflight::class)->assertRenderable($template, $package, $this->school());
            $this->fail('Template dengan marker yang tidak dikenal seharusnya ditolak oleh preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('UNKNOWN_RELEASE_MARKER', $exception->getMessage());
            $this->assertStringContainsString('placeholder yang belum terisi', $exception->getMessage());
        }
    }

    public function test_all_six_canonical_categories_expose_resolved_common_generator_values(): void
    {
        $service = app(SpjTemplateService::class);
        $school = $this->school();

        foreach (SpjDocumentTypeRegistry::categories() as $index => $category) {
            $values = $service->placeholders($this->package($category, 10 + $index), $school);

            $this->assertSame($this->categoryLabel($category), $values['JENIS_SPJ']);
            $this->assertSame('SD Negeri Generator Uji', $values['NAMA_SEKOLAH']);
            $this->assertNotSame('', trim($values['NOMOR_DOKUMEN']));
            $this->assertNotSame('', trim($values['NOMOR_BUKTI']));
            $this->assertSame('1000', $values['NILAI_BRUTO']);
            $this->assertSame('100', $values['TOTAL_PAJAK']);
            $this->assertSame('900', $values['NILAI_DIBAYARKAN']);
            $this->assertNotSame('', trim($values['NILAI_TERBILANG_BRUTO']));
            foreach (['NILAI_BRUTO', 'TOTAL_PAJAK', 'NILAI_DIBAYARKAN'] as $placeholder) {
                $this->assertMatchesRegularExpression('/^\d+$/', $values[$placeholder]);
                $this->assertStringNotContainsString('Rp', $values[$placeholder]);
                $this->assertStringNotContainsString('.', $values[$placeholder]);
                $this->assertStringNotContainsString(',', $values[$placeholder]);
            }
            $this->assertSame('Kepala Sekolah Uji', $values['NAMA_KEPALA_SEKOLAH']);
            $this->assertSame('Bendahara Uji', $values['NAMA_BENDAHARA_BOSP']);
        }
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'SPPD' => 'SPPD',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            default => $category,
        };
    }

    private function package(string $category, int $suffix): SpjPackage
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => (string) $suffix,
            'no_bukti' => 'BKU-'.str_pad((string) $suffix, 3, '0', STR_PAD_LEFT),
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja generator uji',
            'payment_description' => 'Pembayaran generator uji',
            'payment_method' => 'tunai',
            'payment_reference' => 'REF-'.$suffix,
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Generator',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Barang',
            'recipient_name' => 'Penerima Uji',
            'vendor_name' => 'Penyedia Uji',
            'vendor_npwp' => '00.000.000.0-000.000',
            'gross_amount' => 1000,
            'ppn' => 100,
            'tax_total' => 100,
            'net_amount' => 900,
            'spj_category' => $category,
            'source_key' => hash('sha256', 'GENERATOR-'.$category.'-'.$suffix),
        ]);

        $this->mirrorItem($transaction, [
            'source_item_id' => 'ITEM-'.$suffix,
            'description' => 'Barang generator uji',
            'item_description' => 'Barang generator uji',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create([
            'document_number' => str_pad((string) $suffix, 3, '0', STR_PAD_LEFT).'/SPJ/2026',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
    }

    /** @param array<string,string> $cells */
    private function xlsxTemplate(
        array $cells,
        string $documentType = 'RINCIAN_BELANJA',
        string $sheetName = 'TPL_RINCIAN',
    ): DocumentTemplate {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($sheetName);
        foreach ($cells as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value);
        }

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/generator-'.uniqid().'.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => $documentType,
            'name' => 'Template Generator Uji',
            'format' => 'xlsx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
    }

    private function docxTemplate(): DocumentTemplate
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('{{NAMA_SEKOLAH}}');
        $section->addText('{{NOMOR_DOKUMEN}}');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/generator-'.uniqid().'.docx';
        WordIOFactory::createWriter($word, 'Word2007')->save(Storage::disk('local')->path($relativePath));

        return new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'SPJ_CHECKLIST',
            'name' => 'Template Word Generator Uji',
            'format' => 'docx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
    }

    private function school(): School
    {
        return new School([
            'npsn' => '12345678',
            'school_code' => 'SCH-UJI',
            'name' => 'SD Negeri Generator Uji',
            'address' => 'Jl. Pendidikan No. 1',
            'desa' => 'Desa Uji',
            'district' => 'Kecamatan Uji',
            'regency' => 'Kabupaten Uji',
            'province' => 'Sumatera Utara',
        ]);
    }
}
