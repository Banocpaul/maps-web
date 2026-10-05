<?php

namespace App\Services;

use App\Models\PublicIncidentReport;
use App\Models\User;

class IncidentReportWorkflow
{
    public function allowedTypes(User $user): array
    {
        $role = $user->role()->first();
        if (! $user->is_active || ! $role?->is_active) {
            return [];
        }

        return match ($role->slug) {
            'operations-manager', 'operations-officer' => ['fire', 'flood'],
            'fire-responder' => ['fire'],
            'flood-analyst' => ['flood'],
            default => [],
        };
    }

    public function authorize(User $user, string $type, string $action = 'view'): void
    {
        abort_unless(in_array($type, $this->allowedTypes($user), true)
            && $user->hasPermission('public-submissions.'.$action), 403);
        if ($action === 'approve') {
            abort_unless($user->hasPermission($type.'.create'), 403);
        }
    }

    // Always call inside the same transaction that creates the official record.
    public function lockForPublication(int $id, string $type, User $user): PublicIncidentReport
    {
        $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($id);
        $this->authorize($user, $report->incident_type, 'approve');
        abort_unless($report->incident_type === $type, 422, 'The report type does not match the incident.');
        abort_unless($report->status === 'Validated' && ! $report->fire_incident_id && ! $report->flood_training_record_id,
            409, 'This report must be validated and can only be published once.');

        return $report;
    }

    public function published(PublicIncidentReport $report, string $foreignKey, int $incidentId, User $user): void
    {
        $report->update([
            $foreignKey => $incidentId, 'status' => 'Published',
            'published_by' => $user->id, 'published_at' => now(),
        ]);
        $report->events()->create([
            'actor_id' => $user->id, 'from_status' => 'Validated', 'to_status' => 'Published',
            'notes' => 'Official incident created after staff validation.',
        ]);
    }
}
