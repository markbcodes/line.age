<?php
/**
 * The theme/plugin boundary. The theme's functions.php calls maybe_override()
 * on template_include; this is the only place a plugin template replaces a
 * theme template, and only for the sv_app_view routes the plugin owns.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * Theme/plugin template boundary.
 */
final class Templates {

	/**
	 * Returns the plugin template for an app view, or the theme's template.
	 *
	 * @param string $template The template WordPress resolved from the theme.
	 * @return string The plugin template for an app view, otherwise $template untouched.
	 */
	public static function maybe_override( string $template ): string {
		$view = get_query_var( 'sv_app_view' );
		Log::debug( 'Templates::maybe_override', array( 'view' => is_string( $view ) ? $view : null ) );

		if ( ! is_string( $view ) || ! in_array( $view, App::VIEWS, true ) ) {
			return $template;
		}

		$plugin_template = SV_PLUGIN_DIR . 'templates/app/' . $view . '.php';
		if ( is_readable( $plugin_template ) ) {
			return $plugin_template;
		}

		// A missing plugin template falls back to the theme rather than a white screen.
		return $template;
	}
}
