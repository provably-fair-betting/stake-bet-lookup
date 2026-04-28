# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2](https://github.com/provably-fair-betting/stake-bet-lookup/compare/v1.0.1...v1.0.2) (2026-04-28)


### Bug Fixes

* commit before rebase in coverage badge update step ([54dda73](https://github.com/provably-fair-betting/stake-bet-lookup/commit/54dda73473f10e6797e1b70f8e625b1737bffa81))
* stage README before rebase in coverage badge update step ([6b65170](https://github.com/provably-fair-betting/stake-bet-lookup/commit/6b65170cc5eaf7ef19ca87e1ace051d06255ab69))
* switch to xdebug for branch coverage, rewrite badge update step ([b4fe87d](https://github.com/provably-fair-betting/stake-bet-lookup/commit/b4fe87d5fed2e8687380af7bb7d7eeb3efaae8d4))
* use conditionals for branch coverage, add debug output ([5680052](https://github.com/provably-fair-betting/stake-bet-lookup/commit/56800525c0421627325960f9cc6acd7cb1be98d1))


### Miscellaneous Chores

* remove debug output from coverage badge step ([9657a0d](https://github.com/provably-fair-betting/stake-bet-lookup/commit/9657a0d91c20ec92d0e2ded0a2c956d170b89024))
* update coverage badges [skip ci] ([58996d8](https://github.com/provably-fair-betting/stake-bet-lookup/commit/58996d8fb7e7339fc7af533bd064df1a0fed1319))
* update coverage badges [skip ci] ([a443f2a](https://github.com/provably-fair-betting/stake-bet-lookup/commit/a443f2ac8a28f501cc88e68bbf87dce653570fcf))

## [1.0.1](https://github.com/provably-fair-betting/stake-bet-lookup/compare/v1.0.0...v1.0.1) (2026-04-28)


### Miscellaneous Chores

* opt into Node.js 24 for release-please action ([1f49616](https://github.com/provably-fair-betting/stake-bet-lookup/commit/1f496162b2ac7374a19a5acbd43d8bff510f352d))

## [1.0.0] - 2026-04-28

### Added

**Bet lookup**
- `POST /api/bet-lookup` — public endpoint returning normalised provability data for casino, crash, and slide bets
- `BetNormalizerService` — maps raw Stake.games GraphQL responses into a unified format
- `SeedNotRevealedException` thrown when a bet's server seed has not yet been revealed

**Cloudflare clearance management**
- `ClearanceRepository` — stores credentials (cookie, user-agent, expiry) in cache and database, with maintenance mode and alert-sent tracking
- `StakeHttpClientFactory` — builds Guzzle clients with the correct clearance headers per request
- `StakeApiService` — fetches bets via the Stake.games GraphQL API; probes clearance with `probe()` and `probeWith()`
- `capture-clearance.mjs` — browser automation script that captures the Cloudflare clearance cookie and POSTs it directly to the running app

**Admin API** (Bearer token protected)
- `POST /api/admin/update-clearance` — validates and probes new credentials before saving
- `GET /api/admin/clearance-status` — validity, expiry, and maintenance mode info
- `GET /api/admin/clearance-credentials` — retrieve current cookie and user-agent
- `POST /api/admin/test-clearance` — live probe against stake.games

**Artisan commands**
- `stake:check-clearance` — prints status, expiry, and live probe result
- `stake:update-clearance` — manually push new credentials from the CLI

**Notifications**
- `ClearanceExpiredNotification` and `ClearanceExpiringNotification` sent via email and/or Slack webhook when clearance expires or is approaching expiry

**Infrastructure**
- SHA-256 hashed admin token (raw token never stored server-side)
- Rate limiting on the public endpoint (configurable via `BET_LOOKUP_RATE_LIMIT`)
- Docker + Makefile local development harness
- Bruno API collections for admin and public endpoints
- 100 PHPUnit tests across unit and feature suites
