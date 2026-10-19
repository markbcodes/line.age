<?php
/**
 * Server-side word count. The count is derived from the stored document by
 * walking every text node, so it never depends on what the client claimed.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Editor;

use SV\Support\Log;

/**
 * Tiptap word count.
 */
final class WordCount {

	/**
	 * Words in a Tiptap document: collect every text leaf, then count
	 * whitespace-separated runs.
	 *
	 * @param array<mixed> $node Decoded document (or subtree).
	 */
	public static function count( array $node ): int {
		Log::debug( 'WordCount::count' );

		$text = trim( implode( ' ', self::text_nodes( $node ) ) );
		if ( '' === $text ) {
			return 0;
		}

		$count = preg_match_all( '/\S+/u', $text );

		return is_int( $count ) ? $count : 0;
	}

	/**
	 * Every `text` value in the tree, in document order.
	 *
	 * @param array<mixed> $node Decoded node.
	 * @return list<string>
	 */
	private static function text_nodes( array $node ): array {
		$found = array();

		if ( 'text' === ( $node['type'] ?? '' ) && is_string( $node['text'] ?? null ) ) {
			$found[] = $node['text'];
		}

		foreach ( (array) ( $node['content'] ?? array() ) as $child ) {
			if ( is_array( $child ) ) {
				$found = array_merge( $found, self::text_nodes( $child ) );
			}
		}

		return $found;
	}
}
