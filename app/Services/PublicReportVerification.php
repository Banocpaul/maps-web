<?php
namespace App\Services;
use App\Models\PublicIncidentReport;
use Illuminate\Support\Facades\DB;

class PublicReportVerification
{
    // Called inside the submission transaction, before inserting the report.
    // A shared row lock serializes concurrent submissions and settings changes.
    public function lockSettings(): object
    {
        return DB::table('incident_report_settings')->where('id', 1)->lockForUpdate()->first();
    }

    public function verify(PublicIncidentReport $report, object $settings): void
    {
        $anchor = PublicIncidentReport::query()
            ->where('incident_type', $report->incident_type)
            ->whereIn('status', ['Pending', 'Validated'])
            ->where('created_at', '>=', now()->subMinutes($settings->window_minutes))
            ->whereColumn('report_group_id', 'id')->orderBy('id')->lockForUpdate()->get()
            ->filter(fn ($candidate) => $this->distance($report, $candidate) <= $settings->radius_m)
            ->sortBy(fn ($candidate) => $this->distance($report, $candidate))->first();
        $groupId = $anchor?->id ?? $report->id;
        $report->forceFill(['report_group_id' => $groupId])->save();
        $members = PublicIncidentReport::where('report_group_id', $groupId)
            ->whereIn('status', ['Pending', 'Validated'])
            ->where('created_at', '>=', now()->subMinutes($settings->window_minutes))->lockForUpdate()->get();
        $count = $members->pluck('submitted_by')->filter()->unique()->count();
        if ($count < $settings->threshold) {
            return;
        }
        foreach ($members->where('status', 'Pending') as $member) {
            $notes = "Automatically verified by {$count} distinct public residents reporting the same hazard (group #{$groupId}). Staff must complete official incident details before publication.";
            $member->forceFill(['status' => 'Validated', 'community_verified' => true,
                'validated_at' => now(), 'validated_by' => null, 'validation_notes' => $notes])->save();
            $member->events()->create(['actor_id' => null, 'from_status' => 'Pending',
                'to_status' => 'Validated', 'notes' => $notes]);
        }
    }

    private function distance(PublicIncidentReport $a, PublicIncidentReport $b): float
    {
        $lat1 = deg2rad($a->latitude);
        $lat2 = deg2rad($b->latitude);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2)
            * sin(deg2rad($b->longitude - $a->longitude) / 2) ** 2;
        return 6371000 * 2 * asin(sqrt(min(1, $h)));
    }
}
