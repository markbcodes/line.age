<?php
/**
 * WP-CLI surface. Thin wrapper: parses flags and delegates to Cli, which is
 * the same handler bin/sv-cli.php calls locally. Never put logic here.
 */

declare(strict_types=1);

namespace SV\App;

final class WpCli
{
	/** @param array<int,string> $args @param array<int,string> $assoc_args */
	public function migrate(array $args, array $assoc_args): void
	{
		$code = Cli::migrate();
		if ($code !== 0) {
			\WP_CLI::error('migrate failed');
		}
		\WP_CLI::success('migrations complete');
	}

	/** @param array<int,string> $args @param array<int,string> $assoc_args */
	public function jobs(array $args, array $assoc_args): void
	{
		$jobs = Cli::job_rows();
		if ($jobs === []) {
			\WP_CLI::log('Job list is empty.');
			return;
		}
		\WP_CLI\Utils\format_items('table', $jobs, ['id', 'job_key', 'status', 'created_at']);
	}

	/** @param array<int,string> $args @param array<int,string> $assoc_args */
	public function as_status(array $args, array $assoc_args): void
	{
		\WP_CLI::log(Cli::as_status_text());
	}
}
