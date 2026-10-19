<?php
/**
 * WP-CLI surface. Thin wrapper: parses flags and delegates to Cli, which is
 * the same handler bin/sv-cli.php calls locally. Never put logic here.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * WP-CLI command surface.
 */
final class WpCli {

	/**
	 * Runs pending migrations.
	 *
	 * @param array<int,string> $args       Positional arguments (unused).
	 * @param array<int,string> $assoc_args Associative arguments (unused).
	 */
	public function migrate( array $args, array $assoc_args ): void {
		Log::debug( 'WpCli::migrate' );

		$code = Cli::migrate();
		if ( 0 !== $code ) {
			\WP_CLI::error( 'migrate failed' );
		}
		\WP_CLI::success( 'migrations complete' );
	}

	/**
	 * Lists the job table.
	 *
	 * @param array<int,string> $args       Positional arguments (unused).
	 * @param array<int,string> $assoc_args Associative arguments (unused).
	 */
	public function jobs( array $args, array $assoc_args ): void {
		Log::debug( 'WpCli::jobs' );

		$jobs = Cli::job_rows();
		if ( array() === $jobs ) {
			\WP_CLI::log( 'Job list is empty.' );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $jobs, array( 'id', 'job_key', 'status', 'created_at' ) );
	}

	/**
	 * Reports Action Scheduler state.
	 *
	 * @param array<int,string> $args       Positional arguments (unused).
	 * @param array<int,string> $assoc_args Associative arguments (unused).
	 */
	public function as_status( array $args, array $assoc_args ): void {
		Log::debug( 'WpCli::as_status' );

		\WP_CLI::log( Cli::as_status_text() );
	}
}
