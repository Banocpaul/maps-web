<?php

namespace App\Services;

use App\Models\FireIncident;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use Illuminate\Database\QueryException;
use Throwable;

class FireIncidentAlertService
{
    private const ALERT_KEY = 'fire_incident_created';

    public function __construct(
        private readonly SmsGatewayService $smsGateway,
        private readonly NearestFireHydrantService $nearestHydrantService
    ) {
    }

    public function sendCreatedAlert(FireIncident $incident, ?int $sentBy): array
    {
        $incident->loadMissing('barangay');
        $nearestHydrant = $this->nearestHydrantService->findForIncident($incident);
        $message = $this->buildMessage($incident, $nearestHydrant);
        $recipients = SmsRecipient::query()
            ->where('barangay_id', $incident->barangay_id)
            ->where('receive_fire_alerts', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $summary = [
            'eligible' => $recipients->count(),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'nearest_hydrant' => $nearestHydrant,
        ];

        foreach ($recipients as $recipient) {
            try {
                $log = SmsLog::create([
                    'sms_recipient_id' => $recipient->id,
                    'fire_incident_id' => $incident->id,
                    'sent_by' => $sentBy,
                    'recipient_name' => $recipient->full_name,
                    'phone_number' => $recipient->phone_number,
                    'message' => $message,
                    'source' => 'automatic',
                    'alert_key' => self::ALERT_KEY,
                    'status' => 'pending',
                    'condition_data' => $this->conditionData($incident, $nearestHydrant),
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicateAlert($incident, $recipient)) {
                    $summary['skipped']++;
                    continue;
                }

                throw $exception;
            }

            try {
                $result = $this->smsGateway->send($recipient->phone_number, $message);

                if (! ($result['success'] ?? false)) {
                    $this->markFailed($log, $result['error'] ?? 'SMS gateway rejected the message.', $result);
                    $summary['failed']++;
                    continue;
                }

                $log->update([
                    'status' => 'sent',
                    'http_status' => $result['status'] ?? null,
                    'gateway_response' => json_encode($result['response'] ?? $result),
                    'failure_reason' => null,
                    'sent_at' => now(),
                ]);
                $summary['sent']++;
            } catch (Throwable $exception) {
                $this->markFailed($log, $exception->getMessage());
                $summary['failed']++;
                report($exception);
            }
        }

        return $summary;
    }

    private function buildMessage(FireIncident $incident, ?array $nearestHydrant): string
    {
        $hydrant = $nearestHydrant['hydrant'] ?? null;
        $street = trim((string) ($incident->street ?: $incident->location));
        $corner = trim((string) $incident->corner);
        $locationLine = 'Street: ' . $street . ($corner !== '' ? ' / Corner: ' . $corner : '');
        $hydrantLine = $hydrant === null
            ? 'Nearest hydrant: Not available'
            : sprintf(
                'Nearest hydrant: %s, %s (%d m)',
                $hydrant->hydrant_code,
                $hydrant->location,
                (int) round($nearestHydrant['distance_meters'])
            );

        return implode("\n", [
            'M.A.P.S. FIRE ALERT',
            'Incident: ' . $incident->incident_number,
            'Brgy: ' . ($incident->barangay?->name ?? 'Not specified'),
            'Severity: ' . $incident->severity,
            $locationLine,
            sprintf('GPS: %.7f, %.7f', (float) $incident->latitude, (float) $incident->longitude),
            $hydrantLine,
            'Reported: ' . $incident->reported_at->format('M d, Y h:i A'),
            'Coordinate with CDRRMO.',
        ]);
    }

    private function conditionData(FireIncident $incident, ?array $nearestHydrant): array
    {
        $hydrant = $nearestHydrant['hydrant'] ?? null;

        return [
            'event' => self::ALERT_KEY,
            'fire_incident_id' => $incident->id,
            'incident_number' => $incident->incident_number,
            'barangay_id' => $incident->barangay_id,
            'latitude' => (float) $incident->latitude,
            'longitude' => (float) $incident->longitude,
            'severity' => $incident->severity,
            'street' => $incident->street,
            'corner' => $incident->corner,
            'nearest_hydrant_id' => $hydrant?->id,
            'nearest_hydrant_code' => $hydrant?->hydrant_code,
            'nearest_hydrant_distance_meters' => $nearestHydrant['distance_meters'] ?? null,
        ];
    }

    private function isDuplicateAlert(FireIncident $incident, SmsRecipient $recipient): bool
    {
        return SmsLog::query()
            ->where('fire_incident_id', $incident->id)
            ->where('sms_recipient_id', $recipient->id)
            ->where('alert_key', self::ALERT_KEY)
            ->exists();
    }

    private function markFailed(SmsLog $log, string $reason, ?array $result = null): void
    {
        $log->update([
            'status' => 'failed',
            'http_status' => $result['status'] ?? null,
            'gateway_response' => $result === null
                ? null
                : json_encode($result['response'] ?? $result),
            'failure_reason' => $reason,
        ]);
    }
}
