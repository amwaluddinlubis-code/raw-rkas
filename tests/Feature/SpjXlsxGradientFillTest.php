<?php

namespace Tests\Feature;

use App\Services\SpjTemplateService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpjXlsxGradientFillTest extends TestCase
{
    public function test_missing_gradient_type_is_normalized_without_modifying_the_template(): void
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spj-gradient-test-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($temporaryDirectory, 0775, true));

        $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.xlsx';
        $roundTripPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'round-trip.xlsx';
        $spreadsheet = new Spreadsheet;
        $fill = $spreadsheet->getActiveSheet()->getStyle('B4')->getFill();
        $fill->setFillType(Fill::FILL_GRADIENT_LINEAR);
        $fill->setRotation(180);
        $fill->getStartColor()->setARGB('FFFF0000');
        $fill->getEndColor()->setARGB('FF0000FF');
        (new Xlsx($spreadsheet))->save($sourcePath);
        $spreadsheet->disconnectWorksheets();

        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($sourcePath));
        $stylesXml = $archive->getFromName('xl/styles.xml');
        $this->assertIsString($stylesXml);

        $stylesDocument = new \DOMDocument;
        $this->assertTrue($stylesDocument->loadXML($stylesXml));
        $stylesXPath = new \DOMXPath($stylesDocument);
        $stylesXPath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $gradientFill = $stylesXPath->query('//main:gradientFill')->item(0);
        $this->assertNotNull($gradientFill);
        $gradientFill->removeAttribute('type');
        $archive->addFromString('xl/styles.xml', (string) $stylesDocument->saveXML());
        $this->assertTrue($archive->close());

        $sourceHash = hash_file('sha256', $sourcePath);
        $service = new class extends SpjTemplateService
        {
            public function loadForTest(string $path): Spreadsheet
            {
                return $this->loadSpreadsheet($path);
            }
        };

        $loaded = $service->loadForTest($sourcePath);

        try {
            $loadedFill = $loaded->getActiveSheet()->getStyle('B4')->getFill();
            $this->assertSame(Fill::FILL_GRADIENT_LINEAR, $loadedFill->getFillType());
            $this->assertSame('FFFF0000', $loadedFill->getStartColor()->getARGB());
            $this->assertSame('FF0000FF', $loadedFill->getEndColor()->getARGB());
            $this->assertSame($sourceHash, hash_file('sha256', $sourcePath));

            (new Xlsx($loaded))->save($roundTripPath);
        } finally {
            $loaded->disconnectWorksheets();
        }

        $roundTrip = IOFactory::load($roundTripPath);

        try {
            $this->assertSame(
                Fill::FILL_GRADIENT_LINEAR,
                $roundTrip->getActiveSheet()->getStyle('B4')->getFill()->getFillType(),
            );
        } finally {
            $roundTrip->disconnectWorksheets();
            @unlink($sourcePath);
            @unlink($roundTripPath);
            @rmdir($temporaryDirectory);
        }
    }
}
