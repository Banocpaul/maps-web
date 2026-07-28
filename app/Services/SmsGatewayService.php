<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SmsGatewayService
{
    public function send(string $phoneNumber, string $message): array
    {
        $gatewayUrl = config('services.sms_gateway.url');
        $username = config('services.sms_gateway.username');
        $password = config('services.sms_gateway.password');
        $simNumber = (int) config('services.sms_gateway.sim_number', 1);

        if (!$gatewayUrl) {
            return [
                'success' => false,
                'status' => null,
                'response' => null,
                'error' => 'SMS gateway URL is not configured.',
            ];
        }

        try {
            $request = Http::timeout(20)->acceptJson();

            if ($username !== null && $username !== '') {
                $request = $request->withBasicAuth(
                    (string) $username,
                    (string) $password
                );
            }

            $response = $request->post($gatewayUrl, [
                'textMessage' => [
                    'text' => $message,
                ],
                'phoneNumbers' => [$phoneNumber],
                'simNumber' => $simNumber,
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'error' => $response->successful()
                    ? null
                    : 'Gateway returned HTTP '.$response->status(),
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'status' => null,
                'response' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function test(string $phoneNumber): array
    {
        return $this->send(
            $phoneNumber,
            'M.A.P.S. SMS Gateway test message.'
        );
    }
}