<?php
/**
 * Migration runner. Files in /migrations are numbered `.sql`, one file per
 * step (0001_..., 0002_...), and every statement must be idempotent so the
 * whole set is re-runnable. Applied files are recorded in the
 * {prefix}sv_migrations table, which the runner creates before reading it.
 *
 * Installs from the option era (before the table existed) are backfilled once
 * from the sv_migrations option; that option is never written again.
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
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * SQL migration runner.
 */
final class Migrations {

	/**
	 * Message from the last failed migration, or '' when the run was clean.
	 *
	 * @var string
	 */
	public static string $error = '';

	/**
	 * Applies pending migrations, in filename order.
	 *
	 * @return list<string> Names of migrations applied in this run.
	 */
	public static function run(): array {
		self::$error = '';
		Log::debug( 'Migrations::run' );

		if ( ! self::ensure_table() ) {
			return array();
		}

		self::backfill_from_option();

		$applied = self::applied();
		$files   = glob( SV_PLUGIN_DIR . 'migrations/*.sql' );
		if ( false === $files ) {
			$files = array();
		}
		sort( $files );

		$ran = array();
		foreach ( $files as $file ) {
			$name = basename( $file, '.sql' );
			if ( in_array( $name, $applied, true ) ) {
				Log::debug( 'Migrations: already applied', array( 'migration' => $name ) );
				continue;
			}
			if ( ! self::apply( $file, $name ) ) {
				break;
			}
			if ( ! self::record( $name ) ) {
				break;
			}
			$ran[] = $name;
		}

		return $ran;
	}

	/** The tracking table, with the site's own prefix. */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'sv_migrations';
	}

	/** Create the tracking table on first use. Returns false and sets $error. */
	private static function ensure_table(): bool {
		global $wpdb;

		$table = self::table_name();
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			filename varchar(191) NOT NULL,
			applied_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY filename (filename)
		) ENGINE=InnoDB " . $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- bootstrap DDL, table name from $wpdb->prefix.
		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			self::$error = sprintf(
				'Could not create %s: %s',
				$table,
				'' !== $wpdb->last_error ? $wpdb->last_error : 'unknown database error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Seed the table from the old sv_migrations option, once. Safe to call on
	 * every run: it does nothing as soon as the table has a row.
	 */
	private static function backfill_from_option(): void {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( 0 < $count ) {
			return;
		}

		$legacy = get_option( 'sv_migrations', array() );
		if ( ! is_array( $legacy ) || array() === $legacy ) {
			return;
		}

		foreach ( $legacy as $name ) {
			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration bookkeeping, no cache needed.
			$wpdb->insert(
				$table,
				array(
					'filename'   => $name,
					'applied_at' => current_time( 'mysql', true ),
				)
			);
		}

		Log::debug( 'Migrations: backfilled from option', array( 'count' => count( $legacy ) ) );
	}

	/**
	 * Applied migration names, in the order they were recorded.
	 *
	 * @return list<string>
	 */
	private static function applied(): array {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$names = $wpdb->get_col( "SELECT filename FROM {$table} ORDER BY id ASC" );

		return is_array( $names ) ? array_values( array_map( 'strval', $names ) ) : array();
	}

	/**
	 * Record one applied file. Returns false and sets $error on failure.
	 *
	 * @param string $name Migration name.
	 */
	private static function record( string $name ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration bookkeeping, no cache needed.
		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'filename'   => $name,
				'applied_at' => current_time( 'mysql', true ),
			)
		);

		if ( false === $inserted ) {
			self::$error = sprintf(
				'Migration %s ran but could not be recorded: %s',
				$name,
				'' !== $wpdb->last_error ? $wpdb->last_error : 'unknown database error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Run one .sql file. Returns false and sets $error on the first failure.
	 *
	 * @param string $file Absolute path to the .sql file.
	 * @param string $name Migration name.
	 */
	private static function apply( string $file, string $name ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local migration file, not a URL.
		$sql = file_get_contents( $file );
		if ( false === $sql ) {
			self::$error = "Migration {$name} could not be read.";

			return false;
		}

		$sql = str_replace(
			array( '{prefix}', '{charset}' ),
			array( $wpdb->prefix, $wpdb->get_charset_collate() ),
			$sql
		);

		foreach ( self::statements( $sql ) as $statement ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- migration DDL, no user input.
			$result = $wpdb->query( $statement );
			if ( false === $result ) {
				self::$error = sprintf(
					'Migration %s failed: %s',
					$name,
					'' !== $wpdb->last_error ? $wpdb->last_error : 'unknown database error'
				);

				return false;
			}
		}

		Log::debug( 'Migrations: applied', array( 'migration' => $name ) );

		return true;
	}

	/**
	 * Split a migration file into statements: '--' and '#' comment lines and
	 * blank lines are dropped, a statement ends at a line-final ';'.
	 *
	 * @param string $sql Migration file contents.
	 * @return list<string>
	 */
	private static function statements( string $sql ): array {
		$statements = array();
		$buffer     = '';

		$lines = preg_split( '/\R/', $sql );
		if ( false === $lines ) {
			$lines = array();
		}

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || str_starts_with( $trimmed, '--' ) || str_starts_with( $trimmed, '#' ) ) {
				continue;
			}
			$buffer .= $line . "\n";
			if ( str_ends_with( $trimmed, ';' ) ) {
				$statements[] = rtrim( trim( $buffer ), ';' );
				$buffer       = '';
			}
		}

		if ( '' !== trim( $buffer ) ) {
			$statements[] = trim( $buffer );
		}

		return $statements;
	}
}
