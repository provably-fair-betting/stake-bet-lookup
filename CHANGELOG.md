# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
