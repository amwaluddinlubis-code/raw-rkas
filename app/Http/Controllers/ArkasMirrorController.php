<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeArkasMirror;
use App\Models\BackgroundOperation;
use App\Models\School;
use App\Services\ArkasFixedMirrorService;
use App\Services\SchoolDatabaseManager;
use App\UseCases\Spj\SynchronizeArkasMirrorUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ArkasMirrorController extends Controller
{
    public function index(SchoolDatabaseManager $databases): View
    {
        $status = [];

        if (session('active_school_id') && ($school = School::find(session('active_school_id')))) {
            $databases->activate($school);
        }

        foreach (ArkasFixedMirrorService::registry() as $entry) {
            $connection = $entry['connection'] === 'central' ? null : 'school';
            $rows = 0;

            try {
                if (Schema::connection($connection)->hasTable($entry['mirror'])) {
                    $rows = DB::connection($connection)->table($entry['mirror'])->count();
                }
            } catch (\Throwable) {
                $rows = 0;
            }

            $status[] = $entry + ['rows' => $rows];
        }

        $lastRun = BackgroundOperation::query()
            ->whereIn('type', ['ARKAS_FIXED_MIRROR', 'ARKAS_FIXED_MIRROR_REFS', 'ARKAS_FIXED_MIRROR_SCHOOL'])
            ->latest('id')
            ->first();

        $lastRefsRun = BackgroundOperation::query()
            ->where('type', 'ARKAS_FIXED_MIRROR_REFS')
            ->latest('id')
            ->first();

        return view('arkas.mirror', compact('status', 'lastRun', 'lastRefsRun'));
    }

    public function status(): JsonResponse
    {
        $shape = static fn (?BackgroundOperation $operation): ?array => $operation ? [
            'id' => $operation->id,
            'type' => $operation->type,
            'status' => $operation->status,
            'progress' => (int) ($operation->progress ?? 0),
            'message' => (string) ($operation->message ?? ''),
        ] : null;

        return response()->json([
            'refs' => $shape(BackgroundOperation::query()->where('type', 'ARKAS_FIXED_MIRROR_REFS')->latest('id')->first()),
            'school' => $shape(BackgroundOperation::query()->where('type', 'ARKAS_FIXED_MIRROR_SCHOOL')->latest('id')->first()),
        ]);
    }

    public function syncRefs(Request $request, SynchronizeArkasMirrorUseCase $useCase): RedirectResponse
    {
        return $this->runScope($request, $useCase, 'refs', 'ARKAS_FIXED_MIRROR_REFS', 'Mirror referensi ARKAS');
    }

    public function syncSchool(Request $request, SynchronizeArkasMirrorUseCase $useCase): RedirectResponse
    {
        return $this->runScope($request, $useCase, 'school', 'ARKAS_FIXED_MIRROR_SCHOOL', 'Mirror sekolah ARKAS');
    }

    /** @param 'all'|'refs'|'school' $scope */
    private function runScope(Request $request, SynchronizeArkasMirrorUseCase $useCase, string $scope, string $operationType, string $label): RedirectResponse
    {
        $request->validate(['confirm_sync' => ['accepted']]);

        $school = School::findOrFail(session('active_school_id'));
        $fiscalYearId = session('active_fiscal_year_id') ? (int) session('active_fiscal_year_id') : null;

        $operation = BackgroundOperation::query()->create([
            'school_id' => $school->id,
            'fiscal_year_id' => $fiscalYearId,
            'requested_by' => $request->user()?->id,
            'type' => $operationType,
            'status' => 'QUEUED',
            'message' => 'Menunggu '.$label.'.',
        ]);

        if ((bool) config('queue.arkas_sync_async', false)) {
            SynchronizeArkasMirror::dispatch($operation->id, $school->id, $fiscalYearId, $scope)->onQueue('operations');

            return back()->with('success', $label.' masuk antrean. ID proses: '.$operation->id.'.');
        }

        SynchronizeArkasMirror::dispatchSync($operation->id, $school->id, $fiscalYearId, $scope);
        $operation->refresh();

        if ($operation->status === 'FAILED') {
            return back()->with('error', $operation->message ?: $label.' gagal.');
        }

        return back()->with('success', $operation->message ?: $label.' selesai.');
    }
}
