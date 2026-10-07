<?php

namespace App\Http\Controllers;

use App\Services\ArkasMirrorResolver;
use App\UseCases\Spj\SpjQuarterRecapUseCase;
use Illuminate\View\View;

class SpjQuarterRecapController extends Controller
{
    public function __invoke(SpjQuarterRecapUseCase $useCase): View
    {
        $quarter = request()->integer('quarter');
        if ($quarter < 1 || $quarter > 4) {
            $quarter = ArkasMirrorResolver::quarterOfMonth((int) now()->format('n'));
        }

        return view('spj.quarter-recap', [
            'recap' => $useCase->recap($quarter),
        ]);
    }
}
