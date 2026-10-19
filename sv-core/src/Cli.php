<?php
/**
 * Shared command handlers. Every command exists twice: once here (used by
 * WP-CLI via WpCli on the hosted site, and by bin/sv-cli.php locally when no
 * wp binary exists) and once as the Maintenance admin screen. A new `wp sv`
 * subcommand MUST be added to WpCli and bin/sv-cli.php in the same commit —
 * see docs/OWNERSHIP.md.
 */

declare(strict_types=1);

namespace SV\App;

final class Cli
{
	/** @param array<string> $args */
	public static function run(string $command, array $args = []): int
	{
		return match ($command) {
			'migrate' => self::migrate(),
			'jobs' => self::jobs(),
			// WP-CLI registers the method name verbatim ('as_status'), so both
			// spellings must land on the same handler — see docs/CLI.md.
			'as-status', 'as_status' => self::as_status(),
			default => self::usage($command),
		};
	}

	public static function migrate(): int
	{
		$ran = Migrations::run();
		foreach ($ran as $name) {
			echo "Applied: {$name}\n";
		}
		// A failed migration is never recorded, so it is retried next run.
		if (Migrations::$error !== '') {
			echo Migrations::$error . "\n";
			return 1;
		}
		if ($ran === []) {
			echo "No migrations to run.\n";
			return 0;
		}
		echo count($ran) . " migration(s) applied.\n";
		return 0;
	}

	public static function jobs(): int
	{
		$jobs = self::job_rows();
		if ($jobs === []) {
			echo "Job list is empty.\n";
			return 0;
		}
		foreach ($jobs as $job) {
			printf(
				"#%d %s %s created=%s\n",
				(int) $job->id,
				(string) $job->job_key,
				(string) $job->status,
				(string) $job->created_at
			);
		}
		return 0;
	}

	public static function as_status(): int
	{
		echo self::as_status_text() . "\n";
		return 0;
	}

	/** Human-readable Action Scheduler state, shared with the admin screen. */
	public static function as_status_text(): string
	{
		if (! function_exists('as_get_scheduler_status')) {
			return 'Action Scheduler: not installed (jobs run through WP-Cron).';
		}
		$running = as_get_scheduler_status();
		return 'Action Scheduler: ' . ($running === 'running' ? 'running' : 'not running (' . $running . ')');
	}

	/** @return list<object> */
	public static function job_rows(): array
	{
		global $wpdb;
		$table = $wpdb->prefix . 'sv_jobs';
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL
		if ($exists !== $table) {
			return [];
		}
		$rows = $wpdb->get_results("SELECT id, job_key, status, created_at FROM {$table} ORDER BY id ASC"); // phpcs:ignore WordPress.DB.PreparedSQL
		return is_array($rows) ? $rows : [];
	}

	private static function usage(string $command): int
	{
		if ($command !== '') {
			echo "Unknown command: {$command}\n";
		}
		echo "Usage: sv <command>\n";
		echo "  migrate    Run pending wp_sv_* migrations\n";
		echo "  jobs       List the job table\n";
		echo "  as-status  Report Action Scheduler state\n";
		return $command === '' ? 0 : 1;
	}
}
