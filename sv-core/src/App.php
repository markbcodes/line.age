<?php
/**
 * Plugin bootstrap: rewrites, query vars, login gate, asset loading.
 * Every hook the plugin adds is registered here so the surface area is one file.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Security;
use SV\Support\Debug;
use SV\Support\Log;

/**
 * Plugin bootstrap.
 */
final class App {

	/** Views the plugin owns, keyed by the sv_app_view query var value. */
	public const VIEWS = array( 'dashboard', 'book', 'scene' );

	/**
	 * Registers every hook the plugin owns.
	 */
	public static function register(): void {
		Log::debug( 'App::register' );

		add_action( 'init', array( self::class, 'rewrite_rules' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'require_login' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		Maintenance::register();
		Rest::register();
		Security::attach();
		Debug::attach();

		// WP-CLI exists on the hosted site; bin/sv-cli.php is the local
		// equivalent and calls the same SV\App\Cli handlers. See docs/CLI.md.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action(
				'cli_init',
				static function (): void {
					\WP_CLI::add_command( 'sv', 'SV\App\WpCli' );
					// WP-CLI registers class methods under their literal name
					// ('as_status'); this alias keeps the dashed spelling from
					// docs/CLI.md working on both entry points.
					\WP_CLI::add_command( 'sv as-status', array( new WpCli(), 'as_status' ) );
				}
			);
		}
	}

	/** /app/ is the dashboard: the one URL users type. */
	public static function rewrite_rules(): void {
		Log::debug( 'App::rewrite_rules' );

		add_rewrite_rule( '^app/?$', 'index.php?sv_app_view=dashboard', 'top' );
		add_rewrite_tag( '%sv_app_view%', '(' . implode( '|', self::VIEWS ) . ')' );
	}

	/**
	 * Adds sv_app_view to the public query vars.
	 *
	 * @param array<int, string> $vars Registered public query vars.
	 * @return array<int, string> The same list with sv_app_view appended.
	 */
	public static function query_vars( array $vars ): array {
		Log::debug( 'App::query_vars' );

		$vars[] = 'sv_app_view';

		return $vars;
	}

	/** The app is for logged-in users; anonymous hits go to wp-login.php. */
	public static function require_login(): void {
		$view = get_query_var( 'sv_app_view' );
		if ( is_string( $view ) && '' !== $view ) {
			Log::debug( 'App::require_login', array( 'view' => $view ) );

			if ( ! is_user_logged_in() ) {
				auth_redirect();
			}
		}
	}

	/**
	 * Loads the app asset bundle on app views.
	 */
	public static function enqueue_assets(): void {
		$view = get_query_var( 'sv_app_view' );
		if ( ! is_string( $view ) || '' === $view ) {
			return;
		}

		Log::debug( 'App::enqueue_assets', array( 'view' => $view ) );

		$bundle = SV_PLUGIN_DIR . 'assets/js/editor.bundle.js';
		if ( ! is_readable( $bundle ) ) {
			return;
		}

		wp_enqueue_script(
			'sv-core-editor',
			SV_PLUGIN_URL . 'assets/js/editor.bundle.js',
			array(),
			(string) filemtime( $bundle ),
			true
		);
	}
}
