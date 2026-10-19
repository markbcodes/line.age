#!/usr/bin/env bash
# Spike V3 — the config round trip, through the real WP-CLI commands.
#
#   export --all            -> a ZIP bundle of every object
#   rm fieldsets/sv_scene.json  -> the fieldset is gone from the model
#   import <zip> --plan     -> the plan, without applying anything
#   import <zip>            -> applies it
#   config status --strict  -> exit 0
#   export fieldsets sv_scene --write  -> the file comes back
#   config reset fieldsets sv_scene    -> back to the code definition
#
# The schema for sv_scene is dumped before and after and diffed. Whatever
# happens, the trap restores the fieldset file and the model state, so the
# working tree is left exactly as it was found.
#
# Run from the repo root:  bash spike/v3-config-roundtrip.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=bin/php-env.sh
. "$ROOT/bin/php-env.sh"
sv_resolve_php || exit 1

WPCLI="$ROOT/.cache/wp-cli.phar"
if [ ! -f "$WPCLI" ]; then
  echo "spike: $WPCLI not found. Run bin/build.sh first." >&2
  exit 1
fi

FIELD="$ROOT/wp-content/themes/line-age/vx-config/fieldsets/sv_scene.json"
WORK="$(mktemp -d)"
BUNDLE="$WORK/bundle.zip"
BACKUP="$WORK/sv_scene.json"
cp "$FIELD" "$BACKUP"

wp() { $SV_PHP "$WPCLI" --user=1 "$@"; }

restore() {
  if [ ! -f "$FIELD" ]; then
    cp "$BACKUP" "$FIELD"
  fi
  # Rows left in the database win over the file; drop them so the file is the
  # model again.
  wp vx-framework config reset fieldsets sv_scene >/dev/null 2>&1 || true
  cp "$BACKUP" "$FIELD"
  rm -rf "$WORK"
}
trap restore EXIT

echo "== V3: config round trip =="
echo "fieldset file: $FIELD"
echo "bundle:        $BUNDLE"
echo

echo "-- 1. export --all --stdout --"
wp vx-framework config export --all --stdout > "$BUNDLE"
echo "   wrote $(wc -c < "$BUNDLE" | tr -d ' ') bytes"

echo
echo "-- 2. schema for sv_scene, before --"
$SV_PHP "$ROOT/spike/v3-schema-dump.php" > "$WORK/before.json"
cat "$WORK/before.json"

echo
echo "-- 3. delete the fieldset file --"
rm "$FIELD"
echo "   removed $FIELD"
echo "   config status --strict:"
if wp vx-framework config status --strict; then
  echo "   exit=0 (the deleted fieldset is not, by itself, a problem)"
else
  echo "   exit=$?"
fi

echo
echo "-- 4. import <bundle> --plan --"
wp vx-framework config import "$BUNDLE" --plan

echo
echo "-- 5. import <bundle> --yes --"
wp vx-framework config import "$BUNDLE" --yes

echo
echo "-- 6. config status --strict --"
wp vx-framework config status --strict
echo "   exit=0"

echo
echo "-- 7. schema for sv_scene, after --"
$SV_PHP "$ROOT/spike/v3-schema-dump.php" > "$WORK/after.json"
cat "$WORK/after.json"

echo
echo "-- 8. diff of the sv_scene schema entry, before vs after --"
if diff -u "$WORK/before.json" "$WORK/after.json"; then
  echo "   identical: the round trip preserved version, exposure and the field list"
else
  echo "   DIFFERENT (see the diff above)"
  exit 1
fi

echo
echo "-- 9. return the fieldset to code --"
wp vx-framework config export fieldsets sv_scene --write
wp vx-framework config reset fieldsets sv_scene
echo "   config status:"
wp vx-framework config status --strict
echo "   exit=0"

echo
echo "-- 10. the committed file is byte-identical --"
if diff -q "$BACKUP" "$FIELD"; then
  echo "   unchanged: $(wc -c < "$FIELD" | tr -d ' ') bytes"
else
  echo "   CHANGED (the round trip rewrote it)"
  exit 1
fi

echo
echo "== conclusion =="
echo "An exported fieldset reimports to the same definition, and the committed file comes back unchanged."
