SHELL := /bin/zsh
DIV   := ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

.PHONY: setup up down restart shell migrate logs capture token test coverage db adminer reset _generate-token

## First-time setup: copy .env, build image, generate secrets, install capture deps
setup:
	@echo ""
	@echo "$(DIV)"
	@echo "  Stake Bet Lookup — Setup"
	@echo "$(DIV)"
	@echo ""
	@if [ ! -f ".env" ]; then \
		cp .env.example .env; \
		echo "  → .env created from .env.example"; \
	fi
	@echo "  → Building image (this takes a minute on first run)..."
	@docker build -t stake-bet-lookup:local . --progress=plain
	@APP_KEY_VAL=$$(grep "^APP_KEY=" .env | cut -d'=' -f2-); \
	if [ -z "$$APP_KEY_VAL" ]; then \
		KEY=$$(docker run --rm --entrypoint php stake-bet-lookup:local artisan key:generate --show); \
		{ tmp=$$(mktemp); sed "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env > "$$tmp" && mv "$$tmp" .env; }; \
		echo "  ✓ APP_KEY generated"; \
	else \
		echo "  ✓ APP_KEY already set"; \
	fi
	@ADMIN_VAL=$$(grep "^STAKE_ADMIN_TOKEN=" .env | cut -d'=' -f2-); \
	if [ -z "$$ADMIN_VAL" ]; then \
		$(MAKE) -s _generate-token; \
	else \
		echo "  ✓ STAKE_ADMIN_TOKEN already set"; \
	fi
	@if [ ! -d "scripts/node_modules" ]; then \
		(cd scripts && npm install --silent); \
		echo "  ✓ Capture script deps installed"; \
	fi
	@echo ""
	@echo "$(DIV)"
	@echo "  Setup complete!"
	@echo ""
	@echo "  Next:"
	@echo "    make up       — start all services"
	@echo "    make migrate  — run database migrations (first time)"
	@echo "    make capture  — capture Cloudflare clearance credentials"
	@echo "$(DIV)"
	@echo ""

## Start all services (use 'make setup' or 'docker compose build' to rebuild)
up:
	docker compose up -d
	@echo ""
	@echo "  App:     http://localhost:$${PORT:-8080}"
	@echo "  Mailpit: http://localhost:8025"
	@echo ""
	@echo "  First time? Run: make migrate"
	@echo ""

## Stop all services
down:
	docker compose --profile tools down -v --remove-orphans

## Restart the app container
restart:
	docker compose restart app

## Open a shell in the app container
shell:
	docker compose exec app bash

## Run database migrations
migrate:
	@echo ""
	@if [ -z "$$(docker compose ps -q app 2>/dev/null)" ]; then \
		echo "  ✗ App container is not running — run 'make up' first"; \
		echo ""; \
		exit 1; \
	fi
	docker compose exec app php artisan migrate --force
	@echo ""

## Tail app container logs
logs:
	docker compose logs -f app

## Capture Cloudflare clearance credentials and sync to the running app
capture:
	@echo ""
	@echo "$(DIV)"
	@echo "  Capture Clearance Credentials"
	@echo "$(DIV)"
	@VALID=$$(docker compose exec -T app php artisan stake:check-clearance 2>&1 | grep -c "^Probe: Active"); \
	if [ "$$VALID" != "0" ] && [ "$(force)" != "1" ]; then \
		echo "  Clearance still valid — skipping."; \
		echo "  Run 'make capture force=1' to force renewal."; \
		echo ""; \
	else \
		echo "  → Opening browser at https://stake.games"; \
		echo "  → Complete the Cloudflare challenge"; \
		echo ""; \
		(cd scripts && npm run capture); \
	fi

## Rotate the admin token
token:
	@if [ ! -f ".env" ]; then echo "Run make setup first"; exit 1; fi
	@echo ""
	@echo "$(DIV)"
	@echo "  Admin Token Rotation"
	@echo "$(DIV)"
	@$(MAKE) -s _generate-token
	@echo ""
	@echo "  Run 'make restart' to apply."
	@echo ""

# Internal: generate a fresh STAKE_ADMIN_TOKEN pair and write to .env + sync-config.json
_generate-token:
	@RAW=$$(openssl rand -hex 32); \
	HASH=$$(printf '%s' "$$RAW" | openssl dgst -sha256 | awk '{print $$NF}'); \
	{ tmp=$$(mktemp); sed "s|^STAKE_ADMIN_TOKEN=.*|STAKE_ADMIN_TOKEN=$$HASH|" .env > "$$tmp" && mv "$$tmp" .env; }; \
	printf '{\n  "method": "api",\n  "api": {\n    "endpoint": "http://localhost:%s/api/admin/update-clearance",\n    "token": "%s"\n  }\n}\n' "$${PORT:-8080}" "$$RAW" > scripts/sync-config.json; \
	echo "  ✓ STAKE_ADMIN_TOKEN generated"; \
	echo "  ✓ scripts/sync-config.json written"

## Wipe all Docker volumes (fresh database)
reset:
	docker compose down -v --remove-orphans
	@echo "Volumes cleared. Run 'make up && make migrate' to restart."

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

## Open a MySQL shell
db:
	docker compose exec db mysql -u stake -psecret stake_app

## Start Adminer database UI (http://localhost:8090)
adminer:
	docker compose --profile tools up -d adminer
	@echo "Adminer: http://localhost:8090  |  Server: db  |  User: stake  |  Pass: secret"
