#!/bin/sh
set -e

# ── Validate required runtime secrets ─────────────────────────────────────────
# Neither APP_KEY nor STAKE_ADMIN_TOKEN should be baked into the image.
# They must be injected via environment variables or a secrets manager at runtime.

if [ -z "$APP_KEY" ]; then
    echo ""
    echo "ERROR: APP_KEY is required."
    echo ""
    echo "Generate one with:"
    echo "  docker run --rm --entrypoint php verifierform-stake-bet-lookup:standalone artisan key:generate --show"
    echo ""
    exit 1
fi

if [ -z "$STAKE_ADMIN_TOKEN" ]; then
    echo ""
    echo "ERROR: STAKE_ADMIN_TOKEN is required (SHA-256 hash of the raw admin bearer token)."
    echo ""
    echo "Generate a token pair with:"
    echo "  RAW=\$(openssl rand -hex 32)"
    echo "  echo \"Raw token : \$RAW\""
    echo "  echo \"Hash (STAKE_ADMIN_TOKEN): \$(echo -n \$RAW | sha256sum | awk '{print \$1}')\""
    echo ""
    exit 1
fi

# Ensure writable dirs are owned by the php-fpm user regardless of prior state
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

exec "$@"
