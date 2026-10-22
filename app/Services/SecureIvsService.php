<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SecureIvsService
{
    protected string $clientId;
    protected string $apiKey;
    protected string $authUrl;
    protected string $apiUrl;

    public function __construct()
    {
        $this->clientId = (string) config('services.secureivs.client_id', '9b3tvZGZStRrcTsfTZifsjgkaUTER3aOKaXjshkO');
        $this->apiKey = (string) config('services.secureivs.api_key', 'GFUEGLzTzF6UHByZ1');
        $this->authUrl = rtrim((string) config('services.secureivs.auth_url', 'https://customer-api.secureivs.eu'), '/');
        $this->apiUrl = rtrim((string) config('services.secureivs.api_url', 'https://api.secureivs.eu/api/v2'), '/');
    }

    /**
     * Build the redirect URL for SecureIVS hosted authentication.
     */
    public function getRedirectUrl(string $callbackUrl, ?string $loginMethod = 'email'): string
    {
        $redirectUri = (string) (config('services.secureivs.redirect_uri') ?: $callbackUrl);

        $params = [
            'client_id' => $this->clientId,
            'login_method' => $loginMethod ?: 'email',
            'redirect_uri' => $redirectUri,
        ];

        return $this->authUrl . '/?' . http_build_query($params);
    }

    /**
     * Validate the verification token via SecureIVS API v2.
     *
     * @param string $token
     * @return array
     */
    public function validateToken(string $token): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->timeout(15)
        ->post($this->apiUrl . '/auth/token/validate', [
            'token' => $token,
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $message = $errorBody['message'] ?? 'Secure IVS API error (' . $response->status() . ')';

            return [
                'success' => false,
                'code' => $errorBody['code'] ?? 'API_ERROR',
                'message' => $message,
                'status' => $response->status(),
            ];
        }

        return $response->json();
    }
}
