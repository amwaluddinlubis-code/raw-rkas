<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use RuntimeException;
use ZipArchive;

/**
 * Preflight murah sebelum download: gagal-cepat bila template tidak aktif,
 * file hilang/rusak, sheet canonical absen, atau ada marker yang tidak
 * dikenal — tanpa me-render workbook penuh per template.
 *
 * Render penuh tetap terjadi tepat sekali pada download sungguhan
 * (yang kemudian divalidasi guard/output validator), sehingga preflight
 * tidak lagi melipatgandakan biaya IO workbook.
 */
final class SpjTemplateRenderPreflight
{
    public function __construct(
        private readonly SpjTemplateService $templates,
    ) {}

    public function assertRenderable(DocumentTemplate $template, SpjPackage $package, School $school, ?array $values = null): void
    {
        $label = 'Dokumen '.(string) $template->document_type;

        if (! $template->is_active) {
            throw new RuntimeException($label.' memakai template yang sudah tidak aktif.');
        }

        $source = $this->templates->sourcePath($template);
        if (! is_file($source)) {
            throw new RuntimeException('Berkas template '.$label.' tidak ditemukan. Unggah ulang template ini.');
        }

        $values ??= $this->templates->placeholders($package, $school);
        $format = strtolower((string) $template->format);

        if ($format === 'xlsx') {
            $this->assertSpreadsheetMarkers($template, $source, $values);
        } elseif ($format === 'docx') {
            $this->assertWordMarkers($template, $source, $values);
        } else {
            throw new RuntimeException('Format template '.$label.' tidak didukung.');
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function assertAllRenderable(Collection $templates, SpjPackage $package, School $school): void
    {
        // Nilai placeholder hanya bergantung pada paket+sekolah, jadi
        // dihitung sekali untuk seluruh template (bukan per template).
        $values = null;
        foreach ($templates as $template) {
            if ($values === null) {
                $values = $this->templates->placeholders($package, $school);
            }
            $this->assertRenderable($template, $package, $school, $values);
        }
    }

    /** @param array<string, mixed> $values */
    private function assertSpreadsheetMarkers(DocumentTemplate $template, string $source, array $values): void
    {
        $label = 'Dokumen '.(string) $template->document_type;

        try {
            $names = (new XlsxReader)->listWorksheetNames($source);
        } catch (\Throwable $exception) {
            throw new RuntimeException($label.' tidak dapat dibaca sebagai workbook Excel: '.$exception->getMessage());
        }

        $sheetName = $this->selectSheetName($template, $names);

        $reader = new XlsxReader;
        $reader->setReadDataOnly(true);
        try {
            $spreadsheet = $reader->load($source);
        } catch (\Throwable $exception) {
            throw new RuntimeException($label.' tidak dapat dibaca sebagai workbook Excel: '.$exception->getMessage());
        }

        try {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                throw new RuntimeException('Sheet canonical '.$sheetName.' tidak ditemukan pada template '.$label.'.');
            }

            $markers = [];
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $value = $sheet->getCell($coordinate)->getValue();
                if (! is_string($value)) {
                    continue;
                }
                foreach ($this->extractMarkers($value) as $marker) {
                    $markers[$marker] = true;
                }
            }
            $this->assertNoUnknownMarkers($label, array_keys($markers), $values);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param array<string, mixed> $values */
    private function assertWordMarkers(DocumentTemplate $template, string $source, array $values): void
    {
        $label = 'Dokumen '.(string) $template->document_type;

        $zip = new ZipArchive;
        if ($zip->open($source) !== true) {
            throw new RuntimeException($label.' tidak dapat dibuka sebagai dokumen Word.');
        }

        $content = '';
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || ! preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $name)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    $content .= ' '.html_entity_decode((string) preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } finally {
            $zip->close();
        }

        $this->assertNoUnknownMarkers($label, $this->extractMarkers($content), $values);
    }

    /**
     * Pilih sheet canonical mengikuti aturan guard hasil generate:
     * sheet canonical registry, fallback satu sheet non-teknis.
     *
     * @param  array<int, string>  $names
     */
    private function selectSheetName(DocumentTemplate $template, array $names): string
    {
        $canonical = SpjDocumentTypeRegistry::canonical((string) $template->document_type);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;
        $expected = $definition ? (string) $definition['sheet'] : '';

        if ($expected !== '' && in_array($expected, $names, true)) {
            return $expected;
        }

        $technical = array_fill_keys(SpjDocumentTypeRegistry::technicalSheets(), true);
        $candidates = array_values(array_filter($names, static fn (string $name): bool => ! isset($technical[$name])));

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw new RuntimeException('Sheet canonical '.$expected.' tidak ditemukan pada template Dokumen '.(string) $template->document_type.'.');
    }

    /**
     * Marker yang sah: kunci nilai placeholder, atau awalan baris berulang
     * ITEM dan UPAH yang dikonsumsi engine repeating-row (bukan scalar).
     *
     * @param  array<int, string>  $markers
     * @param  array<string, mixed>  $values
     */
    private function assertNoUnknownMarkers(string $label, array $markers, array $values): void
    {
        $unknown = [];
        foreach ($markers as $marker) {
            if (array_key_exists($marker, $values)) {
                continue;
            }
            if (str_starts_with($marker, 'ITEM_') || str_starts_with($marker, 'UPAH_')) {
                continue;
            }
            $unknown[] = $marker;
        }

        if ($unknown !== []) {
            throw new RuntimeException(
                $label.' masih memiliki placeholder yang belum terisi: '.implode(', ', $unknown).'.'
            );
        }
    }

    /** @return array<int, string> */
    private function extractMarkers(string $content): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($marker) => strtoupper(trim((string) $marker)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
