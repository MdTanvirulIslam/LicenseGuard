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
            'license_key' => 'ABC-123',
            'domain' => 'example.com',
            'is_local' => false,
            'status' => 'active',
            'issued_at' => now()->subDay()->timestamp,
            'expires_at' => now()->addDays(30)->timestamp,
        ], $overrides);

        return base64_encode(json_encode($claims));
    }

    public function test_valid_signature_active_status_future_expiry_is_valid(): void
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

        $tampered = $payload.'x';

        $this->assertFalse($validator->isValid($tampered, $signature));
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

    public function test_suspended_status_is_invalid_even_with_valid_signature(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor(['status' => 'suspended']);

        $this->assertFalse($validator->isValid($payload, $validator->sign($payload)));
    }

    public function test_expired_status_is_invalid_even_with_valid_signature(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor(['status' => 'expired']);

        $this->assertFalse($validator->isValid($payload, $validator->sign($payload)));
    }

    public function test_past_expires_at_is_invalid_even_with_active_status(): void
    {
        $validator = new TokenValidator(self::SECRET);
        $payload = $this->payloadFor(['expires_at' => now()->subDay()->timestamp]);

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
        $payload = $this->payloadFor(['license_key' => 'XYZ-999']);

        $this->assertSame('XYZ-999', $validator->decode($payload)['license_key']);
    }
}
