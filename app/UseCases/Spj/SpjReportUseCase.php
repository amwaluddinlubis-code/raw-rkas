<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\SpjHonor;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\DocumentStoragePathService;
use App\Support\ActiveSpjContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpjReportUseCase
{
    public function __construct(private readonly ActiveSpjContext $context) {}

    public function tabLaporan(Request $request): View
    {
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;

        [$packages, $summary] = $this->report($request, $perPage, $pendingPerPage);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'laporan',
            'packages' => $packages,
            'summary' => $summary,
            'pendingPaginator' => $pendingPaginator,
            'transactions' => null,
            ...app(SpjWorkspaceUseCase::class)->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    public function tabMonitoring(Request $request): View
    {
        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;
        [, $summary] = $this->report($request, 15, $pendingPerPage);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'monitoring',
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->orderBy('quarter')->get()->keyBy('quarter'),
            'pendingPaginator' => $pendingPaginator,
            'summary' => $summary,
            'transactions' => null,
            ...app(SpjWorkspaceUseCase::class)->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    /**
     * Resolve mode/periode dari input mentah, termasuk URL bookmark lama
     * yang memakai month/quarter/semester terpisah.
     *
     * @return array{0: string, 1: int|null}
     */
    public static function resolveModePeriode(array $input): array
    {
        $mode = (string) ($input['mode'] ?? '');
        $periode = isset($input['periode']) && $input['periode'] !== '' && $input['periode'] !== null
            ? (int) $input['periode']
            : null;

        if ($mode === '') {
            if (! empty($input['month'])) {
                $mode = 'bulan';
                $periode = (int) $input['month'];
            } elseif (! empty($input['quarter'])) {
                $mode = 'triwulan';
                $periode = (int) $input['quarter'];
            } elseif (! empty($input['semester'])) {
                $mode = 'semester';
                $periode = (int) $input['semester'];
            } else {
                $mode = 'semua';
            }
        }

        return [$mode, $periode];
    }

    /**
     * Jalankan query laporan dari parameter eksplisit memakai implementasi
     * yang sama dengan jalur HTTP, untuk dipakai komponen Livewire.
     */
    public function reportData(string $mode, ?int $periode, int $perPage = 15, int $pendingPerPage = 15): array
    {
        return $this->report(new Request(['mode' => $mode, 'periode' => $periode]), $perPage, $pendingPerPage);
    }

    public function export(Request $request, string $format)
    {
        [$packages, $summary] = $this->report($request);
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.pdf', compact('packages', 'summary'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'REKAP-SPJ-'.$summary['year'].'.pdf', (int) $summary['year']);

            return $pdf->stream('REKAP-SPJ-'.$summary['year'].'.pdf');
        }
        abort_unless($format === 'xlsx', 404);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Rekap SPJ');
        $sheet->fromArray(['No', 'Nomor SPJ', 'No Bukti', 'Tanggal', 'Penerima', 'Kegiatan', 'Rekening', 'Bruto', 'Pajak', 'Dibayarkan', 'Status'], null, 'A1');
        foreach ($packages as $index => $package) {
            $t = $package->transaction;
            $sheet->fromArray([[$index + 1, $package->document_number, $t->sourceValue('no_bukti'), (($d = $t->sourceValue('transaction_date')) ? Carbon::parse($d)->format('d-m-Y') : null), ($t->receipt_recipient_name ?: $t->spj_recipient_name ?: '-'), $t->sourceValue('activity_name'), $t->sourceValue('account_name'), (float) $t->sourceValue('gross_amount'), (float) $t->sourceValue('tax_total'), (float) $t->sourceValue('net_amount'), $package->status]], null, 'A'.($index + 2));
        }
        foreach (['H', 'I', 'J'] as $column) {
            $sheet->getStyle($column.'2:'.$column.($packages->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $this->addRealizationSheet($book, 'Per Kegiatan', $summary['activities'], 'activity_code', 'activity_name');
        $this->addRealizationSheet($book, 'Per Rekening', $summary['accounts'], 'account_code', 'account_name');
        $path = storage_path('app/generated-documents/rekap-spj-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'REKAP-SPJ-'.$summary['year'].'.xlsx', (int) $summary['year']);
    }

    public function exportHonorPayments(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        $school = $this->context->school();
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $query->forSpjContext($this->context)->where('spj_category', 'HONOR_PEGAWAI');
                ArkasMirrorResolver::joinKasUmum($query);
                if ($request->filled('month')) {
                    ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%m', {d}) AS INTEGER) = ?", [$request->integer('month')]);
                }
                if ($request->filled('quarter')) {
                    $quarter = $request->integer('quarter');
                    ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%m', {d}) AS INTEGER) BETWEEN ? AND ?", [(($quarter - 1) * 3) + 1, $quarter * 3]);
                }
                if ($request->filled('semester')) {
                    $semester = $request->integer('semester');
                    ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%m', {d}) AS INTEGER) BETWEEN ? AND ?", [$semester === 1 ? 1 : 7, $semester === 1 ? 6 : 12]);
                }
            })
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf(
                '%s-%010d-%010d-%010d',
                (($d = $honor->item->transaction?->sourceValue('transaction_date')) ? Carbon::parse($d)->format('Y-m-d') : null) ?? '',
                $honor->item->transaction_id,
                $honor->sort_order,
                $honor->id
            ))
            ->values();
        $summary = [
            'gross' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->gross_amount),
            'pph21' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->tax_amount),
            'net' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->net_amount),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.honor-payments', compact('honors', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pembayaran Honor');
        $sheet->fromArray(['No', 'No Bukti', 'Nomor SPJ', 'Tanggal', 'Penerima', 'Jabatan/Jenis Honor', 'Bulan/Kali', 'Tarif', 'Bruto', 'PPh 21', 'Dibayarkan', 'Tanda Tangan'], null, 'A1');
        foreach ($honors as $index => $honor) {
            $transaction = $honor->item->transaction;
            $sheet->fromArray([[$index + 1, $transaction->sourceValue('no_bukti'), $transaction->spjPackage?->document_number, (($d = $transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->format('d-m-Y') : null), $honor->name, $honor->position, (float) $honor->honor_months, (float) $honor->rate_per_unit, (float) $honor->gross_amount, (float) $honor->tax_amount, (float) $honor->net_amount, ($index + 1).'. __________________']], null, 'A'.($index + 2));
        }
        $totalRow = $honors->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', '', '', $summary['gross'], $summary['pph21'], $summary['net'], '']], null, 'A'.$totalRow);
        foreach (['H', 'I', 'J', 'K'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = storage_path('app/generated-documents/daftar-pembayaran-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.xlsx', (int) $year->year);
    }

    private function report(Request $request, ?int $perPage = null, ?int $pendingPerPage = null): array
    {
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        $transactionFilter = fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year);
        $packageQuery = SpjPackage::query()->with(['transaction.items:id,transaction_id,source_item_id', 'documents'])
            ->whereHas('transaction', $transactionFilter)
            ->where(function ($query): void {
                $query->whereNotNull('document_number')
                    ->orWhereHas('documents', fn ($document) => $document
                        ->where('document_type', 'SPJ')
                        ->where('scope_key', 'MAIN')
                        ->where('status', 'CANCELLED'));
            })
            ->orderByRaw("COALESCE((SELECT sequence_number FROM spj_documents WHERE spj_documents.spj_package_id = spj_packages.id AND document_type = 'SPJ' ORDER BY id DESC LIMIT 1), 2147483647)")
            ->orderBy('spj_packages.id');

        $decorate = function ($packages) {
            return $packages->map(function (SpjPackage $package): SpjPackage {
                $cancelledDocument = $package->documents
                    ->where('document_type', 'SPJ')
                    ->where('scope_key', 'MAIN')
                    ->where('status', 'CANCELLED')
                    ->sortByDesc('id')
                    ->first();
                $package->setAttribute('report_document_number', $package->document_number ?: $cancelledDocument?->document_number);
                $package->setAttribute('report_status', $package->document_number ? $package->status : 'CANCELLED');
                $package->setAttribute('report_cancellation_reason', $package->document_number ? null : $cancelledDocument?->cancellation_reason);

                return $package;
            });
        };

        if ($perPage) {
            $packages = $packageQuery->paginate($perPage, ['*'], 'page')->withQueryString();
            $packages->setCollection($decorate($packages->getCollection()));
        } else {
            $packages = $decorate($packageQuery->get())->values();
        }

        $pendingQuery = Transaction::query()->with('spjPackage.documents')
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year))
            ->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->whereNull('document_number'));
            })
            ->select('transactions.*')
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id');
        $pendingTransactions = $pendingPerPage
            ? $pendingQuery->paginate($pendingPerPage, ['*'], 'pending_page')->withQueryString()
            : $pendingQuery->get();

        $activityGroups = Transaction::query()->forSpjContext($this->context)->with('items:id,transaction_id,source_item_id')->get()
            ->groupBy(fn (Transaction $transaction): string => ($transaction->sourceValue('activity_code') ?: '-').'|'.($transaction->sourceValue('activity_name') ?: 'Kegiatan belum diisi'));
        $activities = $activityGroups->map(function ($group): object {
            $first = $group->first();

            return (object) [
                'activity_code' => $first->sourceValue('activity_code') ?: '-',
                'activity_name' => $first->sourceValue('activity_name') ?: 'Kegiatan belum diisi',
                'realization' => $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
            ];
        })->sortByDesc('realization')->values();
        $accountGroups = Transaction::query()->forSpjContext($this->context)->with('items:id,transaction_id,source_item_id')->get()
            ->groupBy(fn (Transaction $transaction): string => ($transaction->sourceValue('account_code') ?: '-').'|'.($transaction->sourceValue('account_name') ?: 'Rekening belum diisi'));
        $accounts = $accountGroups->map(function ($group): object {
            $first = $group->first();

            return (object) [
                'account_code' => $first->sourceValue('account_code') ?: '-',
                'account_name' => $first->sourceValue('account_name') ?: 'Rekening belum diisi',
                'realization' => $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
            ];
        })->sortBy('account_code')->values();

        $successfulTransactions = Transaction::query()
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year))
            ->whereHas('spjPackage', fn ($package) => $package->whereNotNull('document_number'))
            ->select('transactions.*')
            ->with('items:id,transaction_id,source_item_id')
            ->get();
        Transaction::preloadMirrorSource($successfulTransactions);
        $successfulSummary = (object) [
            'aggregate_count' => $successfulTransactions->count(),
            'gross' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
            'tax' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('tax_total')),
            'net' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('net_amount')),
            'ppn' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('ppn')),
            'pph21' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph21')),
            'pph22' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph22')),
            'pph23' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph23')),
            'pph4' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('pph4')),
            'sspd' => $successfulTransactions->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('sspd')),
        ];
        $cancelledCount = SpjPackage::query()
            ->whereHas('transaction', $transactionFilter)
            ->whereNull('document_number')
            ->whereHas('documents', fn ($document) => $document->where(['document_type' => 'SPJ', 'scope_key' => 'MAIN', 'status' => 'CANCELLED']))
            ->count();

        return [$packages, [
            'year' => $year->year,
            'count' => (int) $successfulSummary->aggregate_count,
            'cancelled_count' => $cancelledCount,
            'gross' => (float) $successfulSummary->gross,
            'tax' => (float) $successfulSummary->tax,
            'net' => (float) $successfulSummary->net,
            'ppn' => (float) $successfulSummary->ppn,
            'pph21' => (float) $successfulSummary->pph21,
            'pph22' => (float) $successfulSummary->pph22,
            'pph23' => (float) $successfulSummary->pph23,
            'pph4' => (float) $successfulSummary->pph4,
            'sspd' => (float) $successfulSummary->sspd,
            'pending_transactions' => $pendingTransactions,
            'activities' => $activities,
            'accounts' => $accounts,
        ]];
    }

    private function applyReportTransactionFilters($query, Request $request, FiscalYear $year)
    {
        $mode = (string) $request->input('mode', '');
        $periode = $request->integer('periode') ?: null;

        // Compatibility for bookmarked URLs that used the previous three filters.
        if ($mode === '') {
            $mode = $request->filled('month') ? 'bulan' : ($request->filled('quarter') ? 'triwulan' : ($request->filled('semester') ? 'semester' : 'semua'));
            $periode = $mode === 'bulan' ? $request->integer('month') : ($mode === 'triwulan' ? $request->integer('quarter') : ($mode === 'semester' ? $request->integer('semester') : null));
        }

        ArkasMirrorResolver::joinKasUmum($query);

        if ($mode === 'bulan' && $periode >= 1 && $periode <= 12) {
            ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%Y', {d}) AS INTEGER) = ? AND CAST(strftime('%m', {d}) AS INTEGER) = ?", [$year->year, $periode]);
        } elseif ($mode === 'triwulan' && $periode >= 1 && $periode <= 4) {
            ArkasMirrorResolver::whereMirrorDate($query, '{d} BETWEEN ? AND ?', [
                now()->setYear($year->year)->setMonth(($periode - 1) * 3 + 1)->startOfMonth()->format('Y-m-d'),
                now()->setYear($year->year)->setMonth($periode * 3)->endOfMonth()->format('Y-m-d'),
            ]);
        } elseif ($mode === 'semester' && $periode >= 1 && $periode <= 2) {
            ArkasMirrorResolver::whereMirrorDate($query, '{d} BETWEEN ? AND ?', [
                now()->setYear($year->year)->setMonth($periode === 1 ? 1 : 7)->startOfMonth()->format('Y-m-d'),
                now()->setYear($year->year)->setMonth($periode === 1 ? 6 : 12)->endOfMonth()->format('Y-m-d'),
            ]);
        }

        return $query;
    }

    private function addRealizationSheet(Spreadsheet $book, string $title, $rows, string $code, string $name): void
    {
        $sheet = $book->createSheet()->setTitle($title);
        $sheet->fromArray(['No', 'Kode', 'Nama', 'Realisasi'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([[$index + 1, $row->{$code}, $row->{$name}, (float) $row->realization]], null, 'A'.($index + 2));
        }
        $sheet->getStyle('D2:D'.($rows->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
