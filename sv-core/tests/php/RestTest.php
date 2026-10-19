<?php
/**
 * REST surface: the plugin registers the sv/v1 namespace, its public ping and
 * its skeleton status route. Proves the routes are live, not just declared.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;

final class RestTest extends TestCase
{
	protected function tearDown(): void
	{
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_sv_v1_rest_namespace_is_registered(): void
	{
		// rest_get_server() fires rest_api_init, where Rest::register() adds
		// the routes; get_namespaces() then lists every registered namespace.
		$namespaces = rest_get_server()->get_namespaces();

		$this->assertContains( 'sv/v1', $namespaces, 'sv/v1 REST namespace is not registered' );
	}

	public function test_status_route_is_registered_under_sv_v1(): void
	{
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/sv/v1/status', $routes );
	}

	public function test_ping_route_is_registered_under_sv_v1(): void
	{
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/sv/v1/ping', $routes );
	}

	public function test_ping_is_public_and_reports_no_user_when_signed_out(): void
	{
		wp_set_current_user( 0 );

		$request  = new \WP_REST_Request( 'GET', '/sv/v1/ping' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'ok'   => true,
				'user' => null,
			),
			$response->get_data()
		);
	}

	public function test_ping_reports_the_signed_in_user(): void
	{
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$this->assertNotSame( array(), $admins, 'No administrator exists in this WordPress install' );

		$user = get_userdata( (int) $admins[0] );
		$this->assertNotFalse( $user );

		wp_set_current_user( (int) $user->ID );

		$request  = new \WP_REST_Request( 'GET', '/sv/v1/ping' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( true, $data['ok'] );
		$this->assertSame( $user->user_login, $data['user'] );
	}
}
