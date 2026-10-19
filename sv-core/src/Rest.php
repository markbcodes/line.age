<?php
/**
 * Registers the plugin's REST namespace sv/v1. Skeleton only: a public ping
 * (health check + current user) and a read-only status route proving the
 * namespace is live, so tests and future app endpoints have a home.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * REST namespace bootstrap.
 */
final class Rest {

	/**
	 * Hooks route registration into the REST server.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Registers the sv/v1 routes.
	 */
	public static function routes(): void {
		Log::debug( 'Rest::routes' );

		register_rest_route(
			'sv/v1',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'ping' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'sv/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/** Public health check: {ok: true, user: <login|null>}. */
	public static function ping(): array {
		return array(
			'ok'   => true,
			'user' => is_user_logged_in() ? wp_get_current_user()->user_login : null,
		);
	}

	/**
	 * Authenticated plugin status.
	 *
	 * @return array<string, string>
	 */
	public static function status(): array {
		return array(
			'plugin'      => 'sv-core',
			'version'     => SV_VERSION,
			'environment' => wp_get_environment_type(),
		);
	}
}
