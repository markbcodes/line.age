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
 *
 * @package SV_Core
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SV_PLUGIN_FILE', __FILE__ );
define( 'SV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SV_PATH', SV_PLUGIN_DIR );
define( 'SV_VERSION', '0.1.0' );

// SV_DEBUG follows the site's WP_DEBUG unless a config defines it first.
if ( ! defined( 'SV_DEBUG' ) ) {
	define( 'SV_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

// Prefer the composer autoloader; fall back to a PSR-4 loader so the plugin
// still boots before `composer install` has run (fresh clone, before build).
// The prefixes mirror composer.json and stay longest-first: SV\App\ → src/,
// SV\Support\ → src/Support/, SV\Editor\ → src/Editor/, then SV\ → src/ for
// the few root-namespace classes such as SV\Security.
$sv_autoload = SV_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $sv_autoload ) ) {
	require_once $sv_autoload;
} else {
	spl_autoload_register(
		static function ( string $classname ): void {
			$prefixes = array(
				'SV\\App\\'     => SV_PLUGIN_DIR . 'src/',
				'SV\\Support\\' => SV_PLUGIN_DIR . 'src/Support/',
				'SV\\Editor\\'  => SV_PLUGIN_DIR . 'src/Editor/',
				'SV\\'          => SV_PLUGIN_DIR . 'src/',
			);
			foreach ( $prefixes as $prefix => $dir ) {
				if ( ! str_starts_with( $classname, $prefix ) ) {
					continue;
				}
				$relative = str_replace( '\\', '/', substr( $classname, strlen( $prefix ) ) );
				$file     = $dir . $relative . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
				return;
			}
		}
	);
}

register_activation_hook( __FILE__, array( SV\App\Activation::class, 'run' ) );

SV\App\App::register();
