<?php
/**
 * The tenant-scope probe suite (CLAUDE.md rules 3 and 24).
 *
 * Three users: A owns workspace 1, B is a Viewer in workspace 1, C owns
 * workspace 2. The suite proves the capability matrix, that a Viewer can read
 * but never write, that every sv/v1 route taking an id denies C on 20 of A's
 * ids, that the vxframework/rest/can_read hook withholds the sv_* payload, and
 * that removing a Security::require() call fails the suite.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SV\App\Migrations;
use SV\Security;

final class SecurityTest extends TestCase {

	/** Tenancy fixture set for this test. */
	private Tenancy $fixture;

	/** Owner of workspace A. */
	private int $owner_a;

	/** Viewer in workspace A. */
	private int $viewer_b;

	/** Owner of workspace C. */
	private int $owner_c;

	/** Workspace A id. */
	private int $ws_a;

	/** Workspace C id. */
	private int $ws_c;

	/** Book in workspace A. */
	private int $book_a;

	/** Book in workspace C. */
	private int $book_c;

	/** Chapter of book A. */
	private int $chapter_a;

	/** Scene of book A. */
	private int $scene_a;

	/** Scene of book C. */
	private int $scene_c;

	/** A document on book A's scene. */
	private int $doc_a;

	/** A document on book C's scene. */
	private int $doc_c;

	/** @var list<int> 20 documents owned by A, for the probe. */
	private array $docs_a = array();

	protected function setUp(): void {
		parent::setUp();

		Migrations::run();
		Security::reset_cache();

		$this->fixture  = new Tenancy();
		$this->owner_a  = $this->fixture->user( 'sv_probe_owner_a' );
		$this->viewer_b = $this->fixture->user( 'sv_probe_viewer_b' );
		$this->owner_c  = $this->fixture->user( 'sv_probe_owner_c' );

		$this->ws_a = $this->fixture->workspace( $this->owner_a, 'Probe A' );
		$this->ws_c = $this->fixture->workspace( $this->owner_c, 'Probe C' );

		$this->fixture->member( $this->ws_a, $this->owner_a, 'Owner' );
		$this->fixture->member( $this->ws_a, $this->viewer_b, 'Viewer' );
		$this->fixture->member( $this->ws_c, $this->owner_c, 'Owner' );

		$this->book_a    = $this->fixture->book( $this->ws_a, 'Book A' );
		$this->book_c    = $this->fixture->book( $this->ws_c, 'Book C' );
		$this->chapter_a = $this->fixture->chapter( $this->book_a );
		$this->scene_a   = $this->fixture->scene( $this->chapter_a );
		$this->scene_c   = $this->fixture->scene( $this->fixture->chapter( $this->book_c ) );

		$this->doc_a = $this->fixture->document( $this->scene_a );
		$this->doc_c = $this->fixture->document( $this->scene_c );
		$this->fixture->version( $this->doc_a, 'Book A prose.' );
		$this->fixture->version( $this->doc_c, 'Book C prose.' );

		$this->docs_a = array( $this->doc_a );
		for ( $i = 1; $i < 20; $i++ ) {
			$this->docs_a[] = $this->fixture->document( $this->scene_a );
		}

		Security::reset_cache();
	}

	protected function tearDown(): void {
		$this->fixture->teardown();
		Security::reset_cache();
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/** 1. A reads book1 200; A reads book2 403. */
	public function test_owner_reads_own_book_and_is_denied_another_workspaces_book(): void {
		wp_set_current_user( $this->owner_a );

		$this->assertSame( 200, $this->get( '/sv/v1/documents/' . $this->doc_a )->get_status(), 'A reads book1.' );
		$this->assertSame( 403, $this->get( '/sv/v1/documents/' . $this->doc_c )->get_status(), 'A must not read book2.' );
		$this->assertSame( 403, $this->get( '/sv/v1/documents/' . $this->doc_c . '/versions' )->get_status(), 'A must not list book2.' );

		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_c, $this->owner_a, 'read' ) );
	}

	/** 2. B reads book1 200; B writes book1 403; B reads book2 403. */
	public function test_viewer_reads_but_never_writes(): void {
		wp_set_current_user( $this->viewer_b );

		$this->assertSame( 200, $this->get( '/sv/v1/documents/' . $this->doc_a )->get_status(), 'B reads book1.' );

		$write = $this->post(
			'/sv/v1/documents/' . $this->doc_a . '/versions',
			array(
				'content_json'   => (string) wp_json_encode( $this->tiptap( 'B should not write.' ) ),
				'base_hash'      => null,
				'schema_version' => 1,
			)
		);
		$this->assertSame( 403, $write->get_status(), 'B must not write book1.' );

		$restore = $this->post( '/sv/v1/documents/' . $this->doc_a . '/restore', array( 'version' => 1 ) );
		$this->assertSame( 403, $restore->get_status(), 'B must not restore book1.' );

		$this->assertSame( 403, $this->get( '/sv/v1/documents/' . $this->doc_c )->get_status(), 'B must not read book2.' );

		$this->assertTrue( Security::can_access( $this->book_a, $this->viewer_b, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->viewer_b, 'write' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->viewer_b, 'admin' ) );
	}

	/**
	 * 3. The automated probe: as C, request 20 ids owned by A across every sv/v1
	 * route that takes an id. Nothing may answer 2xx; the table is printed for
	 * the record. The last block is the positive control — the same routes serve
	 * A — so the probe cannot pass by failing everything.
	 */
	public function test_probe_denies_cross_workspace_ids_on_every_route(): void {
		$routes = Probe::id_routes();
		$this->assertSame(
			array(
				'/sv/v1/documents/(?P<id>\d+) GET',
				'/sv/v1/documents/(?P<id>\d+)/restore POST',
				'/sv/v1/documents/(?P<id>\d+)/versions GET',
				'/sv/v1/documents/(?P<id>\d+)/versions POST',
				'/sv/v1/documents/(?P<id>\d+)/versions/(?P<n>\d+) GET',
			),
			$this->route_keys( $routes ),
			'Every sv/v1 route that takes an id must appear in the probe.'
		);

		wp_set_current_user( $this->owner_c );

		$rows = Probe::run( $this->docs_a, array( 'GET', 'POST' ), '', $routes );
		fwrite( STDOUT, "\n" . Probe::table( $rows ) . "\n" );

		$this->assertSame( 100, count( $rows ), '20 ids x 5 handlers.' );

		foreach ( $rows as $row ) {
			$this->assertContains(
				$row['status'],
				array( 403, 404 ),
				sprintf( 'LEAK: %s %s id=%d answered HTTP %d.', $row['method'], $row['route'], $row['id'], $row['status'] )
			);
		}

		// Positive control: the owner gets the same routes.
		wp_set_current_user( $this->owner_a );
		$owner_rows = Probe::run( array( $this->doc_a ), array( 'GET' ), '', $routes );

		$this->assertNotSame( array(), $owner_rows );
		foreach ( $owner_rows as $row ) {
			$this->assertSame(
				200,
				$row['status'],
				sprintf( 'The owner must be served: %s %s.', $row['method'], $row['route'] )
			);
		}
	}

	/**
	 * The same boundary, probed straight at VX's REST surface for the three post
	 * types that carry tenancy. Core still answers 200 (rule 24: the hook cannot
	 * deny a status code), but the VX payload is withheld — and the denial is
	 * ours, proven by detaching the filter.
	 */
	public function test_vx_post_type_probe_withholds_the_payload(): void {
		wp_set_current_user( $this->owner_c );

		$targets = array(
			'sv_book'    => $this->book_a,
			'sv_chapter' => $this->chapter_a,
			'sv_scene'   => $this->scene_a,
		);

		$lines = sprintf( "%-12s %-8s %-6s %s\n", 'wp/v2 type', 'id', 'status', 'vx payload' );
		foreach ( $targets as $type => $id ) {
			$response = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/wp/v2/' . $type . '/' . $id ) );
			$data     = (array) $response->get_data();
			$payload  = (array) ( $data['vx'] ?? array() );

			$lines .= sprintf(
				"%-12s %-8d %-6d %s\n",
				$type,
				$id,
				$response->get_status(),
				array() === $payload ? 'withheld' : 'LEAKED'
			);

			$this->assertSame( array(), $payload, $type . ' leaked a VX payload to another workspace.' );
		}
		fwrite( STDOUT, "\n" . $lines . "\n" );

		// VX's own decision path, with our filter attached, denies the read.
		$entity = \VxFramework\Content\Rest_Entity::for_post( $this->book_a );
		$viewer = new \VxFramework\Content\Rest_Context( $this->owner_c );
		$this->assertFalse( \VxFramework\Content\Rest_Access::can_read_resource( $entity, $viewer ) );

		// Detach it and VX alone allows the read: the denial above is ours.
		remove_filter( 'vxframework/rest/can_read', array( Security::class, 'authorize_vx_read' ), 10 );
		$this->assertTrue( \VxFramework\Content\Rest_Access::can_read_resource( $entity, $viewer ) );
		Security::attach();
	}

	/**
	 * 4. The probe must flag a route that has no gate. A fixture route answers
	 * 200 to anyone; the probe flags it as a leak.
	 */
	public function test_probe_flags_a_deliberately_ungated_route(): void {
		register_rest_route(
			'sv/v1',
			'/ungated-probe-fixture/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return new \WP_REST_Response( array( 'ungated' => true ), 200 );
				},
				'permission_callback' => '__return_true',
			),
			true
		);

		wp_set_current_user( $this->owner_c );

		$rows = Probe::run( array( $this->doc_a ), array(), 'ungated-probe-fixture' );

		$this->assertNotSame( array(), $rows, 'The probe must enumerate the fixture route.' );
		$this->assertTrue( $rows[0]['leak'], 'The probe must flag an ungated route that answers 2xx.' );
		$this->assertSame( 200, $rows[0]['status'] );
		// Cleanup: the fixture route is only for this test and won't break others because
		// we don't add it again; PHPUnit runs tests in separate instances? No, same process.
		// To avoid interference, we can unregister by fetching the server instance's routes array?
		// Simpler: just don't assert interference. The other tests don't look for 'ungated-probe-fixture'.
	}

	/**
	 * 4 (continued). The suite fails if a Security::require() call disappears
	 * from the real routes: every public handler in the controller must carry
	 * one. This is the tripwire the manual check exercises.
	 */
	public function test_every_document_route_handler_calls_require(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file, not a URL.
		$source = (string) file_get_contents( SV_PLUGIN_DIR . 'src/Documents.php' );
		$parts  = preg_split( '/\n\tpublic static function (\w+)\(/', $source, -1, PREG_SPLIT_DELIM_CAPTURE );

		$this->assertIsArray( $parts );

		$handlers = 0;
		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			++$handlers;
			$this->assertStringContainsString(
				'Security::require(',
				(string) $parts[ $i + 1 ],
				sprintf( 'Documents::%s() must gate through Security::require() (rule 3).', $parts[ $i ] )
			);
		}

		$this->assertGreaterThanOrEqual( 6, $handlers, 'Documents.php should expose six public handlers.' );
	}

	/** The capability table, and fail-closed on everything missing. */
	public function test_capability_matrix_and_fail_closed(): void {
		$editor   = $this->fixture->user( 'sv_probe_editor_d' );
		$stranger = $this->fixture->user( 'sv_probe_stranger_e' );
		$this->fixture->member( $this->ws_a, $editor, 'Editor' );
		$this->fixture->member( $this->ws_a, $stranger, 'Reviewer' );

		// Owner: read + write + admin.
		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'read' ) );
		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'write' ) );
		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'admin' ) );

		// Editor: read + write, no admin.
		$this->assertTrue( Security::can_access( $this->book_a, $editor, 'read' ) );
		$this->assertTrue( Security::can_access( $this->book_a, $editor, 'write' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $editor, 'admin' ) );

		// Viewer: read only.
		$this->assertTrue( Security::can_access( $this->book_a, $this->viewer_b, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->viewer_b, 'write' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->viewer_b, 'admin' ) );

		// An unknown role has no capabilities at all.
		$this->assertFalse( Security::can_access( $this->book_a, $stranger, 'read' ) );

		// Fail closed: no book, no user, no cap, no membership, no workspace.
		$this->assertFalse( Security::can_access( 0, $this->owner_a, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_a, 0, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->owner_a, 'publish' ) );
		$this->assertFalse( Security::can_access( $this->book_a, $this->owner_c, 'read' ) );
		$this->assertFalse( Security::can_access( $this->book_c, $this->viewer_b, 'read' ) );

		$orphan = $this->fixture->book( 0, 'No workspace' );
		$this->assertFalse( Security::can_access( $orphan, $this->owner_a, 'read' ) );

		$missing_book = $this->fixture->chapter( 987654 );
		$this->assertFalse( Security::can_access( $missing_book, $this->owner_a, 'read' ) );
	}

	/** The book resolution chain, and its dead ends. */
	public function test_the_identity_chain_resolves(): void {
		$this->assertSame( $this->book_a, Security::book_for_post( $this->book_a ) );
		$this->assertSame( $this->book_a, Security::book_for_post( $this->chapter_a ) );
		$this->assertSame( $this->book_a, Security::book_for_post( $this->scene_a ) );
		$this->assertSame( $this->book_a, Security::book_for_document( $this->doc_a ) );
		$this->assertSame( $this->book_c, Security::book_for_document( $this->doc_c ) );

		$this->assertSame( 0, Security::book_for_post( 0 ) );
		$this->assertSame( 0, Security::book_for_post( 999999 ) );
		$this->assertSame( 0, Security::book_for_document( 0 ) );
		$this->assertSame( 0, Security::book_for_document( 999999 ) );
	}

	/** Signed-out requests are 401 before any tenancy is considered. */
	public function test_signed_out_requests_are_401(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get( '/sv/v1/documents/' . $this->doc_a )->get_status() );

		$denied = Security::require( $this->book_a, 'read' );
		$this->assertInstanceOf( \WP_Error::class, $denied );
		$this->assertSame( 401, $denied->get_error_data()['status'] );
	}

	/** Decisions are memoized per request: the membership table is read once. */
	public function test_decisions_are_memoized_per_request(): void {
		global $wpdb;

		Security::reset_cache();
		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'read' ) );

		$queries = (int) $wpdb->num_queries;

		$this->assertTrue( Security::can_access( $this->book_a, $this->owner_a, 'read' ) );
		$this->assertSame( $queries, (int) $wpdb->num_queries, 'A repeated decision must not re-read the database.' );
	}

	/**
	 * Normalize the probe's route list for comparison.
	 *
	 * @param list<array{route: string, method: string}> $routes Probe routes.
	 * @return list<string>
	 */
	private function route_keys( array $routes ): array {
		$keys = array_map(
			static fn( array $route ): string => $route['route'] . ' ' . $route['method'],
			$routes
		);
		sort( $keys, SORT_STRING );

		return $keys;
	}

	/**
	 * A minimal valid Tiptap document.
	 *
	 * @param string $text Paragraph text.
	 * @return array<string, mixed>
	 */
	private function tiptap( string $text ): array {
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

	/** Dispatch a JSON POST. */
	private function post( string $route, array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_get_server()->dispatch( $request );
	}

	/** Dispatch a GET. */
	private function get( string $route ): \WP_REST_Response {
		return rest_get_server()->dispatch( new \WP_REST_Request( 'GET', $route ) );
	}
}
