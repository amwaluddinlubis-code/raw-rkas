<?php

namespace App\Http\Controllers;

use App\Services\ReferenceLookupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function index(Request $request, ReferenceLookupService $references): View
    {
        $data = $request->validate([
            'tab' => ['nullable', 'string', 'in:rekening,harga'],
            'tahun' => ['nullable', 'string', 'max:4'],
            'q' => ['nullable', 'string', 'max:100'],
            'perPage' => ['nullable', 'in:15,25,50,100'],
        ]);

        $tab = $data['tab'] ?? ReferenceLookupService::TAB_ACCOUNTS;
        $search = trim((string) ($data['q'] ?? ''));
        $perPage = (int) ($data['perPage'] ?? 15);

        $years = $references->availableYears();
        $year = ($data['tahun'] ?? null) !== null && ($data['tahun'] ?? '') !== ''
            ? (string) $data['tahun']
            : ($years[0] ?? null);

        if ($year !== null && $years !== [] && ! in_array($year, $years, true)) {
            $year = $years[0];
        }

        $rows = $tab === ReferenceLookupService::TAB_PRICES
            ? $references->paginatePriceReferences($year, $search, $perPage)
            : $references->paginateAccounts($year, $search, $perPage);

        return view('references.index', [
            'tab' => $tab,
            'years' => $years,
            'year' => $year,
            'search' => $search,
            'perPage' => $perPage,
            'rows' => $rows,
        ]);
    }
}
