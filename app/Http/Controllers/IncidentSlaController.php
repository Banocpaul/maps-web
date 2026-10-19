<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentSlaController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['operations-manager', 'operations-officer']), 403);
        $data = $request->validate([
            'hazard' => ['required', 'in:fire,flood'],
            'validation_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'publication_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'completion_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);
        DB::table('incident_sla_targets')->updateOrInsert(['hazard' => $data['hazard']], [
            'validation_minutes' => $data['validation_minutes'] ?? null,
            'publication_minutes' => $data['publication_minutes'] ?? null,
            'completion_minutes' => $data['completion_minutes'] ?? null,
            'updated_by' => $request->user()->id, 'updated_at' => now(),
            'created_at' => DB::table('incident_sla_targets')->where('hazard', $data['hazard'])->value('created_at') ?? now(),
        ]);

        return redirect()->route('incident-analytics.index', ['analytics' => $data['hazard']])->with('success', 'SLA targets updated. Times use elapsed minutes, including nights and weekends.');
    }
}
