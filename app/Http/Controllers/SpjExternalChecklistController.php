<?php

namespace App\Http\Controllers;

use App\UseCases\Spj\ToggleSpjExternalChecklistUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjExternalChecklistController extends Controller
{
    public function __invoke(string $packageId, Request $request, ToggleSpjExternalChecklistUseCase $useCase): RedirectResponse
    {
        return $useCase->handle($packageId, $request);
    }
}
