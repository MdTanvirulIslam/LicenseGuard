<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Support\BootLicenseGuard;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseBypassLocalTest extends TestCase
{
    public function test_bypass_true_check_returns_true_with_zero_http_requests(): void
    {
        config()->set('license-guard.bypass_local', true);
        $this->bindRequestHost('example.com');
        Http::fake();

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());
        Http::assertNothingSent();
    }

    public function test_bypass_true_force_verify_also_returns_true_with_zero_http_requests(): void
    {
        config()->set('license-guard.bypass_local', true);
        $this->bindRequestHost('example.com');
        Http::fake();

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->forceVerify());
        Http::assertNothingSent();
    }

    public function test_bypass_false_local_domain_sends_http_call_with_is_local_true(): void
    {
        config()->set('license-guard.bypass_local', false);
        $this->bindRequestHost('myapp.test');
        Http::fake(['*' => Http::response($this->fakeServerPayload('myapp.test', true))]);

        $this->app->make(LicenseCheckerInterface::class)->check();

        Http::assertSent(fn ($request) => $request['is_local'] === true);
    }

    public function test_bypass_false_production_domain_sends_http_call_with_is_local_false(): void
    {
        config()->set('license-guard.bypass_local', false);
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->app->make(LicenseCheckerInterface::class)->check();

        Http::assertSent(fn ($request) => $request['is_local'] === false);
    }

    public function test_boot_license_guard_does_not_throw_when_bypassed_even_with_no_cache_and_failing_server(): void
    {
        config()->set('license-guard.bypass_local', true);
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->app->make(BootLicenseGuard::class)->handle();

        $this->addToAssertionCount(1);
    }
}
