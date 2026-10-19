<?php
/**
 * Canonical JSON: the stable form a document's content hash is computed over.
 * Two payloads that differ only in key order or whitespace hash the same,
 * which is what makes the optimistic-concurrency base_hash check reliable.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Editor;

use SV\Support\Log;

/**
 * Canonical JSON encoding and hashing.
 */
final class Canonical {

	/**
	 * SHA-256 over the canonical JSON of a decoded Tiptap document.
	 *
	 * @param array<mixed> $data Decoded document.
	 */
	public static function hash( array $data ): string {
		return hash( 'sha256', self::canonicalize( $data ) );
	}

	/**
	 * Deterministic JSON: every associative array is key-sorted recursively,
	 * list order is preserved, and no whitespace is added.
	 *
	 * @param array<mixed> $data Decoded document.
	 */
	public static function canonicalize( array $data ): string {
		Log::debug( 'Canonical::canonicalize' );

		$json = wp_json_encode( self::sort_keys( $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $json ) ? $json : '';
	}

	/**
	 * Recursively sort associative keys. Lists keep their order.
	 *
	 * @param array<mixed> $data Decoded value.
	 * @return array<mixed>
	 */
	private static function sort_keys( array $data ): array {
		if ( ! array_is_list( $data ) ) {
			ksort( $data );
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::sort_keys( $value );
			}
		}

		return $data;
	}
}
