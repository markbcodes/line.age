#!/usr/bin/env bash
# bin/build.sh — build sv-core and zip it for distribution.
#
#   npm ci && npm run build        esbuild -> sv-core/assets/js/editor.bundle.js
#   composer install --no-dev      in sv-core, -> vendor/autoload.php
#   assert both artifacts exist
#   zip dist/sv-core-{SV_VERSION}.zip  INCLUDES vendor/, EXCLUDES
#        client-src, node_modules, tests, .git
#   print zip size + version
#
# Run from the repository root. PHP resolution: SV_PHP env var, then `php`
# on PATH, then Shader's bundled PHP (.shader/bin/php.cmd).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# --- PHP resolution (Shader local has no `php` on PATH; F1/F2 in docs/ENVIRONMENTS.md)
# shellcheck source=bin/php-env.sh
. "$ROOT/bin/php-env.sh"
sv_resolve_php || exit 1

SV_VERSION="$($SV_PHP -r "preg_match('/Version:\s*(\S+)/', file_get_contents('sv-core/sv-core.php'), \$m); echo \$m[1] ?? '0.0.0';")"
[ -n "$SV_VERSION" ] || { echo "build.sh: could not read SV_VERSION" >&2; exit 1; }
echo "== sv-core $SV_VERSION =="

# --- JS bundle
if [ ! -f package-lock.json ]; then
  echo "-- npm install (no lockfile yet)"
  npm install
fi
echo "-- npm ci && npm run build"
npm ci
npm run build

# --- PHP autoloader
echo "-- composer install --no-dev --optimize-autoloader"
if command -v composer >/dev/null 2>&1; then
  (cd sv-core && composer install --no-dev --optimize-autoloader --no-interaction)
else
  # No composer binary: fetch the phar once into .cache/ (gitignored).
  PHAR="$ROOT/.cache/composer.phar"
  if [ ! -f "$PHAR" ]; then
    mkdir -p "$ROOT/.cache"
    curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o "$PHAR"
  fi
  (cd sv-core && $SV_PHP "$PHAR" install --no-dev --optimize-autoloader --no-interaction)
fi

# --- Assert artifacts
echo "-- assert artifacts"
test -f sv-core/assets/js/editor.bundle.js || { echo "build.sh: bundle missing" >&2; exit 1; }
test -f sv-core/vendor/autoload.php || { echo "build.sh: vendor/autoload.php missing" >&2; exit 1; }

# --- Zip (PHP ZipArchive: no zip binary in Git Bash on Windows)
mkdir -p dist
ZIPNAME="dist/sv-core-$SV_VERSION.zip"
rm -f "$ZIPNAME"
echo "-- zip $ZIPNAME"
$SV_PHP bin/make-zip.php "sv-core" "$ZIPNAME" "client-src,node_modules,tests,.git,.phpunit.cache"

# --- Report
SIZE=$(du -h "$ZIPNAME" | cut -f1)
echo "== built sv-core $SV_VERSION -> $ZIPNAME ($SIZE) =="
