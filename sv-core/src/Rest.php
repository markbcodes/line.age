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

		self::document_routes();
	}

	/**
	 * The document-version routes. Registered together so the whole manuscript
	 * surface is visible in one place; every handler guards through
	 * SV\Security first.
	 */
	private static function document_routes(): void {
		$id_arg = array( 'id' => array( 'sanitize_callback' => 'absint' ) );

		register_rest_route(
			'sv/v1',
			'/documents/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( Documents::class, 'latest' ),
				'permission_callback' => array( Documents::class, 'permission' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			'sv/v1',
			'/documents/(?P<id>\d+)/versions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( Documents::class, 'versions' ),
					'permission_callback' => array( Documents::class, 'permission' ),
					'args'                => array_merge(
						$id_arg,
						array(
							'page'     => array( 'sanitize_callback' => 'absint' ),
							'per_page' => array( 'sanitize_callback' => 'absint' ),
						)
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( Documents::class, 'append' ),
					'permission_callback' => array( Documents::class, 'permission' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			'sv/v1',
			'/documents/(?P<id>\d+)/versions/(?P<n>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( Documents::class, 'version' ),
				'permission_callback' => array( Documents::class, 'permission' ),
				'args'                => array_merge( $id_arg, array( 'n' => array( 'sanitize_callback' => 'absint' ) ) ),
			)
		);

		register_rest_route(
			'sv/v1',
			'/documents/(?P<id>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( Documents::class, 'restore' ),
				'permission_callback' => array( Documents::class, 'permission' ),
				'args'                => array_merge( $id_arg, array( 'version' => array( 'sanitize_callback' => 'absint' ) ) ),
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
