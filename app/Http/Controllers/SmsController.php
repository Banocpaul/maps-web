<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\SmsAutomationRule;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use App\Services\SmsGatewayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class SmsController extends Controller
{
    public function index(): View
    {
        $recipients = SmsRecipient::query()
            ->with('barangay')
            ->latest()
            ->get();

        $barangays = Barangay::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $automationRules = SmsAutomationRule::query()
            ->latest()
            ->get();

        $logs = SmsLog::query()
            ->with([
                'recipient',
                'automationRule',
                'sender',
            ])
            ->latest()
            ->limit(100)
            ->get();

        $statistics = [
            'active_recipients' => SmsRecipient::query()
                ->where('is_active', true)
                ->count(),

            'enabled_rules' => SmsAutomationRule::query()
                ->where('is_enabled', true)
                ->count(),

            'sent_today' => SmsLog::query()
                ->whereDate('created_at', today())
                ->where('status', 'sent')
                ->count(),

            'failed_today' => SmsLog::query()
                ->whereDate('created_at', today())
                ->where('status', 'failed')
                ->count(),
        ];

        return view('sms.index', compact(
            'recipients',
            'automationRules',
            'logs',
            'statistics',
            'barangays'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient Management
    |--------------------------------------------------------------------------
    */

    public function storeRecipient(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
            ],

            'position' => [
                'nullable',
                'string',
                'max:255',
            ],

            'office_or_barangay' => [
                'nullable',
                'string',
                'max:255',
            ],

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
            ],
        ]);

        $phoneNumber = $this->normalizePhoneNumber(
            $validated['phone_number']
        );

        $duplicateExists = SmsRecipient::query()
            ->where('phone_number', $phoneNumber)
            ->exists();

        if ($duplicateExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'phone_number' =>
                        'This phone number is already registered.',
                ]);
        }

        SmsRecipient::create([
            'full_name' => $validated['full_name'],
            'phone_number' => $phoneNumber,
            'position' => $validated['position'] ?? null,
            'office_or_barangay' =>
                $validated['office_or_barangay'] ?? null,
            'barangay_id' => $validated['barangay_id'] ?? null,

            'receive_flood_alerts' =>
                $request->boolean('receive_flood_alerts'),

            'receive_fire_alerts' =>
                $request->boolean('receive_fire_alerts'),

            'receive_general_alerts' =>
                $request->boolean('receive_general_alerts'),

            'is_active' => $request->boolean('is_active', true),

            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            'SMS recipient added successfully.'
        );
    }

    public function updateRecipient(
        Request $request,
        SmsRecipient $recipient
    ): RedirectResponse {
        $validated = $request->validate([
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
            ],

            'position' => [
                'nullable',
                'string',
                'max:255',
            ],

            'office_or_barangay' => [
                'nullable',
                'string',
                'max:255',
            ],

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
            ],
        ]);

        $phoneNumber = $this->normalizePhoneNumber(
            $validated['phone_number']
        );

        $duplicateExists = SmsRecipient::query()
            ->where('phone_number', $phoneNumber)
            ->whereKeyNot($recipient->id)
            ->exists();

        if ($duplicateExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'phone_number' =>
                        'This phone number is already registered.',
                ]);
        }

        $recipient->update([
            'full_name' => $validated['full_name'],
            'phone_number' => $phoneNumber,
            'position' => $validated['position'] ?? null,
            'office_or_barangay' =>
                $validated['office_or_barangay'] ?? null,
            'barangay_id' => $validated['barangay_id'] ?? null,

            'receive_flood_alerts' =>
                $request->boolean('receive_flood_alerts'),

            'receive_fire_alerts' =>
                $request->boolean('receive_fire_alerts'),

            'receive_general_alerts' =>
                $request->boolean('receive_general_alerts'),

            'is_active' => $request->boolean('is_active'),

            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            'SMS recipient updated successfully.'
        );
    }

    public function toggleRecipient(
        SmsRecipient $recipient
    ): RedirectResponse {
        $recipient->update([
            'is_active' => ! $recipient->is_active,
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            $recipient->is_active
                ? 'Recipient activated successfully.'
                : 'Recipient deactivated successfully.'
        );
    }

    public function destroyRecipient(
        SmsRecipient $recipient
    ): RedirectResponse {
        $recipient->delete();

        return back()->with(
            'success',
            'SMS recipient deleted successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Automation Rule Management
    |--------------------------------------------------------------------------
    */

    public function storeAutomationRule(
        Request $request
    ): RedirectResponse {
        $validated = $this->validateAutomationRule($request);

        SmsAutomationRule::create([
            ...$validated,
            'description' => $validated['description'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            'SMS automation rule created successfully.'
        );
    }

    public function updateAutomationRule(
        Request $request,
        SmsAutomationRule $rule
    ): RedirectResponse {
        $validated = $this->validateAutomationRule($request);

        $rule->update([
            ...$validated,
            'description' => $validated['description'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'is_enabled' => $request->boolean('is_enabled'),
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            'SMS automation rule updated successfully.'
        );
    }

    public function toggleAutomationRule(
        SmsAutomationRule $rule
    ): RedirectResponse {
        $rule->update([
            'is_enabled' => ! $rule->is_enabled,
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            $rule->is_enabled
                ? 'Automation rule enabled successfully.'
                : 'Automation rule disabled successfully.'
        );
    }

    public function destroyAutomationRule(
        SmsAutomationRule $rule
    ): RedirectResponse {
        $rule->delete();

        return back()->with(
            'success',
            'SMS automation rule deleted successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Manual SMS
    |--------------------------------------------------------------------------
    */

    public function sendManual(
        Request $request,
        SmsGatewayService $smsGateway
    ): RedirectResponse {
        $validated = $request->validate([
            'recipient_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'recipient_ids.*' => [
                'integer',
                'exists:sms_recipients,id',
            ],

            'message' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $recipients = SmsRecipient::query()
            ->whereIn('id', $validated['recipient_ids'])
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return back()->withErrors([
                'recipient_ids' =>
                    'No active SMS recipients were selected.',
            ]);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $log = SmsLog::create([
                'sms_recipient_id' => $recipient->id,
                'sent_by' => auth()->id(),
                'recipient_name' => $recipient->full_name,
                'phone_number' => $recipient->phone_number,
                'message' => $validated['message'],
                'source' => 'manual',
                'status' => 'pending',
            ]);

            try {
    $result = $smsGateway->send(
        $recipient->phone_number,
        $validated['message']
    );

    if (! ($result['success'] ?? false)) {
        $log->update([
            'status' => 'failed',
            'http_status' => $result['status'] ?? null,
            'gateway_response' => json_encode(
                $result['response'] ?? $result
            ),
            'failure_reason' =>
                $result['error'] ?? 'SMS gateway rejected the message.',
        ]);

        $failedCount++;

        continue;
    }

    $log->update([
        'status' => 'sent',
        'http_status' => $result['status'] ?? null,
        'gateway_response' => json_encode(
            $result['response'] ?? $result
        ),
        'failure_reason' => null,
        'sent_at' => now(),
    ]);

    $sentCount++;
} catch (Throwable $exception) {

                $log->update([
                    'status' => 'failed',
                    'failure_reason' => $exception->getMessage(),
                ]);

                $failedCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->withErrors([
                'sms' =>
                    "All {$failedCount} SMS message(s) failed.",
            ]);
        }

        return back()->with(
            'success',
            "{$sentCount} SMS message(s) sent successfully. " .
            "{$failedCount} message(s) failed."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Test SMS
    |--------------------------------------------------------------------------
    */

    public function sendTest(
        Request $request,
        SmsGatewayService $smsGateway
    ): RedirectResponse {
        $validated = $request->validate([
            'test_phone_number' => [
                'required',
                'string',
                'max:30',
            ],
        ]);

        $phoneNumber = $this->normalizePhoneNumber(
            $validated['test_phone_number']
        );

        $message = sprintf(
            'M.A.P.S. SMS Gateway Test: Connection successful at %s.',
            now()->format('M d, Y h:i A')
        );

        $log = SmsLog::create([
            'sent_by' => auth()->id(),
            'recipient_name' => 'Gateway Test Recipient',
            'phone_number' => $phoneNumber,
            'message' => $message,
            'source' => 'test',
            'status' => 'pending',
        ]);

        try {
            try {
    $result = $smsGateway->send(
        $phoneNumber,
        $message
    );

    if (! ($result['success'] ?? false)) {
        $failureReason =
            $result['error'] ?? 'SMS gateway rejected the test message.';

        $log->update([
            'status' => 'failed',
            'http_status' => $result['status'] ?? null,
            'gateway_response' => json_encode(
                $result['response'] ?? $result
            ),
            'failure_reason' => $failureReason,
        ]);

        return back()->withErrors([
            'sms' => $failureReason,
        ]);
    }

    $log->update([
        'status' => 'sent',
        'http_status' => $result['status'] ?? null,
        'gateway_response' => json_encode(
            $result['response'] ?? $result
        ),
        'failure_reason' => null,
        'sent_at' => now(),
    ]);

    return back()->with(
        'success',
        'Test SMS sent successfully.'
    );
} catch (Throwable $exception) {
    $log->update([
        'status' => 'failed',
        'failure_reason' => $exception->getMessage(),
    ]);

    return back()->withErrors([
        'sms' => $exception->getMessage(),
    ]);
}

            $log->update([
                'status' => 'sent',
                'http_status' => $result['status'] ?? null,
                'gateway_response' => json_encode(
                    $result['data'] ?? $result
                ),
                'sent_at' => now(),
            ]);

            return back()->with(
                'success',
                'Test SMS sent successfully.'
            );
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'sms' => $exception->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function validateAutomationRule(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'hazard_type' => [
                'required',
                Rule::in([
                    'flood',
                    'fire',
                    'general',
                ]),
            ],

            'condition_field' => [
                'required',
                Rule::in([
                    'risk_level',
                    'rainfall_24h_mm',
                    'flood_depth_mm',
                    'fire_status',
                ]),
            ],

            'condition_operator' => [
                'required',
                Rule::in([
                    '=',
                    '!=',
                    '>',
                    '>=',
                    '<',
                    '<=',
                ]),
            ],

            'condition_value' => [
                'required',
                'string',
                'max:150',
            ],

            'barangay' => [
                'nullable',
                'string',
                'max:150',
            ],

            'message_template' => [
                'required',
                'string',
                'max:1000',
            ],

            'recipient_scope' => [
                'required',
                Rule::in([
                    'all',
                    'flood',
                    'fire',
                    'selected',
                ]),
            ],

            'cooldown_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:10080',
            ],
        ]);
    }

    private function normalizePhoneNumber(
        string $phoneNumber
    ): string {
        $number = preg_replace(
            '/[^0-9+]/',
            '',
            trim($phoneNumber)
        );

        if ($number === null || $number === '') {
            throw new RuntimeException(
                'Invalid phone number.'
            );
        }

        if (str_starts_with($number, '09')) {
            return '+63' . substr($number, 1);
        }

        if (str_starts_with($number, '639')) {
            return '+' . $number;
        }

        return $number;
    }
}
