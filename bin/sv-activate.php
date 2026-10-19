<?php
/**
 * php bin/sv-activate.php — one-shot activation helper for environments
 * without wp-cli (the Shader local has no `wp` binary; F1=no).
 * Idempotent; safe to run repeatedly.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
define('WP_USE_THEMES', false);
require_once $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$result = activate_plugin('sv-core/sv-core.php');
if (is_wp_error($result)) {
	fwrite(STDERR, 'Activation failed: ' . $result->get_error_message() . "\n");
	exit(1);
}

// activate_plugin() includes the plugin AFTER init has already fired in this
// process, so App::rewrite_rules() never ran — register the rules explicitly,
// or the flush below would persist rewrite rules without the /app rule.
\SV\App\App::rewrite_rules();
flush_rewrite_rules(false);

echo "sv-core active; rewrite rules flushed.\n";
echo 'active_plugins=' . implode(',', (array) get_option('active_plugins')) . "\n";
