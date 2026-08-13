<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SmsGatewayService
{
    public function send(
        string $phoneNumber,
        string $message
    ): array {
        $gatewayUrl = config('services.sms_gateway.url');
        $username = config('services.sms_gateway.username');
        $password = config('services.sms_gateway.password');

        if (empty($gatewayUrl)) {
            return [
                'success' => false,
                'status' => null,
                'response' => null,
                'error' => 'SMS gateway URL is not configured.',
            ];
        }

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->connectTimeout(20)
                ->timeout(60);

            if (! empty($username)) {
                $request = $request->withBasicAuth(
                    (string) $username,
                    (string) $password
                );
            }

            $response = $request->post(
                (string) $gatewayUrl,
                [
                    'textMessage' => [
                        'text' => $message,
                    ],
                    'phoneNumbers' => [
                        $phoneNumber,
                    ],
                ]
            );

            $responseData = $response->json();

            if ($responseData === null) {
                $responseData = $response->body();
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $responseData,
                'error' => $response->successful()
                    ? null
                    : $this->buildErrorMessage(
                        $response->status(),
                        $responseData
                    ),
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'status' => null,
                'response' => null,
                'error' =>
                    'Could not connect to the SMS gateway: '
                    . $exception->getMessage(),
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

    private function buildErrorMessage(
        int $status,
        mixed $response
    ): string {
        if (is_array($response)) {
            $message = $response['message']
                ?? $response['error']
                ?? $response['detail']
                ?? null;

            if (is_string($message) && $message !== '') {
                return "Gateway HTTP {$status}: {$message}";
            }
        }

        return "SMS gateway returned HTTP {$status}.";
    }
}