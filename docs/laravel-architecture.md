# Laravel Architecture Guide

How the Stake Bet Lookup package integrates into a Laravel application.

---

## Integration Model

This is a **Composer path package**. The `laravel-package/` directory is registered as a local repository in the consuming app's `composer.json`, then required as a normal package. Laravel auto-discovers the service provider via the `extra.laravel.providers` key in the package's `composer.json`.

Once installed, the package:
- Registers its services in Laravel's service container
- Loads its own routes (`/api/bet-lookup`, `/api/admin/*`)
- Publishes its config and database migrations to the app

---

## Request Flow

```
POST /api/bet-lookup
        │
        ▼
[throttle middleware]       ← rate-limit: 60 req/min per IP
        │
        ▼
[ValidateBetId middleware]  ← rejects malformed IDs before the controller sees them
        │
        ▼
[BetLookupController]
        │
        ├── StakeApiService::fetchBet()
        │       ├── ClearanceRepository: is maintenance mode active? → 503 if yes
        │       ├── ClearanceRepository: is clearance expiring soon? → alert if yes
        │       ├── Cache: return cached result if available
        │       └── HTTP POST to stake.games GraphQL API
        │               ├── 403 → enable maintenance mode, send alert, throw AuthenticationException
        │               ├── GraphQL error → throw BetNotFoundException or StakeApiException
        │               └── success → cache result, return data
        │
        └── BetNormalizerService::normalize()   ← flatten GraphQL response for consumers
```

Admin requests (`POST /api/admin/*`) pass through `AuthenticateAdmin` middleware (timing-safe bearer token check) instead of `ValidateBetId`.

---

## Package Structure

```
laravel-package/src/
├── BetLookupServiceProvider.php          # Registers services, routes, commands
├── Services/
│   ├── StakeApiService.php               # GraphQL client; orchestrates clearance + caching
│   ├── ClearanceRepository.php           # Credential store: cache → DB → config fallback
│   ├── ClearanceAlerter.php              # Email + Slack notifications
│   └── BetNormalizerService.php          # Flattens raw GraphQL response
├── Http/
│   ├── Controllers/
│   │   ├── BetLookupController.php       # POST /api/bet-lookup
│   │   └── AdminController.php           # POST/GET /api/admin/*
│   └── Middleware/
│       ├── AuthenticateAdmin.php         # Bearer token validation (timing-safe)
│       └── ValidateBetId.php             # Rejects malformed bet IDs
├── Console/Commands/
│   ├── CheckClearanceCommand.php         # php artisan stake:check-clearance
│   └── UpdateClearanceCommand.php        # php artisan stake:update-clearance
├── Models/
│   └── StakeClearance.php                # Eloquent model for stake_clearance table
├── Notifications/
│   ├── ClearanceExpiredNotification.php
│   └── ClearanceExpiringNotification.php
├── Exceptions/
│   ├── AuthenticationException.php       # 401/503 — bad token or maintenance mode
│   ├── BetNotFoundException.php          # 404 — bet ID not found
│   └── StakeApiException.php             # 5xx — upstream API error
└── Routes/api.php
```

---

## Service Container Bindings

All services are registered as **singletons** in `BetLookupServiceProvider::register()`:

| Service | Depends on |
|---|---|
| `ClearanceRepository` | config array |
| `ClearanceAlerter` | config array |
| `StakeApiService` | `ClearanceRepository`, `ClearanceAlerter`, config array |
| `BetNormalizerService` | — |

Laravel resolves these automatically via dependency injection. Controllers declare them in their constructors — no manual instantiation.

---

## Credential Storage

`ClearanceRepository` reads credentials from three sources in priority order:

1. **Cache** — fastest; populated after first DB read or admin update
2. **Database** (`stake_clearance` table) — survives container restarts
3. **Config / env** — fallback for initial setup before the DB is populated

When credentials are updated via `POST /api/admin/update-clearance` or `php artisan stake:update-clearance`, both the cache and the DB are written. The cache TTL is 7 days; the DB record is the persistent source of truth.

---

## Maintenance Mode

When a 403 from stake.games is detected:
1. `ClearanceRepository::enableMaintenanceMode()` — sets a 5-minute cache flag
2. `ClearanceAlerter::alertExpired()` — sends email and/or Slack notification once per window
3. All subsequent requests return 503 immediately (no upstream call made)
4. Pushing new credentials (`update-clearance`) clears maintenance mode instantly

---

## Database

One table: `stake_clearance`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | primary key |
| `clearance_cookie` | text | `cf_clearance` value |
| `user_agent` | text | must match the browser that produced the cookie |
| `expires_at` | timestamp | used for expiry checks and alerts |
| `updated_by` | string nullable | audit trail — IP or identifier of who pushed the update |
| `created_at` / `updated_at` | timestamp | standard Laravel timestamps |

The package appends a new row on each credential update rather than updating in place, preserving history. `ClearanceRepository` always reads the latest row.

---

## Configuration

Published to `config/bet-lookup.php` in the consuming app. All values read from `.env`:

```php
return [
    'stake_api_url'               => env('STAKE_API_URL', 'https://stake.games/_api/graphql'),
    'stake_clearance_cookie'      => env('STAKE_CLEARANCE_COOKIE'),
    'stake_user_agent'            => env('STAKE_USER_AGENT'),
    'stake_clearance_expiry'      => env('STAKE_CLEARANCE_EXPIRY'),
    'admin_token'                 => env('STAKE_ADMIN_TOKEN'),
    'clearance_alert_email'       => env('STAKE_CLEARANCE_ALERT_EMAIL'),
    'clearance_warning_threshold' => env('STAKE_CLEARANCE_WARNING_THRESHOLD', 3600),
    'cache_ttl'                   => env('BET_LOOKUP_CACHE_TTL', 300),
    'rate_limit'                  => env('BET_LOOKUP_RATE_LIMIT', 60),
    'timeout'                     => env('BET_LOOKUP_TIMEOUT', 10),
];
```

See [Production Setup](../docs/setup.md) for the full `.env` reference.
