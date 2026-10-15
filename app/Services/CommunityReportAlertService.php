<?php
namespace App\Services;

use App\Models\Barangay;
use App\Models\PublicIncidentReport;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use Illuminate\Support\Str;

class CommunityReportAlertService
{
    public function send(int $groupId): void
    {
        try {
            $report = PublicIncidentReport::findOrFail($groupId);
            if (! $report->community_verified || $report->status === 'Rejected') return;
            $names = app(IncidentReportArea::class)->barangayNames($report->latitude, $report->longitude);
            $key = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($s)));
            $matches = Barangay::active()->get()->filter(fn ($b) => collect($names)->contains(fn ($n) => $key($n) === $key($b->name)));
            if ($matches->count() !== 1) return;
            $barangay = $matches->first();
            $message = 'M.A.P.S. COMMUNITY '.strtoupper($report->incident_type).' ALERT'."\n"
                .$report->street.', Brgy. '.$barangay->name.'. Multiple residents reported this incident. Community verified; official details pending. Avoid the affected area and follow CDRRMO instructions.';
            $recipients = SmsRecipient::eligibleForAlerts()->whereNotNull('user_id')
                ->where('barangay_id', $barangay->id)->where('receive_'.$report->incident_type.'_alerts', true)->get();
            foreach ($recipients as $recipient) {
                $log = SmsLog::firstOrCreate([
                    'public_report_group_id' => $groupId, 'sms_recipient_id' => $recipient->id,
                    'alert_key' => 'community_verified',
                ], [
                    'recipient_name' => $recipient->full_name, 'phone_number' => $recipient->phone_number,
                    'message' => $message, 'source' => 'automatic', 'status' => 'pending',
                    'condition_data' => ['event' => 'community_verified', 'incident_type' => $report->incident_type],
                ]);
                if (! $log->wasRecentlyCreated) continue;
                try {
                    $result = app(SmsGatewayService::class)->send($recipient->phone_number, $message);
                    $log->update(['status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                        'sent_at' => ($result['success'] ?? false) ? now() : null,
                        'failure_reason' => $result['error'] ?? null, 'http_status' => $result['status'] ?? null]);
                } catch (\Throwable $error) {
                    $log->update(['status' => 'failed', 'failure_reason' => 'Community alert delivery failed.']);
                    report($error);
                }
            }
        } catch (\Throwable $error) {
            report($error);
        }
    }
}
