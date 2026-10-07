<?php

namespace App\Http\Controllers;

use App\Models\QuarterNumberingRun;
use App\Models\SpjPackage;
use App\Services\ArkasMirrorResolver;
use App\Services\SpjNumberingPolicyService;
use App\UseCases\Spj\SpjQuarterRecapUseCase;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SpjNumberingWorkflowController extends Controller
{
    public function index(Request $request, SpjNumberingPolicyService $numberingPolicy): View
    {
        $data = $request->validate([
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $yearId = (int) session('active_fiscal_year_id');
        $selectedQuarter = (int) ($data['quarter'] ?? min(4, max(1, ArkasMirrorResolver::quarterOfMonth((int) now()->month))));
        $recap = app(SpjQuarterRecapUseCase::class);

        $quarterSummaries = $recap->quarterSummaries();

        $packageQuery = SpjPackage::query()
            ->whereHas('transaction', fn (Builder $query): Builder => $recap->summaryScope($query->activeContext(), $selectedQuarter))
            ->whereIn('status', ['READY', 'NUMBERED', 'FINAL'])
            ->orderByRaw("CASE status WHEN 'READY' THEN 0 WHEN 'NUMBERED' THEN 1 ELSE 2 END")
            ->orderBy('id');

        $packageWith = ['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id,payment_method,vendor_name,invoice_number,invoice_date,event_name,event_location,event_date,participant_count,receipt_recipient_name,payment_reference,siplah_order_number', 'documents:id,spj_package_id,document_type,document_number,status', 'transaction.goods', 'transaction.workOrder.workers', 'transaction.participants', 'transaction.travels', 'transaction.honors', 'transaction.serviceRecipients'];

        $previewPackages = (clone $packageQuery)->with($packageWith)->get();
        $pagedPackages = (clone $packageQuery)
            ->with(['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id', 'documents:id,spj_package_id,document_type,document_number,status'])
            ->paginate(15)
            ->withQueryString();

        $documentTypes = collect($numberingPolicy->automaticDocumentTypes());
        $documentLabels = $numberingPolicy->automaticDocumentLabels();

        $recentRuns = QuarterNumberingRun::query()
            ->where('fiscal_year_id', $yearId)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('spj.numbering', [
            'selectedQuarter' => $selectedQuarter,
            'quarterSummaries' => $quarterSummaries,
            'previewPackages' => $previewPackages,
            'pagedPackages' => $pagedPackages,
            'documentTypes' => $documentTypes,
            'documentLabels' => $documentLabels,
            'recentRuns' => $recentRuns,
            'selectedSummary' => $quarterSummaries->get($selectedQuarter),
        ]);
    }
}
