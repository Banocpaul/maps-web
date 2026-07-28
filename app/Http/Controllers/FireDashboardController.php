<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\FireAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FireDashboardController extends Controller
{
    public function __construct(
        private readonly FireAnalyticsService $fireAnalyticsService
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
            ],
        ]);

        $selectedYear = isset($validated['year'])
            ? (int) $validated['year']
            : null;

        $selectedBarangayId = isset($validated['barangay_id'])
            ? (int) $validated['barangay_id']
            : null;

        $dashboard = $this->fireAnalyticsService->getDashboardData(
            $selectedYear,
            $selectedBarangayId
        );

        $barangays = Barangay::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return view('fire.dashboard', [
            'dashboard' => $dashboard,
            'barangays' => $barangays,
            'selectedYear' => $selectedYear,
            'selectedBarangayId' => $selectedBarangayId,
        ]);
    }
}