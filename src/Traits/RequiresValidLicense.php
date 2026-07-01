<?php

namespace Vendor\LicenseGuard\Traits;

use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Exceptions\LicenseInvalidException;

trait RequiresValidLicense
{
    /**
     * Call at the top of a protected method/constructor. Throws
     * LicenseInvalidException if the license is invalid. Resolves the
     * checker from the container (rather than constructor injection, since
     * traits cannot safely compose constructors into arbitrary host classes)
     * -- this remains DI-through-the-interface, never a `new` call.
     */
    protected function assertLicenseValid(): void
    {
        /** @var LicenseCheckerInterface $checker */
        $checker = app(LicenseCheckerInterface::class);

        // Documented performance shortcut only -- $checker->check() would
        // also correctly return true when bypassed. Do not treat this as
        // the canonical bypass check; that lives solely in LicenseChecker.
        if ($checker->isBypassed()) {
            return;
        }

        if (! $checker->check()) {
            throw new LicenseInvalidException(static::class.' requires a valid license.');
        }
    }
}
