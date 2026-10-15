<?php
namespace App\Http\Controllers;
use App\Services\IncidentArchiveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentArchiveController extends Controller
{
    public function index(Request $request, IncidentArchiveService $service) {
        $data = $request->validate(['type' => ['nullable', Rule::in(array_keys(IncidentArchiveService::TYPES))],
            'state' => ['nullable', Rule::in(['eligible', 'archived'])], 'search' => ['nullable', 'string', 'max:100']]);
        $type = $data['type'] ?? 'fire'; $state = $data['state'] ?? 'archived'; $search = trim($data['search'] ?? '');
        $query = $service->query($type, $state);
        if ($search !== '') {
            $columns = $type === 'fire' ? ['incident_number', 'location', 'source_barangay']
                : ($type === 'flood' ? ['event_id', 'barangay'] : ['barangay', 'location_name']);
            $query->where(function ($q) use ($columns, $search): void {
                foreach ($columns as $column) $q->orWhere($column, 'like', '%'.$search.'%');
            });
        }
        $records = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $cutoff = $service->cutoff(true)->toDateString();
        $canManage = $request->user()->hasPermission($service->definition($type)[1]);
        return view('incident-archives.index', compact('records', 'type', 'state', 'search', 'cutoff', 'canManage'));
    }
    public function bulk(Request $request, IncidentArchiveService $service) {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(IncidentArchiveService::TYPES))],
            'action' => ['required', Rule::in(['archive', 'restore'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $service->authorize($request->user(), $data['type']);
        sort($data['ids']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $service): void {
            foreach ($data['ids'] as $id) $service->change($data['type'], $id, $request->user(), $data['reason'], $data['action'] === 'restore');
        });
        return back()->with('success', count($data['ids']).' incident(s) '.($data['action'] === 'archive' ? 'archived' : 'restored').' successfully.');
    }
    public function archive(Request $request, string $type, int $id, IncidentArchiveService $service) {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->change($type, $id, $request->user(), $data['reason']);
        return back()->with('success', 'Incident archived. Historical analytics are preserved.');
    }
    public function restore(Request $request, string $type, int $id, IncidentArchiveService $service) {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->change($type, $id, $request->user(), $data['reason'], true);
        return back()->with('success', 'Incident restored to current records.');
    }
    public function show(Request $request, string $type, int $id, IncidentArchiveService $service) {
        $record = $service->query($type, 'archived')->findOrFail($id);
        return view('incident-archives.show', compact('record', 'type'));
    }
}
