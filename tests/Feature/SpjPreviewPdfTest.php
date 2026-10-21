<?php

namespace Tests\Feature;

use App\Services\SpjExcelComPdfRenderer;
use App\Services\SpjSpreadsheetPdfConverter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpjPreviewPdfTest extends TestCase
{
    public function test_office_to_pdf_conversion_returns_pdf_bytes_when_a_renderer_is_available(): void
    {
        config()->set('spj.xlsx_pdf_renderer', 'libreoffice');
        $source = tempnam(sys_get_temp_dir(), 'spj-preview-test-');
        $this->assertIsString($source);
        @unlink($source);
        $source .= '.xlsx';

        try {
            $workbook = new Spreadsheet;
            $workbook->getActiveSheet()->setCellValue('A1', 'Pratinjau');
            (new Xlsx($workbook))->save($source);
            $workbook->disconnectWorksheets();

            $converter = app(SpjSpreadsheetPdfConverter::class);
            $result = $converter->convertFile($source);

            if ($converter->isAvailable()) {
                $this->assertIsString($result);
                $this->assertStringStartsWith('%PDF', $result);
            } else {
                $this->assertNull($result);
            }
        } finally {
            if (is_file($source)) {
                @unlink($source);
            }
        }
    }

    public function test_xlsx_uses_excel_com_when_the_renderer_is_available(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spj-excel-renderer-test-');
        $this->assertIsString($source);
        @unlink($source);
        $source .= '.xlsx';
        file_put_contents($source, 'test');

        $renderer = new class extends SpjExcelComPdfRenderer
        {
            public ?string $sourcePath = null;

            public function isAvailable(): bool
            {
                return true;
            }

            public function convertFile(string $sourcePath): ?string
            {
                $this->sourcePath = $sourcePath;

                return "%PDF-1.4\nexcel-com";
            }
        };
        $this->app->instance(SpjExcelComPdfRenderer::class, $renderer);
        config()->set('spj.xlsx_pdf_renderer', 'auto');

        try {
            $pdf = app(SpjSpreadsheetPdfConverter::class)->convertFile($source);

            $this->assertSame("%PDF-1.4\nexcel-com", $pdf);
            $this->assertSame($source, $renderer->sourcePath);
        } finally {
            @unlink($source);
        }
    }

    public function test_excel_com_failures_are_not_silently_replaced_by_libreoffice(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spj-excel-renderer-test-');
        $this->assertIsString($source);
        @unlink($source);
        $source .= '.xlsx';
        file_put_contents($source, 'test');

        $renderer = new class extends SpjExcelComPdfRenderer
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function convertFile(string $sourcePath): ?string
            {
                throw new \RuntimeException('Excel COM test failure.');
            }
        };
        $this->app->instance(SpjExcelComPdfRenderer::class, $renderer);
        config()->set('spj.xlsx_pdf_renderer', 'auto');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Excel COM test failure.');

        try {
            app(SpjSpreadsheetPdfConverter::class)->convertFile($source);
        } finally {
            @unlink($source);
        }
    }

    public function test_explicit_excel_com_configuration_fails_when_the_renderer_is_unavailable(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'spj-excel-renderer-test-');
        $this->assertIsString($source);
        @unlink($source);
        $source .= '.xlsx';
        file_put_contents($source, 'test');

        $renderer = new class extends SpjExcelComPdfRenderer
        {
            public function isAvailable(): bool
            {
                return false;
            }
        };
        $this->app->instance(SpjExcelComPdfRenderer::class, $renderer);
        config()->set('spj.xlsx_pdf_renderer', 'excel_com');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Renderer Excel COM dipilih');

        try {
            app(SpjSpreadsheetPdfConverter::class)->convertFile($source);
        } finally {
            @unlink($source);
        }
    }

    public function test_preview_page_embeds_print_pdf_and_states_archive_parity(): void
    {
        $view = file_get_contents(resource_path('views/spj-documents/template-preview.blade.php'));

        $this->assertStringContainsString('<embed', $view);
        $this->assertStringContainsString('previewPdfUrl', $view);
        $this->assertStringContainsString('createObjectURL', $view);
        $this->assertStringContainsString('Cetak langsung', $view);
        $this->assertStringContainsString('spj-print-frame', $view);
        $this->assertStringContainsString('belum menjadi SPJ asli', $view);
        $this->assertStringContainsString('Buka &amp; Cetak PDF', $view);
        $this->assertStringContainsString('sama dengan berkas unduhan', $view);
        $this->assertStringNotContainsString('window.print()', $view);
    }

    public function test_preview_page_is_honest_when_pdf_preview_is_unavailable(): void
    {
        $view = file_get_contents(resource_path('views/spj-documents/template-preview.blade.php'));

        $this->assertStringContainsString('LibreOffice', $view);
        $this->assertStringContainsString('Unduh dokumen asli (Excel/Word) tetap tersedia', $view);
    }

    public function test_workspace_preview_button_is_not_gated_to_excel_templates(): void
    {
        $view = file_get_contents(resource_path('views/spj/partials/package/documents.blade.php'));

        $this->assertStringContainsString('data-template-preview', $view);
        $this->assertStringContainsString('Arsip Excel', $view);
        $this->assertStringContainsString('Arsip Paket PDF', $view);
        $this->assertStringNotContainsString("@if(strtolower(\$template->format) === 'xlsx')<button type=\"button\" data-template-preview", $view);
    }

    public function test_preview_use_case_exposes_inline_pdf_routes(): void
    {
        $this->assertTrue(app('router')->has('spj.preview-template-pdf'));
        $this->assertTrue(app('router')->has('spj.preview-package-pdf'));
    }

    public function test_preview_page_does_not_render_pdf_twice_for_readiness(): void
    {
        $source = file_get_contents(app_path('UseCases/Spj/SpjDocumentUseCase.php'));
        $this->assertIsString($source);

        $start = (int) strpos($source, 'function previewTemplate(');
        $end = (int) strpos($source, 'function previewTemplatePdf(');
        $previewTemplateBlock = substr($source, $start, $end - $start);
        $this->assertStringNotContainsString('previewTemplatePdfBytes', $previewTemplateBlock);
        $this->assertStringNotContainsString('SpjTemplateRenderPreflight', $previewTemplateBlock);
        $this->assertStringContainsString('isAvailable()', $previewTemplateBlock);

        $start = (int) strpos($source, 'function previewPackage(');
        $end = (int) strpos($source, 'function downloadTemplate(');
        $previewPackageBlock = substr($source, $start, $end - $start);
        $this->assertStringNotContainsString('SpjTemplateRenderPreflight', $previewPackageBlock);
    }

    public function test_pdf_preview_failures_return_an_error_instead_of_redirecting_back_into_the_preview_page(): void
    {
        $source = file_get_contents(app_path('UseCases/Spj/SpjDocumentUseCase.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("return response('Pratinjau PDF gagal dibuat: '.\$exception->getMessage(), 500);", $source);
        $this->assertStringContainsString("return response('Pratinjau PDF paket gagal dibuat: '.\$exception->getMessage(), 500);", $source);
        $this->assertStringNotContainsString("redirect()->route('spj.preview-package', [\$packageId])->with('error', 'Pratinjau PDF paket gagal dibuat", $source);
    }
}
