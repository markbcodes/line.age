<?php
/**
 * Shared CLI bootstrap for the development tools in this folder.
 *
 * Boots this site's WordPress and authorizes an administrator so VX abilities
 * can run in-process. Required by tools/check.php and tools/seed-lib.php. Never
 * include this from a frontend request, theme activation hook, or ordinary
 * admin request.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "tools/ scripts can only run from the command line.\n");
	exit(1);
}

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (! is_readable($wp_load)) {
	fwrite(STDERR, "Could not find wp-load.php. Run this from a Shader site theme.\n");
	exit(1);
}

require $wp_load;

if (! function_exists('wp_get_ability')) {
	fwrite(STDERR, "WordPress abilities API is not available.\n");
	exit(1);
}

$admins = get_users(
	[
		'role'    => 'administrator',
		'number'  => 1,
		'orderby' => 'ID',
		'order'   => 'ASC',
	]
);
if ($admins === []) {
	fwrite(STDERR, "No administrator user is available to authorize VX abilities.\n");
	exit(1);
}

wp_set_current_user((int) $admins[0]->ID);
