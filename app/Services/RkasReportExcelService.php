<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
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
        if ($payload['scope'] === 'triwulan') {
            $sheet->setCellValue("A{$row}", 'Triwulan');
        } elseif ($payload['scope'] === 'tahap') {
            $sheet->setCellValue("A{$row}", 'Tahap');
        } elseif ($payload['scope'] === 'bulanan') {
            $sheet->setCellValue("A{$row}", 'Bulan');
        }
        if (in_array($payload['scope'], ['triwulan', 'tahap', 'bulanan'], true)) {
            $sheet->setCellValue("B{$row}", ':');
            $sheet->setCellValue("C{$row}", $payload['scope_label'].($payload['scope'] === 'bulanan' ? (string) $payload['year'] : ''));
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Sumber Dana');
        $sheet->setCellValue("B{$row}", ':');
        $sheet->setCellValue("C{$row}", $payload['fund_name']);
        $row += 2;

        $sheet->setCellValue("A{$row}", 'A. PENERIMAAN');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", 'Sumber Dana :');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->fromArray([['No. Kode', 'Penerimaan', 'Jumlah']], null, "A{$row}");
        $this->headerRow($sheet, $row, 3);
        $row++;
        foreach ($payload['penerimaan'] as $item) {
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
        $sheet->setCellValue($this->col($lastCol - 2).$row, $payload['place_date']);
        $row++;
        $third = (int) floor($lastCol / 3);
        $sheet->setCellValue('A'.$row, 'Komite Sekolah');
        $sheet->setCellValue($this->col($third).$row, 'Kepala Sekolah');
        $sheet->setCellValue($this->col($lastCol - 2).$row, 'Bendahara Sekolah');
        $sheet->getStyle("A{$row}:{$lastLetter}{$row}")->getFont()->setBold(true);
        $row += 4;
        $sheet->setCellValue('A'.$row, '');
        $sheet->setCellValue($this->col($third).$row, (string) ($payload['signatories']['principal_name'] ?? ''));
        $sheet->setCellValue($this->col($lastCol - 2).$row, (string) ($payload['signatories']['treasurer_name'] ?? ''));
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

        $path = tempnam(sys_get_temp_dir(), 'rkas-laporan-').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function columnCount(string $scope): int
    {
        return match ($scope) {
            'tahunan' => 17,
            'triwulan' => 13,
            'tahap' => 11,
            default => 9,
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
        $sheet->getStyle('A'.$row.':'.$this->col($cols).$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
            $periodHeaders = $scope === 'triwulan' ? ['1', '2', '3', '4'] : ($scope === 'tahap' ? ['Tahap 1', 'Tahap 2'] : []);
            $col = 1;
            foreach (array_merge($headers, $periodHeaders) as $header) {
                $sheet->setCellValue($this->col($col).$row, $header);
                $col++;
            }
            $this->headerRow($sheet, $row, $cols);
            $row++;
            foreach ($payload['lines'] as $line) {
                $values = [
                    $line['no'], $this->accountCell($line), $line['activity_code'], $line['name'],
                    $line['level'] === 'item' ? $line['volume'] : null,
                    $line['level'] === 'item' ? $line['unit'] : null,
                    $line['level'] === 'item' ? $line['unit_price'] : null,
                    $scope === 'bulanan' ? $line['scoped'] : $line['jumlah'],
                ];
                if ($scope === 'triwulan') {
                    array_push($values, $line['tw'][1], $line['tw'][2], $line['tw'][3], $line['tw'][4]);
                } elseif ($scope === 'tahap') {
                    array_push($values, $line['tahap'][0], $line['tahap'][1]);
                }
                $row = $this->dataRow($sheet, $row, $cols, $values, $line['level']);
            }
            $values = ['Jumlah', '', '', '', '', '', '', $scope === 'bulanan' ? $payload['totals']['scoped'] : $payload['totals']['jumlah']];
            if ($scope === 'triwulan') {
                array_push($values, $payload['totals']['tw'][1], $payload['totals']['tw'][2], $payload['totals']['tw'][3], $payload['totals']['tw'][4]);
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
}
