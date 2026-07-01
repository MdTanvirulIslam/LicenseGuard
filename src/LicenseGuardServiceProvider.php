<?php

namespace Vendor\LicenseGuard;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Vendor\LicenseGuard\Console\Commands\LicenseSetup;
use Vendor\LicenseGuard\Console\Commands\LicenseStatus;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Http\Middleware\HttpLicenseGuard;
use Vendor\LicenseGuard\Services\DomainResolver;
use Vendor\LicenseGuard\Services\LicenseChecker;
use Vendor\LicenseGuard\Services\LicenseClient;
use Vendor\LicenseGuard\Services\TokenValidator;
use Vendor\LicenseGuard\Support\BootLicenseGuard;

class LicenseGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/license-guard.php', 'license-guard');

        $this->app->singleton(TokenValidator::class, fn ($app) => new TokenValidator(
            (string) config('license-guard.secret')
        ));
        $this->app->singleton(DomainResolver::class);
        $this->app->singleton(LicenseClient::class);
        $this->app->singleton(LicenseCheckerInterface::class, LicenseChecker::class);
        $this->app->singleton(BootLicenseGuard::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/license-guard.php' => config_path('license-guard.php'),
        ], 'license-guard-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([LicenseStatus::class, LicenseSetup::class]);

            // Never gate console commands (migrate, queue workers, etc.) --
            // avoids a chicken-and-egg failure before license_cache exists.
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(HttpLicenseGuard::class);
        }

        $this->app->make(BootLicenseGuard::class)->handle();
    }
}
