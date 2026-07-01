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

        if ($cache === null || $cache->status !== 'active') {
            return $this->forceVerify();
        }

        if ($cache->token === null || $cache->signature === null) {
            return $this->forceVerify();
        }

        if (! $this->validator->isValid($cache->token, $cache->signature)) {
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
        $hadCache = $this->cacheRow() !== null;

        $response = $hadCache
            ? $this->client->verify($domain, $isLocal)
            : $this->client->activate($domain, $isLocal);

        if (! $response->success || $response->token === null || $response->signature === null) {
            // Transport/server failure: leave any prior known-good state untouched
            // so the grace period keeps counting from the last successful check.
            return false;
        }

        if (! $this->validator->verifySignature($response->token, $response->signature)) {
            // Untrusted/tampered payload: do not persist anything from it.
            return false;
        }

        $isTokenValid = $this->validator->isValid($response->token, $response->signature);
        $status = $response->status ?? ($this->validator->decode($response->token)['status'] ?? 'unknown');

        $attributes = [
            'token' => $response->token,
            'signature' => $response->signature,
            'status' => $status,
            'is_local' => $isLocal,
        ];

        if ($isTokenValid) {
            $attributes['last_checked_at'] = now();
        }

        LicenseCache::query()->updateOrCreate(['domain' => $domain], $attributes);

        return $isTokenValid;
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

    private function cacheRow(): ?LicenseCache
    {
        return LicenseCache::query()->where('domain', $this->currentDomain())->first();
    }
}
