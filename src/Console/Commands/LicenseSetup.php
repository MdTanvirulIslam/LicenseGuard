<?php

namespace Vendor\LicenseGuard\Console\Commands;

use Illuminate\Console\Command;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Services\LicenseClient;
use Vendor\LicenseGuard\Support\EnvFileWriter;

class LicenseSetup extends Command
{
    protected $signature = 'license:setup
        {--url= : The license server base URL}
        {--key= : The license key issued for this customer}
        {--secret= : The product secret key}
        {--skip-verify : Save the values without making a live verification call}
        {--path= : Path to the .env file to update (defaults to the app\'s own .env)}';

    protected $description = 'Write LICENSE_SERVER_URL, LICENSE_KEY, and LICENSE_SECRET into .env, verifying them against the license server first.';

    public function handle(EnvFileWriter $writer): int
    {
        $url = $this->option('url') ?: $this->ask('License server URL');
        $key = $this->option('key') ?: $this->ask('License key');
        $secret = $this->option('secret') ?: $this->secret('Product secret key');

        foreach (['License server URL' => $url, 'License key' => $key, 'Product secret key' => $secret] as $label => $value) {
            if (! is_string($value) || $value === '') {
                $this->error("{$label} is required.");

                return self::FAILURE;
            }
        }

        config([
            'license-guard.server_url' => $url,
            'license-guard.license_key' => $key,
            'license-guard.secret' => $secret,
        ]);

        $checker = $this->laravel->make(LicenseCheckerInterface::class);
        $domain = $checker->currentDomain();
        $verified = 'skipped';

        if (! $this->option('skip-verify')) {
            if (! $checker->forceVerify()) {
                $response = $this->laravel->make(LicenseClient::class)->activate($domain, $checker->isLocalDomain());
                $this->error("License verification failed: {$response->message}");
                $this->line('Nothing was written to .env. Pass --skip-verify to save anyway.');

                return self::FAILURE;
            }

            $verified = 'yes';
        }

        $path = $this->option('path') ?: $this->laravel->environmentFilePath();

        $result = $writer->write($path, [
            'LICENSE_SERVER_URL' => $url,
            'LICENSE_KEY' => $key,
            'LICENSE_SECRET' => $secret,
        ]);

        $this->table(['Field', 'Value'], [
            ['Domain', $domain],
            ['Verified', $verified],
            ['Added', $result['added'] ? implode(', ', $result['added']) : 'none'],
            ['Updated', $result['updated'] ? implode(', ', $result['updated']) : 'none'],
        ]);

        if ($this->laravel->configurationIsCached()) {
            $this->warn('Configuration is cached -- run "php artisan config:clear" (and re-cache if you use config:cache in production) for these values to take effect.');
        }

        return self::SUCCESS;
    }
}
