<?php
/**
 * Activation hook. init has not fired when the plugin is activated, so the
 * app rewrite rules are added explicitly before the flush — the same sequence
 * bin/sv-activate.php performs for environments without wp-cli.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * Activation hook.
 */
final class Activation {

	/**
	 * Adds the app rewrite rules and flushes them.
	 */
	public static function run(): void {
		Log::debug( 'Activation::run' );

		App::rewrite_rules();
		flush_rewrite_rules( false );
	}
}
