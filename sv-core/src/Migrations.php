<?php
/**
 * Migration runner. Files in /migrations named NNNN_description.php return a
 * callable receiving wpdb. Applied migrations are recorded in the
 * wp_sv_migrations option, so the same list runs on SQLite locally and on
 * MariaDB/MySQL on the host — keep every migration portable across both.
 */

declare(strict_types=1);

namespace SV\App;

final class Migrations
{
	/** @return list<string> names of migrations applied in this run */
	public static function run(): array
	{
		global $wpdb;

		$applied = get_option('sv_migrations', []);
		$applied = is_array($applied) ? $applied : [];

		$files = glob(SV_PLUGIN_DIR . 'migrations/*.php') ?: [];
		sort($files);

		$ran = [];
		foreach ($files as $file) {
			$name = basename($file, '.php');
			if (in_array($name, $applied, true)) {
				continue;
			}
			$callable = require $file;
			if (! is_callable($callable)) {
				continue;
			}
			$callable($wpdb);
			$applied[] = $name;
			update_option('sv_migrations', $applied, false);
			$ran[] = $name;
		}
		return $ran;
	}
}
