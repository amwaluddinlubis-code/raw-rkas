<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export XLSX laporan Kertas Kerja RKAS dengan struktur kolom yang sama
 * seperti keluaran PDF (dan ARKAS): kop, penerimaan, belanja hierarkis,
 * jumlah, dan blok tanda tangan.
 */
final class RkasReportExcelService
{
    /** @param array<string,mixed> $payload */
    public function export(array $payload): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr('RKAS '.ucfirst($payload['scope']), 0, 31));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_FOLIO);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $row = 1;
        $lastCol = $this->columnCount($payload['scope']);
        $lastLetter = $this->col($lastCol);

        $sheet->mergeCells("A{$row}:{$lastLetter}{$row}");
        $sheet->setCellValue("A{$row}", $payload['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastLetter}{$row}");
        $sheet->setCellValue("A{$row}", 'TAHUN ANGGARAN : '.$payload['year']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        $sheet->mergeCells("A{$row}:{$lastLetter}{$row}");
        $sheet->setCellValue("A{$row}", $payload['revision_label'].($payload['revision_date'] !== '-' ? ' · '.$payload['revision_date'] : ''));
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        $school = $payload['school'];
        foreach ([
            ['NPSN', $school['npsn']],
            ['Nama Sekolah', $school['name']],
            ['Alamat', $school['address']],
            ['Kabupaten', $school['regency']],
            ['Provinsi', $school['province']],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", ':');
            $sheet->setCellValue("C{$row}", $value);
            $row++;
        }
        if (in_array($payload['scope'], ['triwulan', 'triwulan-bulanan'], true)) {
            $sheet->setCellValue("A{$row}", 'Triwulan');
        } elseif ($payload['scope'] === 'tahap') {
            $sheet->setCellValue("A{$row}", 'Tahap');
        } elseif ($payload['scope'] === 'bulanan') {
            $sheet->setCellValue("A{$row}", 'Bulan');
        }
        if (in_array($payload['scope'], ['triwulan', 'triwulan-bulanan', 'tahap', 'bulanan'], true)) {
            $sheet->setCellValue("B{$row}", ':');
            $sheet->setCellValue("C{$row}", $payload['scope_label'].($payload['scope'] === 'bulanan' ? (string) $payload['year'] : ''));
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Sumber Dana');
        $sheet->setCellValue("B{$row}", ':');
        $sheet->setCellValue("C{$row}", $payload['fund_name']);
        $row += 2;

        $sheet->setCellValue("A{$row}", $payload['penerimaan_heading']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", $payload['penerimaan_source_label']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->fromArray([['No. Kode', 'Penerimaan', 'Jumlah']], null, "A{$row}");
        $this->headerRow($sheet, $row, 3);
        $row++;
        $keuangan = $payload['scope'] === 'tahunan'
            ? $payload['penerimaan']
            : array_values(array_filter($payload['penerimaan'], fn (array $item): bool => (bool) $item['active']));
        foreach ($keuangan as $item) {
            $sheet->setCellValue("A{$row}", $item['code']);
            $sheet->setCellValue("B{$row}", $item['label'].($item['active'] ? '' : ' **'));
            $sheet->setCellValue("C{$row}", $item['amount']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $this->borderRow($sheet, $row, 3);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Total Penerimaan');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", $payload['totals']['jumlah']);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $this->borderRow($sheet, $row, 3);
        $row++;
        $sheet->setCellValue("A{$row}", '* belum pengesahan, ** belum aktivasi anggaran, ~ penerimaan dan belanja tidak sesuai');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(8);
        $row += 2;

        $sheet->setCellValue("A{$row}", 'B. BELANJA');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $row = $this->belanjaTable($sheet, $payload, $row);

        $row += 2;
        $third = (int) floor($lastCol / 3);
        $treasurerStart = $lastCol - 2;
        $sheet->mergeCells($this->col($treasurerStart).$row.':'.$lastLetter.$row);
        $sheet->setCellValue($this->col($treasurerStart).$row, $payload['place_date']);
        $sheet->getStyle($this->col($treasurerStart).$row.':'.$lastLetter.$row)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        $sheet->setCellValue('A'.$row, 'Komite Sekolah');
        $sheet->setCellValue($this->col($third).$row, 'Kepala Sekolah');
        $sheet->setCellValue($this->col($lastCol - 2).$row, 'Bendahara Sekolah');
        $sheet->getStyle("A{$row}:{$lastLetter}{$row}")->getFont()->setBold(true);
        $row += 10;
        $committeeStart = 1;
        $principalStart = $third;
        $sheet->setCellValue($this->col($committeeStart).$row, (string) ($payload['signatories']['committee_name'] ?? ''));
        $sheet->setCellValue($this->col($principalStart).$row, (string) ($payload['signatories']['principal_name'] ?? ''));
        $sheet->setCellValue($this->col($treasurerStart).$row, (string) ($payload['signatories']['treasurer_name'] ?? ''));
        foreach ([
            [$committeeStart, $principalStart - 1],
            [$principalStart, $treasurerStart - 1],
            [$treasurerStart, $lastCol],
        ] as [$start, $end]) {
            $lineEnd = (int) floor(($start + $end) / 2);
            $sheet->getStyle($this->col($start).$row.':'.$this->col($lineEnd).$row)
                ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        }
        $this->addSignatureDrawing($sheet, $payload['signatories']['committee_signature_path'] ?? '', $this->col($committeeStart), $row - 5, 'Komite');
        $this->addSignatureDrawing($sheet, $payload['signatories']['principal_signature_path'] ?? '', $this->col($principalStart), $row - 5, 'Kepala Sekolah');
        $this->addSignatureDrawing($sheet, $payload['signatories']['treasurer_signature_path'] ?? '', $this->col($treasurerStart), $row - 5, 'Bendahara');
        $sheet->getStyle("A{$row}:{$lastLetter}{$row}")->getFont()->setBold(true);
        $row++;
        if (trim((string) ($payload['signatories']['principal_nip'] ?? '')) !== '') {
            $sheet->setCellValue($this->col($third).$row, 'NIP: '.$payload['signatories']['principal_nip']);
        }
        if (trim((string) ($payload['signatories']['treasurer_nip'] ?? '')) !== '') {
            $sheet->setCellValue($this->col($lastCol - 2).$row, 'NIP: '.$payload['signatories']['treasurer_nip']);
        }

        foreach (range(1, $lastCol) as $col) {
            $sheet->getColumnDimension($this->col($col))->setAutoSize(true);
        }
        if ($payload['scope'] !== 'tahunan') {
            // Kolom Volume (E) dan Satuan (F) dibuat sama-sama ringkas.
            $detailWidths = match ($payload['scope']) {
                'triwulan' => ['D' => 32, 'E' => 6, 'F' => 9],
                'triwulan-bulanan' => ['D' => 34, 'E' => 6, 'F' => 9],
                'tahap' => ['D' => 38, 'E' => 6, 'F' => 9],
                default => ['D' => 45, 'E' => 6, 'F' => 9],
            };
            foreach ($detailWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setAutoSize(false);
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getStyle('E1:F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $footTag = str_replace('&', '&&', $payload['footer_tag'].' - NPSN : '.$payload['school']['npsn'].', '.$payload['school']['name']);
        $sheet->getHeaderFooter()->setOddFooter('&L&8 '.$footTag.'&R&8 Halaman &P dari &N');

        $path = tempnam(sys_get_temp_dir(), 'rkas-laporan-').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function columnCount(string $scope): int
    {
        return match ($scope) {
            'tahunan' => 17,
            'triwulan' => 12,
            'tahap' => 10,
            'triwulan-bulanan' => 11,
            default => 8,
        };
    }

    private function col(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function headerRow(object $sheet, int $row, int $cols): void
    {
        $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->borderRow($sheet, $row, $cols);
    }

    private function borderRow(object $sheet, int $row, int $cols): void
    {
        $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /** @param array<string,mixed> $payload */
    private function belanjaTable(object $sheet, array $payload, int $row): int
    {
        $scope = $payload['scope'];
        $cols = $this->columnCount($scope);

        if ($scope === 'tahunan') {
            $headers = ['No. Urut', 'Kode Rekening', 'Kode Kegiatan', 'Uraian Kegiatan', 'Jumlah'];
            foreach (RkasReportService::FUND_COLUMNS as $fundCol) {
                array_push($headers, $fundCol.' - Operasi', $fundCol.' - Modal');
            }
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValue($this->col($col).$row, $header);
                $col++;
            }
            $this->headerRow($sheet, $row, $cols);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($row, $row);
            $row++;
            foreach ($payload['lines'] as $line) {
                $values = [$line['no'], $this->accountCell($line), $line['activity_code'], $line['name'], $line['jumlah']];
                for ($c = 0; $c < 6; $c++) {
                    $values[] = $c === $payload['fund_column'] ? $line['operasi'] : 0;
                    $values[] = $c === $payload['fund_column'] ? $line['modal'] : 0;
                }
                $row = $this->dataRow($sheet, $row, $cols, $values, $line['level']);
            }
            $totals = [$payload['totals']['jumlah']];
            $row = $this->dataRow($sheet, $row, $cols, array_merge(['Jumlah', '', '', '', $payload['totals']['jumlah']], $this->fundTotals($payload)), 'total');
        } else {
            $headers = ['No. Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif Harga', 'Jumlah'];
            $periodHeaders = match ($scope) {
                'triwulan' => ['1', '2', '3', '4'],
                'triwulan-bulanan' => array_map(fn (int $month): string => RkasReportService::MONTH_NAMES[$month - 1], $payload['quarter_months']),
                'tahap' => ['Tahap 1', 'Tahap 2'],
                default => [],
            };
            $col = 1;
            foreach (array_merge($headers, $periodHeaders) as $header) {
                $sheet->setCellValue($this->col($col).$row, $header);
                $col++;
            }
            $this->headerRow($sheet, $row, $cols);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($row, $row);
            $row++;
            foreach ($payload['lines'] as $line) {
                $values = [
                    $line['no'], $this->accountCell($line), $line['activity_code'], $line['name'],
                    $line['level'] === 'item' ? $line['volume'] : null,
                    $line['level'] === 'item' ? $line['unit'] : null,
                    $line['level'] === 'item' ? $line['unit_price'] : null,
                    in_array($scope, ['bulanan', 'triwulan-bulanan'], true) ? $line['scoped'] : $line['jumlah'],
                ];
                if ($scope === 'triwulan') {
                    array_push($values, $line['tw'][1], $line['tw'][2], $line['tw'][3], $line['tw'][4]);
                } elseif ($scope === 'triwulan-bulanan') {
                    foreach ($payload['quarter_months'] as $month) {
                        $values[] = $line['months'][$month] ?? 0;
                    }
                } elseif ($scope === 'tahap') {
                    array_push($values, $line['tahap'][0], $line['tahap'][1]);
                }
                $row = $this->dataRow($sheet, $row, $cols, $values, $line['level']);
            }
            $values = ['Jumlah', '', '', '', '', '', '', in_array($scope, ['bulanan', 'triwulan-bulanan'], true) ? $payload['totals']['scoped'] : $payload['totals']['jumlah']];
            if ($scope === 'triwulan') {
                array_push($values, $payload['totals']['tw'][1], $payload['totals']['tw'][2], $payload['totals']['tw'][3], $payload['totals']['tw'][4]);
            } elseif ($scope === 'triwulan-bulanan') {
                foreach ($payload['quarter_months'] as $month) {
                    $values[] = $payload['totals']['months'][$month] ?? 0;
                }
            } elseif ($scope === 'tahap') {
                array_push($values, $payload['totals']['tahap'][0], $payload['totals']['tahap'][1]);
            }
            $row = $this->dataRow($sheet, $row, $cols, $values, 'total');
        }

        return $row;
    }

    /** @param array<string,mixed> $line */
    private function accountCell(array $line): string
    {
        return in_array($line['level'], ['item', 'account'], true) ? (string) $line['account_code'] : '';
    }

    /** @param array<string,mixed> $payload @return array<int,float> */
    private function fundTotals(array $payload): array
    {
        $values = [];
        for ($c = 0; $c < 6; $c++) {
            $values[] = $c === $payload['fund_column'] ? (float) $payload['totals']['operasi'] : 0;
            $values[] = $c === $payload['fund_column'] ? (float) $payload['totals']['modal'] : 0;
        }

        return $values;
    }

    /** @param array<int,mixed> $values */
    private function dataRow(object $sheet, int $row, int $cols, array $values, string $level): int
    {
        $col = 1;
        foreach ($values as $value) {
            $cell = $this->col($col).$row;
            $sheet->setCellValue($cell, $value);
            if (is_numeric($value) && $col > 4) {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
            }
            $col++;
        }
        if (in_array($level, ['program', 'subprogram', 'activity', 'total'], true)) {
            $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getFont()->setBold(true);
            $fill = match ($level) {
                'program' => 'F8D7DA',
                'subprogram' => 'D4EDDA',
                'activity' => 'FFF3CD',
                default => 'E2E3E5',
            };
            $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        }
        $this->borderRow($sheet, $row, $cols);

        return $row + 1;
    }

    private function addSignatureDrawing(object $sheet, string $path, string $coordinate, int $row, string $name): void
    {
        $disk = Storage::disk('local');
        if (trim($path) === '' || ! $disk->exists($path)) {
            return;
        }

        try {
            $drawing = new Drawing;
            $drawing->setName('Tanda tangan '.$name);
            $drawing->setDescription('Tanda tangan '.$name);
            $drawing->setPath($disk->path($path));
            $drawing->setHeight(45);
            $drawing->setCoordinates($coordinate.$row);
            $drawing->setWorksheet($sheet);
        } catch (\Throwable) {
            // File tanda tangan opsional; laporan tetap dibuat tanpa gambar.
        }
    }
}
