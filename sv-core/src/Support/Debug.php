<?php
/**
 * Request debugger. Adds a `_debug` object to sv/v1 responses when the request
 * carries ?sv_debug=1 and the current user is an administrator. Anything else
 * is returned untouched.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\Support;

/**
 * REST request debugger.
 */
final class Debug {

	/**
	 * When the sv/v1 request was dispatched, for the ms measurement.
	 *
	 * @var float
	 */
	private static float $started_at = 0.0;

	/**
	 * Hooks the debug filters.
	 */
	public static function attach(): void {
		add_filter( 'rest_pre_dispatch', array( self::class, 'mark_start' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( self::class, 'annotate' ), 10, 3 );
		Log::debug( 'Debug::attach' );
	}

	/**
	 * Records the dispatch start time for opted-in requests.
	 *
	 * @param mixed $result  Pre-dispatch result, passed through unchanged.
	 * @param mixed $server  REST server instance.
	 * @param mixed $request Request being dispatched.
	 * @return mixed The result untouched.
	 */
	public static function mark_start( $result, $server, $request ) {
		if ( self::wants( $request ) ) {
			self::$started_at = microtime( true );
		}

		return $result;
	}

	/**
	 * Adds _debug to opted-in sv/v1 responses for administrators.
	 *
	 * @param mixed $response Response being sent.
	 * @param mixed $server   REST server instance.
	 * @param mixed $request  Request that produced the response.
	 * @return mixed The response, annotated for admins who opted in.
	 */
	public static function annotate( $response, $server, $request ) {
		if ( ! self::wants( $request ) || ! current_user_can( 'manage_options' ) ) {
			return $response;
		}

		if ( ! $response instanceof \WP_REST_Response ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}

		global $wpdb;

		$started_at     = self::$started_at > 0.0 ? self::$started_at : microtime( true );
		$data['_debug'] = array(
			'ms'      => round( ( microtime( true ) - $started_at ) * 1000, 2 ),
			'queries' => (int) $wpdb->num_queries,
		);

		$response->set_data( $data );
		Log::debug( 'Debug::annotate', $data['_debug'] );

		return $response;
	}

	/**
	 * True only for sv/v1 requests that opted in with ?sv_debug=1.
	 *
	 * @param mixed $request Request being dispatched.
	 */
	private static function wants( $request ): bool {
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		if ( '1' !== (string) $request->get_param( 'sv_debug' ) ) {
			return false;
		}

		return str_starts_with( (string) $request->get_route(), '/sv/v1/' );
	}
}
