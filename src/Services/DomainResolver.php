<?php

namespace Vendor\LicenseGuard\Services;

use Illuminate\Http\Request;

class DomainResolver
{
    public function __construct(private Request $request)
    {
    }

    /**
     * Resolve the current request's host: lowercase, leading "www." stripped.
     * Falls back to config('app.url')'s host when running in console or when
     * no request host is available (e.g. artisan commands, queue workers).
     */
    public function resolve(): string
    {
        $host = $this->request->getHost();

        if ($host === '') {
            $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return static::normalize($host);
    }

    public function isLocalDomain(): bool
    {
        return static::matchesLocalList($this->resolve(), (array) config('license-guard.local_domains', []));
    }

    public static function normalize(string $rawHost): string
    {
        $host = strtolower(trim($rawHost));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? $host;
        $host = rtrim($host, '/');

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function matchesLocalList(string $normalizedHost, array $localDomains): bool
    {
        foreach ($localDomains as $entry) {
            $entry = strtolower((string) $entry);

            if ($entry === '') {
                continue;
            }

            if (str_starts_with($entry, '.')) {
                if (str_ends_with($normalizedHost, $entry)) {
                    return true;
                }

                continue;
            }

            if ($normalizedHost === $entry) {
                return true;
            }
        }

        return false;
    }
}
