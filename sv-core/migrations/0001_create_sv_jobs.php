<?php
/**
 * Migration 0001: the job table. Portable dbDelta DDL — works on SQLite
 * locally and MariaDB/MySQL on the host.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

return static function (\wpdb $wpdb): void {
	$table = $wpdb->prefix . 'sv_jobs';
	$charset = $wpdb->get_charset_collate();

	// dbDelta needs each CREATE on its own line, two spaces after PRIMARY KEY.
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		job_key varchar(191) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY job_key (job_key)
	) {$charset};";

	if (! function_exists('dbDelta')) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	dbDelta($sql);
};
