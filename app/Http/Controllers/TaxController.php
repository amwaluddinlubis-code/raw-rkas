<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request): View
    {
        // Daftar + ringkasan dirender Livewire <livewire:tax-filter /> tanpa
        // reload; controller hanya meneruskan filter awal agar tidak ada
        // query agregat ganda yang hasilnya tidak dipakai view.
        return view('taxes.index', [
            'search' => trim((string) $request->string('q')),
            'month' => $request->integer('month') ?: null,
            'quarter' => $request->integer('quarter') ?: null,
            'semester' => $request->integer('semester') ?: null,
        ]);
    }
}
