<?php

namespace Vendor\LicenseGuard\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseClient
{
    /** POST {server_url}/api/license/activate */
    public function activate(string $domain, bool $isLocal): LicenseServerResponse
    {
        return $this->post('/api/license/activate', $domain, $isLocal);
    }

    /** POST {server_url}/api/license/verify */
    public function verify(string $domain, bool $isLocal): LicenseServerResponse
    {
        return $this->post('/api/license/verify', $domain, $isLocal);
    }

    private function post(string $path, string $domain, bool $isLocal): LicenseServerResponse
    {
        $baseUrl = rtrim((string) config('license-guard.server_url'), '/');

        try {
            $response = Http::timeout((int) config('license-guard.http.timeout', 5))
                ->connectTimeout((int) config('license-guard.http.connect_timeout', 3))
                ->retry((int) config('license-guard.http.retries', 1), 0)
                ->post($baseUrl.$path, [
                    'license_key' => config('license-guard.license_key'),
                    'domain' => $domain,
                    'is_local' => $isLocal,
                ]);
        } catch (Throwable $e) {
            return LicenseServerResponse::failure($e->getMessage());
        }

        if (! $response->successful()) {
            return LicenseServerResponse::failure('License server returned HTTP '.$response->status());
        }

        $body = $response->json();

        if (! is_array($body) || ! ($body['success'] ?? false)) {
            return LicenseServerResponse::failure((string) ($body['message'] ?? 'License server rejected the request.'));
        }

        return new LicenseServerResponse(
            success: true,
            token: $body['payload'] ?? null,
            signature: $body['signature'] ?? null,
            status: $body['status'] ?? null,
        );
    }
}
