<?php

namespace Vendor\LicenseGuard\Services;

use Illuminate\Support\Carbon;

class TokenValidator
{
    public function __construct(private string $secret)
    {
    }

    /**
     * Verifies a base64-encoded payload against its base64-encoded HMAC-SHA256
     * signature, then enforces the payload's exp claim. The license server's
     * token carries only license_id, domain, and exp -- there is no embedded
     * status; suspension/expiry are conveyed by the outer API response instead.
     */
    public function isValid(string $payload, string $signature): bool
    {
        if (! $this->verifySignature($payload, $signature)) {
            return false;
        }

        $decoded = $this->decode($payload);

        if (! isset($decoded['exp'])) {
            return false;
        }

        return ! Carbon::createFromTimestamp((int) $decoded['exp'])->isPast();
    }

    /**
     * Decodes the base64 payload into its claims array. Does not verify the
     * signature; callers that need trust must call isValid() first.
     */
    public function decode(string $payload): array
    {
        $json = base64_decode($payload, true);

        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Computes the HMAC-SHA256 signature (base64) over the base64 payload string. */
    public function sign(string $payload): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    /**
     * Verifies only the HMAC integrity of a payload/signature pair, independent
     * of the payload's status/expiry claims. Used when the caller needs to
     * trust a payload's authenticity (e.g. to persist a "suspended" status)
     * even though isValid() would reject it on business-rule grounds.
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    /**
     * Splits the license server's combined "payload.signature" token string.
     * Returns null if the token doesn't have exactly two non-empty parts.
     */
    public static function split(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return ['payload' => $parts[0], 'signature' => $parts[1]];
    }
}
