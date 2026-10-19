<?php
/**
 * Plugin Name:       SV Core
 * Description:       App logic, app templates and built assets for the line.age site. The theme owns chrome and steward screens; this plugin owns the app.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Shader
 * License:           GPL-2.0-or-later
 * Text Domain:       sv-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('SV_PLUGIN_FILE', __FILE__);
define('SV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SV_VERSION', '0.1.0');

// Prefer the composer autoloader; fall back to a PSR-4 loader so the plugin
// still boots before `composer install` has run (fresh clone, before build).
// PSR-4 prefix is SV\App\ → src/ (matches composer.json).
$sv_autoload = SV_PLUGIN_DIR . 'vendor/autoload.php';
if (is_readable($sv_autoload)) {
	require_once $sv_autoload;
} else {
	spl_autoload_register(
		static function (string $class): void {
			$prefix = 'SV\\App\\';
			if (! str_starts_with($class, $prefix)) {
				return;
			}
			$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
			$file = SV_PLUGIN_DIR . 'src/' . $relative . '.php';
			if (is_readable($file)) {
				require_once $file;
			}
		}
	);
}

SV\App\App::register();
