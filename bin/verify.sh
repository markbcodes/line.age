#!/usr/bin/env bash
# bin/verify.sh — full verification pipeline, fail-fast, nonzero exit on any
# failure. Order per the brief: phpunit -> vitest -> playwright.
#
#   WP_BASE_URL=http://line-age.localhost   bin/verify.sh   # Shader local
#   WP_BASE_URL=https://line-age.instawp.co bin/verify.sh   # InstaWP hosted
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=bin/php-env.sh
. "$ROOT/bin/php-env.sh"
sv_resolve_php || exit 1

# Load .env (WP_BASE_URL, SV_E2E_*) — never overriding variables already set
# by the caller, so `WP_BASE_URL=https://line-age.instawp.co bin/verify.sh`
# (and inline SV_E2E_PASSWORD) win over the local defaults.
if [ -f "$ROOT/.env" ]; then
  while IFS= read -r line; do
    case "$line" in ''|'#'*) continue ;; esac
    key="${line%%=*}"; val="${line#*=}"
    if [ -z "${!key:-}" ]; then
      export "$key=$val"
    fi
  done < "$ROOT/.env"
fi

echo "== 1/3 phpunit =="
if [ ! -f sv-core/vendor/bin/phpunit ]; then
  if command -v composer >/dev/null 2>&1; then
    (cd sv-core && composer install --no-interaction)
  else
    mkdir -p .cache
    [ -f .cache/composer.phar ] || curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o .cache/composer.phar
    (cd sv-core && $SV_PHP "$ROOT/.cache/composer.phar" install --no-interaction)
  fi
fi
(cd sv-core && $SV_PHP vendor/bin/phpunit)

echo "== 2/3 vitest =="
npm run test:unit

echo "== 3/3 playwright (WP_BASE_URL=${WP_BASE_URL:-http://line-age.localhost}) =="
if [ ! -f .env ] && [ -z "${SV_E2E_PASSWORD:-}" ]; then
  echo "verify: SV_E2E_PASSWORD not set and no .env found. Copy .env.example to .env." >&2
  exit 1
fi
npx playwright test

echo "== verify: OK =="
