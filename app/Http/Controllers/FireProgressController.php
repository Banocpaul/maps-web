<?php
namespace App\Http\Controllers;
use App\Models\FireIncident;
use App\Models\FireIncidentUpdate;
use App\Services\FireIncidentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FireProgressController extends Controller {
    public function store(Request $request, FireIncident $fireIncident) {
        $data = $request->validate([
            'observed_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['Reported', 'Responding', 'Controlled', 'Resolved'])],
            'alarm_level' => ['nullable', Rule::in(FireIncidentUpdate::ALARMS)],
            'alarm_source' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $update = DB::transaction(function () use ($request, $fireIncident, $data) {
            $incident = FireIncident::lockForUpdate()->findOrFail($fireIncident->id);
            abort_unless($incident->canUpdateResponse(), 409, 'This incident is closed.');
            $observed = \Carbon\Carbon::parse($data['observed_at'], 'Asia/Manila')->utc();
            if ($observed->gt(now())) throw ValidationException::withMessages(['observed_at' => 'The update cannot be in the future.']);
            $last = $incident->progressUpdates()->first();
            if ($observed->lt($incident->reported_at) || ($last && $observed->lt($last->observed_at))
                || ($incident->responded_at && $observed->lt($incident->responded_at))) {
                throw ValidationException::withMessages(['observed_at' => 'The update must follow the report, response and previous progress update.']);
            }
            $order = ['Reported' => 0, 'Responding' => 1, 'Controlled' => 2, 'Resolved' => 3];
            if ($order[$data['status']] < $order[$incident->status]) throw ValidationException::withMessages(['status' => 'The response status cannot move backwards.']);
            $values = ['status' => $data['status'], 'alarm_level' => $data['alarm_level'] ?? $incident->alarm_level];
            if ($data['status'] === 'Responding' && ! $incident->responded_at) $values['responded_at'] = $observed;
            if ($data['status'] === 'Resolved') {
                $values += ['fire_out_at' => $observed, 'resolved_at' => $observed,
                    'occurred_at' => $incident->occurred_at ?? $incident->reported_at];
            }
            $incident->update($values);
            return $incident->progressUpdates()->create(array_replace($data, [
                'actor_id' => $request->user()->id, 'observed_at' => $observed, 'alarm_level' => $incident->alarm_level,
            ]));
        });
        try { app(FireIncidentAlertService::class)->sendProgressAlert($fireIncident->fresh(), $update, $request->user()->id); }
        catch (\Throwable $error) { report($error); }
        return back()->with('success', 'Fire progress saved. Check SMS logs for alert delivery.');
    }
}
