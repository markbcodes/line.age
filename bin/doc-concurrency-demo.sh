#!/usr/bin/env bash
# bin/doc-concurrency-demo.sh — the optimistic-concurrency proof for the
# document store. Two saves from the same base_hash must produce exactly one
# 201 and one 409, and the losing save must write no row.
#
#   bash bin/doc-concurrency-demo.sh
#
# Run it locally (Shader, behind the six-worker PHP server) and on InstaWP, and
# paste both outputs. Requires curl. PHP is resolved through bin/php-env.sh
# (F1: no `php` on PATH on the Shader local).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=bin/php-env.sh
. "$ROOT/bin/php-env.sh"
sv_resolve_php || exit 1

if ! command -v curl >/dev/null 2>&1; then
  echo "doc-demo: curl is required." >&2
  exit 1
fi

SETUP="$ROOT/bin/.doc-concurrency-setup.php"
TMP="$(mktemp -d)"
cleanup() { rm -rf "$TMP"; rm -f "$SETUP"; }
trap cleanup EXIT

cat > "$SETUP" <<'PHP'
<?php
/**
 * Setup/teardown for bin/doc-concurrency-demo.sh. Bootstraps WordPress, runs
 * migrations, creates a document, and mints a one-shot application password
 * for the demo user. Prints four lines: doc id, sv/v1 REST base, login,
 * password. `… cleanup <doc_id>` deletes the document, its versions, and the
 * demo application password.
 *
 * @package SV_Core
 */

declare(strict_types=1);

define('WP_USE_THEMES', false);
require_once dirname(__DIR__) . '/wp-load.php';

$mode   = $argv[1] ?? '';
$doc_id = (int) ($argv[2] ?? 0);

if (! class_exists('SV\App\Migrations') || ! class_exists('SV\App\Documents')) {
	fwrite(STDERR, "sv-core is not available.\n");
	exit(1);
}

global $wpdb;

$user = get_user_by('login', 'admin');
if (! $user) {
	$user = get_user_by('id', 1);
}
if (! $user) {
	fwrite(STDERR, "no administrator user found.\n");
	exit(1);
}

/**
 * Delete the demo application password, if any.
 */
function sv_demo_forget_password(int $user_id): void
{
	foreach ((array) \WP_Application_Passwords::get_user_application_passwords($user_id) as $item) {
		if (('sv-doc-demo' === ($item['name'] ?? '')) && isset($item['uuid'])) {
			\WP_Application_Passwords::delete_application_password($user_id, $item['uuid']);
		}
	}
}

if ('cleanup' === $mode) {
	if ($doc_id > 0) {
		$wpdb->delete($wpdb->prefix . 'sv_doc_versions', array('document_id' => $doc_id), array('%d'));
		$wpdb->delete($wpdb->prefix . 'sv_documents', array('id' => $doc_id), array('%d'));
		$wpdb->delete($wpdb->prefix . 'sv_audit_log', array('entity' => 'document:' . $doc_id), array('%s'));
	}
	sv_demo_forget_password((int) $user->ID);
	exit(0);
}

\SV\App\Migrations::run();
if ('' !== \SV\App\Migrations::$error) {
	fwrite(STDERR, 'migrations failed: ' . \SV\App\Migrations::$error . "\n");
	exit(1);
}

$wpdb->insert(
	$wpdb->prefix . 'sv_documents',
	array(
		'scene_id'   => 0,
		'format'     => 'tiptap',
		'created_at' => current_time('mysql', true),
	),
	array('%d', '%s', '%s')
);
$document_id = (int) $wpdb->insert_id;

sv_demo_forget_password((int) $user->ID);
$created = \WP_Application_Passwords::create_new_application_password(
	(int) $user->ID,
	array('name' => 'sv-doc-demo')
);
if (is_wp_error($created)) {
	fwrite(STDERR, 'application password failed: ' . $created->get_error_message() . "\n");
	exit(1);
}

echo $document_id . "\n";
echo rest_url('sv/v1') . "\n";
echo $user->user_login . "\n";
echo $created[0] . "\n";
PHP

echo "== doc-concurrency-demo =="
setup_out="$($SV_PHP "$SETUP")"
DOC_ID="$(printf '%s\n' "$setup_out" | sed -n '1p')"
API="$(printf '%s\n' "$setup_out" | sed -n '2p')"
DEMO_USER="$(printf '%s\n' "$setup_out" | sed -n '3p')"
DEMO_PASS="$(printf '%s\n' "$setup_out" | sed -n '4p')"

if [ -z "$DOC_ID" ] || [ -z "$API" ] || [ -z "$DEMO_PASS" ]; then
  echo "doc-demo: setup failed: $setup_out" >&2
  exit 1
fi

echo "document: $DOC_ID"
echo "endpoint: $API/documents/$DOC_ID/versions"
echo

# A minimal valid Tiptap document as content_json, with a JSON base_hash.
doc_body() {
  printf '{"content_json":{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"%s"}]}]},"schema_version":1,"base_hash":%s}' "$1" "$2"
}

save() { # $1 = output prefix, $2 = JSON body
  curl -sS -o "$1.json" -w '%{http_code}' \
    -u "$DEMO_USER:$DEMO_PASS" \
    -H 'Content-Type: application/json' \
    -X POST "$API/documents/$DOC_ID/versions" \
    --data "$2" > "$1.code"
}

echo "--- save v1 (base_hash = null) ---"
save "$TMP/v1" "$(doc_body 'Draft one.' null)"
echo "HTTP $(cat "$TMP/v1.code")"
cat "$TMP/v1.json"
echo
echo

HASH="$(sed -n 's/.*"content_hash":"\([^"]*\)".*/\1/p' "$TMP/v1.json")"
if [ -z "$HASH" ]; then
  echo "doc-demo: could not read content_hash from v1; aborting." >&2
  exit 1
fi
echo "current hash: $HASH"
echo

echo "--- two concurrent saves from the same base_hash ---"
save "$TMP/a" "$(doc_body 'Writer one.' "\"$HASH\"")" &
save "$TMP/b" "$(doc_body 'Writer two.' "\"$HASH\"")" &
wait

echo "response A: HTTP $(cat "$TMP/a.code")"
cat "$TMP/a.json"
echo
echo "response B: HTTP $(cat "$TMP/b.code")"
cat "$TMP/b.json"
echo
echo

CODE_A="$(cat "$TMP/a.code")"
CODE_B="$(cat "$TMP/b.code")"
RESULT=0
if { [ "$CODE_A" = '201' ] && [ "$CODE_B" = '409' ]; } || { [ "$CODE_A" = '409' ] && [ "$CODE_B" = '201' ]; }; then
  RESULT=1
  echo "PASS: exactly one 201 and one 409."
else
  echo "FAIL: expected one 201 and one 409, got $CODE_A and $CODE_B." >&2
fi

# Tear the demo fixtures back down.
$SV_PHP "$SETUP" cleanup "$DOC_ID" >/dev/null 2>&1 || true

[ "$RESULT" = 1 ]
