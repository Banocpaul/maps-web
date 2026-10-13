<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\FloodTrainingRecord;
use App\Models\FloodObservationChange;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use Illuminate\Database\QueryException;
use Throwable;

class FloodIncidentAlertService
{
    public function __construct(private readonly SmsGatewayService $gateway) {}

    public function sendChangeAlert(FloodTrainingRecord $record, FloodObservationChange $change, bool $retryFailed = false): array
    {
        return $this->sendCreatedAlert($record, $retryFailed ? auth()->id() : $change->changed_by, $change, $retryFailed);
    }

    public function sendCreatedAlert(FloodTrainingRecord $record, ?int $sentBy, ?FloodObservationChange $change = null, bool $retryFailed = false): array
    {
        $summary = ['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (($change === null && $record->flood_status !== 'Active') || $record->trashed()) {
            return $summary;
        }
        $barangay = Barangay::active()->where('name', $record->barangay)->first();
        if (! $barangay) {
            return $summary;
        }
        $recipients = SmsRecipient::eligibleForAlerts()->where('barangay_id', $barangay->id)
            ->where('receive_flood_alerts', true)->orderBy('id')->get();
        $summary['eligible'] = $recipients->count();
        $alertKey = $change ? 'flood_change_'.$change->id : 'flood_incident_created';
        $message = implode("\n", [
            'M.A.P.S. FLOOD ALERT', 'Brgy: '.$barangay->name,
            'Location: '.($record->location_name ?: $barangay->name),
            'Flood code: '.($change ? $change->previous_code.' → '.$change->new_code : $record->flood_level_code),
            'Status: '.($change?->new_status ?? $record->flood_status),
            'Update: '.($change?->action ?? 'New verified flood'),
            'Observed: '.($change?->observed_at ?? $record->observed_at)->copy()->timezone('Asia/Manila')->format('M d, Y h:i A').' (Manila time)',
            ($change?->new_status === 'Subsided' ? 'Flooding reported subsided. Remain cautious and follow CDRRMO advisories.' : 'Avoid flooded roads. Follow CDRRMO advisories.'),
        ]);
        foreach ($recipients as $recipient) {
            try {
                if ($retryFailed) {
                    $log = SmsLog::where('flood_training_record_id', $record->id)->where('sms_recipient_id', $recipient->id)
                        ->where('alert_key', $alertKey)->where('status', 'failed')->first();
                    if (! $log || SmsLog::whereKey($log->id)->where('status', 'failed')->update(['status' => 'pending', 'sent_by' => $sentBy, 'failure_reason' => null]) !== 1) {
                        $summary['skipped']++;
                        continue;
                    }
                } else {
                $log = SmsLog::create([
                    'sms_recipient_id' => $recipient->id, 'flood_training_record_id' => $record->id,
                    'sent_by' => $sentBy, 'recipient_name' => $recipient->full_name,
                    'phone_number' => $recipient->phone_number, 'message' => $message,
                    'source' => 'automatic', 'alert_key' => $alertKey, 'status' => 'pending',
                    'condition_data' => ['barangay_id' => $barangay->id, 'flood_level_code' => $change?->new_code ?? $record->flood_level_code, 'change_id' => $change?->id],
                ]);
                }
            } catch (QueryException $exception) {
                if (SmsLog::where('flood_training_record_id', $record->id)->where('sms_recipient_id', $recipient->id)
                    ->where('alert_key', $alertKey)->exists()) {
                    $summary['skipped']++;
                    continue;
                }
                throw $exception;
            }
            try {
                $result = $this->gateway->send($recipient->phone_number, $message);
                $success = (bool) ($result['success'] ?? false);
                $log->update([
                    'status' => $success ? 'sent' : 'failed', 'http_status' => $result['status'] ?? null,
                    'gateway_response' => json_encode($result['response'] ?? $result),
                    'failure_reason' => $success ? null : ($result['error'] ?? 'SMS gateway rejected the message.'),
                    'sent_at' => $success ? now() : null,
                ]);
                $summary[$success ? 'sent' : 'failed']++;
            } catch (Throwable $exception) {
                $log->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
                $summary['failed']++;
                report($exception);
            }
        }

        return $summary;
    }
}
