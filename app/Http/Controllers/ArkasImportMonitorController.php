<?php

namespace App\Http\Controllers;

use App\Services\ArkasImportMonitorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArkasImportMonitorController extends Controller
{
    public function __invoke(Request $request, ArkasImportMonitorService $monitor): View
    {
        return view('arkas.import-monitor', $monitor->summarize());
    }
}
