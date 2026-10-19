<?php
/**
 * Debugger: _debug appears on sv/v1 responses for administrators who opt in
 * with ?sv_debug=1, and never for anyone else.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;

final class DebugTest extends TestCase
{
	private int $subscriber_id = 0;

	protected function tearDown(): void
	{
		wp_set_current_user( 0 );

		if ( 0 < $this->subscriber_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->subscriber_id );
			$this->subscriber_id = 0;
		}

		parent::tearDown();
	}

	public function test_debug_is_added_for_an_admin_who_opts_in(): void
	{
		wp_set_current_user( $this->admin_id() );

		$data = $this->dispatch_ping( true );

		$this->assertArrayHasKey( '_debug', $data );
		$this->assertArrayHasKey( 'ms', $data['_debug'] );
		$this->assertArrayHasKey( 'queries', $data['_debug'] );
		$this->assertIsFloat( $data['_debug']['ms'] );
		$this->assertIsInt( $data['_debug']['queries'] );
		$this->assertGreaterThanOrEqual( 0, $data['_debug']['ms'] );
	}

	public function test_debug_is_absent_without_the_flag(): void
	{
		wp_set_current_user( $this->admin_id() );

		$data = $this->dispatch_ping( false );

		$this->assertArrayNotHasKey( '_debug', $data );
	}

	public function test_debug_is_absent_for_a_subscriber(): void
	{
		$this->subscriber_id = $this->create_subscriber();
		wp_set_current_user( $this->subscriber_id );

		$data = $this->dispatch_ping( true );

		$this->assertArrayNotHasKey( '_debug', $data );
	}

	/** @return array<string, mixed> */
	private function dispatch_ping( bool $debug ): array
	{
		$request = new \WP_REST_Request( 'GET', '/sv/v1/ping' );
		if ( $debug ) {
			$request->set_query_params( array( 'sv_debug' => '1' ) );
		}

		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		// dispatch() applies rest_pre_dispatch but not rest_post_dispatch;
		// serve_request() (the HTTP entry point) applies the latter.
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		return $data;
	}

	private function admin_id(): int
	{
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);

		$this->assertNotSame( array(), $admins, 'No administrator exists in this WordPress install' );

		return (int) $admins[0];
	}

	private function create_subscriber(): int
	{
		$id = wp_insert_user(
			array(
				'user_login' => 'sv_test_sub_' . wp_generate_password( 8, false, false ),
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => 'sv-test-sub@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->assertIsInt( $id, 'Could not create a subscriber for the test' );

		return $id;
	}
}
