<?php
/**
 * The single security boundary (CLAUDE.md rules 3 and 24).
 *
 * Can_access( book, user, cap ) is the ONE authorization function in the app:
 * book -> workspace (the book's VX `workspace` field) -> membership row ->
 * capability. Anything missing denies. Every sv/v1 route starts with
 * require(), and the same decision is hooked into vxframework/rest/can_read for
 * the sv_* post types as defence in depth (that hook withholds the payload but
 * cannot change the status code, so it is never the real gate).
 *
 * Decisions are memoized per request: one page renders many fields and many
 * records, and the membership table must be read once per (book, user, cap).
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV;

use SV\Support\Log;

/**
 * Route and field access control.
 */
final class Security {

	/**
	 * Role -> capability table. Owner ⊃ Editor ⊃ Viewer; anything else has no
	 * capabilities at all, so an unknown role (or a role written by something
	 * other than this app) denies rather than allows.
	 *
	 * @var array<string, list<string>>
	 */
	private const ROLE_CAPS = array(
		'owner'  => array( 'read', 'write', 'admin' ),
		'editor' => array( 'read', 'write' ),
		'viewer' => array( 'read' ),
	);

	/** Capabilities this class understands. An unknown cap denies. */
	private const CAPS = array( 'read', 'write', 'admin' );

	/** How deep a scene -> chapter -> book walk may go before we give up. */
	private const MAX_HOPS = 4;

	/**
	 * Memoized can_access decisions, keyed "book:user:cap".
	 *
	 * @var array<string, bool>
	 */
	private static array $access = array();

	/**
	 * Memoized book id -> workspace id (0 when the book is missing).
	 *
	 * @var array<int, int>
	 */
	private static array $workspace_of = array();

	/**
	 * Memoized "workspace:user" -> role, or null when there is no membership.
	 *
	 * @var array<string, string|null>
	 */
	private static array $roles = array();

	/**
	 * Memoized any sv_* post id -> owning book post id (0 when unresolved).
	 *
	 * @var array<int, int>
	 */
	private static array $book_of = array();

	/**
	 * Memoized document id -> scene post id.
	 *
	 * @var array<int, int>
	 */
	private static array $scene_of = array();

	/**
	 * Hooks the defence-in-depth read filter. Registered from App::register()
	 * so every hook the plugin owns stays in one file.
	 */
	public static function attach(): void {
		add_filter( 'vxframework/rest/can_read', array( self::class, 'authorize_vx_read' ), 10, 2 );
		Log::debug( 'Security::attach' );
	}

	/**
	 * May $user_id do $cap on $book_id?
	 *
	 * @param int    $book_id Book post id (the sv_book post).
	 * @param int    $user_id User to test.
	 * @param string $cap     One of read, write, admin.
	 */
	public static function can_access( int $book_id, int $user_id, string $cap = 'read' ): bool {
		$cap = strtolower( trim( $cap ) );

		if ( $book_id <= 0 || $user_id <= 0 || ! in_array( $cap, self::CAPS, true ) ) {
			return false;
		}

		$key = $book_id . ':' . $user_id . ':' . $cap;
		if ( ! array_key_exists( $key, self::$access ) ) {
			self::$access[ $key ] = self::decide( $book_id, $user_id, $cap );
		}

		return self::$access[ $key ];
	}

	/**
	 * The gate every sv/v1 route calls first. Returns null when the request may
	 * proceed, or the 401/403 WP_Error the route should return.
	 *
	 * @param int    $book_id Book post id (the sv_book post).
	 * @param string $cap     One of read, write, admin.
	 */
	public static function require( int $book_id, string $cap = 'read' ): ?\WP_Error {
		Log::debug(
			'Security::require',
			array(
				'book_id' => $book_id,
				'cap'     => $cap,
			)
		);

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new \WP_Error( 'sv_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
		}

		if ( ! self::can_access( $book_id, $user_id, $cap ) ) {
			return new \WP_Error( 'sv_forbidden', 'You may not access this book.', array( 'status' => 403 ) );
		}

		return null;
	}

	/**
	 * The book a document belongs to: document -> scene post -> book post.
	 * 0 when any link in the chain is missing.
	 *
	 * @param int $document_id Document primary key.
	 */
	public static function book_for_document( int $document_id ): int {
		if ( $document_id <= 0 ) {
			return 0;
		}

		if ( ! array_key_exists( $document_id, self::$scene_of ) ) {
			global $wpdb;

			$table = $wpdb->prefix . 'sv_documents';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$scene = $wpdb->get_var( $wpdb->prepare( "SELECT scene_id FROM {$table} WHERE id = %d", $document_id ) );

			self::$scene_of[ $document_id ] = null === $scene ? 0 : (int) $scene;
		}

		return self::book_for_post( self::$scene_of[ $document_id ] );
	}

