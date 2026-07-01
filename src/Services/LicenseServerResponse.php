<?php

namespace Vendor\LicenseGuard\Services;

class LicenseServerResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $token = null,
        public readonly ?string $signature = null,
        public readonly ?string $status = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function failure(string $message): self
    {
        return new self(success: false, message: $message);
    }
}
