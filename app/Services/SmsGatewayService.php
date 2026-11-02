<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsGatewayService
{
    /** @return array<string, mixed> */
    public function send(string $destination, string $message): array
    {
        $endpoint = trim((string) config('services.sms_gateway.endpoint'));
        $apiKey = trim((string) config('services.sms_gateway.api_key'));
        $sender = trim((string) config('services.sms_gateway.sender'));

        if ($endpoint === '' || $apiKey === '' || $sender === '') {
            throw new RuntimeException('The SMS gateway is not configured.');
        }

        if (! preg_match('/^[A-Za-z0-9]{1,11}$/', $sender)) {
            throw new RuntimeException('The configured SMS sender name is invalid.');
        }

        $response = Http::acceptJson()->connectTimeout(5)->timeout(20)->get($endpoint, [
            'key' => $apiKey,
            'avs' => $sender,
            'dest' => $destination,
            'msg' => $message,
        ]);

        $body = trim($response->body());

        if (! $response->successful()) {
            throw new RuntimeException('The SMS gateway could not process the request.');
        }

        if (strcasecmp(rtrim($body, '.'), 'Client not found') === 0) {
            throw new RuntimeException('The SMS gateway credentials were rejected.');
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new RuntimeException('The SMS gateway returned an invalid response.');
        }

        return $result;
    }
}
