# License Guard

Domain-locked, phone-home license enforcement for Laravel applications you sell to customers. Installed into each sold app; enforces licensing on every request via boot-time + terminate-time checks, with no cron dependency.

This package is the **client side** only. It expects a separate License Manager server exposing `POST /api/license/activate` and `POST /api/license/verify`.

## Installation

```bash
composer require vendor/license-guard
php artisan vendor:publish --tag=license-guard-config
php artisan migrate
```

Set these in the target app's `.env` before handover (see `.env.example` in this repo for the full block):

- `LICENSE_SERVER_URL`
- `LICENSE_KEY` — unique per customer
- `LICENSE_SECRET` — shared per product
- `LICENSE_CHECK_INTERVAL_HOURS` (default 6)
- `LICENSE_GRACE_PERIOD_HOURS` (default 24)

**Never set `LICENSE_BYPASS_LOCAL=true` in a customer `.env`.** It is for the vendor's own development machine only — when true, all license checks short-circuit to valid with zero HTTP calls.

## Configuration Reference

| Key | Env var | Purpose |
|---|---|---|
| `server_url` | `LICENSE_SERVER_URL` | Base URL of the License Manager server |
| `license_key` | `LICENSE_KEY` | Per-customer license key |
| `secret` | `LICENSE_SECRET` | Shared HMAC secret used to verify server-issued tokens |
| `check_interval_hours` | `LICENSE_CHECK_INTERVAL_HOURS` | Heartbeat throttle interval |
| `grace_period_hours` | `LICENSE_GRACE_PERIOD_HOURS` | How long a locally-cached, valid token is trusted offline |
| `bypass_local` | `LICENSE_BYPASS_LOCAL` | Vendor dev-machine-only bypass |
| `local_domains` | — | Domains/suffixes treated as local for the `is_local` payload flag |

## Usage

Protect a business-critical class with the trait:

```php
use Vendor\LicenseGuard\Traits\RequiresValidLicense;

class ReportExporter
{
    use RequiresValidLicense;

    public function export(): void
    {
        $this->assertLicenseValid();

        // ...
    }
}
```

Or resolve the interface directly via dependency injection:

```php
use Vendor\LicenseGuard\Contracts\LicenseCheckerInterface;

class SomeController
{
    public function __construct(private LicenseCheckerInterface $license) {}
}
```

Check status manually:

```bash
php artisan license:status
php artisan license:status --fresh   # force a live server recheck
```

## Local Development

Set `LICENSE_BYPASS_LOCAL=true` in your own `.env` only. Any domain matching `local_domains` (default: `localhost`, `127.0.0.1`, `::1`, `*.test`, `*.local`, `*.dev`) is still checked against the server as normal when bypass is off — it just sends `is_local: true` in the request payload so the server doesn't consume a domain slot for it.

## Server Contract

Both `/api/license/activate` and `/api/license/verify` accept:

```json
{"license_key": "...", "domain": "example.com", "is_local": false}
```

And return:

```json
{
    "success": true,
    "payload": "<base64 JSON: license_key, domain, is_local, status, issued_at, expires_at>",
    "signature": "<base64 HMAC-SHA256 of the base64 payload string, using the shared secret>",
    "status": "active"
}
```

## Release Process

This build ships plain, readable PHP with no obfuscation applied. Running the source through ionCube (or another encoder) is a separate, manual step performed by the vendor after build and test, using the vendor's own tooling — nothing in this package invokes an encoder.
