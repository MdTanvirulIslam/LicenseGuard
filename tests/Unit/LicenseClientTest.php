<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Vendor\LicenseGuard\Services\LicenseClient;
use Vendor\LicenseGuard\Tests\TestCase;

class LicenseClientTest extends TestCase
{
    public function test_activate_sends_post_with_expected_payload_and_bearer_auth(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        (new LicenseClient())->activate('example.com', false);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license.example-vendor.test/api/license/activate'
                && $request->hasHeader('Authorization', 'Bearer test-shared-secret')
                && $request['license_key'] === 'TEST-LICENSE-KEY'
                && $request['domain'] === 'example.com'
                && $request['is_local'] === false;
        });
    }

    public function test_verify_sends_post_with_expected_payload_and_current_token(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        (new LicenseClient())->verify('example.com', 'prior-payload.prior-signature');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license.example-vendor.test/api/license/verify'
                && $request->hasHeader('Authorization', 'Bearer test-shared-secret')
                && $request['license_key'] === 'TEST-LICENSE-KEY'
                && $request['domain'] === 'example.com'
                && $request['token'] === 'prior-payload.prior-signature';
        });
    }

    public function test_verify_omits_token_field_when_none_cached_yet(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        (new LicenseClient())->verify('example.com', null);

        Http::assertSent(fn ($request) => ! array_key_exists('token', $request->data()));
    }

    public function test_successful_response_is_parsed_into_response_object(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        $result = (new LicenseClient())->activate('example.com', false);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('.', $result->token);
        $this->assertSame(false, $result->isLocal);
        $this->assertNotNull($result->expiresAt);
    }

    public function test_failure_response_is_parsed_without_throwing(): void
    {
        Http::fake(['*' => Http::response($this->fakeFailureResponse('License is suspended.'), 402)]);

        $result = (new LicenseClient())->verify('example.com', 'p.s');

        $this->assertFalse($result->success);
        $this->assertNull($result->token);
        $this->assertSame('License is suspended.', $result->message);
    }

    public function test_connection_exception_is_handled_without_throwing(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $result = (new LicenseClient())->verify('example.com', null);

        $this->assertFalse($result->success);
    }

    public function test_local_domain_sends_is_local_true_on_activate(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('myapp.test', true))]);

        (new LicenseClient())->activate('myapp.test', true);

        Http::assertSent(fn ($request) => $request['is_local'] === true);
    }

    public function test_production_domain_sends_is_local_false_on_activate(): void
    {
        Http::fake(['*' => Http::response($this->fakeServerPayload('example.com', false))]);

        (new LicenseClient())->activate('example.com', false);

        Http::assertSent(fn ($request) => $request['is_local'] === false);
    }
}
