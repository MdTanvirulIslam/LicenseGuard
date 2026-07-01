<?php

namespace Vendor\LicenseGuard\Services;

use Illuminate\Support\Carbon;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;
use Vendor\LicenseGuard\Models\LicenseCache;

/**
 * The single orchestrator for bypass, fail-closed, and grace-period decisions.
 * Every other consumer (boot guard, middleware, trait, artisan command) must
 * go through this class via the interface rather than re-deriving these rules.
 */
class LicenseChecker implements LicenseCheckerInterface
{
    private ?string $domain = null;
    private ?bool $isLocal = null;

    public function __construct(
        private LicenseClient $client,
        private TokenValidator $validator,
        private DomainResolver $domainResolver,
    ) {
    }

    public function check(): bool
    {
        if ($this->isBypassed()) {
            return true;
        }

        $cache = $this->cacheRow();

        if ($cache === null || $cache->token === null || $cache->signature === null) {
            return $this->forceVerify();
        }

        if (! $this->isCachedTokenValid($cache)) {
            return $this->forceVerify();
        }

        $graceDeadline = $cache->last_checked_at?->copy()
            ->addHours((int) config('license-guard.grace_period_hours', 24));

        if ($graceDeadline === null || now()->greaterThan($graceDeadline)) {
            return $this->forceVerify();
        }

        return true;
    }

    public function forceVerify(): bool
    {
        if ($this->isBypassed()) {
            return true;
        }

        $domain = $this->currentDomain();
        $isLocal = $this->isLocalDomain();
        $cache = $this->cacheRow();

        $currentToken = ($cache?->token !== null && $cache?->signature !== null)
            ? $cache->token.'.'.$cache->signature
            : null;

        // The server only issues a fresh token via /activate the first time a
        // domain is seen; every subsequent check re-pings /verify with the
        // previously issued token.
        $response = $currentToken === null
            ? $this->client->activate($domain, $isLocal)
            : $this->client->verify($domain, $currentToken);

        // The server never returns a token on failure (suspended, expired,
        // domain limit, unreachable, etc.) -- so any missing token is
        // fail-closed, and we leave any prior known-good cache row untouched.
        if (! $response->success || $response->token === null) {
            return false;
        }

        $split = TokenValidator::split($response->token);

        if ($split === null) {
            return false;
        }

        if (! $this->tokenIsValidForDomain($split['payload'], $split['signature'], $domain)) {
            return false;
        }

        LicenseCache::query()->updateOrCreate(['domain' => $domain], [
            'token' => $split['payload'],
            'signature' => $split['signature'],
            'is_local' => $isLocal,
            'last_checked_at' => now(),
        ]);

        return true;
    }

    public function currentDomain(): string
    {
        return $this->domain ??= $this->domainResolver->resolve();
    }

    public function isLocalDomain(): bool
    {
        return $this->isLocal ??= $this->domainResolver->isLocalDomain();
    }

    public function isBypassed(): bool
    {
        return (bool) config('license-guard.bypass_local', false);
    }

    public function lastCheckedAt(): ?Carbon
    {
        return $this->cacheRow()?->last_checked_at;
    }

    private function isCachedTokenValid(LicenseCache $cache): bool
    {
        return $this->tokenIsValidForDomain($cache->token, $cache->signature, $this->currentDomain());
    }

    /** Signature integrity + expiry + domain-binding (a token issued for one domain must not validate for another). */
    private function tokenIsValidForDomain(string $payload, string $signature, string $domain): bool
    {
        if (! $this->validator->isValid($payload, $signature)) {
            return false;
        }

        return ($this->validator->decode($payload)['domain'] ?? null) === $domain;
    }

    private function cacheRow(): ?LicenseCache
    {
        return LicenseCache::query()->where('domain', $this->currentDomain())->first();
    }
}
