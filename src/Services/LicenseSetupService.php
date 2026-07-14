<?php

namespace Vendor\LicenseGuard\Services;

use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Support\EnvFileWriter;

/**
 * Shared by both the license:setup console command and the web setup page,
 * so the verify-then-write logic exists in exactly one place.
 */
class LicenseSetupService
{
    public function __construct(private EnvFileWriter $writer)
    {
    }

    public function apply(string $url, string $key, string $secret, string $envPath, bool $skipVerify): LicenseSetupResult
    {
        config([
            'license-guard.server_url' => $url,
            'license-guard.license_key' => $key,
            'license-guard.secret' => $secret,
        ]);

        $checker = app(LicenseCheckerInterface::class);
        $domain = $checker->currentDomain();

        if (! $skipVerify) {
            if (! $checker->forceVerify()) {
                $response = app(LicenseClient::class)->activate($domain, $checker->isLocalDomain());

                return LicenseSetupResult::failure($domain, $response->message ?? 'License server rejected the request.');
            }
        }

        $written = $this->writer->write($envPath, [
            'LICENSE_SERVER_URL' => $url,
            'LICENSE_KEY' => $key,
            'LICENSE_SECRET' => $secret,
        ]);

        return LicenseSetupResult::success($domain, $skipVerify ? 'skipped' : 'yes', $written['added'], $written['updated']);
    }
}