	/**
	 * The book any VX model post belongs to: sv_book is itself, the book-scoped
	 * types carry a `book` number field, and a scene points at its chapter.
	 * 0 when the post is not one of our types or the chain is broken.
	 *
	 * @param int $post_id Any post id.
	 * @param int $depth   Internal cycle guard.
	 */
	public static function book_for_post( int $post_id, int $depth = 0 ): int {
		if ( $post_id <= 0 || $depth > self::MAX_HOPS ) {
			return 0;
		}

		if ( array_key_exists( $post_id, self::$book_of ) ) {
			return self::$book_of[ $post_id ];
		}

		switch ( get_post_type( $post_id ) ) {
			case 'sv_book':
				$book = $post_id;
				break;
			case 'sv_act':
			case 'sv_chapter':
			case 'sv_character':
			case 'sv_location':
			case 'sv_plot_beat':
				$book = self::book_for_post( self::field_int( $post_id, 'book' ), $depth + 1 );
				break;
			case 'sv_scene':
				$book = self::book_for_post( self::field_int( $post_id, 'chapter' ), $depth + 1 );
				break;
			default:
				$book = 0;
				break;
		}

		self::$book_of[ $post_id ] = $book;

		return $book;
	}

	/**
	 * The vxframework/rest/can_read filter for our post types. It can only ever
	 * withhold a payload VX already allowed — never widen a denial — and it
	 * re-uses the same can_access() decision as the routes (rule 24).
	 *
	 * @param mixed $allowed VX's own decision, passed through by the filter.
	 * @param mixed $context Normalized context: type, entity_kind, entity_id, viewer_id.
	 */
	public static function authorize_vx_read( $allowed, $context ): bool {
		$allowed = (bool) $allowed;

		if ( ! $allowed || ! is_array( $context ) ) {
			return false;
		}

		if ( 'post' !== ( $context['entity_kind'] ?? '' ) ) {
			return $allowed;
		}

		$post_id = (int) ( $context['entity_id'] ?? 0 );
		$type    = $post_id > 0 ? get_post_type( $post_id ) : '';
		if ( ! is_string( $type ) || ! str_starts_with( $type, 'sv_' ) ) {
			return $allowed;
		}

		$viewer = (int) ( $context['viewer_id'] ?? 0 );
		if ( $viewer <= 0 ) {
			return false;
		}

		return self::can_access( self::book_for_post( $post_id ), $viewer, 'read' ) ? $allowed : false;
	}

	/**
	 * Drop every memoized decision. Only tests need this: a real request gets a
	 * fresh process.
	 */
	public static function reset_cache(): void {
		self::$access       = array();
		self::$workspace_of = array();
		self::$roles        = array();
		self::$book_of      = array();
		self::$scene_of     = array();
	}

	/**
	 * The raw decision behind the memoized can_access().
	 *
	 * @param int    $book_id Book post id.
	 * @param int    $user_id User to test.
	 * @param string $cap     Already validated, lowercase cap.
	 */
	private static function decide( int $book_id, int $user_id, string $cap ): bool {
		$workspace_id = self::workspace_for_book( $book_id );
		if ( $workspace_id <= 0 ) {
			return false;
		}

		$role = self::member_role( $workspace_id, $user_id );
		if ( null === $role ) {
			return false;
		}

		$caps = self::ROLE_CAPS[ strtolower( $role ) ] ?? array();

		return in_array( $cap, $caps, true );
	}

	/**
	 * The workspace a book belongs to, read from the book's VX `workspace`
	 * number field. 0 when the post is not an sv_book or carries no workspace.
	 *
	 * @param int $book_id Book post id.
	 */
	private static function workspace_for_book( int $book_id ): int {
		if ( array_key_exists( $book_id, self::$workspace_of ) ) {
			return self::$workspace_of[ $book_id ];
		}

		$workspace = 0;
		if ( 'sv_book' === get_post_type( $book_id ) ) {
			$value = self::field_int( $book_id, 'workspace' );
			if ( $value > 0 ) {
				$workspace = $value;
			}
		}

		self::$workspace_of[ $book_id ] = $workspace;

		return $workspace;
	}

	/**
	 * The user's role in a workspace, or null when they are not a member.
	 *
	 * @param int $workspace_id Workspace primary key.
	 * @param int $user_id      User to test.
	 */
	private static function member_role( int $workspace_id, int $user_id ): ?string {
		$key = $workspace_id . ':' . $user_id;
		if ( array_key_exists( $key, self::$roles ) ) {
			return self::$roles[ $key ];
		}

		global $wpdb;

		$table = $wpdb->prefix . 'sv_workspace_members';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		$role = $wpdb->get_var( $wpdb->prepare( "SELECT role FROM {$table} WHERE workspace_id = %d AND user_id = %d LIMIT 1", $workspace_id, $user_id ) );

		self::$roles[ $key ] = is_string( $role ) && '' !== $role ? $role : null;

		return self::$roles[ $key ];
	}

	/**
	 * A positive integer VX field on a post. VX stores a number field as plain
	 * post meta under the field key; it is read through the facade when the
	 * plugin is loaded and falls back to the meta, because every VX field is
	 * untyped and either read can be missing (rule 22).
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Field key.
	 */
	private static function field_int( int $post_id, string $key ): int {
		if ( $post_id <= 0 || '' === $key ) {
			return 0;
		}

		if ( class_exists( '\VxFramework\Post' ) ) {
			$value = \VxFramework\Post::get( $post_id )->field( $key )->int();
			if ( $value > 0 ) {
				return $value;
			}
		}

		$meta = get_post_meta( $post_id, $key, true );

		return is_numeric( $meta ) ? (int) $meta : 0;
	}
}
