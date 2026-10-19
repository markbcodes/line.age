<?php
/**
 * Content validation. A Tiptap document is accepted only if it has the shape
 * the editor can render. 1.4 exports the full schema to
 * assets/tiptap-schema.json; until that lands, this enforces the minimal
 * doc > paragraph > text shape the brief calls for.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Editor;

use SV\Support\Log;

/**
 * Content schema validation.
 */
final class SchemaValidator {

	/**
	 * Returns null when the document is valid, or a 422 WP_Error when it is not.
	 *
	 * @param array<mixed> $content Decoded document.
	 */
	public static function validate( array $content ): ?\WP_Error {
		Log::debug( 'SchemaValidator::validate' );

		if ( 'doc' !== ( $content['type'] ?? null ) ) {
			return self::invalid( 'The content must be a Tiptap document (type "doc").' );
		}

		$blocks = $content['content'] ?? array();
		if ( ! is_array( $blocks ) ) {
			return self::invalid( 'A document\'s content must be an array of block nodes.' );
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || 'paragraph' !== ( $block['type'] ?? null ) ) {
				return self::invalid( 'Only paragraph nodes are supported at the top level.' );
			}

			$inline = $block['content'] ?? array();
			if ( ! is_array( $inline ) ) {
				return self::invalid( 'A paragraph\'s content must be an array of inline nodes.' );
			}

			foreach ( $inline as $node ) {
				if ( ! is_array( $node ) || 'text' !== ( $node['type'] ?? null ) || ! is_string( $node['text'] ?? null ) ) {
					return self::invalid( 'Paragraph nodes may only contain text nodes.' );
				}
			}
		}

		return null;
	}

	/**
	 * The 422 error every validation failure returns.
	 *
	 * @param string $message What is wrong with the document.
	 */
	private static function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'sv_invalid_content', $message, array( 'status' => 422 ) );
	}
}
