<?php
/**
 * Document versions: the manuscript store and its sv/v1 endpoints. Scene prose
 * lives in {prefix}sv_doc_versions and nowhere else (CLAUDE.md rule 13).
 *
 * Routes (registered in Rest::routes()):
 *   GET  /documents/{id}                latest {content, content_hash, version}
 *   POST /documents/{id}/versions       append one version, base_hash guarded
 *   GET  /documents/{id}/versions       paginated version list
 *   GET  /documents/{id}/versions/{n}   one version
 *   POST /documents/{id}/restore        append a copy of version {n}
 *
 * Concurrency: a client sends the hash it last read as base_hash. A mismatch
 * is 409 with the current hash, identical content is a 200 noop, and the
 * unique (document_id, version) key turns a lost race into another 409.
 *
 * Tenancy: every handler gates through SV\Security::require() on the book
 * behind document -> scene -> chapter -> book (rule 3). Reads ask for `read`,
 * writes for `write`, so a Viewer can read a manuscript but never append to it.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Editor\Canonical;
use SV\Editor\SchemaValidator;
use SV\Editor\WordCount;
use SV\Security;
use SV\Support\Audit;
use SV\Support\Log;

/**
 * Document version controller.
 */
final class Documents {

	/** Largest request body accepted, in bytes (2 MB). */
	public const MAX_BODY_BYTES = 2097152;

