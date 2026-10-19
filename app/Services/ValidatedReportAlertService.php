<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\PublicIncidentReport;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ValidatedReportAlertService
{
    public function send(int $reportId, ?int $sentBy = null): array
    {
        $summary = ['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            $report = PublicIncidentReport::findOrFail($reportId);
            if ($report->status !== 'Validated') {
                return $summary;
            }

            $barangay = $report->incidentBarangay()->where('is_active', true)->first();
            if (! $barangay && $report->incident_barangay_id === null) {
                // Legacy reports have no selected incident barangay. Never substitute the reporter's home.
                $names = app(IncidentReportArea::class)->barangayNames($report->latitude, $report->longitude);
                $key = fn (string $name): string => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
                $matches = Barangay::active()->get()->filter(fn ($candidate) => collect($names)
                    ->contains(fn ($name) => $key($name) === $key($candidate->name)));
                $barangay = $matches->count() === 1 ? $matches->first() : null;
            }
            if (! $barangay) {
                Log::warning('Validated report SMS skipped: confirm the incident barangay.', ['public_report_id' => $report->id]);

                return $summary;
            }

            $groupId = $report->report_group_id ?: $report->id;
            $recipients = SmsRecipient::eligibleForAlerts()->whereNotNull('user_id')
                ->where('barangay_id', $barangay->id)->where('receive_'.$report->incident_type.'_alerts', true)->get();
            $summary['eligible'] = $recipients->count();
            $message = $this->message($report, $barangay);

            foreach ($recipients as $recipient) {
                // Older community alerts already notified these subscribers for this report group.
                if (SmsLog::where('public_report_group_id', $groupId)->where('sms_recipient_id', $recipient->id)
                    ->where('alert_key', 'community_verified')->exists()) {
                    $summary['skipped']++;
                    continue;
                }
                $log = SmsLog::firstOrCreate([
                    'public_report_group_id' => $groupId, 'sms_recipient_id' => $recipient->id,
                    'alert_key' => 'report_validated',
                ], [
                    'recipient_name' => $recipient->full_name, 'phone_number' => $recipient->phone_number,
                    'message' => $message, 'source' => 'automatic', 'status' => 'pending', 'sent_by' => $sentBy,
                    'condition_data' => ['event' => 'report_validated', 'public_report_id' => $report->id,
                        'incident_type' => $report->incident_type, 'barangay_id' => $barangay->id,
                        'validation_method' => $report->community_verified ? 'community' : 'staff'],
                ]);
                if (! $log->wasRecentlyCreated) {
                    $summary['skipped']++;
                    continue;
                }

                try {
                    $result = app(SmsGatewayService::class)->send($recipient->phone_number, $message);
                    $success = (bool) ($result['success'] ?? false);
                    $log->update([
                        'status' => $success ? 'sent' : 'failed', 'sent_at' => $success ? now() : null,
                        'failure_reason' => $success ? null : ($result['error'] ?? 'SMS gateway rejected the alert.'),
                        'http_status' => $result['status'] ?? null,
                        'gateway_response' => json_encode($result['response'] ?? $result),
                    ]);
                    $summary[$success ? 'sent' : 'failed']++;
                } catch (Throwable $error) {
                    $log->update(['status' => 'failed', 'failure_reason' => 'Validated report alert delivery failed.']);
                    $summary['failed']++;
                    report($error);
                }
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $summary;
    }

    private function message(PublicIncidentReport $report, Barangay $barangay): string
    {
        $lines = [
            'M.A.P.S. '.($report->community_verified ? 'COMMUNITY ' : 'VERIFIED ').strtoupper($report->incident_type).' ALERT',
            'Reference: '.$report->reference,
            'Location: '.($report->street ?: 'Pinned incident location').', Brgy. '.$barangay->name,
            sprintf('GPS: %.7f, %.7f', $report->latitude, $report->longitude),
        ];
        if ($report->incident_type === 'fire') {
            $lines[] = 'BFP alarm: '.($report->alarm_level ?: 'Not yet confirmed');
        }
        $lines[] = $report->community_verified
            ? 'Community verified; official details pending.'
            : 'Verified by response staff; official details pending.';
        $lines[] = 'Avoid the affected area and follow CDRRMO instructions.';

        return implode("\n", $lines);
    }
}
