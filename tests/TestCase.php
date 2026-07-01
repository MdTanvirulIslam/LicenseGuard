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

    protected function fakeServerPayload(string $domain, bool $isLocal, array $claimOverrides = []): array
    {
        $validator = new TokenValidator('test-shared-secret');

        $claims = array_merge([
            'license_key' => 'TEST-LICENSE-KEY',
            'domain' => $domain,
            'is_local' => $isLocal,
            'status' => 'active',
            'issued_at' => now()->subMinute()->timestamp,
            'expires_at' => now()->addDays(30)->timestamp,
        ], $claimOverrides);

        $payload = base64_encode(json_encode($claims));

        return [
            'success' => true,
            'payload' => $payload,
            'signature' => $validator->sign($payload),
            'status' => $claims['status'],
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
