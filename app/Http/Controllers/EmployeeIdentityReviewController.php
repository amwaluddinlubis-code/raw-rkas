<?php

namespace App\Http\Controllers;

use App\Services\EmployeeIdentityReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EmployeeIdentityReviewController extends Controller
{
    public function __invoke(Request $request, EmployeeIdentityReviewService $review): View
    {
        return view('employees.identity-review', ['groups' => $review->ambiguousGroups()]);
    }
}
