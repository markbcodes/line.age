<?php
/**
 * Migration runner. Files in /migrations are numbered `.sql`, one file per
 * step (0001_..., 0002_...), and every statement must be idempotent so the
 * whole set is re-runnable. Applied migrations are recorded in the
 * sv_migrations option.
 *
 * Two placeholders are substituted before a file runs, so no file is ever tied
 * to a table prefix or charset:
 *
 *   {prefix}   -> $wpdb->prefix   (wp_ locally, iwp3f42_ on the hosted site)
 *   {charset}  -> $wpdb->get_charset_collate()
 *
 * Keep the DDL portable: it runs on SQLite locally and MariaDB/MySQL (InnoDB)
 * on the hosted site. A file that fails stops the run and is NOT recorded as
 * applied, so the next run retries it.
 */

declare(strict_types=1);

namespace SV\App;

final class Migrations
{
	/** Message from the last failed migration, or '' when the run was clean. */
	public static string $error = '';

	/** @return list<string> names of migrations applied in this run */
	public static function run(): array
	{
		self::$error = '';

		$applied = get_option('sv_migrations', []);
		$applied = is_array($applied) ? $applied : [];

		$files = glob(SV_PLUGIN_DIR . 'migrations/*.sql') ?: [];
		sort($files);

		$ran = [];
		foreach ($files as $file) {
			$name = basename($file, '.sql');
			if (in_array($name, $applied, true)) {
				continue;
			}
			if (! self::apply($file, $name)) {
				break;
			}
			$applied[] = $name;
			update_option('sv_migrations', $applied, false);
			$ran[] = $name;
		}

		return $ran;
	}

	/** Run one .sql file. Returns false and sets $error on the first failure. */
	private static function apply(string $file, string $name): bool
	{
		global $wpdb;

		$sql = file_get_contents($file);
		if ($sql === false) {
			self::$error = "Migration {$name} could not be read.";
			return false;
		}

		$sql = str_replace(
			['{prefix}', '{charset}'],
			[$wpdb->prefix, $wpdb->get_charset_collate()],
			$sql
		);

		foreach (self::statements($sql) as $statement) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- migration DDL, no user input.
			$result = $wpdb->query($statement);
			if ($result === false) {
				self::$error = sprintf(
					'Migration %s failed: %s',
					$name,
					$wpdb->last_error !== '' ? $wpdb->last_error : 'unknown database error'
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Split a migration file into statements: '--' and '#' comment lines and
	 * blank lines are dropped, a statement ends at a line-final ';'.
	 *
	 * @return list<string>
	 */
	private static function statements(string $sql): array
	{
		$statements = [];
		$buffer = '';

		foreach (preg_split('/\R/', $sql) ?: [] as $line) {
			$trimmed = trim($line);
			if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
				continue;
			}
			$buffer .= $line . "\n";
			if (str_ends_with($trimmed, ';')) {
				$statements[] = rtrim(trim($buffer), ';');
				$buffer = '';
			}
		}

		if (trim($buffer) !== '') {
			$statements[] = trim($buffer);
		}

		return $statements;
	}
}
