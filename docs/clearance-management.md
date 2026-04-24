# Clearance Management

The Stake.games API sits behind Cloudflare. Requests require a valid `cf_clearance` cookie and a matching `User-Agent`, obtained by completing a Cloudflare challenge in a real browser. These credentials expire periodically and must be renewed manually.

---

## When Clearance Expires

1. The API detects a `403` from stake.games and switches to maintenance mode.
2. An alert is sent to `STAKE_CLEARANCE_ALERT_EMAIL` via the configured mail driver (Mailpit locally; a real SMTP/SES/etc. driver in production).
3. All `POST /api/bet-lookup` requests return `503` until credentials are renewed.

A proactive warning is also sent when clearance is within `STAKE_CLEARANCE_WARNING_THRESHOLD` seconds of expiry (default: 1 hour), giving time to renew before any downtime.

---

## Renewal

**Local dev harness:**

```bash
make capture          # capture + sync in one step
make capture force=1  # force renewal even if still valid
```

**Production / standalone (from the `stake-clearance/` directory):**

```bash
npm run capture
```

Opens Chrome, completes the Cloudflare challenge, and POSTs credentials directly to the app.

Maintenance mode clears automatically when new credentials are accepted.

---

## Check Status

```bash
php artisan stake:check-clearance
```

Prints credential details, expiry countdown, and runs a live probe against stake.games:

```
Status: Active
Clearance Cookie: abcd1234...
User Agent: Mozilla/5.0...
Expires: 2025-01-16 12:00:00
Time Remaining: 2h 34m
Probing stake.games...
Probe: Active (HTTP 200)
```

---

## Admin API Endpoints

All require `Authorization: Bearer <raw-token>`.

**Fetch credentials (also populates Bruno environment via post-response script):**
```bash
curl https://your-app.com/api/admin/clearance-credentials \
  -H "Authorization: Bearer <token>"
```

**Check status:**
```bash
curl https://your-app.com/api/admin/clearance-status \
  -H "Authorization: Bearer <token>"
```

**Push new credentials:**
```bash
curl -X POST https://your-app.com/api/admin/update-clearance \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"clearance": "<cookie>", "userAgent": "<ua>", "expiry": <unix-timestamp>}'
```

**Test live connectivity:**
```bash
curl -X POST https://your-app.com/api/admin/test-clearance \
  -H "Authorization: Bearer <token>"
```

---

## Admin Token

The raw token is the Bearer value used in requests. The server stores only its SHA-256 hash (`STAKE_ADMIN_TOKEN` in `.env`) — so even if `.env` is exposed, the working token cannot be derived from the hash. The middleware uses `hash_equals` for timing-safe comparison.

**Local (dev harness):** `make token` — generates a new pair, writes hash to `app/.env`, prints raw token.

**Production:**
```bash
TOKEN=$(openssl rand -hex 32)
HASH=$(php -r "echo hash('sha256', '$TOKEN');")
echo "Raw  : $TOKEN"   # → update scripts/sync-config.json
echo "Hash : $HASH"    # → set as STAKE_ADMIN_TOKEN in .env
```

After updating `.env`: `php artisan config:cache`, then update `scripts/sync-config.json` with the new raw token.
