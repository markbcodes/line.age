<?php
/**
 * REST surface: the plugin registers the sv/v1 namespace and its skeleton
 * status route. Proves the namespace is live, not just declared in source.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;

final class RestTest extends TestCase
{
	public function test_sv_v1_rest_namespace_is_registered(): void
	{
		// rest_get_server() fires rest_api_init, where Rest::register() adds
		// the routes; get_namespaces() then lists every registered namespace.
		$namespaces = rest_get_server()->get_namespaces();

		$this->assertContains('sv/v1', $namespaces, 'sv/v1 REST namespace is not registered');
	}

	public function test_status_route_is_registered_under_sv_v1(): void
	{
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey('/sv/v1/status', $routes);
	}
}
