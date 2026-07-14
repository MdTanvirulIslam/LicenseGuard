<?php

namespace Vendor\LicenseGuard\Console\Commands;

use Illuminate\Console\Command;
use Vendor\LicenseGuard\Services\LicenseSetupService;

class LicenseSetup extends Command
{
    protected $signature = 'license:setup
        {--url= : The license server base URL}
        {--key= : The license key issued for this customer}
        {--secret= : The product secret key}
        {--skip-verify : Save the values without making a live verification call}
        {--path= : Path to the .env file to update (defaults to the app\'s own .env)}';

    protected $description = 'Write LICENSE_SERVER_URL, LICENSE_KEY, and LICENSE_SECRET into .env, verifying them against the license server first.';

    public function handle(LicenseSetupService $service): int
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

        $path = $this->option('path') ?: $this->laravel->environmentFilePath();

        $result = $service->apply($url, $key, $secret, $path, (bool) $this->option('skip-verify'));

        if (! $result->success) {
            $this->error("License verification failed: {$result->message}");
            $this->line('Nothing was written to .env. Pass --skip-verify to save anyway.');

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['Domain', $result->domain],
            ['Verified', $result->verified],
            ['Added', $result->added ? implode(', ', $result->added) : 'none'],
            ['Updated', $result->updated ? implode(', ', $result->updated) : 'none'],
        ]);

        if ($this->laravel->configurationIsCached()) {
            $this->warn('Configuration is cached -- run "php artisan config:clear" (and re-cache if you use config:cache in production) for these values to take effect.');
        }

        return self::SUCCESS;
    }
}
