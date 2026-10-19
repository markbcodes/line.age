<?php
/**
 * Tenancy fixtures for the probe suite: throwaway users, workspaces and book
 * chains, torn down together. The chain is the real one the app resolves:
 *
 *   user -> sv_workspace_members -> sv_workspaces
 *   sv_book(workspace=ws) <- sv_chapter(book) <- sv_scene(chapter) <- sv_documents
 *
 * Every row is created through the same tables and post types the app uses, so
 * a test that passes here passes for the same reason production does.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Tests;

use SV\Editor\Canonical;

/**
 * Seeder + teardown for tenancy tests.
 */
final class Tenancy {

	/** @var list<int> */
	private array $users = array();

	/** @var list<int> */
	private array $posts = array();

	/** @var list<int> */
	private array $documents = array();

	/** @var list<int> */
	private array $workspaces = array();

	/**
	 * Create a subscriber with the given login.
	 *
	 * @param string $login Unique login.
	 */
	public function user( string $login ): int {
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $login . '@sv.test',
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $id ) ) {
			$existing = get_user_by( 'login', $login );
			if ( ! $existing ) {
				throw new \RuntimeException( 'Tenancy: user ' . $login . ': ' . $id->get_error_message() );
			}
			$id = $existing->ID;
		}

		$this->users[] = (int) $id;

		return (int) $id;
	}

	/**
	 * Create a workspace owned by a user.
	 *
	 * @param int    $owner_id Owner user id.
	 * @param string $name     Workspace name.
	 */
	public function workspace( int $owner_id, string $name = 'Workspace' ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sv_workspaces',
			array(
				'universe_id' => null,
				'name'        => $name,
				'owner_id'    => $owner_id,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		$id                   = (int) $wpdb->insert_id;
		$this->workspaces[]   = $id;

		return $id;
	}

	/**
	 * Add a membership row.
	 *
	 * @param int    $workspace_id Workspace id.
	 * @param int    $user_id      Member user id.
	 * @param string $role         Owner|Editor|Viewer.
	 * @param int    $invited_by   Inviting user id, or 0.
	 */
	public function member( int $workspace_id, int $user_id, string $role, int $invited_by = 0 ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sv_workspace_members',
			array(
				'workspace_id' => $workspace_id,
				'user_id'      => $user_id,
				'role'         => $role,
				'invited_by'   => $invited_by > 0 ? $invited_by : null,
				'accepted_at'  => current_time( 'mysql', true ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Create a published post of one of our types with its VX number fields.
	 *
	 * @param string           $type  Post type.
	 * @param string           $title Post title.
	 * @param array<string,int> $meta Field key => number value.
	 */
	private function post( string $type, string $title, array $meta ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( 'Tenancy: ' . $type . ': ' . $id->get_error_message() );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $id, $key, $value );
		}

		$this->posts[] = (int) $id;

		return (int) $id;
	}

	/**
	 * A book in a workspace (the VX `workspace` field is the tenancy link).
	 *
	 * @param int    $workspace_id Workspace id.
	 * @param string $title        Book title.
	 */
	public function book( int $workspace_id, string $title = 'Book' ): int {
		return $this->post( 'sv_book', $title, array( 'workspace' => $workspace_id ) );
	}

	/**
	 * A chapter in a book.
	 *
	 * @param int $book_id Book post id.
	 */
	public function chapter( int $book_id ): int {
		return $this->post( 'sv_chapter', 'Chapter', array( 'book' => $book_id ) );
	}

	/**
	 * A scene in a chapter.
	 *
	 * @param int $chapter_id Chapter post id.
	 */
	public function scene( int $chapter_id ): int {
		return $this->post( 'sv_scene', 'Scene', array( 'chapter' => $chapter_id ) );
	}

	/**
	 * A document on a scene.
	 *
	 * @param int $scene_id Scene post id.
	 */
	public function document( int $scene_id ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sv_documents',
			array(
				'scene_id'   => $scene_id,
				'format'     => 'tiptap',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s' )
		);

		$id                = (int) $wpdb->insert_id;
		$this->documents[] = $id;

		return $id;
	}

	/**
	 * Write version 1 of a document directly, so read probes find content.
	 *
	 * @param int    $document_id Document id.
	 * @param string $text        A single paragraph of prose.
	 */
	public function version( int $document_id, string $text ): void {
		global $wpdb;

		$content = array(
			'type'    => 'doc',
			'content' => array(
				array(
					'type'    => 'paragraph',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $text,
						),
					),
				),
			),
		);

		$wpdb->insert(
			$wpdb->prefix . 'sv_doc_versions',
			array(
				'document_id'    => $document_id,
				'version'        => 1,
				'content_json'   => Canonical::canonicalize( $content ),
				'content_hash'   => Canonical::hash( $content ),
				'schema_version' => 1,
				'word_count'     => 1,
				'created_by'     => 0,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Remove everything this fixture created, newest dependency first.
	 */
	public function teardown(): void {
		global $wpdb;

		foreach ( $this->documents as $id ) {
			$wpdb->delete( $wpdb->prefix . 'sv_doc_versions', array( 'document_id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'sv_documents', array( 'id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'sv_audit_log', array( 'entity' => 'document:' . $id ), array( '%s' ) );
		}

		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}

		foreach ( $this->workspaces as $id ) {
			$wpdb->delete( $wpdb->prefix . 'sv_workspace_members', array( 'workspace_id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'sv_workspaces', array( 'id' => $id ), array( '%d' ) );
		}

		if ( array() !== $this->users && ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}

		$this->users      = array();
		$this->posts      = array();
		$this->documents  = array();
		$this->workspaces = array();
	}
}
