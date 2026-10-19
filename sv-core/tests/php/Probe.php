<?php
/**
 * The tenant-scope probe. It enumerates every registered sv/v1 route that
 * reads an {id} (and {n}) placeholder and dispatches it once per supplied id,
 * reporting the status of each. A route that answers 2xx to a user who owns
 * none of those ids is a leak, so the probe fails on a missing Security::require()
 * instead of waiting for someone to remember to write that assertion.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Tests;

/**
 * Route enumerator and dispatcher for tenant-scope probes.
 */
final class Probe {

	/**
	 * Every registered sv/v1 handler whose route reads an {id}.
	 *
	 * @return list<array{route: string, method: string}>
	 */
	public static function id_routes(): array {
		$found = array();

		foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
			if ( ! is_string( $route ) || ! str_starts_with( $route, '/sv/v1/' ) || ! str_contains( $route, '(?P<id>' ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				foreach ( array_keys( (array) ( $handler['methods'] ?? array() ) ) as $method ) {
					if ( is_string( $method ) ) {
						$found[] = array(
							'route'  => $route,
							'method' => strtoupper( $method ),
						);
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Dispatch every matching sv/v1 id route once per id.
	 *
	 * @param list<int>                       $ids      Ids to send.
	 * @param list<string>                    $methods  Restrict to these methods.
	 * @param string                          $contains Restrict to routes containing this text.
	 * @param list<array{route:string,method:string}>|null $routes Explicit route set.
	 * @return list<array{route: string, method: string, id: int, status: int, leak: bool}>
	 */
	public static function run( array $ids, array $methods = array(), string $contains = '', ?array $routes = null ): array {
		$rows = array();

		foreach ( null === $routes ? self::id_routes() : $routes as $entry ) {
			if ( array() !== $methods && ! in_array( $entry['method'], $methods, true ) ) {
				continue;
			}
			if ( '' !== $contains && ! str_contains( $entry['route'], $contains ) ) {
				continue;
			}

			foreach ( $ids as $id ) {
				$status = self::dispatch( $entry['method'], self::path( $entry['route'], (int) $id ) );

				$rows[] = array(
					'route'  => $entry['route'],
					'method' => $entry['method'],
					'id'     => (int) $id,
					'status' => $status,
					'leak'   => $status < 400,
				);
			}
		}

		return $rows;
	}

	/**
	 * Fill the placeholders in a route pattern.
	 *
	 * @param string $route Route pattern.
	 * @param int    $id    Id for {id}.
	 */
	public static function path( string $route, int $id ): string {
		return str_replace(
			array( '(?P<id>\d+)', '(?P<id>[\d]+)', '(?P<n>\d+)' ),
			array( (string) $id, (string) $id, '1' ),
			$route
		);
	}

	/**
	 * Dispatch one request and return its status.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Route path with placeholders filled.
	 */
	public static function dispatch( string $method, string $path ): int {
		$request = new \WP_REST_Request( $method, $path );
		$request->set_header( 'content-type', 'application/json' );

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request->set_body(
				(string) wp_json_encode(
					array(
						'content_json'   => array(
							'type'    => 'doc',
							'content' => array(
								array(
									'type'    => 'paragraph',
									'content' => array(
										array(
											'type' => 'text',
											'text' => 'probe',
										),
									),
								),
							),
						),
						'base_hash'      => null,
						'schema_version' => 1,
						'version'        => 1,
					)
				)
			);
		}

		return (int) rest_get_server()->dispatch( $request )->get_status();
	}

	/**
	 * Render the probe results as a fixed-width table.
	 *
	 * @param list<array{route: string, method: string, id: int, status: int, leak: bool}> $rows Probe rows.
	 */
	public static function table( array $rows ): string {
		$out  = sprintf( "%-48s %-6s %-8s %-6s %s\n", 'sv/v1 route', 'method', 'id', 'status', 'verdict' );
		$out .= str_repeat( '-', 48 + 1 ) . str_repeat( '-', 6 + 1 ) . str_repeat( '-', 8 + 1 ) . str_repeat( '-', 6 + 1 ) . str_repeat( '-', 10 ) . "\n";

		foreach ( $rows as $row ) {
			$out .= sprintf(
				"%-48s %-6s %-8d %-6d %s\n",
				$row['route'],
				$row['method'],
				$row['id'],
				$row['status'],
				$row['leak'] ? 'LEAK' : 'denied'
			);
		}

		return $out;
	}
}
