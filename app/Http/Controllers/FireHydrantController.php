<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FireHydrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FireHydrantController extends Controller
{
    /**
     * Display all fire hydrants.
     */
    public function index(Request $request): View
    {
        $query = FireHydrant::query()
            ->with('barangay')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('hydrant_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('barangay', function ($barangayQuery) use ($search) {
                        $barangayQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('barangay_id')) {
            $query->where(
                'barangay_id',
                $request->integer('barangay_id')
            );
        }

        $hydrants = $query
            ->paginate(15)
            ->withQueryString();

        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        $statistics = [
            'total' => FireHydrant::count(),

            'active' => FireHydrant::where(
                'status',
                'Active'
            )->count(),

            'inactive' => FireHydrant::where(
                'status',
                'Inactive'
            )->count(),

            'maintenance' => FireHydrant::where(
                'status',
                'Maintenance'
            )->count(),
        ];

        return view('fire.hydrants.index', compact(
            'hydrants',
            'barangays',
            'statistics'
        ));
    }

    /**
     * Show the form for creating a fire hydrant.
     */
    public function create(): View
    {
        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        return view('fire.hydrants.create', compact('barangays'));
    }

    /**
     * Store a new fire hydrant.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

            'hydrant_code' => [
                'required',
                'string',
                'max:100',
                'unique:fire_hydrants,hydrant_code',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'status' => [
                'required',
                Rule::in([
                    'Active',
                    'Inactive',
                    'Maintenance',
                ]),
            ],

            'installation_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'last_inspection_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        FireHydrant::create($validated);

        return redirect()
            ->route('fire-hydrants.index')
            ->with(
                'success',
                'Fire hydrant created successfully.'
            );
    }

    /**
     * Display a specific fire hydrant.
     */
    public function show(FireHydrant $fireHydrant): View
    {
        $fireHydrant->load('barangay');

        return view(
            'fire.hydrants.show',
            compact('fireHydrant')
        );
    }

    /**
     * Show the form for editing a fire hydrant.
     */
    public function edit(FireHydrant $fireHydrant): View
    {
        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        return view('fire.hydrants.edit', compact(
            'fireHydrant',
            'barangays'
        ));
    }

    /**
     * Update an existing fire hydrant.
     */
    public function update(
        Request $request,
        FireHydrant $fireHydrant
    ): RedirectResponse {
        $validated = $request->validate([
            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

            'hydrant_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fire_hydrants', 'hydrant_code')
                    ->ignore($fireHydrant->id),
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'status' => [
                'required',
                Rule::in([
                    'Active',
                    'Inactive',
                    'Maintenance',
                ]),
            ],

            'installation_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'last_inspection_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $fireHydrant->update($validated);

        return redirect()
            ->route('fire-hydrants.show', $fireHydrant)
            ->with(
                'success',
                'Fire hydrant updated successfully.'
            );
    }

    /**
     * Delete a fire hydrant.
     */
    public function destroy(FireHydrant $fireHydrant): RedirectResponse
    {
        $fireHydrant->delete();

        return redirect()
            ->route('fire-hydrants.index')
            ->with(
                'success',
                'Fire hydrant deleted successfully.'
            );
    }
}