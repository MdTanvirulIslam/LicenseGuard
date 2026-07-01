<?php

namespace Vendor\LicenseGuard\Tests;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vendor\LicenseGuard\LicenseGuardServiceProvider;
use Vendor\LicenseGuard\Services\TokenValidator;

abstract class TestCase extends OrchestraTestCase
{
    protected function bindRequestHost(string $host): void
    {
        $this->app->instance(Request::class, Request::create('http://'.$host.'/'));
    }

    /** Builds a fake success response matching the real license server's exact contract. */
    protected function fakeServerPayload(string $domain, bool $isLocal, array $claimOverrides = []): array
    {
        $validator = new TokenValidator('test-shared-secret');

        $claims = array_merge([
            'license_id' => 1,
            'domain' => $domain,
            'exp' => now()->addHours(24)->timestamp,
        ], $claimOverrides);

        $payloadB64 = base64_encode(json_encode($claims));

        return [
            'valid' => true,
            'token' => $payloadB64.'.'.$validator->sign($payloadB64),
            'message' => 'License verified.',
            'expires_at' => now()->addHours(24)->toIso8601String(),
            'is_local' => $isLocal,
        ];
    }

    protected function fakeFailureResponse(string $message = 'License is suspended.'): array
    {
        return [
            'valid' => false,
            'token' => null,
            'message' => $message,
            'expires_at' => null,
            'is_local' => false,
        ];
    }
    protected function getPackageProviders($app): array
    {
        return [
            LicenseGuardServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('license-guard.server_url', 'https://license.example-vendor.test');
        $app['config']->set('license-guard.license_key', 'TEST-LICENSE-KEY');
        $app['config']->set('license-guard.secret', 'test-shared-secret');
        $app['config']->set('license-guard.check_interval_hours', 6);
        $app['config']->set('license-guard.grace_period_hours', 24);
        $app['config']->set('license-guard.bypass_local', false);
        $app['config']->set('license-guard.local_domains', [
            'localhost', '127.0.0.1', '::1', '.test', '.local', '.dev',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
