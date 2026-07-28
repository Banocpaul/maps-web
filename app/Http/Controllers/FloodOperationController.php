<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FloodOperationController extends Controller
{
    public function index(): View
    {
        return view('flood-operation.index');
    }
}