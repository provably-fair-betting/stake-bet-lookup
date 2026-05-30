# verifierform-stake-bet-lookup

[![CI](https://github.com/provably-fair-betting/verifierform-stake-bet-lookup/actions/workflows/ci.yml/badge.svg)](https://github.com/provably-fair-betting/verifierform-stake-bet-lookup/actions/workflows/ci.yml)
[![Version](https://img.shields.io/github/v/release/provably-fair-betting/verifierform-stake-bet-lookup)](https://github.com/provably-fair-betting/verifierform-stake-bet-lookup/releases/latest)
[![Coverage](https://codecov.io/gh/provably-fair-betting/verifierform-stake-bet-lookup/graph/badge.svg)](https://codecov.io/gh/provably-fair-betting/verifierform-stake-bet-lookup)

A Laravel package that exposes a public REST API for looking up Stake.games bet data. Given a bet ID, it fetches the provably fair seeds, nonce, and game-specific state from the Stake.games GraphQL API and returns a normalised response ready for use in a verifier frontend.

Stake.games sits behind Cloudflare, so the package manages a `cf_clearance` cookie that must be refreshed periodically. Credentials are stored in the database and pushed in via an admin API — the application itself is stateless between deployments.

---

## API Reference

### Public

#### `POST /api/bet-lookup`

Looks up a bet by ID and returns its provably fair inputs.

**Request**

```json
{ "betId": "house:476694353054" }
```

**Response — 200 OK**

```json
{
  "success": true,
  "data": {
    "betType": "CasinoBet",
    "game": "mines",
    "inputs": {
      "clientSeed": "abc123",
      "serverSeed": "def456",
      "serverSeedHash": "ghi789",
      "nonce": 42,
      "minesCount": 5
    }
  }
}
```

The `inputs` object always contains `clientSeed`, `serverSeed`, `serverSeedHash`, and `nonce` for casino bets. Games that require additional parameters include extra fields:

| Game | Extra inputs |
|------|-------------|
| Mines | `minesCount` |
| Moles | `molesCount` |
| Plinko | `risk`, `rows` |
| Wheel | `risk`, `segments` |
| Bars | `difficulty`, `tiles` |
| Cases, Chicken, Darts, Dragon Tower, Pump, Snakes, Tarot | `difficulty` |

For multiplayer bets the response shape differs:

```json
{
  "success": true,
  "data": {
    "betType": "MultiplayerCrashBet",
    "game": "crash",
    "inputs": {
      "serverSeed": "abc123",
      "gameHash": "def456"
    }
  }
}
```

**Error responses**

| Status | Cause |
|--------|-------|
| `400` | Invalid or missing `betId` (must match `house:\d+`) |
| `404` | Bet not found |
| `422` | Server seed not yet revealed |
| `429` | Rate limit exceeded |
| `503` | Clearance expired or service in maintenance mode |

All error responses follow the shape `{ "success": false, "error": "..." }`.

---

### Admin

All admin endpoints require `Authorization: Bearer <token>` where the token is the raw value whose SHA-256 hash is stored in `STAKE_ADMIN_TOKEN`.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/admin/update-clearance` | Push new `cf_clearance` cookie, user agent, and expiry — probes Stake.games before saving |
| `GET`  | `/api/admin/clearance-status` | Validity, expiry time, and maintenance mode state |
| `GET`  | `/api/admin/clearance-credentials` | Retrieve the currently stored credentials |
| `POST` | `/api/admin/test-clearance` | Probe Stake.games live with the current credentials |

---

## Artisan Commands

```bash
php artisan stake:check-clearance                                    # Status, expiry, and live probe
php artisan stake:update-clearance <cookie> <user-agent> <expiry>   # Manual credential update
```

---

## Bruno Collection

A [Bruno](https://www.usebruno.com/) API collection is included in the `bruno/` directory, covering all public and admin endpoints as well as a direct Stake.games GraphQL query for debugging.

To get started:

1. Open the `bruno/` directory as a collection in Bruno
2. Select the `Local` environment and set `adminToken` to your raw admin token
3. Run **Admin → Fetch Clearance** — this populates `clearanceCookie` and `userAgent` in the environment automatically
4. All other requests are ready to use

The collection can also be published into a consumer application:

```bash
php artisan vendor:publish --tag=bet-lookup-bruno
```

---

## Docker Image

A pre-built image is published to GitHub Container Registry on every release:

```
ghcr.io/provably-fair-betting/verifierform-stake-bet-lookup:<version>
```

| Tag pattern | Resolves to | Updates on |
|---|---|---|
| `1.2.3` | `1.2.3` | that release only |
| `1.2` | latest `1.2.x` (e.g. `1.2.4`) | patch releases within `1.2` |
| `1` | latest `1.x.y` (e.g. `1.3.0`) | minor + patch releases within `1` |

The image (~350–400 MB, Alpine-based) bundles Nginx + PHP-FPM via Supervisor. It requires no local PHP or Composer — all runtime secrets are injected via environment variables.

**Required environment variables:**

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel application key (`base64:...`) |
| `STAKE_ADMIN_TOKEN` | SHA-256 hash of the raw admin bearer token |

See the [integration environment](https://github.com/provably-fair-betting/verifierform-stake-env) for a ready-made Docker Compose setup that wires this image together with the frontend.

The [publish workflow](.github/workflows/publish.yml) triggers automatically when release-please merges a release PR and pushes a version tag. It builds and publishes the major, minor, and patch floating tags to ghcr.io.

---

## Documentation

- [Production setup](docs/setup.md) — install, configure, and deploy in a Laravel app
- [Local development](docs/local-development.md) — Docker-based dev environment and Makefile reference
- [Clearance management](docs/clearance-management.md) — operational guide for credential renewal
- [Laravel architecture](docs/laravel-architecture.md) — package internals and design decisions
