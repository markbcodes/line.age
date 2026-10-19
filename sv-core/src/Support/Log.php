<?php
/**
 * Minimal debug logger. Silent unless SV_DEBUG is on, so production pays
 * nothing for log formatting. Every line is prefixed with [SV] so it is easy
 * to grep out of a shared error log.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Support;

/**
 * Debug logger.
 */
final class Log {

	/**
	 * Write a [SV]-prefixed line to error_log, but only when SV_DEBUG is on.
	 *
	 * @param string               $message What happened.
	 * @param array<string, mixed> $context Optional extra data, JSON-encoded.
	 */
	public static function debug( string $message, array $context = array() ): void {
		if ( ! defined( 'SV_DEBUG' ) || ! SV_DEBUG ) {
			return;
		}

		$line = '[SV] ' . $message;
		if ( array() !== $context ) {
			$line .= ' ' . (string) wp_json_encode( $context );
		}

		error_log( $line );
	}
}
