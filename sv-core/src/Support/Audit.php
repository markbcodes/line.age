<?php
/**
 * Audit log (CLAUDE.md rule 14). VX's own writes log themselves; this plugin's
 * custom mutating routes do not, so every one of them writes a row here.
 *
 * Best-effort by design: a logging failure must not fail the write it is
 * recording, so the return value is informational and this never throws.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Support;

/**
 * Append-only audit log.
 */
final class Audit {

	/**
	 * Record one mutation.
	 *
	 * @param string      $action      Dotted action, e.g. document.version.create.
	 * @param string      $entity      Entity handle, e.g. document:12.
	 * @param string|null $before_hash Content hash before the change, if any.
	 * @param string|null $after_hash  Content hash after the change, if any.
	 */
	public static function record( string $action, string $entity, ?string $before_hash = null, ?string $after_hash = null ): bool {
		global $wpdb;

		Log::debug(
			'Audit::record',
			array(
				'action' => $action,
				'entity' => $entity,
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- append-only audit write, no cache.
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'sv_audit_log',
			array(
				'workspace_id' => null,
				'actor_id'     => get_current_user_id(),
				'action'       => $action,
				'entity'       => $entity,
				'before_hash'  => $before_hash,
				'after_hash'   => $after_hash,
				'at'           => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}
}
