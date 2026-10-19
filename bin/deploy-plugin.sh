#!/usr/bin/env bash
# bin/deploy-plugin.sh — build sv-core and install it on the hosted site.
#
# HOW THE TARGET IS REACHED (documented per the brief):
#   InstaWP exposes SSH for this site (dashboard -> SSH Access). The host and
#   user are in ~/.ssh/config / $SV_DEPLOY_SSH; auth is the key at
#   $SV_DEPLOY_KEY. InstaWP also has a dashboard WP-CLI terminal — if SSH ever
#   goes away, the manual fallback is printed at the end of this script.
#   wp-cli is available on the target as /usr/bin/wp (verified, see
#   docs/ENVIRONMENTS.md).
#
# Steps:
#   1. Guard FIRST: query the target's wp_get_environment_type() over SSH and
#      refuse production unless --i-know-what-im-doing is passed.
#   2. Run bin/build.sh.
#   3. scp the zip to the target's /tmp and `wp plugin install --force`.
#   4. Run `wp sv migrate` on the target (falls back to bin/sv-cli.php if the
#      wp binary is missing there) and print the result.
#
# Usage:
#   bin/deploy-plugin.sh [--i-know-what-im-doing]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# --- Load .env if present (SV_DEPLOY_* overrides)
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
fi

SV_DEPLOY_SSH="${SV_DEPLOY_SSH:-<ssh-user>@<host>}"
SV_DEPLOY_PATH="${SV_DEPLOY_PATH:-/web/line-age.instawp.co/public_html}"
SV_DEPLOY_KEY="${SV_DEPLOY_KEY:-$HOME/.ssh/instawp_rsa}"
# Host key goes to a repo-local file (.cache/, gitignored) — never ~/.ssh/known_hosts.
# accept-new pins the key after the first contact; re-provisioning the host
# makes the deploy fail loudly until .cache/known_hosts is cleared.
mkdir -p "$ROOT/.cache"
SSH_OPTS=(-i "$SV_DEPLOY_KEY" -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=15
  -o "UserKnownHostsFile=$ROOT/.cache/known_hosts" -o StrictHostKeyChecking=accept-new)

# shellcheck source=bin/php-env.sh
. "$ROOT/bin/php-env.sh"
sv_resolve_php || exit 1

OVERRIDE=""
case " $* " in *" --i-know-what-im-doing "*) OVERRIDE="--i-know-what-im-doing" ;; esac

# --- 1. Guard FIRST: never build for a production target by accident
echo "== guard: checking environment_type on $SV_DEPLOY_SSH =="
ENV_TYPE="$(ssh "${SSH_OPTS[@]}" "$SV_DEPLOY_SSH" "cd $SV_DEPLOY_PATH && wp eval 'echo wp_get_environment_type();' 2>/dev/null || echo ''" | tr -d '\r' | tail -n1)"
echo "target environment_type: '${ENV_TYPE:-<unreadable>}'"
$SV_PHP bin/deploy-guard.php "${ENV_TYPE:-}" $OVERRIDE || exit 1

# --- 2. Build
echo "== build =="
bin/build.sh

SV_VERSION="$($SV_PHP -r "preg_match('/Version:\s*(\S+)/', file_get_contents('sv-core/sv-core.php'), \$m); echo \$m[1] ?? '0.0.0';")"
ZIP="dist/sv-core-$SV_VERSION.zip"
test -f "$ZIP" || { echo "deploy: $ZIP missing after build" >&2; exit 1; }

# --- 3. Ship + install
echo "== install $ZIP on $SV_DEPLOY_SSH:$SV_DEPLOY_PATH =="
REMOTE_ZIP="/tmp/sv-core-$SV_VERSION.zip"
scp "${SSH_OPTS[@]}" "$ZIP" "$SV_DEPLOY_SSH:$REMOTE_ZIP"
ssh "${SSH_OPTS[@]}" "$SV_DEPLOY_SSH" "\
  cd '$SV_DEPLOY_PATH' && \
  wp plugin install '$REMOTE_ZIP' --force 2>&1 && \
  wp plugin activate sv-core 2>&1 && \
  rm -f '$REMOTE_ZIP'"

# --- 4. Migrate on the target (wp sv migrate, or the sv-cli fallback)
# `wp rewrite flush` runs in this same fresh process, after init has fired
# with sv-core active — otherwise activation in the previous step persisted
# rules without the /app rule (same trap as bin/sv-activate.php).
echo "== migrate on target =="
ssh "${SSH_OPTS[@]}" "$SV_DEPLOY_SSH" "\
  cd '$SV_DEPLOY_PATH' && \
  if command -v wp >/dev/null 2>&1; then wp sv migrate && wp rewrite flush; \
  else php bin/sv-cli.php migrate; fi"

echo "== deploy complete: sv-core $SV_VERSION on $SV_DEPLOY_SSH =="
echo "Manual fallback (if SSH is unavailable): run bin/build.sh, upload dist/sv-core-$SV_VERSION.zip"
echo "via the InstaWP dashboard file manager or plugin uploader, then in the dashboard WP-CLI tab:"
echo "  wp plugin install /path/to/sv-core-$SV_VERSION.zip --force && wp plugin activate sv-core && wp sv migrate"
