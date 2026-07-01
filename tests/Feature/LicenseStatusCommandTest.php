<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseStatusCommandTest extends TestCase
{
    public function test_shows_is_local_yes_for_local_domain(): void
    {
        $this->app->instance(Request::class, Request::create('http://myapp.test/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('myapp.test', true))]);

        $this->artisan('license:status')
            ->expectsOutputToContain('myapp.test')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_shows_bypass_active_with_no_http_calls(): void
    {
        config()->set('license-guard.bypass_local', true);
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake();

        $this->artisan('license:status')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_shows_is_local_no_for_production_domain(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $this->artisan('license:status')
            ->expectsOutputToContain('example.com')
            ->assertExitCode(0);
    }

    public function test_exit_code_is_failure_when_invalid(): void
    {
        $this->app->instance(Request::class, Request::create('http://example.com/'));
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->artisan('license:status')->assertExitCode(1);
    }
}
