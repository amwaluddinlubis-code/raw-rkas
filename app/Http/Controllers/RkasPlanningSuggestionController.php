<?php

namespace App\Http\Controllers;

use App\Services\RkasPlanningSuggestionService;
use App\Support\SpjDisplay;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RkasPlanningSuggestionController extends Controller
{
    public function __invoke(RkasPlanningSuggestionService $suggestions): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $report = $suggestions->build($yearId, $fundSourceId);

        return view('rkas-planning.index', $report);
    }

    public function export(string $modul, RkasPlanningSuggestionService $suggestions): BinaryFileResponse
    {
        abort_unless(in_array($modul, ['pagu-awal', 'sisa-pagu'], true), 404);

        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $report = $suggestions->build($yearId, $fundSourceId);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();

        if ($modul === 'pagu-awal') {
            $sheet->setTitle('Saran Pagu Awal');
            $sheet->fromArray([['Kode Kegiatan', 'Kegiatan', 'Pagu Tahun Lalu', 'Realisasi Tahun Lalu', 'Serapan %', 'Status Serapan', 'Saran Pagu (Baseline)']], null, 'A1');
            $row = 2;
            foreach ($report['initial']['rows'] as $item) {
                $sheet->fromArray([
                    $item['activity_code'], $item['activity_name'],
                    SpjDisplay::rupiah($item['last_budget']), SpjDisplay::rupiah($item['last_spent']),
                    $item['absorption'], $item['absorption_label'], SpjDisplay::rupiah($item['suggested']),
                ], null, "A{$row}");
                $row++;
            }
            $filename = 'SARAN-PAGU-AWAL-'.($report['initial']['base_year'] ?? 'xx').'.xlsx';
        } else {
            $sheet->setTitle('Saran Sisa Pagu');
            $sheet->fromArray([['Kode Kegiatan', 'Kegiatan', 'Pagu', 'Realisasi', 'Sisa', 'Serapan %', 'Status', 'Saran']], null, 'A1');
            $row = 2;
            foreach ($report['remaining']['rows'] as $item) {
                $sheet->fromArray([
                    $item['activity_code'], $item['activity_name'],
                    SpjDisplay::rupiah($item['budget']), SpjDisplay::rupiah($item['spent']), SpjDisplay::rupiah($item['remaining']),
                    $item['absorption'], $item['status'], $item['suggestion'],
                ], null, "A{$row}");
                $row++;
            }
            $totals = $report['remaining']['totals'];
            $sheet->fromArray([['TOTAL', '', SpjDisplay::rupiah($totals['budget']), SpjDisplay::rupiah($totals['spent']), SpjDisplay::rupiah($totals['remaining']), '', '', '']], null, "A{$row}");
            $filename = 'SARAN-SISA-PAGU.xlsx';
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'rkas-saran-').'.xlsx';
        (new Xlsx($book))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend();
    }
}
