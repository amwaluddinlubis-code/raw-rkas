<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Services\ArkasMirrorBudgetService;
use App\Services\RkasRevisionComparisonService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RkasRevisionComparisonController extends Controller
{
    public function __invoke(Request $request, ArkasMirrorBudgetService $budgets, RkasRevisionComparisonService $comparisonService): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $year = (int) FiscalYear::query()->whereKey($yearId)->value('year');
        $revisions = $budgets->revisions($fundSourceId, $year);
        $revisionIds = array_column($revisions, 'id');
        $defaultPair = array_slice($revisions, -2);
        $fromId = trim((string) $request->query('from', $defaultPair[0]['id'] ?? ''));
        $toId = trim((string) $request->query('to', $defaultPair[1]['id'] ?? ''));
        $comparison = null;

        if ($request->hasAny(['from', 'to'])) {
            $validated = $request->validate([
                'from' => ['nullable', 'string', Rule::in($revisionIds)],
                'to' => ['nullable', 'string', Rule::in($revisionIds)],
            ]);
            $fromId = (string) ($validated['from'] ?? $fromId);
            $toId = (string) ($validated['to'] ?? $toId);

            // The workspace tab link intentionally supplies only the selected
            // destination revision. Pick its preceding revision automatically.
            if (empty($validated['from']) && ! empty($validated['to'])) {
                $toIndex = array_search($toId, $revisionIds, true);
                if (is_int($toIndex) && $toIndex > 0) {
                    $fromId = $revisionIds[$toIndex - 1];
                }
            }

            if ($fromId !== '' && $toId !== '' && $fromId === $toId) {
                abort(422, 'Pilih dua revisi yang berbeda untuk dibandingkan.');
            }
        }

        if ($fromId !== '' && $toId !== '') {
            $comparison = $comparisonService->compare($fundSourceId, $year, $fromId, $toId);
        }

        return view('rkas-budget.revision-comparison', [
            'revisions' => $revisions,
            'comparison' => $comparison,
            'selectedFrom' => $fromId,
            'selectedTo' => $toId,
            'year' => $year,
            'fundName' => (string) (\Illuminate\Support\Facades\DB::connection('school')->table('fund_sources')->where('id', $fundSourceId)->value('name') ?? ''),
        ]);
    }
}
