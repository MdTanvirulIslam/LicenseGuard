<?php

namespace Vendor\LicenseGuard\Services;

class LicenseSetupResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $domain,
        public readonly string $verified,
        public readonly ?string $message,
        public readonly array $added,
        public readonly array $updated,
    ) {
    }

    public static function success(string $domain, string $verified, array $added, array $updated): self
    {
        return new self(true, $domain, $verified, null, $added, $updated);
    }

    public static function failure(string $domain, string $message): self
    {
        return new self(false, $domain, 'no', $message, [], []);
    }
}
