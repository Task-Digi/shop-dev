<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SecureIvsService
{
    private string $clientId;

    private string $apiKey;

    private string $authUrl;

    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = trim((string) config('services.secureivs.client_id'));
        $this->apiKey = trim((string) config('services.secureivs.api_key'));
        $this->authUrl = rtrim((string) config('services.secureivs.auth_url'), '/');
        $this->baseUrl = rtrim((string) config('services.secureivs.base_url'), '/');
    }

    public function getRedirectUrl(string $callbackUrl, string $state): string
    {
        $this->ensureConfigured();
        $redirectUri = (string) (config('services.secureivs.redirect_uri') ?: $callbackUrl);
        $returnCode = base64_encode((string) json_encode(['state' => $state], JSON_THROW_ON_ERROR));

        return $this->authUrl.'/?'.http_build_query([
            'client_id' => $this->clientId,
            'login_method' => 'email',
            'redirect_uri' => $redirectUri,
            'return_code' => $returnCode,
        ]);
    }

    /** @return array{valid: bool, user: ?array, request_id: ?string, message?: string} */
    public function validateToken(string $token): array
    {
        $this->ensureConfigured();
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asJson()->connectTimeout(5)->timeout(15)->post($this->v2Path('/auth/token/validate'), ['token' => $token]);

        $payload = $response->json() ?? [];
        $requestId = isset($payload['requestId']) ? (string) $payload['requestId'] : null;
        Log::info('Secure IVS token validation', [
            'http_status' => $response->status(),
            'code' => $payload['code'] ?? null,
            'request_id' => $requestId,
        ]);

        if (! $response->successful() || ($payload['success'] ?? false) !== true) {
            return [
                'valid' => false,
                'user' => null,
                'request_id' => $requestId,
                'message' => (string) ($payload['message'] ?? 'Secure IVS verification failed.'),
            ];
        }

        $user = $payload['data']['user'] ?? null;

        return [
            'valid' => is_array($user),
            'user' => is_array($user) ? $user : null,
            'request_id' => $requestId,
        ];
    }

    public function logout(string $token): void
    {
        if ($token === '') {
            return;
        }

        $this->ensureConfigured();
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->asJson()->connectTimeout(5)->timeout(15)->post($this->v2Path('/auth/token/logout'), ['token' => $token]);

        if (! $response->successful() || ($response->json('success') ?? false) !== true) {
            throw new RuntimeException('Secure IVS logout failed.');
        }
    }

    /** @return array<string, mixed> */
    public function unpackReturnCode(?string $returnCode): array
    {
        if (! $returnCode) {
            return [];
        }

        $decoded = base64_decode($returnCode, true);
        $context = $decoded === false ? null : json_decode($decoded, true);

        return is_array($context) ? $context : [];
    }

    private function v2Path(string $path): string
    {
        $base = str_ends_with($this->baseUrl, '/api/v2') ? $this->baseUrl : $this->baseUrl.'/api/v2';

        return $base.$path;
    }

    private function ensureConfigured(): void
    {
        if ($this->clientId === '' || $this->apiKey === '' || $this->authUrl === '' || $this->baseUrl === '') {
            throw new RuntimeException('Secure IVS is not configured.');
        }
    }
}
