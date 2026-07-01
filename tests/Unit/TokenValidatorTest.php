<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Vendor\LicenseGuard\Services\TokenValidator;
use Vendor\LicenseGuard\Tests\TestCase;

class TokenValidatorTest extends TestCase
{
    private const SECRET = 'test-shared-secret';

    private function payloadFor(array $overrides = []): string
    {
        $claims = array_merge([
            'license_id' => 1,
            'domain' => 'example.com',
            'exp' => now()->addDays(30)->timestamp,
        ], $overrides);

        return base64_encode(json_encode($claims));
    }

    public function test_valid_signature_and_future_expiry_is_valid(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor();

        $this->assertTrue($validator->isValid($payload, $validator->sign($payload)));
    }

    public function test_tampered_payload_is_invalid(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor();
        $signature = $validator->sign($payload);

        $this->assertFalse($validator->isValid($payload.'x', $signature));
    }

    public function test_tampered_signature_is_invalid(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor();

        $this->assertFalse($validator->isValid($payload, 'not-the-real-signature=='));
    }

    public function test_wrong_secret_used_to_sign_is_invalid(): void
    {
        $signer = new TokenValidator('a-different-secret');
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor();

        $this->assertFalse($validator->isValid($payload, $signer->sign($payload)));
    }

    public function test_past_exp_is_invalid_even_with_valid_signature(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor(['exp' => now()->subDay()->timestamp]);

        $this->assertFalse($validator->isValid($payload, $validator->sign($payload)));
    }

    public function test_missing_exp_claim_is_invalid(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = base64_encode(json_encode(['license_id' => 1, 'domain' => 'example.com']));

        $this->assertFalse($validator->isValid($payload, $validator->sign($payload)));
    }

    public function test_malformed_payload_is_invalid_without_throwing(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $garbage = 'not-valid-base64-json!!!';

        $this->assertFalse($validator->isValid($garbage, $validator->sign($garbage)));
    }

    public function test_decode_returns_expected_claims_for_known_good_payload(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor(['license_id' => 42, 'domain' => 'myapp.test']);

        $decoded = $validator->decode($payload);

        $this->assertSame(42, $decoded['license_id']);
        $this->assertSame('myapp.test', $decoded['domain']);
    }

    public function test_split_parses_combined_payload_dot_signature_token(): void
    {
        $result = TokenValidator::split('cGF5bG9hZA==.c2lnbmF0dXJl');

        $this->assertSame(['payload' => 'cGF5bG9hZA==', 'signature' => 'c2lnbmF0dXJl'], $result);
    }

    public function test_split_returns_null_for_malformed_token(): void
    {
        $this->assertNull(TokenValidator::split('no-dot-here'));
        $this->assertNull(TokenValidator::split('.missing-payload'));
        $this->assertNull(TokenValidator::split('missing-signature.'));
    }
}
