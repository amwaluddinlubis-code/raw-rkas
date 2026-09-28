<?php

namespace App\Http\Controllers;

use App\Services\RkasReportExcelService;
use App\Services\RkasReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Unduhan laporan Kertas Kerja RKAS per pengesahan revisi (PDF + Excel). */
final class RkasReportController extends Controller
{
    public function __construct(
        private readonly RkasReportService $reports,
        private readonly RkasReportExcelService $excel,
    ) {}

    public function pdf(Request $request, string $scope): Response
    {
        $payload = $this->payload($request, $scope);

        return Pdf::loadView('rkas-reports.pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->stream($payload['file_name'].'.pdf');
    }

    public function excel(Request $request, string $scope): Response
    {
        $payload = $this->payload($request, $scope);
        $path = $this->excel->export($payload);

        return response()->download(
            $path,
            $payload['file_name'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, string $scope): array
    {
        abort_unless(in_array($scope, RkasReportService::SCOPES, true), 404);

        $month = $request->integer('bulan');
        if ($scope === 'bulanan') {
            abort_unless($month >= 1 && $month <= 12, 422, 'Bulan laporan belum dipilih atau tidak valid.');
        }

        $payload = $this->reports->build($scope, [
            'school_id' => (int) session('active_school_id'),
            'fiscal_year_id' => (int) session('active_fiscal_year_id'),
            'fund_source_id' => (int) session('active_fund_source_id'),
            'revision' => trim((string) $request->query('revisi', '')),
            'month' => $month,
        ]);
        abort_if($payload === null, 422, 'Data laporan RKAS belum siap. Jalankan sinkronisasi ARKAS terlebih dahulu.');

        return $payload;
    }
}
