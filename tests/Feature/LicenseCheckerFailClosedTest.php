<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Exceptions\LicenseInvalidException;
use Vendor\LicenseGuard\Models\LicenseCache;
use Vendor\LicenseGuard\Services\TokenValidator;
use Vendor\LicenseGuard\Support\BootLicenseGuard;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseCheckerFailClosedTest extends TestCase
{
    private function seedValidCacheRow(string $domain, \DateTimeInterface $lastCheckedAt): void
    {
        $split = TokenValidator::split($this->fakeServerPayload($domain, false)['token']);

        LicenseCache::create([
            'domain' => $domain,
            'token' => $split['payload'],
            'signature' => $split['signature'],
            'is_local' => false,
            'last_checked_at' => $lastCheckedAt,
        ]);
    }

    public function test_no_cache_and_server_failure_returns_false(): void
    {
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response($this->fakeFailureResponse(), 500)]);

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
    }

    public function test_expired_grace_period_and_unreachable_server_returns_false(): void
    {
        $this->bindRequestHost('example.com');
        $this->seedValidCacheRow('example.com', now()->subHours(48));

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
    }

    public function test_valid_cache_within_grace_period_returns_true_without_any_http_call(): void
    {
        $this->bindRequestHost('example.com');
        $this->seedValidCacheRow('example.com', now()->subHour());

        Http::fake(['*' => Http::response($this->fakeFailureResponse(), 500)]);

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());
        Http::assertNothingSent();
    }

    public function test_cached_token_issued_for_a_different_domain_is_rejected(): void
    {
        $this->bindRequestHost('example.com');
        $split = TokenValidator::split($this->fakeServerPayload('other-domain.com', false)['token']);

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => $split['payload'],
            'signature' => $split['signature'],
            'is_local' => false,
            'last_checked_at' => now()->subMinute(),
        ]);

        Http::fake(['*' => Http::response($this->fakeFailureResponse(), 500)]);

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
    }

    public function test_server_denial_leaves_prior_cached_state_untouched_and_returns_false(): void
    {
        $this->bindRequestHost('example.com');

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => 'irrelevant',
            'signature' => 'irrelevant',
            'is_local' => false,
            'last_checked_at' => now()->subHours(48),
        ]);

        Http::fake(['*' => Http::response($this->fakeFailureResponse('License is suspended.'), 402)]);

        $this->assertFalse($this->app->make(LicenseCheckerInterface::class)->check());
        $this->assertSame('irrelevant', LicenseCache::query()->where('domain', 'example.com')->first()->token);
    }

    public function test_tampered_cache_row_self_heals_via_force_verify_when_server_responds_validly(): void
    {
        $this->bindRequestHost('example.com');

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => 'tampered-token',
            'signature' => 'tampered-signature',
            'is_local' => false,
            'last_checked_at' => now()->subMinutes(5),
        ]);

        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());
        Http::assertSentCount(1);
    }

    public function test_expired_cached_token_falls_back_from_verify_to_activate_and_self_heals(): void
    {
        $this->bindRequestHost('example.com');

        // A token that was valid when cached but has since expired -- the exact
        // production state that dead-ended every request before the fallback.
        $expired = TokenValidator::split(
            $this->fakeServerPayload('example.com', false, ['exp' => now()->subHour()->timestamp])['token']
        );

        LicenseCache::create([
            'domain' => 'example.com',
            'token' => $expired['payload'],
            'signature' => $expired['signature'],
            'is_local' => false,
            'last_checked_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            '*/api/license/verify' => Http::response($this->fakeFailureResponse('Token is invalid or has been tampered with.'), 401),
            '*/api/license/activate' => Http::response($this->fakeServerPayload('example.com', false)),
        ]);

        $this->assertTrue($this->app->make(LicenseCheckerInterface::class)->check());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/license/verify'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/license/activate'));
    }

    public function test_boot_license_guard_throws_when_invalid_and_not_bypassed(): void
    {
        $this->bindRequestHost('example.com');
        Http::fake(['*' => Http::response($this->fakeFailureResponse(), 500)]);

        $this->expectException(LicenseInvalidException::class);

        $this->app->make(BootLicenseGuard::class)->handle();
    }
}
