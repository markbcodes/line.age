<?php
/**
 * Spike V3 (helper) — print the `sv_scene` entry of
 * `GET vx-framework/v1/rest/schema`.
 *
 * The schema route is editor-only, and it is a static inventory (no viewer, no
 * `can_read` applied), so it is read as the administrator. Used by
 * `v3-config-roundtrip.sh` to diff the fieldset before and after a round trip,
 * and on its own for the manual verification paste.
 *
 * Run from the repo root:  php spike/v3-schema-dump.php
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/wp-load.php';

$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
wp_set_current_user( (int) $admins[0] );

$request  = new WP_REST_Request( 'GET', '/vx-framework/v1/rest/schema' );
$response = rest_get_server()->dispatch( $request );
$schema   = json_decode( (string) wp_json_encode( $response->get_data() ), true );

if ( ! is_array( $schema ) ) {
	fwrite( STDERR, "The schema route did not return a JSON object.\n" );
	exit( 1 );
}

$scene = null;
foreach ( (array) ( $schema['resources']['post_types'] ?? [] ) as $entry ) {
	if ( is_array( $entry ) && ( $entry['key'] ?? '' ) === 'sv_scene' ) {
		$scene = $entry;
	}
}

if ( null === $scene ) {
	echo "sv_scene is not listed in the schema.\n";
	exit( 0 );
}

echo wp_json_encode( $scene, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
