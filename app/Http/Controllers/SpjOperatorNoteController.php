<?php

namespace App\Http\Controllers;

use App\UseCases\Spj\SpjOperatorNoteUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjOperatorNoteController extends Controller
{
    public function store(string $packageId, Request $request, SpjOperatorNoteUseCase $useCase): RedirectResponse
    {
        return $useCase->store($packageId, $request);
    }

    public function destroy(string $noteId, Request $request, SpjOperatorNoteUseCase $useCase): RedirectResponse
    {
        return $useCase->destroy($noteId, $request);
    }
}
