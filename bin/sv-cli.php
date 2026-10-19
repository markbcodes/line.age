<?php
/**
 * php bin/sv-cli.php <command> [--flags]
 *
 * Local CLI fallback for hosts without wp-cli (the Shader local has no `wp`
 * binary; F1=no in docs/ENVIRONMENTS.md). Bootstraps wp-load.php and
 * dispatches to the SAME SV\App\Cli handlers WP-CLI uses, so behavior is
 * identical on both sides. Every `wp sv ...` command must exist here too.
 *
 * Usage:
 *   php bin/sv-cli.php migrate
 *   php bin/sv-cli.php jobs
 *   php bin/sv-cli.php as-status
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "sv-cli.php is CLI-only.\n");
	exit(1);
}

// bin/ lives at the repository root, one level above wp-load.php.
$root = dirname(__DIR__);
if (! is_readable($root . '/wp-load.php')) {
	fwrite(STDERR, "Cannot find wp-load.php at {$root}. Run from the repository root.\n");
	exit(1);
}

define('WP_USE_THEMES', false);
require_once $root . '/wp-load.php';

if (! class_exists('SV\App\Cli')) {
	fwrite(STDERR, "sv-core is not available. Activate it first.\n");
	exit(1);
}

$command = (string) ($argv[1] ?? '');
$args = array_slice($argv, 2);

exit(\SV\App\Cli::run($command, $args));
