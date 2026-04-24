SHELL := /bin/zsh
DIV   := ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

.PHONY: setup reinstall reset up down restart shell migrate logs capture token test coverage db adminer

## Steps 1–2, 4–7 from README.md (run once)
setup:
	@if [ ! -d "app" ]; then \
		echo ""; \
		echo "$(DIV)"; \
		echo "  Stake Bet Lookup — Local Dev Setup"; \
		echo "  Following README.md"; \
		echo "$(DIV)"; \
		echo ""; \
		\
		echo "Step 1 — Install the Package"; \
		composer create-project laravel/laravel:^12.0 app --prefer-dist --no-install --no-scripts --quiet; \
		cp .env.example app/.env; \
		(cd app && composer config platform.php 8.2 \
			&& composer config repositories.bet-lookup '{"type":"path","url":".."}' \
			&& composer require stake/bet-lookup --quiet); \
		echo "<?php" > app/routes/web.php; \
		rm -rf app/resources/js app/resources/css app/resources/views app/tests; \
		rm -f app/package.json app/vite.config.js app/.editorconfig; \
		(cd app && php artisan key:generate --ansi && php artisan package:discover --quiet); \
		echo "  ✓"; \
		echo ""; \
		\
		echo "Step 2 — Publish Config and Migrations"; \
		(cd app && php artisan vendor:publish --tag=bet-lookup-config --quiet \
			&& php artisan vendor:publish --tag=bet-lookup-migrations --quiet); \
		echo "  ✓ config/bet-lookup.php and database/migrations/ published"; \
		echo ""; \
		\
		echo "Step 3 — Run Migrations"; \
		echo "  (skipped here — run 'make up' then 'make migrate')"; \
		echo ""; \
		\
		echo "Step 4 — Configure Environment Variables"; \
		echo "  ✓ .env.example copied to app/.env — defaults are set for local Docker"; \
		echo ""; \
		\
		echo "Step 5 — Generate an Admin Token"; \
		TOKEN=$$(openssl rand -hex 32); \
		HASH=$$(php -r "echo hash('sha256', '$$TOKEN');"); \
		sed -i.bak "s|^STAKE_ADMIN_TOKEN=.*|STAKE_ADMIN_TOKEN=$$HASH|" app/.env && rm app/.env.bak; \
		echo "  Raw   → app/stake-clearance/sync-config.json (written in step 7)"; \
		echo "  Hash  → app/.env STAKE_ADMIN_TOKEN"; \
		echo "  ✓"; \
		echo ""; \
		\
		echo "Step 6 — Configure the Mail Driver"; \
		echo "  ✓ Mailpit catches all local email — no config needed (http://localhost:8025)"; \
		echo ""; \
		\
		echo "Step 7 — Publish and Install the Capture Scripts"; \
		(cd app && php artisan vendor:publish --tag=bet-lookup-scripts --quiet); \
		printf '{\n  "api": {\n    "endpoint": "http://localhost:8080/api/admin/update-clearance",\n    "token": "%s"\n  }\n}\n' "$$TOKEN" > app/stake-clearance/sync-config.json; \
		(cd app/stake-clearance && npm install --silent); \
		(cd app && php artisan vendor:publish --tag=bet-lookup-bruno --quiet); \
		echo "  ✓ scripts published to app/stake-clearance/"; \
		echo "  ✓ Bruno collection published to app/stake-bruno/"; \
		echo ""; \
		\
		echo "$(DIV)"; \
		echo "  Setup complete!"; \
		echo ""; \
		echo "  Next:"; \
		echo "    make up       — start Docker services"; \
		echo "    make migrate  — Step 3: Run Migrations"; \
		echo "    make capture  — Step 8: Capture Initial Clearance Credentials"; \
		echo "$(DIV)"; \
		echo ""; \
	else \
		echo "app/ already exists — run 'make reinstall' to start from scratch."; \
	fi

## Wipe app/ and re-run setup from scratch
reinstall:
	-chmod -RN app 2>/dev/null
	rm -rf app
	$(MAKE) setup

## Wipe all Docker volumes (fresh DB + vendor) — use when migrations are stale
reset:
	docker compose down -v --remove-orphans

## Start all services
up:
	docker compose up -d --build
	@echo ""
	@echo "  App:     http://localhost:8080"
	@echo "  Mailpit: http://localhost:8025"
	@echo ""
	@echo "  First time? Run: make migrate"
	@echo ""

## Stop all services
down:
	docker compose --profile tools down --remove-orphans

## Restart app container
restart:
	docker compose restart app

## Open a shell in the app container
shell:
	docker compose exec app bash

## Step 3 — Run Migrations
migrate:
	@echo ""
	@echo "$(DIV)"
	@echo "  Step 3 — Run Migrations"
	@echo "$(DIV)"
	@if [ -z "$$(docker compose ps -q app 2>/dev/null)" ]; then \
		echo "  ✗ App container is not running — run 'make up' first"; \
		echo ""; \
		exit 1; \
	fi
	docker compose exec app php artisan migrate
	@echo ""

## Run package test suite
test:
	composer update --quiet && composer test

## Run package test suite with coverage (requires Xdebug or PCOV)
coverage:
	@if php -m | grep -qiE "xdebug|pcov"; then \
		composer update --quiet && composer coverage; \
	else \
		echo ""; \
		echo "  ✗ No coverage driver found."; \
		echo "  Install PCOV (lightweight) or Xdebug:"; \
		echo "    pecl install pcov"; \
		echo ""; \
	fi

## Tail app logs
logs:
	docker compose logs -f app

## Open a MySQL shell
db:
	docker compose exec mysql mysql -u stake -psecret stake_app

## Start Adminer database UI (http://localhost:8090)
adminer:
	docker compose --profile tools up -d adminer
	@echo "Adminer: http://localhost:8090  |  Server: mysql  |  User: stake  |  Pass: secret"

## Step 8 — Capture Initial Clearance Credentials
capture:
	@echo ""
	@echo "$(DIV)"
	@echo "  Step 8 — Capture Initial Clearance Credentials"
	@echo "$(DIV)"
	@VALID=$$(docker compose exec -T app php artisan stake:check-clearance 2>&1 | grep -c "Active"); \
	if [ "$$VALID" != "0" ] && [ "$(force)" != "1" ]; then \
		echo "  Clearance still valid — skipping."; \
		echo "  Run 'make capture force=1' to force renewal."; \
		echo ""; \
	else \
		echo "  → Opening browser at https://stake.games"; \
		echo "  → Complete the Cloudflare challenge"; \
		echo ""; \
		(cd app/stake-clearance && npm run capture); \
	fi

## Admin Token Rotation
token:
	@if [ ! -f "app/.env" ]; then echo "Run make setup first"; exit 1; fi
	@echo ""
	@echo "$(DIV)"
	@echo "  Admin Token Rotation"
	@echo "$(DIV)"
	@TOKEN=$$(openssl rand -hex 32); \
	HASH=$$(php -r "echo hash('sha256', '$$TOKEN');"); \
	sed -i.bak "s|^STAKE_ADMIN_TOKEN=.*|STAKE_ADMIN_TOKEN=$$HASH|" app/.env && rm app/.env.bak; \
	echo "  Raw token → update app/stake-clearance/sync-config.json:"; \
	echo "    $$TOKEN"; \
	echo ""; \
	echo "  Hash → written to app/.env as STAKE_ADMIN_TOKEN"; \
	echo ""; \
	echo "  Run 'make restart' to apply."
	@echo ""
