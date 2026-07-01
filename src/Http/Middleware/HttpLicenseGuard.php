<?php

namespace Vendor\LicenseGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;

/**
 * Terminable middleware. handle() never blocks the request -- fail-closed
 * enforcement for the live request already happened at boot via
 * BootLicenseGuard. This middleware's only job is the throttled,
 * post-response heartbeat, with no cron dependency.
 */
class HttpLicenseGuard
{
    private const CACHE_KEY = 'license-guard:last-heartbeat';

    public function __construct(private LicenseCheckerInterface $checker)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->checker->isBypassed()) {
            return;
        }

        $intervalSeconds = max(1, (int) config('license-guard.check_interval_hours', 6)) * 3600;

        $lastHeartbeat = Cache::get(self::CACHE_KEY);

        if ($lastHeartbeat !== null && (time() - $lastHeartbeat) < $intervalSeconds) {
            return;
        }

        // Claim the throttle slot before the network call to avoid a
        // thundering herd of concurrent requests all firing the heartbeat
        // the moment the interval elapses.
        Cache::put(self::CACHE_KEY, time(), $intervalSeconds * 2);

        $this->checker->forceVerify();
    }
}
