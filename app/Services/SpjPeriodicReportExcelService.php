<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpjPeriodicReportExcelService
{
    public function export(array $payload): string
    {
        $book = new Spreadsheet;
        $summary = $book->getActiveSheet()->setTitle('Ringkasan');
        $this->writeSummary($summary, $payload);

        $report = $book->createSheet()->setTitle('Laporan');
        if (($payload['presentation'] ?? '') === 'rekap_bosp') {
            $this->writeBospRecap($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'bpk_bos') {
            $this->writeBpk($report, $payload);
        } else {
            $this->writeTable($report, $payload);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'spj-periodic-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('File sementara Excel tidak dapat dibuat.');
        }
        $path = $temporaryPath.'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    private function writeSummary(object $sheet, array $payload): void
    {
        $summary = $payload['summary'];
        $rows = [
            ['Laporan', $payload['report']['label']],
            ['Periode', $summary['period_label']],
            ['Tanggal Mulai', $summary['date_from'] ?? '-'],
            ['Tanggal Akhir', $summary['date_to'] ?? '-'],
            ['Tahun Anggaran', $payload['year']->year],
            ['Sekolah', $payload['school']->name],
            ['NPSN', $payload['school']->npsn ?? '-'],
            ['Sumber Dana', $payload['fundSource']],
            [],
            ['Jumlah Transaksi', (int) $summary['transaction_count']],
            ['Nilai Bruto', (float) $summary['gross']],
            ['Total Pajak', (float) $summary['tax']],
            ['Nilai Dibayarkan', (float) $summary['net']],
            ['PPN', (float) $summary['ppn']],
            ['PPh 21', (float) $summary['pph21']],
            ['PPh 22', (float) $summary['pph22']],
            ['PPh 23', (float) $summary['pph23']],
            ['PPh 4(2)', (float) $summary['pph4']],
            ['SSPD', (float) $summary['sspd']],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $this->styleHeader($sheet, 'A1:B1');
        $sheet->getStyle('A1:A'.count($rows))->getFont()->setBold(true);
        $sheet->getStyle('B10:B'.count($rows))->getNumberFormat()->setFormatCode('#,##0');
        $this->autoSize($sheet, 2);
        $sheet->freezePane('A2');
    }

    private function writeTable(object $sheet, array $payload): void
    {
        $columns = $payload['columns'];
        $rows = $payload['rows'];
        $headers = array_column($columns, 'label');
        $sheet->fromArray([$headers], null, 'A1');

        foreach ($rows as $rowIndex => $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column['key']] ?? '';
                $values[] = $column['type'] === 'stacked' ? strip_tags((string) $value) : $value;
            }
            $sheet->fromArray([$values], null, 'A'.($rowIndex + 2));
        }

        $this->styleHeader($sheet, 'A1:'.$this->columnLetter(count($headers)).'1');
        $this->formatColumns($sheet, $columns, count($rows) + 1);
        $sheet->freezePane('A2');
        $lastColumn = $this->columnLetter(max(1, count($headers)));
        $lastRow = max(1, count($rows) + 1);
        $sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
        $this->autoSize($sheet, count($headers));
    }

    private function writeBospRecap(object $sheet, array $payload): void
    {
        $data = $payload['rekapBosp'];
        $headers = ['No', 'Standar Nasional Pendidikan'];
        for ($column = 1; $column <= 12; $column++) {
            $headers[] = 'Sub Program '.$column;
        }
        $headers[] = 'Jumlah';
        $sheet->fromArray([$headers], null, 'A1');

        $labels = BospRekapStandardMapper::standardRows();
        foreach ($labels as $index => $label) {
            $row = [$index + 1, $label];
            for ($column = 1; $column <= 12; $column++) {
                $row[] = (float) ($data['grid'][$index + 1][$column] ?? 0);
            }
            $row[] = (float) ($data['rowTotals'][$index + 1] ?? 0);
            $sheet->fromArray([$row], null, 'A'.($index + 2));
        }

        $totalRow = count($labels) + 2;
        $total = ['', 'JUMLAH'];
        for ($column = 1; $column <= 12; $column++) {
            $total[] = (float) ($data['colTotals'][$column] ?? 0);
        }
        $total[] = (float) ($data['grand'] ?? 0);
        $sheet->fromArray([$total], null, 'A'.$totalRow);
        $this->styleHeader($sheet, 'A1:'.$this->columnLetter(count($headers)).'1');
        $this->formatMoneyRange($sheet, 'C2:O'.$totalRow);
        $sheet->getStyle('A'.$totalRow.':O'.$totalRow)->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $this->autoSize($sheet, count($headers));
    }

    private function writeBpk(object $sheet, array $payload): void
    {
        $data = $payload['bpkData'];
        $sheet->fromArray([['Uraian', 'Jumlah']], null, 'A1');
        $row = 2;
        $line = static function (string $label, float $amount) use ($sheet, &$row): void {
            $sheet->fromArray([[$label, $amount]], null, 'A'.$row++);
        };
        $line('Saldo awal (Bank + Tunai)', (float) $data['openingBank'] + (float) $data['openingCash']);
        $line('Penerimaan dana BOS', (float) $data['terimaTotal']);
        $line('Pendapatan bunga bank', (float) $data['bungaTotal']);
        $line('Belanja persediaan', (float) $data['op']['persediaan']);
        $line('Belanja perjalanan dinas', (float) $data['op']['perjalanan']);
        $line('Belanja pemeliharaan', (float) $data['op']['pemeliharaan']);
        $line('Belanja koran, listrik, air, telepon', (float) $data['op']['koran']);
        $line('Belanja makan dan minum', (float) $data['op']['makan']);
        $line('Belanja jasa', (float) $data['op']['jasa']);
        $line('Beban bunga bank', (float) $data['bebanBunga']);
        $line('Belanja modal KIB B', (float) $data['modal']['B']);
        $line('Belanja modal KIB E', (float) $data['modal']['E']);
        $line('Saldo akhir', (float) $data['closing']);
        $this->styleHeader($sheet, 'A1:B1');
        $this->formatMoneyRange($sheet, 'B2:B'.$row);
        $sheet->getStyle('A1:B'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->autoSize($sheet, 2);
    }

    private function formatColumns(object $sheet, array $columns, int $lastRow): void
    {
        foreach ($columns as $index => $column) {
            if ($column['type'] === 'money' || $column['type'] === 'integer') {
                $this->formatMoneyRange($sheet, $this->columnLetter($index + 1).'2:'.$this->columnLetter($index + 1).$lastRow);
            }
        }
    }

    private function formatMoneyRange(object $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0');
    }

    private function styleHeader(object $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E2F3');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function autoSize(object $sheet, int $columns): void
    {
        for ($index = 1; $index <= $columns; $index++) {
            $sheet->getColumnDimension($this->columnLetter($index))->setAutoSize(true);
        }
    }

    private function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }
}
