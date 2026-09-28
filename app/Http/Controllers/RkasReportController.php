<?php

namespace App\Http\Controllers;

use App\Services\RkasReportExcelService;
use App\Services\RkasReportPackageService;
use App\Services\RkasReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->setPaper('folio', 'landscape')
            ->stream($payload['file_name'].'.pdf');
    }

    public function preview(Request $request, string $scope): Response
    {
        return response()->view('rkas-reports.pdf', $this->payload($request, $scope));
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

    public function package(Request $request, RkasReportPackageService $packages): Response
    {
        $validated = $request->validate([
            'scopes' => ['required', 'array', 'min:1', 'max:5'],
            'scopes.*' => ['required', 'distinct', Rule::in(RkasReportService::SCOPES)],
            'formats' => ['required', 'array', 'min:1', 'max:2'],
            'formats.*' => ['required', 'distinct', Rule::in(['pdf', 'xlsx'])],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'triwulan' => ['nullable', 'integer', 'between:1,4'],
            'revisi' => ['nullable', 'string', 'max:255'],
        ]);
        if (in_array('bulanan', $validated['scopes'], true) && empty($validated['bulan'])) {
            abort(422, 'Pilih bulan sebelum memasukkan laporan bulanan ke paket.');
        }
        if (in_array('triwulan-bulanan', $validated['scopes'], true) && empty($validated['triwulan'])) {
            abort(422, 'Pilih triwulan sebelum memasukkan laporan triwulan per bulan ke paket.');
        }

        $schoolId = (int) session('active_school_id');
        $fiscalYearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $result = $packages->export($validated['scopes'], $validated['formats'], [
            'school_id' => $schoolId,
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => $fundSourceId,
            'revision' => trim((string) ($validated['revisi'] ?? '')),
            'month' => (int) ($validated['bulan'] ?? 0),
            'quarter' => (int) ($validated['triwulan'] ?? 0),
        ]);

        return response()->download($result['path'], $result['file_name'].'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, string $scope): array
    {
        abort_unless(in_array($scope, RkasReportService::SCOPES, true), 404);

        $month = $request->integer('bulan');
        if ($scope === 'bulanan') {
            abort_unless($month >= 1 && $month <= 12, 422, 'Bulan laporan belum dipilih atau tidak valid.');
        }
        $quarter = $request->integer('triwulan');
        if ($scope === 'triwulan-bulanan') {
            abort_unless($quarter >= 1 && $quarter <= 4, 422, 'Triwulan laporan belum dipilih atau tidak valid.');
        }

        $payload = $this->reports->build($scope, [
            'school_id' => (int) session('active_school_id'),
            'fiscal_year_id' => (int) session('active_fiscal_year_id'),
            'fund_source_id' => (int) session('active_fund_source_id'),
            'revision' => trim((string) $request->query('revisi', '')),
            'month' => $month,
            'quarter' => $quarter,
        ]);
        abort_if($payload === null, 422, 'Data laporan RKAS belum siap. Jalankan sinkronisasi ARKAS terlebih dahulu.');

        return $payload;
    }
}
