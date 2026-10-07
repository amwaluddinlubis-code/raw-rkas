<?php

namespace App\UseCases\Spj;

use App\Models\FiscalYear;
use App\Models\SpjHonor;
use App\Models\SpjServiceRecipient;
use App\Models\Transaction;
use App\Services\ArkasMirrorResolver;
use App\Services\DocumentStoragePathService;
use App\Services\RoutineHonorRegisterService;
use App\Support\ActiveSpjContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class ExtendedSpjReportUseCase extends SpjReportUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $activeContext,
        private readonly RoutineHonorRegisterService $routineHonorRegister,
    ) {
        parent::__construct($activeContext);
    }

    /** @return Collection<int,Transaction> */
    public function selectionTransactions(string $category, array $filters = []): Collection
    {
        $relation = $category === 'HONOR_PEGAWAI' ? 'honors' : 'serviceRecipients';

        return $this->baseSelectionQuery($category, [$relation, 'spjPackage'], $filters)->get();
    }

    /**
     * Query dasar seleksi transaksi per kategori (Honor vs Jasa).
     *
     * Satu sumber untuk select/compose agar filter konteks, join mirror,
     * scope periode, dan ordering tidak divergen.
     *
     * @param  array<int, string>  $with
     */
    private function baseSelectionQuery(string $category, array $with, array $filters = []): Builder
    {
        $relation = $category === 'HONOR_PEGAWAI' ? 'honors' : 'serviceRecipients';

        return Transaction::query()
            ->with($with)
            ->forSpjContext($this->activeContext)
            ->where('transactions.spj_category', $category)
            ->has($relation)
            ->tap(fn ($query) => ArkasMirrorResolver::joinKasUmum($query))
            ->when(! empty($filters['month']), fn ($query) => $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' = ?', [(int) $filters['month']]))
            ->when(! empty($filters['quarter']), function ($query) use ($filters): void {
                [$from, $to] = ArkasMirrorResolver::quarterMonthRange((int) $filters['quarter']);
                $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' BETWEEN ? AND ?', [$from, $to]);
            })
            ->when(! empty($filters['semester']), function ($query) use ($filters): void {
                [$from, $to] = ArkasMirrorResolver::semesterMonthRange((int) $filters['semester']);
                $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' BETWEEN ? AND ?', [$from, $to]);
            })
            ->select('transactions.*')
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id');
    }

    /**
     * Filter periode + transaction_ids yang dipakai ulang export Honor dan Jasa.
     */
    private function applyRecipientExportScope(mixed $query, Request $request, string $category): void
    {
        $query->forSpjContext($this->activeContext)->where('transactions.spj_category', $category);
        ArkasMirrorResolver::joinKasUmum($query);
        if ($request->filled('transaction_ids')) {
            $query->whereIn('transactions.id', collect($request->input('transaction_ids', []))->map(fn ($id): int => (int) $id)->all());
        }
        if ($request->filled('month')) {
            $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' = ?', [$request->integer('month')]);
        }
        if ($request->filled('quarter')) {
            [$from, $to] = ArkasMirrorResolver::quarterMonthRange($request->integer('quarter'));
            $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' >= ?', [$from])
                ->whereRaw(ArkasMirrorResolver::mirrorMonth().' <= ?', [$to]);
        }
        if ($request->filled('semester')) {
            [$from, $to] = ArkasMirrorResolver::semesterMonthRange($request->integer('semester'));
            $query->whereRaw(ArkasMirrorResolver::mirrorMonth().' >= ?', [$from])
                ->whereRaw(ArkasMirrorResolver::mirrorMonth().' <= ?', [$to]);
        }
    }

    /**
     * Query dasar compose (validasi ids + konteks + relasi) untuk Honor vs Jasa.
     *
     * @return Collection<int, Transaction>
     */
    private function baseComposeTransactions(string $category, string $relation, array $transactionIds): Collection
    {
        return Transaction::query()
            ->with([$relation, 'spjPackage'])
            ->forSpjContext($this->activeContext)
            ->where('transactions.spj_category', $category)
            ->whereKey($transactionIds)
            ->has($relation)
            ->tap(fn ($query) => ArkasMirrorResolver::joinKasUmum($query))
            ->select('transactions.*')
            ->orderByRaw(ArkasMirrorResolver::mirrorDate())
            ->orderBy('transactions.id')
            ->get();
    }

    public function selectHonorPayments(Request $request): View
    {
        $transactions = $this->selectionTransactions('HONOR_PEGAWAI', $request->all());

        return view('spj-reports.honor-select', compact('transactions'));
    }

    public function composeHonorPayments(Request $request): View
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct'],
        ]);
        $transactions = $this->baseComposeTransactions('HONOR_PEGAWAI', 'honors', $data['transaction_ids']);
        abort_if($transactions->count() !== count($data['transaction_ids']), 422, 'Sebagian transaksi honor tidak berada pada konteks aktif atau bukan kategori Honor Pegawai.');

        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item', fn ($query) => $query->whereIn('transaction_id', $transactions->modelKeys()))
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf('%s-%010d-%010d', $honor->item->transaction?->sourceDateString() ?? '', $honor->item->transaction_id, $honor->id))
            ->values();
        $register = $this->routineHonorRegister->aggregate($honors);

        return view('spj-reports.honor-compose', [
            'transactions' => $transactions,
            'rows' => $register['rows'],
            'summary' => $register['summary'],
        ]);
    }

    public function exportHonorPayments(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $year = FiscalYear::query()->findOrFail($this->activeContext->fiscalYearId());
        $school = $this->activeContext->school();
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $this->applyRecipientExportScope($query, $request, 'HONOR_PEGAWAI');
            })
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf(
                '%s-%010d-%010d-%010d',
                $honor->item->transaction?->sourceDateString() ?? '',
                $honor->item->transaction_id,
                $honor->sort_order,
                $honor->id,
            ))
            ->values();

        $register = $this->routineHonorRegister->aggregate($honors);
        $rows = $register['rows'];
        $summary = $register['summary'];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.honor-routine-register', compact('rows', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Penerimaan Honor');
        $sheet->fromArray([
            'No', 'Penerima', 'Jabatan/Jenis Honor', 'Periode', 'Bulan/Kali', 'Tarif',
            'Bruto', 'PPh 21', 'Dibayarkan', 'No Bukti/BPU', 'Nomor SPJ', 'Tanda Tangan',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray([[
                $index + 1,
                $row['name'],
                $row['position'],
                $row['period'],
                $row['honor_units'],
                $row['rate_per_unit'],
                $row['gross'],
                $row['tax'],
                $row['net'],
                $row['proof_references'],
                $row['spj_references'],
                ($index + 1).'. __________________',
            ]], null, 'A'.($index + 2));
        }

        $totalRow = $rows->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', $summary['gross'], $summary['tax'], $summary['net'], '', '', '']], null, 'A'.$totalRow);
        foreach (['F', 'G', 'H', 'I'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K'.$totalRow)->getAlignment()->setWrapText(true)->setVertical('center');
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.35)->setRight(0.35);

        $path = storage_path('app/generated-documents/daftar-penerimaan-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.xlsx', (int) $year->year);
    }

    public function exportServiceRecipients(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $year = FiscalYear::query()->findOrFail($this->activeContext->fiscalYearId());
        $school = $this->activeContext->school();
        $recipients = SpjServiceRecipient::query()
            ->with('transaction.spjPackage')
            ->whereHas('transaction', function ($query) use ($request): void {
                $this->applyRecipientExportScope($query, $request, 'JASA_LAINNYA');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $summary = [
            'gross' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->amount),
            'tax' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->tax_amount),
            'net' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->net_amount),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.service-recipient-payments', compact('recipients', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pembayaran Jasa');
        $sheet->fromArray(['No', 'No Bukti', 'Nomor SPJ', 'Tanggal', 'Penerima Jasa', 'Jenis Jasa', 'Uraian', 'Jumlah', 'Satuan', 'Hari', 'Tarif/Hari', 'Bruto', 'Pajak', 'Dibayarkan', 'Tanda Tangan'], null, 'A1');
        foreach ($recipients as $index => $recipient) {
            $transaction = $recipient->transaction;
            $sheet->fromArray([[
                $index + 1,
                $transaction->sourceValue('no_bukti'),
                $transaction->spjPackage?->document_number,
                $transaction->sourceDateString('transaction_date', 'd-m-Y'),
                $recipient->name,
                $recipient->service_type,
                $recipient->service_description,
                (float) $recipient->quantity,
                $recipient->unit,
                (float) $recipient->rental_days,
                (float) $recipient->daily_rate,
                (float) $recipient->amount,
                (float) $recipient->tax_amount,
                (float) $recipient->net_amount,
                ($index + 1).'. __________________',
            ]], null, 'A'.($index + 2));
        }
        $totalRow = $recipients->count() + 2;
        $sheet->fromArray([['', '', '', '', '', '', '', '', '', '', 'TOTAL', $summary['gross'], $summary['tax'], $summary['net'], '']], null, 'A'.$totalRow);
        foreach (['K', 'L', 'M', 'N'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = storage_path('app/generated-documents/daftar-pembayaran-jasa-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.xlsx', (int) $year->year);
    }

    public function selectServiceRecipients(Request $request): View
    {
        $transactions = $this->selectionTransactions('JASA_LAINNYA', $request->all());

        return view('spj-reports.service-recipient-select', compact('transactions'));
    }

    public function composeServiceRecipients(Request $request): View
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct'],
        ]);
        $transactions = $this->baseComposeTransactions('JASA_LAINNYA', 'serviceRecipients', $data['transaction_ids']);
        abort_if($transactions->count() !== count($data['transaction_ids']), 422, 'Sebagian transaksi jasa tidak berada pada konteks aktif atau tidak memiliki penerima jasa.');
        $recipients = $transactions->flatMap(function (Transaction $transaction) {
            return $transaction->serviceRecipients->each(
                fn (SpjServiceRecipient $recipient): SpjServiceRecipient => $recipient->setRelation('transaction', $transaction),
            );
        })->values();

        return view('spj-reports.service-recipient-compose', [
            'transactions' => $transactions,
            'recipients' => $recipients,
            'summary' => [
                'gross' => $recipients->sum('amount'),
                'tax' => $recipients->sum('tax_amount'),
                'net' => $recipients->sum('net_amount'),
            ],
        ]);
    }
}
