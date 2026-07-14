<?php

namespace Vendor\LicenseGuard\Tests\Feature;

use Vendor\LicenseGuard\Tests\TestCase;

class LicenseSetupWebDisabledTest extends TestCase
{
    public function test_setup_routes_do_not_exist_when_no_token_is_configured(): void
    {
        $this->get('/license-setup/anything')->assertNotFound();
    }
}
