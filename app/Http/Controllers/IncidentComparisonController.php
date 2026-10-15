<?php
namespace App\Http\Controllers;
use App\Models\Barangay;
use App\Services\IncidentComparisonService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class IncidentComparisonController extends Controller {
    public function index(Request $request, IncidentComparisonService $service) {
        $user = $request->user();
        $types = $user->isAdministrator() || $user->hasAnyRole(['operations-manager', 'operations-officer'])
            ? ['fire', 'flood'] : ($user->hasRole('fire-responder') ? ['fire'] : ($user->hasRole('flood-analyst') ? ['flood'] : []));
        abort_unless($types && $user->hasPermission('dashboard.view'), 403);
        $data = $request->validate([
            'hazard' => ['nullable', Rule::in(['all', ...$types])],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'year' => ['nullable', 'integer', 'min:2001', 'max:'.now('Asia/Manila')->year],
            'baseline_year' => ['nullable', 'integer', 'min:2001', 'max:'.now('Asia/Manila')->year, 'different:year'],
            'print' => ['nullable', 'boolean'],
        ]);
        $hazard = $data['hazard'] ?? (count($types) === 1 ? $types[0] : 'all');
        $selectedTypes = $hazard === 'all' ? $types : [$hazard];
        $year = (int) ($data['year'] ?? now('Asia/Manila')->year - 1);
        $baselineYear = (int) ($data['baseline_year'] ?? $year - 1);
        $report = $service->build($year, $selectedTypes, isset($data['barangay_id']) ? (int) $data['barangay_id'] : null, $baselineYear);
        $barangays = Barangay::active()->orderBy('name')->get(['id', 'name']);
        $filters = ['hazard' => $hazard, 'year' => $year, 'baseline_year' => $baselineYear, 'barangay_id' => $data['barangay_id'] ?? ''];
        return view($request->boolean('print') ? 'incident-comparison.print' : 'incident-comparison.index',
            compact('report', 'types', 'filters', 'barangays'));
    }
}
