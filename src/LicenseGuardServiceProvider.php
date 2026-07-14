<?php

namespace Vendor\LicenseGuard;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Vendor\LicenseGuard\Console\Commands\LicenseSetup;
use Vendor\LicenseGuard\Console\Commands\LicenseStatus;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Http\Controllers\LicenseSetupController;
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'license-guard');

        if ((string) config('license-guard.setup_token', '') !== '') {
            $this->registerSetupRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([LicenseStatus::class, LicenseSetup::class]);

            // Never gate console commands (migrate, queue workers, etc.) --
            // avoids a chicken-and-egg failure before license_cache exists.
            return;
        }

        // The setup page must stay reachable precisely when the license is
        // broken, and must never trigger the checker before its own
        // controller can override config() with freshly-submitted values --
        // so it's exempted before anything gets a chance to resolve
        // LicenseCheckerInterface (which would otherwise cache a singleton
        // built from the OLD config for the rest of this request).
        if ($this->app->make(Request::class)->is('license-setup*')) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(HttpLicenseGuard::class);
        }

        $this->app->make(BootLicenseGuard::class)->handle();
    }

    private function registerSetupRoutes(): void
    {
        Route::middleware(['web', 'throttle:10,1'])->group(function () {
            Route::get('/license-setup/{token}', [LicenseSetupController::class, 'show'])->name('license-guard.setup.show');
            Route::post('/license-setup/{token}', [LicenseSetupController::class, 'store'])->name('license-guard.setup.store');
            Route::post('/license-setup/{token}/disable', [LicenseSetupController::class, 'disable'])->name('license-guard.setup.disable');
        });
    }
}
