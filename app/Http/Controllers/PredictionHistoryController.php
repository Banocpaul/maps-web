<?php

namespace App\Http\Controllers;

use App\Models\PredictionExecution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PredictionHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'forecast_hours' => ['nullable', Rule::in([24, 48, 72])],
            'kind' => ['nullable', Rule::in(['Forecast', 'Simulation'])],
            'status' => ['nullable', Rule::in(['Running', 'Completed', 'Failed'])],
            'needs_remark' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['search'] = trim((string) ($filters['search'] ?? ''));
        $runs = PredictionExecution::query()->withCount('remarks');
        if ($filters['search'] !== '') {
            $runs->where(function ($query) use ($filters): void {
                $query->where('requested_by_name', 'like', '%'.$filters['search'].'%');
                if (preg_match('/^(?:run\s*)?#?(\d+)$/i', $filters['search'], $match)) {
                    $query->orWhere('id', $match[1]);
                }
            });
        }
        if ($request->boolean('needs_remark')) {
            $runs->where('kind', 'Forecast')->where('status', 'Completed')->doesntHave('remarks');
        }
        foreach (array_diff_key($filters, ['needs_remark' => true, 'search' => true]) as $key => $value) {
            if ($value !== null && $value !== '') {
                $runs->where($key, $value);
            }
        }

        return view('prediction.history.index', [
            'runs' => $runs->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters,
        ]);
    }

    public function show(PredictionExecution $execution): View
    {
        $execution->load(['remarks' => fn ($query) => $query->orderBy('id')]);

        return view('prediction.history.show', compact('execution'));
    }

    public function remark(Request $request, PredictionExecution $execution): RedirectResponse
    {
        // Validation uses the saved result, so observations stay tied to the original run.
        $barangays = collect($execution->result_snapshot['predictions'] ?? [])
            ->pluck('barangay')->filter()->unique()->values()->all();
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'],
            'barangay' => ['nullable', 'string', Rule::in($barangays)],
        ]);
        $user = $request->user();
        $execution->remarks()->create([
            'user_id' => $user->id, 'author_name' => $user->full_name ?: ($user->name ?: 'Staff #'.$user->id),
            'barangay' => $validated['barangay'] ?? null, 'body' => trim($validated['body']),
        ]);

        return redirect()->route('prediction.history.show', $execution)->with('success', 'Remark added.');
    }
}
