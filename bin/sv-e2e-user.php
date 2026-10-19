<?php
/**
 * bin/sv-e2e-user.php — creates/updates the dedicated Playwright account
 * (default sv-e2e, administrator) and prints its password once.
 * Idempotent: resets the password on every run so .env always matches.
 * Passwords are alphanumeric so .env stays safe to `source` (see below).
 *
 *   php bin/sv-e2e-user.php [username] [email]
 *
 * Works on any environment where wp-load.php is reachable — including over
 * SSH on the hosted site (its wp-cli can run it as: wp eval-file ... no:
 * plain `php bin/sv-e2e-user.php` after copying, or via wp user generate
 * password — see docs/ENVIRONMENTS.md).
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	exit(1);
}

$username = $argv[1] ?? 'sv-e2e';
$email = $argv[2] ?? 'sv-e2e@example.test';

$root = dirname(__DIR__);
define('WP_USE_THEMES', false);
require_once $root . '/wp-load.php';

// Alphanumeric only, on purpose: .env is sourced by the documented hosted run
// (`set -a; . ./.env; set +a`), so a password containing a backtick, a space or
// a quote would break the shell before it ever reaches wp-login.php. 24 chars
// of [A-Za-z0-9] is ~143 bits.
$password = wp_generate_password(24, false);

$user = get_user_by('login', $username);
if ($user) {
	wp_set_password($password, $user->ID);
	$user_id = (int) $user->ID;
	echo "updated existing user #{$user_id}\n";
} else {
	$user_id = (int) wp_insert_user([
		'user_login' => $username,
		'user_email' => $email,
		'user_pass' => $password,
		'role' => 'administrator',
		'display_name' => 'SV E2E',
	]);
	if (is_wp_error($user_id)) {
		fwrite(STDERR, 'create failed: ' . $user_id->get_error_message() . "\n");
		exit(1);
	}
	echo "created administrator #{$user_id}\n";
}

echo "SV_E2E_USER={$username}\n";
echo "SV_E2E_PASSWORD={$password}\n";