	/**
	 * Permission callback for every document route: a WP_Error (401 signed
	 * out, 403 not allowed) or true.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public static function permission( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$denied = Security::require( Security::book_for_document( $id ), 'read' );

		return null === $denied ? true : $denied;
	}

	/**
	 * GET /documents/{id} — the latest version.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function latest( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Log::debug( 'Documents::latest', array( 'document_id' => $id ) );

		$denied = Security::require( Security::book_for_document( $id ), 'read' );
		if ( null !== $denied ) {
			return $denied;
		}

		$row = self::latest_row( $id );
		if ( null === $row ) {
			return self::not_found();
		}

		return new \WP_REST_Response( self::version_payload( $row, true ), 200 );
	}

	/**
	 * POST /documents/{id}/versions — append a version.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function append( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Log::debug( 'Documents::append', array( 'document_id' => $id ) );

		$denied = Security::require( Security::book_for_document( $id ), 'write' );
		if ( null !== $denied ) {
			return $denied;
		}

		$oversized = self::check_size( $request );
		if ( null !== $oversized ) {
			return $oversized;
		}

		if ( ! self::document_exists( $id ) ) {
			return self::not_found();
		}

		$content = self::decode_content( $request['content_json'] ?? null );
		if ( null === $content ) {
			return new \WP_Error( 'sv_invalid_content', 'content_json must be a JSON object.', array( 'status' => 422 ) );
		}

		$invalid = SchemaValidator::validate( $content );
		if ( null !== $invalid ) {
			return $invalid;
		}

		$schema_version = max( 1, (int) ( $request['schema_version'] ?? 1 ) );

		return self::store( $id, $content, self::base_hash( $request ), $schema_version, 'document.version.create' );
	}

	/**
	 * GET /documents/{id}/versions — a page of version summaries.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function versions( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Log::debug( 'Documents::versions', array( 'document_id' => $id ) );

		$denied = Security::require( Security::book_for_document( $id ), 'read' );
		if ( null !== $denied ) {
			return $denied;
		}

		if ( ! self::document_exists( $id ) ) {
			return self::not_found();
		}

		$per_page = self::clamp( (int) ( $request['per_page'] ?? 20 ), 1, 100 );
		$page     = max( 1, (int) ( $request['page'] ?? 1 ) );
		$rows     = self::version_rows( $id, $per_page, ( $page - 1 ) * $per_page );

		return new \WP_REST_Response(
			array(
				'items'    => array_map( static fn( $row ) => self::version_payload( $row, false ), $rows ),
				'total'    => self::version_count( $id ),
				'page'     => $page,
				'per_page' => $per_page,
			),
			200
		);
	}

	/**
	 * GET /documents/{id}/versions/{n} — one exact version.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function version( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$n  = (int) $request['n'];
		Log::debug(
			'Documents::version',
			array(
				'document_id' => $id,
				'version'     => $n,
			)
		);

		$denied = Security::require( Security::book_for_document( $id ), 'read' );
		if ( null !== $denied ) {
			return $denied;
		}

		if ( ! self::document_exists( $id ) ) {
			return self::not_found();
		}

		$row = self::version_row( $id, $n );
		if ( null === $row ) {
			return self::not_found();
		}

		return new \WP_REST_Response( self::version_payload( $row, true ), 200 );
	}

	/**
	 * POST /documents/{id}/restore — append a copy of an earlier version.
	 * History is never truncated: this only ever inserts.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function restore( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Log::debug( 'Documents::restore', array( 'document_id' => $id ) );

		$denied = Security::require( Security::book_for_document( $id ), 'write' );
		if ( null !== $denied ) {
			return $denied;
		}

		$oversized = self::check_size( $request );
		if ( null !== $oversized ) {
			return $oversized;
		}

		if ( ! self::document_exists( $id ) ) {
			return self::not_found();
		}

		$source = self::version_row( $id, (int) ( $request['version'] ?? 0 ) );
		if ( null === $source ) {
			return self::not_found();
		}

		$content = json_decode( (string) $source->content_json, true );
		if ( ! is_array( $content ) ) {
			return new \WP_Error( 'sv_read_failed', 'The stored version could not be read.', array( 'status' => 500 ) );
		}

		return self::store( $id, $content, self::base_hash( $request ), (int) $source->schema_version, 'document.version.restore' );
	}

	/**
	 * The shared write path: guard on base_hash, skip identical content, then
	 * append version+1. A lost insert race is re-read and reported as 409.
	 *
	 * @param int          $document_id    Document primary key.
	 * @param array<mixed> $content       Decoded document.
	 * @param string|null  $base_hash      The hash the client last read.
	 * @param int          $schema_version Schema version of the incoming content.
	 * @param string       $action         Audit action name.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function store( int $document_id, array $content, ?string $base_hash, int $schema_version, string $action ) {
		$hash    = Canonical::hash( $content );
		$current = self::latest_row( $document_id );

		$current_hash    = null === $current ? null : (string) $current->content_hash;
		$current_version = null === $current ? 0 : (int) $current->version;

		if ( $current_hash !== $base_hash ) {
			return self::conflict( $current_hash, $current_version );
		}

		if ( null !== $current_hash && $hash === $current_hash ) {
			return new \WP_REST_Response(
				array(
					'noop'    => true,
					'version' => $current_version,
				),
				200
			);
		}

		$version    = $current_version + 1;
		$word_count = WordCount::count( $content );

		if ( ! self::insert_version( $document_id, $version, Canonical::canonicalize( $content ), $hash, $schema_version, $word_count ) ) {
			$now = self::latest_row( $document_id );
			if ( null !== $now && (string) $now->content_hash !== $base_hash ) {
				return self::conflict( (string) $now->content_hash, (int) $now->version );
			}

			return new \WP_Error( 'sv_write_failed', 'Could not store the version.', array( 'status' => 500 ) );
		}

		Audit::record( $action, 'document:' . $document_id, $current_hash, $hash );

		return new \WP_REST_Response(
			array(
				'version'      => $version,
				'content_hash' => $hash,
				'word_count'   => $word_count,
			),
			201
		);
	}

	/**
	 * Decode content_json, which may arrive as a JSON string or a JSON object.
	 *
	 * @param mixed $value Raw request value.
	 * @return array<mixed>|null
	 */
	private static function decode_content( $value ): ?array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );

			return is_array( $decoded ) ? $decoded : null;
		}

		if ( is_array( $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * The client's base_hash, normalised: an absent or empty string means
	 * "no base", i.e. the document had no versions when it was read.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	private static function base_hash( \WP_REST_Request $request ): ?string {
		$hash = $request['base_hash'] ?? null;

		if ( ! is_string( $hash ) || '' === $hash ) {
			return null;
		}

		return $hash;
	}

	/**
	 * Reject bodies larger than MAX_BODY_BYTES before parsing them.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	private static function check_size( \WP_REST_Request $request ): ?\WP_Error {
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY_BYTES ) {
			return new \WP_Error( 'sv_payload_too_large', 'Request bodies are limited to 2 MB.', array( 'status' => 413 ) );
		}

		return null;
	}

	/**
	 * A prefixed sv table name.
	 *
	 * @param string $suffix Table suffix after sv_, e.g. documents.
	 */
	private static function table( string $suffix ): string {
		global $wpdb;

		return $wpdb->prefix . 'sv_' . $suffix;
	}

	/**
	 * Whether the document row exists.
	 *
	 * @param int $id Document primary key.
	 */
	private static function document_exists( int $id ): bool {
		global $wpdb;

		$table = self::table( 'documents' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );

		return null !== $found;
	}

	/**
	 * The newest version row, or null.
	 *
	 * @param int $id Document primary key.
	 */
	private static function latest_row( int $id ): ?object {
		global $wpdb;

		$table = self::table( 'doc_versions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE document_id = %d ORDER BY version DESC LIMIT 1", $id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * One exact version row, or null.
	 *
	 * @param int $id      Document primary key.
	 * @param int $version Version number.
	 */
	private static function version_row( int $id, int $version ): ?object {
		global $wpdb;

		$table = self::table( 'doc_versions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE document_id = %d AND version = %d", $id, $version ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * A page of version rows, newest first.
	 *
	 * @param int $id     Document primary key.
	 * @param int $limit  Page size (max 100).
	 * @param int $offset Rows to skip before the page.
	 * @return list<object>
	 */
	private static function version_rows( int $id, int $limit, int $offset ): array {
		global $wpdb;

		$table = self::table( 'doc_versions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE document_id = %d ORDER BY version DESC LIMIT %d OFFSET %d", $id, $limit, $offset ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * How many versions a document has.
	 *
	 * @param int $id Document primary key.
	 */
	private static function version_count( int $id ): int {
		global $wpdb;

		$table = self::table( 'doc_versions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE document_id = %d", $id ) );
	}

	/**
	 * Insert one immutable version row.
	 *
	 * @param int    $id             Document primary key.
	 * @param int    $version        Version number to write.
	 * @param string $json           Canonical content JSON.
	 * @param string $hash           Canonical hash of that content.
	 * @param int    $schema_version Schema version.
	 * @param int    $word_count     Server-side word count.
	 */
	private static function insert_version( int $id, int $version, string $json, string $hash, int $schema_version, int $word_count ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- append-only document write, no cache.
		$inserted = $wpdb->insert(
			self::table( 'doc_versions' ),
			array(
				'document_id'    => $id,
				'version'        => $version,
				'content_json'   => $json,
				'content_hash'   => $hash,
				'schema_version' => $schema_version,
				'word_count'     => $word_count,
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * The REST shape of a version row.
	 *
	 * @param object $row          Version row.
	 * @param bool   $with_content Include the decoded content.
	 * @return array<string, mixed>
	 */
	private static function version_payload( object $row, bool $with_content ): array {
		$payload = array(
			'version'        => (int) $row->version,
			'content_hash'   => (string) $row->content_hash,
			'schema_version' => (int) $row->schema_version,
			'word_count'     => (int) $row->word_count,
			'created_by'     => (int) $row->created_by,
			'created_at'     => (string) $row->created_at,
		);

		if ( $with_content ) {
			$decoded            = json_decode( (string) $row->content_json, true );
			$payload['content'] = is_array( $decoded ) ? $decoded : array();
		}

		return $payload;
	}

	/** 404 for a missing document or version. */
	private static function not_found(): \WP_Error {
		return new \WP_Error( 'sv_not_found', 'Document or version not found.', array( 'status' => 404 ) );
	}

	/**
	 * 409 for a stale base_hash.
	 *
	 * @param string|null $current_hash    The hash that is actually current.
	 * @param int         $current_version The version that is actually current.
	 */
	private static function conflict( ?string $current_hash, int $current_version ): \WP_Error {
		return new \WP_Error(
			'conflict',
			'The document has changed since your last read.',
			array(
				'status'          => 409,
				'current_hash'    => $current_hash,
				'current_version' => $current_version,
			)
		);
	}

	/**
	 * Clamp an integer into a range.
	 *
	 * @param int $value Value.
	 * @param int $min   Minimum.
	 * @param int $max   Maximum.
	 */
	private static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
