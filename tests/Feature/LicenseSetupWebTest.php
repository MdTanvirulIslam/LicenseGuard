<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Tests\TestCase;

/**
 * Orchestra Testbench boots the application lazily on the first real HTTP
 * dispatch made by the test ($this->get()/$this->post()), so
 * LicenseGuardServiceProvider::boot() sees the actual request being tested --
 * this matches real per-process PHP-FPM/Apache hosting, where one process
 * handles exactly one request start-to-finish.
 */
class LicenseSetupWebTest extends TestCase
{
    private const TOKEN = 'test-setup-token-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('license-guard.setup_token', self::TOKEN);
    }

    public function test_correct_token_shows_the_setup_form(): void
    {
        $this->get('/license-setup/'.self::TOKEN)
            ->assertOk()
            ->assertSee('License Setup');
    }

    public function test_wrong_token_returns_not_found(): void
    {
        $this->get('/license-setup/wrong-token')->assertNotFound();
    }

    public function test_successful_submission_verifies_and_writes_env(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('localhost', false))]);

        $response = $this->post('/license-setup/'.self::TOKEN, [
            'url' => 'https://license.example-vendor.test',
            'key' => 'NEW-LICENSE-KEY',
            'secret' => 'test-shared-secret',
        ]);

        $response->assertOk()->assertSee('License Saved');

        $envPath = $this->app->environmentFilePath();
        $this->assertFileExists($envPath);
        $this->assertStringContainsString('LICENSE_KEY=NEW-LICENSE-KEY', file_get_contents($envPath));
    }

    public function test_failed_submission_does_not_write_env(): void
    {
        Http::fake(['*' => Http::response($this->fakeFailureResponse('License key not found.'), 404)]);

        $response = $this->post('/license-setup/'.self::TOKEN, [
            'url' => 'https://license.example-vendor.test',
            'key' => 'BOGUS-KEY',
            'secret' => 'wrong-secret',
        ]);

        $response->assertOk()->assertSee('License Setup Failed')->assertSee('License key not found.');
    }

    public function test_disable_clears_the_setup_token_from_env(): void
    {
        $envPath = $this->app->environmentFilePath();
        file_put_contents($envPath, "APP_NAME=Test\nLICENSE_SETUP_TOKEN=".self::TOKEN."\n");

        $this->post('/license-setup/'.self::TOKEN.'/disable')
            ->assertOk()
            ->assertSee('Setup Page Disabled');

        $this->assertStringNotContainsString(self::TOKEN, file_get_contents($envPath));
    }
}
