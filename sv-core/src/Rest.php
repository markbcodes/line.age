<?php
/**
 * Registers the plugin's REST namespace sv/v1. Skeleton only: one read-only
 * route proving the namespace is live, so tests and future app endpoints have
 * a home. No product behavior here.
 */

declare(strict_types=1);

namespace SV\App;

final class Rest
{
	public static function register(): void
	{
		add_action('rest_api_init', [self::class, 'routes']);
	}

	public static function routes(): void
	{
		register_rest_route(
			'sv/v1',
			'/status',
			[
				'methods' => 'GET',
				'callback' => [self::class, 'status'],
				'permission_callback' => 'is_user_logged_in',
			]
		);
	}

	public static function status(): array
	{
		return [
			'plugin' => 'sv-core',
			'version' => SV_VERSION,
			'environment' => wp_get_environment_type(),
		];
	}
}
