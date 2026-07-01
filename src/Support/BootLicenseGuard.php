<?php

namespace Vendor\LicenseGuard\Support;

use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Exceptions\LicenseInvalidException;

class BootLicenseGuard
{
    public function __construct(private LicenseCheckerInterface $checker)
    {
    }

    /** Invoked once per request from the service provider's boot(). Fail-closed unless bypassed. */
    public function handle(): void
    {
        if ($this->checker->isBypassed()) {
            return;
        }

        if (! $this->checker->check()) {
            throw new LicenseInvalidException('License check failed during application boot.');
        }
    }
}
