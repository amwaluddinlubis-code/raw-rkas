<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Services\ArkasMirrorAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArkasMirrorAuditController extends Controller
{
    public function __invoke(Request $request, ArkasMirrorAuditService $audit): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $year = (int) FiscalYear::query()->whereKey($yearId)->value('year');

        return view('rkas-budget.audit-mirror', $audit->summarize($fundSourceId, $year));
    }
}
