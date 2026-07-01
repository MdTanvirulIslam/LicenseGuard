<?php

namespace Vendor\LicenseGuard\Contracts;

use Illuminate\Support\Carbon;

interface LicenseCheckerInterface
{
    /**
     * Perform a license check, preferring cached state within the grace period.
     * Falls through to a live server call when the cache is missing, invalid,
     * or the grace period has elapsed. Fail-closed on any error.
     */
    public function check(): bool;

    /**
     * Force a live server round-trip, bypassing the grace-period throttle.
     * Still short-circuits to true when LICENSE_BYPASS_LOCAL is active.
     */
    public function forceVerify(): bool;

    /** Current resolved, normalized domain being evaluated. */
    public function currentDomain(): string;

    /** Whether the current domain is considered local per config('license-guard.local_domains'). */
    public function isLocalDomain(): bool;

    /** Whether LICENSE_BYPASS_LOCAL is active. */
    public function isBypassed(): bool;

    /** Timestamp of the last successful check, or null if never checked. */
    public function lastCheckedAt(): ?Carbon;
}
