<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseSetupCommandTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = tempnam(sys_get_temp_dir(), 'license-setup-test-');
        file_put_contents($this->envPath, "APP_NAME=Test\nAPP_ENV=local\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_successful_verification_writes_env_file(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->artisan('license:setup', [
            '--url' => 'https://license.example-vendor.test',
            '--key' => 'NEW-LICENSE-KEY',
            '--secret' => 'test-shared-secret',
            '--path' => $this->envPath,
        ])->assertExitCode(0);

        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('LICENSE_SERVER_URL=https://license.example-vendor.test', $contents);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', $contents);
        $this->assertStringContainsString('LICENSE_SECRET=test-shared-secret', $contents);
        $this->assertStringContainsString('APP_NAME=Test', $contents);
    }

    public function test_failed_verification_does_not_touch_env_file(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeFailureResponse('License key not found.'), 404)]);

        $this->artisan('license:setup', [
            '--url' => 'https://license.example-vendor.test',
            '--key' => 'BOGUS-KEY',
            '--secret' => 'wrong-secret',
            '--path' => $this->envPath,
        ])
            ->expectsOutputToContain('License key not found.')
            ->assertExitCode(1);

        $this->assertSame("APP_NAME=Test\nAPP_ENV=local\n", file_get_contents($this->envPath));
    }

    public function test_skip_verify_writes_env_without_any_http_call(): void
    {
        Http::fake();

        $this->artisan('license:setup', [
            '--url' => 'https://license.example-vendor.test',
            '--key' => 'NEW-LICENSE-KEY',
            '--secret' => 'new-shared-secret',
            '--skip-verify' => true,
            '--path' => $this->envPath,
        ])->assertExitCode(0);

        Http::assertNothingSent();

        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', $contents);
    }

    public function test_prompts_interactively_for_missing_values(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->artisan('license:setup', ['--path' => $this->envPath])
            ->expectsQuestion('License server URL', 'https://license.example-vendor.test')
            ->expectsQuestion('License key', 'NEW-LICENSE-KEY')
            ->expectsQuestion('Product secret key', 'test-shared-secret')
            ->assertExitCode(0);

        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', $contents);
    }

    public function test_replaces_existing_license_lines_in_place(): void
    {
        file_put_contents($this->envPath, implode("\n", [
            'APP_NAME=Test',
            'LICENSE_SERVER_URL=https://old.example.test',
            'LICENSE_KEY=OLD-KEY',
            'LICENSE_SECRET=old-secret',
        ]));

        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->artisan('license:setup', [
            '--url' => 'https://license.example-vendor.test',
            '--key' => 'NEW-LICENSE-KEY',
            '--secret' => 'test-shared-secret',
            '--path' => $this->envPath,
        ])->assertExitCode(0);

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES);

        $this->assertSame([
            'APP_NAME=Test',
            'LICENSE_SERVER_URL=https://license.example-vendor.test',
            'LICENSE_KEY=NEW-LICENSE-KEY',
            'LICENSE_SECRET=test-shared-secret',
        ], $lines);
    }
}
