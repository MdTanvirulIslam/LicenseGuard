<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Exceptions\LicenseInvalidException;
use Vendor\LicenseGuard\Models\LicenseCache;
use Vendor\LicenseGuard\Support\BootLicenseGuard;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseCheckerFailClosedTest extends TestCase
{
    public function test_no_cache_and_server_failure_returns_false(): void
    {
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
    }

    public function test_expired_grace_period_and_unreachable_server_returns_false(): void
    {
        $this->bindRequestHost('example.com');

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => 'stale-token',
            'signature' => 'stale-signature',
            'status' => 'active',
            'is_local' => false,
            'last_checked_at' => now()->subHours(48),
        ]);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
    }

    public function test_valid_cache_within_grace_period_returns_true_without_any_http_call(): void
    {
        $this->bindRequestHost('example.com');
        $payload = $this->fakeServerPayload('example.com', false);

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => $payload['payload'],
            'signature' => $payload['signature'],
            'status' => 'active',
            'is_local' => false,
            'last_checked_at' => now()->subHour(),
        ]);

        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());
        Http::assertNothingSent();
    }

    public function test_server_returns_suspended_updates_cache_status_and_returns_false(): void
    {
        $this->bindRequestHost('example.com');

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => 'irrelevant',
            'signature' => 'irrelevant',
            'status' => 'active',
            'is_local' => false,
            'last_checked_at' => now()->subHours(48),
        ]);

        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false, ['status' => 'suspended']))]);

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
        $this->assertSame('suspended', LicenseCache::query()->where('domain', 'example.com')->first()->status);
    }

    public function test_tampered_cache_row_self_heals_via_force_verify_when_server_responds_validly(): void
    {
        $this->bindRequestHost('example.com');

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => 'tampered-token',
            'signature' => 'tampered-signature',
            'status' => 'active',
            'is_local' => false,
            'last_checked_at' => now()->subMinutes(5),
        ]);

        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());
        Http::assertSentCount(1);
    }

    public function test_boot_license_guard_throws_when_invalid_and_not_bypassed(): void
    {
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->expectException(LicenseInvalidException::class);

        $this->app->make(BootLicenseGuard::class)->handle();
    }
}
