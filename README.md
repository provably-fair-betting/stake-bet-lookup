[![CI](https://github.com/provably-fair-betting/stake-bet-lookup/actions/workflows/ci.yml/badge.svg)](https://github.com/provably-fair-betting/stake-bet-lookup/actions/workflows/ci.yml)
[![Release](https://github.com/provably-fair-betting/stake-bet-lookup/actions/workflows/release.yml/badge.svg)](https://github.com/provably-fair-betting/stake-bet-lookup/actions/workflows/release.yml)

# Production Setup

How to install and configure the Stake Bet Lookup package in an existing Laravel application.

---

## Access

This is a private package. Before installing you need GitHub access granted by the maintainer, and Composer configured to authenticate with GitHub.

**1. Generate a GitHub personal access token**

Go to github.com → Settings → Developer settings → Personal access tokens → Generate new token. The token needs `repo` (read) scope.

**2. Authenticate Composer**

```bash
composer config --global github-oauth.github.com <your-personal-access-token>
```

**3. Add the repository to your `composer.json`**

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:provably-fair-betting/stake-bet-lookup.git"
    }
  ]
}
```

Then follow the steps below.

---

## 1. Install the Package

```bash
composer require stake/bet-lookup:^1.0
```

The service provider is auto-discovered — no manual registration needed.

---

## 2. Publish Config and Migrations

```bash
php artisan vendor:publish --tag=bet-lookup-config
php artisan vendor:publish --tag=bet-lookup-migrations
```

This copies:
- `config/bet-lookup.php` — tunable settings (rate limits, timeout)
- A migration creating the `stake_clearance` table

Optionally publish the Bruno API collection to `stake-bruno/` in your project root:

```bash
php artisan vendor:publish --tag=bet-lookup-bruno
```

---

## 3. Run Migrations

```bash
php artisan migrate
```

Creates the `stake_clearance` table, which persists clearance credentials across deployments and container restarts.

---

## 4. Configure Environment Variables

Add to your `.env`:

```env
# Required
STAKE_ADMIN_TOKEN=<sha256-hash-of-your-raw-token>   # see Step 5
STAKE_CLEARANCE_ALERT_EMAIL=you@example.com

# Optional
STAKE_API_URL=https://stake.games/_api/graphql      # default; rarely needs changing
STAKE_ACCESS_TOKEN=                                  # x-access-token header, if required
STAKE_CLEARANCE_WARNING_THRESHOLD=3600               # alert N seconds before expiry
BET_LOOKUP_RATE_LIMIT=60                             # requests/min on the public endpoint
BET_LOOKUP_TIMEOUT=10                                # upstream HTTP timeout in seconds
```

> Clearance credentials (`cf_clearance` cookie, user agent, expiry) are stored in the database after the first capture — they do not live in `.env`.

---

## 5. Generate an Admin Token

The admin token protects the `/api/admin/*` endpoints. Your `.env` stores only its SHA-256 hash — the raw token is used as the Bearer token in requests and is never stored server-side.

Generate a token pair on your local machine:

```bash
TOKEN=$(openssl rand -hex 32)
HASH=$(php -r "echo hash('sha256', '$TOKEN');")
echo "Raw token : $TOKEN"
echo "Hash      : $HASH"
```

- Set `STAKE_ADMIN_TOKEN=$HASH` in your production `.env`
- Save `$TOKEN` somewhere safe — you'll need it for the capture scripts in Step 7

After updating `.env`, if you use config caching:

```bash
php artisan config:cache
```

---

## 6. Configure the Mail Driver

Clearance-expiry alerts are sent via Laravel's mail system. Configure a real driver in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-app.com
```

Any Laravel-supported driver works (SMTP, SES, Mailgun, Postmark, etc.). Without a working mail driver, expiry alerts will silently fail — the API will still function, but you won't receive warnings before clearance expires.

---

## 7. Publish and Install the Capture Scripts

Publish the clearance scripts to your project:

```bash
php artisan vendor:publish --tag=bet-lookup-scripts
```

This copies the scripts to `stake-clearance/` in your project root — outside `vendor/`, so they persist across `composer update`. A `.gitignore` is included that excludes `node_modules/` and `sync-config.json` automatically.

Install Node dependencies:

```bash
cd stake-clearance && npm install
```

Configure `stake-clearance/sync-config.json` with your endpoint and raw token. On first run the file is created as a template — edit it, then re-run.

```json
{
  "api": {
    "endpoint": "https://your-app.com/api/admin/update-clearance",
    "token": "<raw token from Step 5>"
  }
}
```

> The `token` field is the **raw token**, not the hash in `.env`.

---

## 8. Capture Initial Clearance Credentials

From the `stake-clearance/` directory:

```bash
npm run capture
```

Opens Chrome, waits for the Cloudflare challenge, then POSTs credentials directly to your app. The app stores them in the database and cache. `POST /api/bet-lookup` is now live.

Verify it worked:

```bash
curl -X POST https://your-app.com/api/admin/test-clearance \
  -H "Authorization: Bearer <raw-token>"
# → {"success":true,"message":"Clearance is working","status_code":200}
```

---

## Ongoing Renewal

Clearance credentials expire periodically (usually within days). When they do:

1. You receive an alert at `STAKE_CLEARANCE_ALERT_EMAIL`
2. The API returns `503` for all bet lookup requests
3. From `stake-clearance/`: run `npm run capture`
4. The API resumes immediately — no restart or deployment needed

See the [clearance management guide](docs/clearance-management.md) for the full operational reference including status checks and token rotation.

---

## Routes Registered

The package registers these routes automatically:

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/api/bet-lookup` | None | Public bet lookup |
| `POST` | `/api/admin/update-clearance` | Bearer | Push new clearance credentials (probes before saving) |
| `GET`  | `/api/admin/clearance-status` | Bearer | Validity and expiry info |
| `GET`  | `/api/admin/clearance-credentials` | Bearer | Retrieve current credentials |
| `POST` | `/api/admin/test-clearance` | Bearer | Probe stake.games live |

---

## Artisan Commands

```bash
php artisan stake:check-clearance              # Status, expiry, and live probe
php artisan stake:update-clearance <cookie> <user-agent> <expiry>  # Manual update
```

---

## Development

This repository includes a local development harness (Docker + Makefile) for working on the package itself.

See [docs/local-development.md](docs/local-development.md) for setup instructions.
