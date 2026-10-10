<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DatabaseBackup;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\FloodIncidentRecord;
use App\Models\PredictionExecution;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class ProcessDashboardService
{
    public function build(User $user, Role $role): array
    {
        $cards = [];
        $tasks = [];
        $links = [];
        $admin = $role->slug === 'administrator';
        $permissions = $role->permissions->where('is_active', true)->pluck('slug')->all();
        $can = fn (string $permission): bool => $user->is_active && $role->is_active
            && $role->slug !== 'public-resident' && ($admin || in_array($permission, $permissions, true));
        $hazards = match ($role->slug) {
            'fire-responder' => ['fire'], 'flood-analyst' => ['flood'],
            'operations-manager' => ['fire', 'flood'], default => [],
        };
        $today = now('Asia/Manila')->startOfDay()->utc();
        $tomorrow = $today->copy()->addDay();

        if ($hazards && $can('public-submissions.view')) {
            foreach (['Pending' => 'Reports to Review', 'Validated' => 'Ready to Publish'] as $status => $title) {
                $query = PublicIncidentReport::whereIn('incident_type', $hazards)->where('status', $status);
                $this->card($cards, 'reports-'.$status, $title, $query->count(), route('public-submissions.index', ['status' => $status]), 'Open Reports');
                foreach ($query->orderBy('updated_at')->orderBy('id')->limit(10)->get() as $report) {
                    $action = $status === 'Pending' && $can('public-submissions.review') ? 'Review Report' : 'View Report';
                    if ($status === 'Validated' && $can('public-submissions.approve') && $can($report->incident_type.'.create')) {
                        $action = 'Publish Incident';
                    }
                    $this->task($tasks, 'report-'.$report->id, ucfirst($report->incident_type).' report · '.$report->reference,
                        'Map pin to verify', $status, $report->updated_at, $action,
                        route('public-submissions.show', $report), $status === 'Validated' ? 1 : 2);
                }
            }
        }

        if (in_array('fire', $hazards, true) && $can('fire.view')) {
            $query = FireIncident::active();
            $this->card($cards, 'fire', 'Fire Response Follow-up', $query->count(), route('fire-incidents.index', ['status' => 'active']), 'Open Fire Incidents');
            foreach ($query->with('barangay')->orderByRaw("CASE status WHEN 'Reported' THEN 0 WHEN 'Responding' THEN 1 ELSE 2 END")
                ->orderBy('updated_at')->orderBy('id')->limit(10)->get() as $incident) {
                $edit = $can('fire.edit');
                $this->task($tasks, 'fire-'.$incident->id, 'Fire · '.$incident->incident_number,
                    $incident->barangay?->name ?? 'Unassigned', $incident->status, $incident->updated_at,
                    $edit ? 'Update Status' : 'View Incident', route($edit ? 'fire-incidents.edit' : 'fire-incidents.show', $incident),
                    match ($incident->status) {
                        'Reported' => 0, 'Responding' => 1, default => 2
                    });
            }
        }

        if (in_array('flood', $hazards, true) && $can('prediction.view')) {
            $query = FloodTrainingRecord::where('flood_status', 'Active');
            $operational = FloodIncidentRecord::where('status', 'Active')->whereNotNull('geometry_geojson');
            $operationalCount = ($can('records.view') || $can('flood.edit')) ? $operational->count() : 0;
            $floodUrl = $operationalCount && $can('gis.view') ? route('gis.index', ['hazard' => 'flood'])
                : route('flood-operation.index', ['flood_status' => 'Active']).'#dataset-management';
            $this->card($cards, 'flood', 'Active Flood Follow-up', $query->count() + $operationalCount, $floodUrl, 'Open Flood Records');
            if ($operationalCount) {
                foreach ($operational->orderByRaw("CASE WHEN flood_code IN ('C', 'D') THEN 0 ELSE 1 END")
                    ->orderBy('updated_at')->limit(10)->get() as $record) {
                    $this->task($tasks, 'flood-record-'.$record->id, 'Flood · '.$record->event_id, $record->barangay,
                        'Active · Level '.$record->flood_code, $record->updated_at,
                        $can('flood.edit') ? 'Update Flood Status' : 'View Flood Records',
                        $can('flood.edit') ? route('operational-records.flood.edit', $record)
                            : route('operational-records.index', ['dataset' => 'flood-records', 'search' => $record->event_id]),
                        in_array($record->flood_code, ['C', 'D'], true) ? 0 : 2);
                }
            }
            foreach ($query->orderByRaw("CASE WHEN flood_level_code IN ('C', 'D') THEN 0 ELSE 1 END")
                ->orderBy('updated_at')->orderBy('id')->limit(10)->get() as $record) {
                $edit = $can('flood.edit');
                $this->task($tasks, 'flood-'.$record->id, 'Flood · '.($record->location_name ?: 'Record #'.$record->id),
                    $record->barangay, 'Active · Level '.($record->flood_level_code ?: 'Unknown'), $record->updated_at,
                    $edit ? 'Update Flood Status' : 'View Flood Records', route('flood-operation.index',
                        $edit ? ['record_id' => $record->id] : ['flood_status' => 'Active']).'#dataset-management',
                    in_array($record->flood_level_code, ['C', 'D'], true) ? 0 : 2);
            }

            $query = PredictionExecution::where('kind', 'Forecast')->where('status', 'Completed')->doesntHave('remarks');
            $this->card($cards, 'prediction', 'Forecasts without Remarks', $query->count(), route('prediction.history.index', ['status' => 'Completed', 'kind' => 'Forecast', 'needs_remark' => 1]), 'Review Predictions');
            foreach ($query->orderBy('requested_at')->orderBy('id')->limit(10)->get() as $run) {
                $this->task($tasks, 'prediction-'.$run->id, 'Forecast #'.$run->id.' · '.$run->forecast_hours.' hours',
                    'Citywide', 'Awaiting remarks', $run->updated_at, $can('prediction.review') ? 'Review / Add Remark' : 'View Prediction',
                    route('prediction.history.show', $run), 3);
            }
        }

        if ($admin) {
            $query = User::where(fn ($q) => $q->where('is_active', false)->orWhereDoesntHave('role', fn ($r) => $r->where('is_active', true)));
            $this->card($cards, 'accounts', 'Inactive / Unassigned Accounts', $query->count(), route('users.index', ['attention' => 1]), 'Review Accounts');
            foreach ($query->with('role')->orderBy('updated_at')->orderBy('id')->limit(10)->get() as $account) {
                $this->task($tasks, 'user-'.$account->id, $account->full_name ?: ($account->name ?: 'Account #'.$account->id), 'System',
                    ! $account->is_active ? 'Inactive account' : 'No active role', $account->updated_at,
                    'Review Account', route('users.edit', $account), 2);
            }
            $query = DatabaseBackup::where('status', 'completed')->whereNull('verified_at');
            $this->card($cards, 'backups', 'Backups to Verify', $query->count(), route('admin.backups.index', ['attention' => 'unverified']), 'Review Backups');
            foreach ($query->orderBy('created_at')->orderBy('id')->limit(10)->get() as $backup) {
                $this->task($tasks, 'backup-'.$backup->id, $backup->filename ?: 'Backup #'.$backup->id, 'System',
                    'Awaiting verification', $backup->updated_at, 'Review / Verify', route('admin.backups.index', ['attention' => 'unverified']).'#backup-'.$backup->uuid, 2);
            }
            $query = DatabaseBackup::where('status', 'failed')->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow);
            $this->card($cards, 'backup-failures', 'Failed Backups Today', $query->count(), route('admin.backups.index', ['attention' => 'failed-today']), 'Check Backups');
            foreach ($query->orderBy('created_at')->orderBy('id')->limit(10)->get() as $backup) {
                $this->task($tasks, 'backup-failed-'.$backup->id, 'Failed backup #'.$backup->id, 'System', 'Failed', $backup->updated_at,
                    'Check Failure', route('admin.backups.index', ['attention' => 'failed-today']).'#backup-'.$backup->uuid, 1);
            }
            $query = ActivityLog::where('action', 'failed_login')->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow);
            $this->card($cards, 'security', 'Failed Logins Today', $query->count(), route('activity-logs.index', ['action' => 'failed_login', 'today' => 1]), 'Review Security Events');
            foreach ($query->orderBy('created_at')->orderBy('id')->limit(10)->get() as $event) {
                $this->task($tasks, 'security-'.$event->id, 'Sign-in failure · '.($event->user_name ?: 'Unknown user'), 'System',
                    'Authentication failed', $event->created_at, 'Review Event', route('activity-logs.index', ['action' => 'failed_login', 'today' => 1]), 1);
            }
        }

        if (($admin || $role->slug === 'operations-manager') && $can('sms.view')) {
            $query = SmsLog::where('status', 'failed')->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow);
            $url = route('sms.index', ['status' => 'failed', 'date' => $today->copy()->timezone('Asia/Manila')->format('Y-m-d')]).'#sms-logs';
            $this->card($cards, 'sms', 'SMS Failures Today', $query->count(), $url, 'Check Delivery Logs');
            foreach ($query->with('recipient.barangay')->orderBy('created_at')->orderBy('id')->limit(10)->get() as $log) {
                $this->task($tasks, 'sms-'.$log->id, 'SMS · '.($log->recipient_name ?: 'Delivery #'.$log->id),
                    $log->recipient?->barangay?->name ?? 'Unassigned', 'Delivery failed', $log->updated_at, 'Check Delivery', $url, 4);
            }
        }

        $navigation = $admin ? [
            ['User Management', 'users.index', 'users.manage'], ['Backup & Recovery', 'admin.backups.index', 'dashboard.view'],
            ['Activity Logs', 'activity-logs.index', 'activity-logs.view'], ['System Analytics', 'system-analytics.index', 'dashboard.view'],
            ['Settings', 'settings.index', 'settings.manage'],
        ] : [
            ['Incident Analytics', 'incident-analytics.index', 'dashboard.view'], ['GIS Mapping', 'gis.index', 'gis.view'],
            ['Prediction History', 'prediction.history.index', 'prediction.view'],
            ['Flood Prediction', 'prediction.index', 'prediction.view'], ['Flood Operations', 'flood-operation.index', 'prediction.view'],
            ['Fire Incidents', 'fire-incidents.index', 'fire.view'], ['SMS Center', 'sms.index', 'sms.view'],
        ];
        foreach ($navigation as [$label, $route, $permission]) {
            $inScope = $admin || (! str_starts_with($permission, 'prediction.') || in_array('flood', $hazards, true))
                && (! str_starts_with($permission, 'fire.') || in_array('fire', $hazards, true))
                && ($permission !== 'sms.view' || $role->slug === 'operations-manager');
            if ($inScope && $can($permission) && Route::has($route)) {
                $links[] = ['label' => $label, 'url' => route($route)];
            }
        }

        return [
            'workflowCards' => $cards,
            'workQueue' => collect($tasks)->sortBy([['priority', 'asc'], ['updated_at', 'asc'], ['key', 'asc']])->take(20)->values(),
            'quickLinks' => $links,
        ];
    }

    private function card(array &$cards, string $key, string $title, int $count, string $url, string $action): void
    {
        $cards[] = compact('key', 'title', 'count', 'url', 'action');
    }

    private function task(array &$tasks, string $key, string $title, ?string $barangay, string $stage, ?Carbon $updated_at, string $action, string $url, int $priority): void
    {
        $tasks[] = compact('key', 'title', 'barangay', 'stage', 'updated_at', 'action', 'url', 'priority');
    }
}
