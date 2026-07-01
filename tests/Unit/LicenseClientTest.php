<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Services\LicenseClient;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseClientTest extends TestCase
{
    public function test_activate_sends_post_with_expected_payload(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'payload' => 'p', 'signature' => 's', 'status' => 'active'])]);

        (new LicenseClient())->activate('example.com', false);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license.example-vendor.test/api/license/activate'
                && $request['license_key'] === 'TEST-LICENSE-KEY'
                && $request['domain'] === 'example.com'
                && $request['is_local'] === false;
        });
    }

    public function test_verify_sends_post_with_expected_payload(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'payload' => 'p', 'signature' => 's', 'status' => 'active'])]);

        (new LicenseClient())->verify('example.com', false);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license.example-vendor.test/api/license/verify';
        });
    }

    public function test_successful_response_is_parsed_into_response_object(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'payload' => 'the-payload',
            'signature' => 'the-signature',
            'status' => 'active',
        ])]);

        $result = (new LicenseClient())->activate('example.com', false);

        $this->assertTrue($result->success);
        $this->assertSame('the-payload', $result->token);
        $this->assertSame('the-signature', $result->signature);
        $this->assertSame('active', $result->status);
    }

    public function test_non_2xx_response_is_handled_without_throwing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'suspended'], 403)]);

        $result = (new LicenseClient())->verify('example.com', false);

        $this->assertFalse($result->success);
    }

    public function test_connection_exception_is_handled_without_throwing(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $result = (new LicenseClient())->verify('example.com', false);

        $this->assertFalse($result->success);
    }

    public function test_local_domain_sends_is_local_true(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        (new LicenseClient())->activate('myapp.test', true);

        Http::assertSent(fn ($request) => $request['is_local'] === true);
    }

    public function test_production_domain_sends_is_local_false(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        (new LicenseClient())->activate('example.com', false);

        Http::assertSent(fn ($request) => $request['is_local'] === false);
    }
}
