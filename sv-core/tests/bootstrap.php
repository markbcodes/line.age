<?php
/**
 * PHPUnit bootstrap: boots the local WordPress through wp-load.php so tests
 * run against the same SQLite database the site uses. The plugin is force-
 * loaded (not just activated) so tests never depend on activation state.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/wp-load.php';

// Load the plugin directly: active_plugins may not include it yet, and tests
// must not depend on option state.
require_once $root . '/wp-content/plugins/sv-core/sv-core.php';

if (! class_exists('SV\App\Templates')) {
	fwrite(STDERR, "sv-core did not load: SV\\App\\Templates missing.\n");
	exit(1);
}
