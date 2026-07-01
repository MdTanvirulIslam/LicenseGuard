<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Illuminate\Http\Request;
use Vendor\LicenseGuard\Services\DomainResolver;
use Vendor\LicenseGuard\Tests\TestCase;

class DomainResolverTest extends TestCase
{
    private function resolverForHost(string $host): DomainResolver
    {
        $request = Request::create('http://'.$host.'/some/path');

        return new DomainResolver($request);
    }

    public function test_normalize_strips_scheme_www_and_trailing_slash_and_lowercases(): void
    {
        $this->assertSame('example.com', DomainResolver::normalize('HTTPS://WWW.Example.com/'));
        $this->assertSame('example.com', DomainResolver::normalize('http://example.com'));
        $this->assertSame('example.com', DomainResolver::normalize('www.example.com'));
    }

    public function test_resolve_returns_normalized_request_host(): void
    {
        $this->assertSame('example.com', $this->resolverForHost('example.com')->resolve());
    }

    public function test_is_local_domain_true_for_exact_matches(): void
    {
        $this->assertTrue($this->resolverForHost('localhost')->isLocalDomain());
        $this->assertTrue($this->resolverForHost('127.0.0.1')->isLocalDomain());
    }

    public function test_is_local_domain_true_for_suffix_matches(): void
    {
        $this->assertTrue($this->resolverForHost('myapp.test')->isLocalDomain());
        $this->assertTrue($this->resolverForHost('myapp.local')->isLocalDomain());
        $this->assertTrue($this->resolverForHost('myapp.dev')->isLocalDomain());
    }

    public function test_is_local_domain_false_for_production_domain(): void
    {
        $this->assertFalse($this->resolverForHost('example.com')->isLocalDomain());
    }

    public function test_is_local_domain_does_not_falsely_match_domain_that_merely_contains_suffix(): void
    {
        $this->assertFalse($this->resolverForHost('test.example.com')->isLocalDomain());
    }

    public function test_custom_local_domains_config_override_is_respected(): void
    {
        config()->set('license-guard.local_domains', ['.internal']);

        $this->assertTrue($this->resolverForHost('app.internal')->isLocalDomain());
        $this->assertFalse($this->resolverForHost('myapp.test')->isLocalDomain());
    }
}
