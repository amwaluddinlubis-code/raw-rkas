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
        } elseif (($payload['presentation'] ?? '') === 'bos_a1') {
            $this->writeBosA1($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'bpk_bos') {
            $this->writeBpk($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'k7b') {
            $this->writeK7b($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'k7c') {
            $this->writeK7c($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'k7a') {
            $this->writeK7a($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'k7') {
            $this->writeK7($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'k2') {
            $this->writeK2($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'rpd') {
            $this->writeRpd($report, $payload);
        } elseif (($payload['presentation'] ?? '') === 'sptjm_doc') {
            $this->writeSptjm($report, $payload);
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

    private function writeBosA1(object $sheet, array $payload): void
    {
        $data = $payload['bosA1'];
        $sheet->fromArray([[
            'No', 'Program/Kegiatan',
            'Belanja Pegawai', 'Barang dan Jasa',
            'Belanja Modal Peralatan dan Mesin', 'Belanja Modal Aset Tetap Lainnya',
            'Belanja Modal Jumlah', 'Total',
        ]], null, 'A1');

        $rowNumber = 2;
        foreach ($data['rows'] as $row) {
            $sheet->fromArray([[
                $row['no'], $row['program'],
                (float) $row['pegawai'], (float) $row['barang_jasa'],
                (float) $row['modal_mesin'], (float) $row['modal_aset'],
                (float) $row['modal_jumlah'], (float) $row['total'],
            ]], null, 'A'.$rowNumber++);
        }

        $totals = $data['totals'];
        $sheet->fromArray([[
            '', 'TOTAL',
            (float) $totals['pegawai'], (float) $totals['barang_jasa'],
            (float) $totals['modal_mesin'], (float) $totals['modal_aset'],
            (float) $totals['modal_jumlah'], (float) $totals['grand'],
        ]], null, 'A'.$rowNumber);
        $this->styleHeader($sheet, 'A1:'.$this->columnLetter(8).'1');
        $this->formatMoneyRange($sheet, 'C2:H'.$rowNumber);
        $sheet->getStyle('A'.$rowNumber.':H'.$rowNumber)->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $this->autoSize($sheet, 8);
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

    private function writeK7b(object $sheet, array $payload): void
    {
        $data = $payload['k7b'];
        $rows = [
            ['REGISTER PENUTUPAN KAS (Formulir BOS-K7B)'],
            ['Tanggal Penutupan Kas', $data['closing_date']],
            ['Tanggal Penutupan Kas Yang Lalu', $data['prev_closing']],
            [],
            ['Jumlah Total Penerimaan (D)', (float) $data['total_in']],
            ['Jumlah Total Pengeluaran (K)', (float) $data['total_out']],
            ['Saldo Buku (A = D - K)', (float) $data['book']],
            ['Saldo Kas (B)', (float) $data['bank'] + (float) $data['cash']],
            ['Saldo Bank, Surat Berharga dll', (float) $data['bank']],
            ['Perbedaan (A-B)', (float) $data['diff']],
            ['Penjelasan Perbedaan', 'Nihil — rincian pecahan diisi manual saat opname fisik'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $this->styleHeader($sheet, 'A1:B1');
        $this->formatMoneyRange($sheet, 'B5:B10');
        $sheet->getStyle('A1:B'.count($rows))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->autoSize($sheet, 2);
    }

    private function writeK7c(object $sheet, array $payload): void
    {
        $data = $payload['k7c'];
        $rows = [
            ['BERITA ACARA PEMERIKSAAN KAS (Formulir BOS-K7C)'],
            ['Tanggal pemeriksaan', $data['closing_date']],
            [],
            ['a. Uang kertas bank, uang logam', (float) $data['cash']],
            ['b. Saldo Bank', (float) $data['bank']],
            ['c. Surat Berharga dll', (float) $data['securities']],
            ['Jumlah', (float) $data['total']],
            ['Saldo uang menurut Buku Kas Umum', (float) $data['book']],
            ['Perbedaan antara saldo kas dan saldo buku', (float) $data['diff']],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $this->styleHeader($sheet, 'A1:B1');
        $this->formatMoneyRange($sheet, 'B4:B9');
        $sheet->getStyle('A1:B'.count($rows))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->autoSize($sheet, 2);
    }

    private function writeK7a(object $sheet, array $payload): void
    {
        $data = $payload['k7a'];
        $components = $data['components'];
        $compNos = array_keys($components);

        $header = array_merge(['No', 'Program/Kegiatan'], array_column($components, 'label'), ['TOTAL']);
        $sheet->fromArray([$header], null, 'A1');
        $lastCol = chr(ord('A') + count($header) - 1);

        $rowNumber = 2;
        foreach ($data['programs'] as $program) {
            $row = [$program['no'], $program['label']];
            foreach ($compNos as $compNo) {
                $row[] = (float) $program['components'][$compNo];
            }
            $row[] = (float) $program['total'];
            $sheet->fromArray([$row], null, 'A'.$rowNumber++);
        }

        $totalRow = ['', 'TOTAL'];
        foreach ($compNos as $compNo) {
            $totalRow[] = (float) $data['componentTotals'][$compNo];
        }
        $totalRow[] = (float) $data['grandTotal'];
        $sheet->fromArray([$totalRow], null, 'A'.$rowNumber);

        $rowNumber++;
        $sheet->fromArray([['Saldo Tahap Sebelumnya', (float) $data['summary']['saldo_awal']]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['Total Penerimaan Dana BOS Tahap ini', (float) $data['summary']['penerimaan']]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['Total Penggunaan Dana BOS Tahap ini', (float) $data['summary']['penggunaan']]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['Saldo Tahap ini', (float) $data['summary']['saldo_akhir']]], null, 'A'.$rowNumber);

        $this->styleHeader($sheet, 'A1:'.$lastCol.'1');
        $moneyStart = chr(ord('A') + 2);
        $this->formatMoneyRange($sheet, $moneyStart.'2:'.$lastCol.$rowNumber);
        $sheet->freezePane('A2');
        $this->autoSize($sheet, count($header));
    }

    /**
     * Lembar Excel Format BOS K-2: No | Uraian | Jumlah (dalam Rp) |
     * Triwulan (I–IV), baris "I Program Sekolah" -> 1.1–1.8 ->
     * "II Belanja Lainnya" -> "2.1" -> JUMLAH.
     */
    private function writeK2(object $sheet, array $payload): void
    {
        $data = $payload['k2'];

        $header = ['No', 'Uraian', 'Jumlah (dalam Rp)', 'TW I', 'TW II', 'TW III', 'TW IV'];
        $sheet->fromArray([$header], null, 'A1');
        $lastCol = 'G';

        $rowNumber = 2;
        $sheet->fromArray([['I', 'Program Sekolah']], null, 'A'.$rowNumber++);
        foreach ($data['programs'] as $program) {
            $sheet->fromArray([[
                $program['no'],
                $program['label'],
                (float) $program['jumlah'],
                (float) $program['tw'][1],
                (float) $program['tw'][2],
                (float) $program['tw'][3],
                (float) $program['tw'][4],
            ]], null, 'A'.$rowNumber++);
        }
        $sheet->fromArray([['II', 'Belanja Lainnya']], null, 'A'.$rowNumber++);
        $sheet->fromArray([['2.1']], null, 'A'.$rowNumber++);
        $sheet->fromArray([[
            '',
            'JUMLAH',
            (float) $data['totals']['jumlah'],
            (float) $data['totals']['tw'][1],
            (float) $data['totals']['tw'][2],
            (float) $data['totals']['tw'][3],
            (float) $data['totals']['tw'][4],
        ]], null, 'A'.$rowNumber);

        $this->styleHeader($sheet, 'A1:'.$lastCol.'1');
        $this->formatMoneyRange($sheet, 'C2:'.$lastCol.$rowNumber);
        $sheet->freezePane('A2');
        $this->autoSize($sheet, count($header));
    }

    /**
     * Lembar Excel RPD BOS resmi: judul + identitas, header 3 baris
     * (No | Kode Program | Uraian | Rincian Perhitungan -> Vol | Satuan |
     * Tarif Harga | Jumlah | Bulan -> m1 | m2 | m3) + baris nomor 1..10,
     * lalu hierarki baris dari payload dan footer Sub Total I/II +
     * JUMLAH TOTAL.
     */
    private function writeRpd(object $sheet, array $payload): void
    {
        $data = $payload['rpd'];
        $school = $payload['school'];
        $monthNames = array_values($data['month_names']);
        $monthKeys = array_keys($data['month_names']);

        $rowNumber = 1;
        $sheet->setCellValue('A'.$rowNumber, 'RINCIAN PENGGUNAAN DANA BOS (RPD BOS)');
        $sheet->setCellValue('A'.(++$rowNumber), $data['month_range']);
        $sheet->setCellValue('A'.(++$rowNumber), 'Nama Sekolah: '.$school->name);
        $sheet->setCellValue('A'.(++$rowNumber), 'Desa / Kec: '.trim(trim((string) ($school->desa ?? '')).' / '.trim((string) ($school->district ?? '')), ' /'));
        $sheet->setCellValue('A'.(++$rowNumber), 'Kab / Kota: '.($school->regency ?: '-'));
        $sheet->setCellValue('A'.(++$rowNumber), 'Provinsi: '.($school->province ?: '-'));
        $sheet->setCellValue('A'.(++$rowNumber), 'TRIWULAN: '.$data['quarter_label']);

        $headerRow = ++$rowNumber;
        // Baris 1 header: gabungan kolom bertingkat ditulis per sel.
        $sheet->fromArray([['No', 'Kode Program', 'Uraian', 'Rincian Perhitungan', '', '', 'Jumlah', 'Bulan', '', '']], null, 'A'.$headerRow);
        $sheet->fromArray([['', '', '', 'Vol', 'Satuan', 'Tarif Harga', '', $monthNames[0], $monthNames[1], $monthNames[2]]], null, 'A'.($headerRow + 1));
        $sheet->fromArray([[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]], null, 'A'.($headerRow + 2));
        $lastCol = 'J';
        $rowNumber = $headerRow + 3;

        $sheet->fromArray([['I', '', 'PROGRAM SEKOLAH']], null, 'A'.$rowNumber++);
        foreach ($data['rows'] as $row) {
            $sheet->fromArray([[
                $row['no'],
                $row['code'],
                $row['label'],
                $row['level'] === 'item' ? (float) $row['volume'] : null,
                $row['level'] === 'item' ? $row['unit'] : null,
                $row['level'] === 'item' ? (float) $row['unit_price'] : null,
                (float) $row['jumlah'],
                (float) $row['months'][$monthKeys[0]],
                (float) $row['months'][$monthKeys[1]],
                (float) $row['months'][$monthKeys[2]],
            ]], null, 'A'.$rowNumber++);
        }
        $sheet->fromArray([['', '', 'Sub Total Penggunaan Dana I', '', '', '', (float) $data['totals']['jumlah'],
            (float) $data['totals']['months'][$monthKeys[0]], (float) $data['totals']['months'][$monthKeys[1]], (float) $data['totals']['months'][$monthKeys[2]]]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['II', '', 'Penggunaan Dana Lainnya']], null, 'A'.$rowNumber++);
        $sheet->fromArray([['', '', 'Belanja….', '', '', '', 0, 0, 0, 0]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['', '', 'Sub Total Penggunaan Dana II', '', '', '', 0, 0, 0, 0]], null, 'A'.$rowNumber++);
        $sheet->fromArray([['', '', 'JUMLAH TOTAL (I + II)', '', '', '', (float) $data['totals']['jumlah'],
            (float) $data['totals']['months'][$monthKeys[0]], (float) $data['totals']['months'][$monthKeys[1]], (float) $data['totals']['months'][$monthKeys[2]]]], null, 'A'.$rowNumber);

        $this->styleHeader($sheet, 'A'.$headerRow.':'.$lastCol.($headerRow + 2));
        $this->formatMoneyRange($sheet, 'D'.$headerRow.':'.$lastCol.$rowNumber);
        $sheet->freezePane('A'.($headerRow + 3));
        $this->autoSize($sheet, 10);
    }

    /**
     * Lembar Excel Formulir BOS-K7 resmi: judul + identitas, header 4 baris
     * (No. Kode | Uraian Kegiatan | Jumlah | Penggunaan Dana Per Sumber Dana
     * -> Rutin | BOS Pusat/Provinsi/Kab-Kota | Bantuan Lain | Sumber
     * Pendapatan Lainnya) + baris nomor 1,2,3,4,5,8,9,10,11, lalu hierarki
     * baris dari payload dengan warna baris resmi formulir.
     */
    private function writeK7(object $sheet, array $payload): void
    {
        $data = $payload['k7'];
        $school = $payload['school'];

        $row = 1;
        foreach ([$data['title']['heading'], $data['title']['year_line'], $data['title']['range_line']] as $line) {
            $sheet->setCellValue('A'.$row, $line);
            $sheet->mergeCells('A'.$row.':I'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;
        $desaKec = trim(trim((string) ($school->desa ?? '')).' / '.trim((string) ($school->district ?? '')), ' /') ?: '-';
        foreach ([['NPSN', $school->npsn ?? '-'], ['Nama Sekolah', $school->name], ['Desa/Kecamatan', $desaKec], ['Kabupaten', $school->regency ?: '-'], ['Provinsi', $school->province ?: '-']] as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, ': '.$value);
            $row++;
        }

        $row++;
        $headerRow = $row;
        $sheet->setCellValue('A'.$row, 'No. Kode');
        $sheet->mergeCells('A'.$row.':A'.($row + 2));
        $sheet->setCellValue('B'.$row, 'Uraian Kegiatan');
        $sheet->mergeCells('B'.$row.':B'.($row + 2));
        $sheet->setCellValue('C'.$row, 'Jumlah');
        $sheet->mergeCells('C'.$row.':C'.($row + 2));
        $sheet->setCellValue('D'.$row, 'Penggunaan Dana Per Sumber Dana');
        $sheet->mergeCells('D'.$row.':I'.$row);
        $row++;
        $sheet->setCellValue('D'.$row, 'Rutin');
        $sheet->mergeCells('D'.$row.':D'.($row + 1));
        $sheet->setCellValue('E'.$row, 'Bantuan Operasional Sekolah (BOS)');
        $sheet->mergeCells('E'.$row.':G'.$row);
        $sheet->setCellValue('H'.$row, 'Bantuan Lain');
        $sheet->mergeCells('H'.$row.':H'.($row + 1));
        $sheet->setCellValue('I'.$row, 'Sumber Pendapatan Lainnya');
        $sheet->mergeCells('I'.$row.':I'.($row + 1));
        $row++;
        $sheet->setCellValue('E'.$row, 'Pusat');
        $sheet->setCellValue('F'.$row, 'Provinsi');
        $sheet->setCellValue('G'.$row, 'Kab/Kota');
        $row++;
        foreach (['1', '2', '3', '4', '5', '8', '9', '10', '11'] as $index => $number) {
            $sheet->setCellValue($this->columnLetter($index + 1).$row, $number);
        }
        $headerEnd = $row;
        $this->styleK7Header($sheet, 'A'.$headerRow.':I'.$headerEnd);
        $row++;

        $columns = ['C' => 'jumlah', 'D' => 'rutin', 'E' => 'pusat', 'F' => 'provinsi', 'G' => 'kab_kota', 'H' => 'bantuan_lain', 'I' => 'sumber_lainnya'];
        $fills = ['section' => 'D9D9D9', 'program' => 'FFD6D6', 'sub' => 'D4FFD4'];
        $firstData = $row;
        foreach ($data['rows'] as $item) {
            $sheet->setCellValue('A'.$row, $item['code']);
            $sheet->setCellValue('B'.$row, $item['label']);
            foreach ($columns as $letter => $key) {
                $value = (float) ($item['amounts'][$key] ?? 0);
                if ($value == 0.0) {
                    $sheet->setCellValue($letter.$row, $item['zero_dash'] ? '-' : '');
                } else {
                    $sheet->setCellValue($letter.$row, $value);
                }
            }
            if ($item['bold']) {
                $sheet->getStyle('A'.$row.':I'.$row)->getFont()->setBold(true);
            }
            if (isset($fills[$item['kind']])) {
                $sheet->getStyle('A'.$row.':I'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fills[$item['kind']]);
            }
            $row++;
        }
        $lastData = $row - 1;

        if (($data['unmapped'] ?? 0) > 0) {
            $row++;
            $sheet->setCellValue('A'.$row, 'Catatan: Rp. '.number_format((float) $data['unmapped'], 0, ',', '.').' ('.(int) ($data['unmappedCount'] ?? 0).' transaksi) tanpa kode kegiatan 01-08 dan tidak masuk JUMLAH.');
            $sheet->mergeCells('A'.$row.':I'.$row);
        }

        $sheet->getStyle('A'.$headerRow.':I'.$lastData)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.$headerRow.':I'.$headerEnd)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $this->formatMoneyRange($sheet, 'C'.$firstData.':I'.$lastData);
        $sheet->getStyle('C'.$firstData.':I'.$lastData)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A'.$firstData.':A'.$lastData)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->autoSize($sheet, 9);
    }

    private function styleK7Header(object $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F2F1EB');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function writeSptjm(object $sheet, array $payload): void
    {
        $data = $payload['sptjm'];
        $rows = [
            ['SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK'],
            ['Tahun Anggaran', $data['year']],
            ['Periode', $data['period_label']],
            [],
            ['Penerimaan dana BOS periode ini', (float) $data['received']],
            ['Penggunaan dana BOS periode ini', (float) $data['used']],
            ['Tempat / Tanggal', $data['place'].', '.$data['signed_date']],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $this->styleHeader($sheet, 'A1:B1');
        $this->formatMoneyRange($sheet, 'B5:B6');
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
