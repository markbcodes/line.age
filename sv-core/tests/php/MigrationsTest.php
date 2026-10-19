<?php
/**
 * Migrations: ordered, re-runnable, tracked in {prefix}sv_migrations, and
 * every CREATE TABLE declares InnoDB.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;
use SV\App\Migrations;

final class MigrationsTest extends TestCase
{
	public function test_migrations_run_twice_without_error(): void
	{
		Migrations::run();
		$this->assertSame('', Migrations::$error, 'First run failed: ' . Migrations::$error);

		$second = Migrations::run();
		$this->assertSame('', Migrations::$error, 'Second run failed: ' . Migrations::$error);
		$this->assertSame(array(), $second, 'A second run must apply nothing');
	}

	public function test_tracking_table_records_every_migration_file(): void
	{
		Migrations::run();

		global $wpdb;
		$recorded = $wpdb->get_col( 'SELECT filename FROM ' . Migrations::table_name() ); // phpcs:ignore
		$this->assertIsArray( $recorded );

		foreach ( $this->migration_files() as $file ) {
			$this->assertContains( basename( $file, '.sql' ), $recorded, basename( $file ) . ' is not recorded in the tracking table' );
		}
	}

	public function test_every_create_table_declares_innodb(): void
	{
		$files = $this->migration_files();
		$this->assertNotSame( array(), $files, 'No migrations found' );

		foreach ( $files as $file ) {
			$sql = file_get_contents( $file );
			$this->assertIsString( $sql );

			if ( str_contains( $sql, 'CREATE TABLE' ) ) {
				$this->assertStringContainsString( 'ENGINE=InnoDB', $sql, basename( $file ) . ' must declare ENGINE=InnoDB' );
			}
		}
	}

	/** @return list<string> */
	private function migration_files(): array
	{
		$files = glob( SV_PLUGIN_DIR . 'migrations/*.sql' );

		return false === $files ? array() : $files;
	}
}
