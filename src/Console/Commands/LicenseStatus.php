<?php

namespace Vendor\LicenseGuard\Console\Commands;

use Illuminate\Console\Command;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;

class LicenseStatus extends Command
{
    protected $signature = 'license:status {--fresh : Force a live server recheck instead of using cached state}';

    protected $description = 'Display the current license status for this installation.';

    public function handle(LicenseCheckerInterface $checker): int
    {
        // Run the check first so lastCheckedAt() below reflects the outcome
        // of this invocation rather than stale pre-check state.
        $valid = $this->option('fresh') ? $checker->forceVerify() : $checker->check();

        $domain = $checker->currentDomain();
        $isLocal = $checker->isLocalDomain();
        $bypassed = $checker->isBypassed();
        $lastChecked = $checker->lastCheckedAt();

        $this->table(['Field', 'Value'], [
            ['Domain', $domain],
            ['Is Local', $isLocal ? 'yes' : 'no'],
            ['Bypass Local Active', $bypassed ? 'yes' : 'no'],
            ['Last Checked At', $lastChecked?->toDateTimeString() ?? 'never'],
            ['License Valid', $valid ? 'valid' : 'invalid'],
        ]);

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
