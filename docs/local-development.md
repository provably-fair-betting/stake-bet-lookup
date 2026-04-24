# Local Development

The `app/` directory is a minimal Laravel harness for developing and testing the package locally. **It does not exist in the repository** — `make setup` creates it. It is gitignored and not part of the distributed package.

---

## Prerequisites

- Docker and Docker Compose
- Composer
- Node.js 18+

---

## Quickstart

```bash
make setup      # Create app/, generate APP_KEY and admin token, install Node deps
make up         # Start services
make migrate    # Run migrations
make capture    # Open browser, complete the Cloudflare challenge, then sync into the app
```

No manual `.env` edits required — `make setup` generates everything automatically.

---

## Services

| Service  | URL                        | Notes                              |
|----------|----------------------------|------------------------------------|
| App      | http://localhost:8080      |                                    |
| Mailpit  | http://localhost:8025      | Catches all outgoing email locally |
| Adminer  | http://localhost:8090      | `make adminer` to start            |
| MySQL    | localhost:3306             |                                    |

> **Mailpit is local only.** It intercepts all outgoing mail regardless of the `MAIL_FROM_ADDRESS` or recipient. In production you configure a real mail driver — see [production-setup.md](production-setup.md).

---

## Commands

```bash
make setup      # First-time bootstrap
make reinstall  # Wipe app/ and re-run setup from scratch
make up         # Start all services
make down       # Stop all services
make restart    # Restart the app container
make migrate    # Run database migrations
make capture    # Capture clearance credentials and sync (skips if still valid)
make capture force=1  # Force renewal even if clearance is still valid
make token      # Rotate the admin token
make shell      # Shell into the app container
make logs       # Tail app container logs
make db         # Open a MySQL shell
make adminer    # Start Adminer UI at http://localhost:8090
```

---

## How `make setup` Works

`make setup` executes the same numbered steps as [README.md](../README.md), logging each one as it runs:

| Step | Title | Notes |
|------|-------|-------|
| 1 | Install the Package | `composer create-project` + path repository |
| 2 | Publish Config and Migrations | `vendor:publish` for config and migrations |
| 3 | Run Migrations | Skipped — run `make migrate` after `make up` |
| 4 | Configure Environment Variables | `.env.example` → `app/.env` (done during step 1; step 4 confirms defaults) |
| 5 | Generate an Admin Token | Hash → `app/.env`, raw → `sync-config.json` |
| 6 | Configure the Mail Driver | Skipped — Mailpit handles all local email |
| 7 | Publish and Install the Capture Scripts | `vendor:publish` → `app/stake-clearance/` + `app/stake-bruno/`, then `npm install` |
| 8 | Capture Initial Clearance Credentials | Skipped — run `make capture` |

After `make setup`, `make capture` works immediately — no manual configuration needed.

---

## Clearance Renewal

```bash
make capture          # Capture + sync in one step (skips capture if still valid)
make capture force=1  # Force renewal even if clearance is still valid
```

`make capture` opens a Chrome window, waits for the Cloudflare challenge, then POSTs credentials directly to the running app. Maintenance mode clears automatically.

---

## Token Rotation

```bash
make token
```

Generates a new token pair, writes the hash to `app/.env`, and prints the raw token. Update `app/stake-clearance/sync-config.json` with the new raw token, then run `make restart`.
