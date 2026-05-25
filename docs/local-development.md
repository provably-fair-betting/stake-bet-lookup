# Local Development

The Docker image bootstraps a complete Laravel environment from scratch — no local PHP, Composer, or `app/` directory required. All app code is baked into the image at build time; secrets are injected at runtime via environment variables.

---

## Prerequisites

- Docker and Docker Compose
- Node.js 18+ (for the Cloudflare clearance capture script)

---

## Quickstart

```bash
make setup    # Copy .env, build image, generate APP_KEY + admin token, install capture deps
make up       # Start services
make migrate  # Run database migrations (first time only)
make capture  # Open browser, complete the Cloudflare challenge, sync credentials
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

---

## Commands

```bash
make setup            # First-time setup
make build            # Rebuild app image after source changes (prunes old image)
make up               # Start all services
make down             # Stop all services
make restart          # Restart the app container
make migrate          # Run database migrations
make capture          # Capture clearance (skips if still valid)
make capture force=1  # Force capture even if clearance is still valid
make token            # Rotate the admin token
make shell            # Shell into the app container
make logs             # Tail app container logs
make db               # Open a MySQL shell
make adminer          # Start Adminer UI at http://localhost:8090
make reset            # Wipe Docker volumes (fresh database)
```

---

## How `make setup` Works

1. Copies `.env.example` → `.env` (if `.env` doesn't exist)
2. Builds the Docker image — Laravel is bootstrapped inside Docker; no local Composer needed
3. Generates `APP_KEY` via the built image and writes it to `.env`
4. Generates an admin token pair — hash written to `.env`, raw token written to `scripts/sync-config.json`
5. Installs Node deps in `scripts/` for the capture script

After `make setup`, `make capture` works immediately.

---

## Clearance Renewal

Cloudflare clearance credentials expire periodically. When they do, the API returns `503` for all bet lookups:

```bash
make capture          # Capture + sync (skips if still valid)
make capture force=1  # Force renewal
```

`make capture` opens a Chrome window at stake.games, waits for you to complete the challenge, then POSTs credentials directly to the running app.

---

## Token Rotation

```bash
make token
```

Generates a new token pair, writes the hash to `.env` and the raw token to `scripts/sync-config.json`, then prompts you to `make restart`.

---

## Rebuilding After Package Changes

The app code is baked into the image. After editing package source:

```bash
make build   # rebuilds the image and prunes the previous dangling image
make up      # starts with the new image
```

`make up` does **not** rebuild automatically — it starts whatever image was last built. This avoids a full rebuild on every `make up` when nothing has changed.

Each `make build` replaces the previous image. Without pruning, the old image becomes a dangling `<none>:<none>` entry and accumulates on disk — `make build` handles this automatically with `docker image prune -f`.

Thanks to layer ordering in the Dockerfile, only the `composer require` step re-runs on source changes (~45–90 s). The `composer create-project` layer stays cached unless the Laravel version itself bumps.
