<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\View\View;

class FloodOperationController extends Controller
{
    public function index(): View
    {
        return view('flood-operation.index', [
            'barangayNames' => Barangay::query()
                ->active()
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }
}
