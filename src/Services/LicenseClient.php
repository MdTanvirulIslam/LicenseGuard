<?php

namespace Vendor\LicenseGuard\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseClient
{
    /** POST {server_url}/api/license/activate, authenticated as the product via LICENSE_SECRET. */
    public function activate(string $domain, bool $isLocal): LicenseServerResponse
    {
        return $this->post('/api/license/activate', [
            'license_key' => config('license-guard.license_key'),
            'domain' => $domain,
            'is_local' => $isLocal,
        ]);
    }

    /**
     * POST {server_url}/api/license/verify. $currentToken (the previously
     * issued "payload.signature" string) is echoed back when available, per
     * the server's contract; it is optional so a first-ever verify still works.
     */
    public function verify(string $domain, ?string $currentToken): LicenseServerResponse
    {
        $payload = [
            'license_key' => config('license-guard.license_key'),
            'domain' => $domain,
        ];

        if ($currentToken !== null) {
            $payload['token'] = $currentToken;
        }

        return $this->post('/api/license/verify', $payload);
    }

    private function post(string $path, array $payload): LicenseServerResponse
    {
        $baseUrl = rtrim((string) config('license-guard.server_url'), '/');

        try {
            $response = Http::withToken((string) config('license-guard.secret'))
                ->timeout((int) config('license-guard.http.timeout', 5))
                ->connectTimeout((int) config('license-guard.http.connect_timeout', 3))
                ->retry((int) config('license-guard.http.retries', 1), 0)
                ->post($baseUrl.$path, $payload);
        } catch (Throwable $e) {
            return LicenseServerResponse::failure($e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body) || ! ($body['valid'] ?? false)) {
            return LicenseServerResponse::failure((string) ($body['message'] ?? 'License server rejected the request.'));
        }

        return new LicenseServerResponse(
            success: true,
            token: $body['token'] ?? null,
            expiresAt: $body['expires_at'] ?? null,
            isLocal: $body['is_local'] ?? null,
            message: $body['message'] ?? null,
        );
    }
}
