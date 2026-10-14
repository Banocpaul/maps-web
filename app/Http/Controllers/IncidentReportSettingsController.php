<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
class IncidentReportSettingsController extends Controller {
    public function update(Request $request): RedirectResponse {
        abort_unless($request->user()->hasAnyRole(['operations-manager', 'operations-officer'])
            && $request->user()->hasPermission('public-submissions.review'), 403);
        $data = $request->validate([
            'threshold' => ['required', 'integer', 'between:2,100'],
            'radius_m' => ['required', 'integer', 'between:10,500'],
            'window_minutes' => ['required', 'integer', 'between:5,1440'],
        ]);
        DB::transaction(function () use ($data): void {
            $before = (array) app(\App\Services\PublicReportVerification::class)->lockSettings();
            DB::table('incident_report_settings')->where('id', 1)->update($data);
            app(\App\Services\Audit\AuditRecorder::class)->record([
                'action' => 'update_report_settings', 'module' => 'incident-reports',
                'description' => 'Updated flood and fire public verification settings.',
                'subject_type' => 'incident_report_settings', 'subject_id' => 1,
                'old_values' => $before, 'new_values' => $data,
            ]);
        });
        return back()->with('success', 'Incident reporting settings saved. Applied to new submissions for flood and fire.');
    }
}
