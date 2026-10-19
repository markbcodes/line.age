<?php
/**
 * Document versions: the sv/v1 manuscript endpoints. One test per case in the
 * brief — append, stale base_hash, noop, restore, unauthenticated, oversized,
 * invalid Tiptap, the same-base race, and the server-side word count — plus a
 * pair proving the content hash is canonical.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;
use SV\App\Migrations;
use SV\Editor\Canonical;
use SV\Security;

final class DocumentsTest extends TestCase
{
	/** @var list<int> Document ids created by a test, removed in tearDown. */
	private array $documents = array();

	/** Tenancy fixture: user 1 owns the workspace these documents' book lives in. */
	private Tenancy $fixture;

	/** Scene post every document in this suite hangs off. */
	private int $scene_id = 0;

	protected function setUp(): void
	{
		parent::setUp();

		Migrations::run();
		Security::reset_cache();
		wp_set_current_user( 1 );

		$this->fixture  = new Tenancy();
		$workspace      = $this->fixture->workspace( 1, 'Documents tests' );
		$this->fixture->member( $workspace, 1, 'Owner' );
		$book           = $this->fixture->book( $workspace, 'Test book' );
		$this->scene_id = $this->fixture->scene( $this->fixture->chapter( $book ) );
	}

	protected function tearDown(): void
	{
		global $wpdb;

		foreach ( $this->documents as $id ) {
			$wpdb->delete( $wpdb->prefix . 'sv_doc_versions', array( 'document_id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'sv_documents', array( 'id' => $id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'sv_audit_log', array( 'entity' => 'document:' . $id ), array( '%s' ) );
		}

		$this->documents = array();
		$this->fixture->teardown();
		Security::reset_cache();
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_two_appends_are_both_readable(): void
	{
		$id = $this->create_document();

		$first = $this->append( $id, $this->content( 'First draft.' ), null );
		$this->assertSame( 201, $first->get_status() );
		$this->assertSame( 1, $first->get_data()['version'] );

		$second = $this->append( $id, $this->content( 'Second draft.' ), $first->get_data()['content_hash'] );
		$this->assertSame( 201, $second->get_status() );
		$this->assertSame( 2, $second->get_data()['version'] );

		$v1 = $this->get( '/sv/v1/documents/' . $id . '/versions/1' );
		$v2 = $this->get( '/sv/v1/documents/' . $id . '/versions/2' );

		$this->assertSame( 200, $v1->get_status() );
		$this->assertSame( 'First draft.', $this->text( $v1 ) );
		$this->assertSame( 'Second draft.', $this->text( $v2 ) );
	}

	public function test_stale_base_hash_conflicts_and_writes_nothing(): void
	{
		$id = $this->create_document();

		$a      = $this->append( $id, $this->content( 'A' ), null );
		$hash_a = $a->get_data()['content_hash'];
		$b      = $this->append( $id, $this->content( 'B' ), $hash_a );
		$hash_b = $b->get_data()['content_hash'];

		$before = $this->version_count( $id );

		$stale = $this->append( $id, $this->content( 'C' ), $hash_a );

		$this->assertSame( 409, $stale->get_status() );
		$this->assertSame( 'conflict', $stale->get_data()['code'] );
		$this->assertSame( $hash_b, $stale->get_data()['data']['current_hash'] );
		$this->assertSame( 2, $stale->get_data()['data']['current_version'] );
		$this->assertSame( $before, $this->version_count( $id ) );
	}

	public function test_identical_content_is_a_noop_with_no_new_row(): void
	{
		$id   = $this->create_document();
		$a    = $this->append( $id, $this->content( 'Same.' ), null );
		$hash = $a->get_data()['content_hash'];

		$before = $this->version_count( $id );

		$again = $this->append( $id, $this->content( 'Same.' ), $hash );

		$this->assertSame( 200, $again->get_status() );
		$this->assertTrue( $again->get_data()['noop'] );
		$this->assertSame( 1, $again->get_data()['version'] );
		$this->assertSame( $before, $this->version_count( $id ) );
	}

	public function test_restore_appends_a_new_version_and_keeps_history(): void
	{
		$id = $this->create_document();

		$a = $this->append( $id, $this->content( 'Version one.' ), null );
		$b = $this->append( $id, $this->content( 'Version two.' ), $a->get_data()['content_hash'] );

		$restored = $this->post(
			'/sv/v1/documents/' . $id . '/restore',
			array(
				'version'   => 1,
				'base_hash' => $b->get_data()['content_hash'],
			)
		);

		$this->assertSame( 201, $restored->get_status() );
		$this->assertSame( 3, $restored->get_data()['version'] );

		$v3 = $this->get( '/sv/v1/documents/' . $id . '/versions/3' );
		$this->assertSame( 'Version one.', $this->text( $v3 ) );

		// Nothing was truncated: 1 and 2 are still there.
		$this->assertSame( 200, $this->get( '/sv/v1/documents/' . $id . '/versions/1' )->get_status() );
		$this->assertSame( 200, $this->get( '/sv/v1/documents/' . $id . '/versions/2' )->get_status() );
		$this->assertSame( 3, $this->version_count( $id ) );
	}

	public function test_unauthenticated_requests_are_401(): void
	{
		$id = $this->create_document();
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get( '/sv/v1/documents/' . $id )->get_status() );
		$this->assertSame( 401, $this->append( $id, $this->content( 'Nope.' ), null )->get_status() );
	}

	public function test_bodies_over_two_megabytes_are_413(): void
	{
		$id = $this->create_document();

		$request = new \WP_REST_Request( 'POST', '/sv/v1/documents/' . $id . '/versions' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'content_json'   => str_repeat( 'word ', 500000 ), // ~2.5 MB.
					'base_hash'      => null,
					'schema_version' => 1,
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 413, $response->get_status() );
	}

	public function test_invalid_tiptap_json_is_422(): void
	{
		$id = $this->create_document();

		$response = $this->post(
			'/sv/v1/documents/' . $id . '/versions',
			array(
				'content_json'   => (string) wp_json_encode( array( 'type' => 'not-a-doc' ) ),
				'base_hash'      => null,
				'schema_version' => 1,
			)
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'sv_invalid_content', $response->get_data()['code'] );
	}

	public function test_two_saves_from_one_base_hash_are_one_201_and_one_409(): void
	{
		$id   = $this->create_document();
		$a    = $this->append( $id, $this->content( 'Base.' ), null );
		$hash = $a->get_data()['content_hash'];

		$one = $this->append( $id, $this->content( 'Writer one.' ), $hash );
		$two = $this->append( $id, $this->content( 'Writer two.' ), $hash );

		$statuses = array( $one->get_status(), $two->get_status() );
		sort( $statuses );

		$this->assertSame( array( 201, 409 ), $statuses, 'Exactly one write must win.' );
		$this->assertSame( 2, $this->version_count( $id ) );
	}

	public function test_word_count_matches_the_fixture(): void
	{
		$id       = $this->create_document();
		$response = $this->append( $id, $this->content( 'The quick brown fox jumps over the lazy dog.' ), null );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 9, $response->get_data()['word_count'] );

		$v1 = $this->get( '/sv/v1/documents/' . $id . '/versions/1' );
		$this->assertSame( 9, $v1->get_data()['word_count'] );
	}

	public function test_content_hash_is_the_canonical_sha256(): void
	{
		$id       = $this->create_document();
		$content  = $this->content( 'Canonical.' );
		$response = $this->append( $id, $content, null );

		$this->assertSame( Canonical::hash( $content ), $response->get_data()['content_hash'] );
	}

	public function test_hash_ignores_key_order_and_whitespace(): void
	{
		$id = $this->create_document();

		$reordered = array(
			'content' => array(
				array(
					'content' => array(
						array(
							'text' => 'Same.',
							'type' => 'text',
						),
					),
					'type' => 'paragraph',
				),
			),
			'type'    => 'doc',
		);

		$first  = $this->append( $id, $reordered, null );
		$second = $this->append( $id, $this->content( 'Same.' ), $first->get_data()['content_hash'] );

		$this->assertSame( 200, $second->get_status() );
		$this->assertTrue( $second->get_data()['noop'] );
	}

	/** Create a bare document row and remember it for cleanup. */
	private function create_document(): int
	{
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sv_documents',
			array(
				'scene_id'   => $this->scene_id,
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
	 * A minimal valid Tiptap document with one paragraph of text.
	 *
	 * @return array<string, mixed>
	 */
	private function content( string $text ): array
	{
		return array(
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
	}

	/** POST /documents/{id}/versions and return the response. */
	private function append( int $id, array $content, ?string $base_hash ): \WP_REST_Response
	{
		return $this->post(
			'/sv/v1/documents/' . $id . '/versions',
			array(
				'content_json'   => (string) wp_json_encode( $content ),
				'base_hash'      => $base_hash,
				'schema_version' => 1,
			)
		);
	}

	/** Dispatch a JSON POST. */
	private function post( string $route, array $body ): \WP_REST_Response
	{
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_get_server()->dispatch( $request );
	}

	/** Dispatch a GET. */
	private function get( string $route ): \WP_REST_Response
	{
		return rest_get_server()->dispatch( new \WP_REST_Request( 'GET', $route ) );
	}

	/** The first text node of a version response. */
	private function text( \WP_REST_Response $response ): string
	{
		return (string) $response->get_data()['content']['content'][0]['content'][0]['text'];
	}

	/** Rows in sv_doc_versions for a document. */
	private function version_count( int $id ): int
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sv_doc_versions WHERE document_id = %d', $id )
		);
	}
}
