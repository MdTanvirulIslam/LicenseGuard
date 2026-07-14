<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Services\LicenseSetupService;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseSetupServiceTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = tempnam(sys_get_temp_dir(), 'license-setup-service-test-');
        file_put_contents($this->envPath, "APP_NAME=Test\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_successful_verification_writes_env_and_reports_added_keys(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $result = $this->app->make(LicenseSetupService::class)->apply(
            'https://license.example-vendor.test',
            'NEW-LICENSE-KEY',
            'test-shared-secret',
            $this->envPath,
            false,
        );

        $this->assertTrue($result->success);
        $this->assertSame('example.com', $result->domain);
        $this->assertSame('yes', $result->verified);
        $this->assertSame(['LICENSE_SERVER_URL', 'LICENSE_KEY', 'LICENSE_SECRET'], $result->added);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', file_get_contents($this->envPath));
    }

    public function test_failed_verification_does_not_write_env(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeFailureResponse('License key not found.'), 404)]);

        $result = $this->app->make(LicenseSetupService::class)->apply(
            'https://license.example-vendor.test',
            'BOGUS-KEY',
            'wrong-secret',
            $this->envPath,
            false,
        );

        $this->assertFalse($result->success);
        $this->assertSame('License key not found.', $result->message);
        $this->assertSame("APP_NAME=Test\n", file_get_contents($this->envPath));
    }

    public function test_skip_verify_writes_without_any_http_call(): void
    {
        Http::fake();

        $result = $this->app->make(LicenseSetupService::class)->apply(
            'https://license.example-vendor.test',
            'NEW-LICENSE-KEY',
            'any-secret',
            $this->envPath,
            true,
        );

        Http::assertNothingSent();
        $this->assertTrue($result->success);
        $this->assertSame('skipped', $result->verified);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', file_get_contents($this->envPath));
    }
}
