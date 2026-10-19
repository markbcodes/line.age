<?php
/**
 * Spike V1b — which inputs actually fail a VX `vx` write, strict and lenient.
 *
 * The brief names "`vx_strict=1` plus a bad relation id" as the failure
 * injector. This probe measures that and four realistic alternatives, and
 * reports which ones produce a blocking response. On this model there is
 * exactly one: an unknown `vx` key.
 *
 * Why the others do not fail:
 *   - `required` is compiled out. VX's validation registry ships with
 *     `REQUIRED_FIELDS_ENABLED = false` (`app/modules/fieldsets/validation/registry.php`),
 *     so `name` being required on sv_character never blocks a write.
 *   - Relation targets are not checked for existence or post type. The value is
 *     filtered out silently; see `v1-relation-targets.php`.
 *   - Number/list/relation bounds are hard errors, not warnings, and none of the
 *     modelled fields configure any, so there is nothing for `vx_strict` to
 *     escalate. `vx_strict` turns VX's *warnings* into a refusal; on this model
 *     only fieldset config-save produces `core_shadow_warnings`.
 *
 * Run from the repo root:  php spike/v1-injector-census.php
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/wp-load.php';

$admins   = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
$admin_id = (int) $admins[0];
wp_set_current_user( $admin_id );

$created = [];

/**
 * One vx write, normalized to the status and the blocking messages.
 */
function census_write( string $route, array $vx, bool $strict ): array {
	$params = [ 'vx' => $vx ];
	if ( $strict ) {
		$params['vx_strict'] = 1;
	}

	$request = new WP_REST_Request( 'POST', $route );
	$request->set_body_params( $params );

	$response = rest_get_server()->dispatch( $request );
	$data     = json_decode( (string) wp_json_encode( $response->get_data() ), true );

	return [
		'status' => $response->get_status(),
		'code'   => is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '',
		'errors' => is_array( $data ) && isset( $data['errors'] ) ? $data['errors'] : [],
	];
}

function census_post( string $post_type, string $title ): int {
	return (int) wp_insert_post(
		[
			'post_title'  => $title,
			'post_type'   => $post_type,
			'post_status' => 'publish',
		]
	);
}

function census_report( string $label, array $strict, array $lenient ): void {
	printf( "%-46s strict: %s %-16s lenient: %s %s\n", $label, $strict['status'], $strict['code'], $lenient['status'], $lenient['code'] );
}

echo "== V1b: failure injectors ==\n\n";
echo "injector                                       strict result     lenient result\n";
echo "-------------------------------------------------------------------------------\n";

// 1. Bad relation id — the brief's named injector.
$scene = census_post( 'sv_scene', 'Census scene (relation)' );
$created[] = $scene;
$lenient = census_write( '/wp/v2/sv_scene/' . $scene, [ 'cast' => [ 999999 ] ], false );
$strict  = census_write( '/wp/v2/sv_scene/' . $scene, [ 'cast' => [ 999999 ] ], true );
census_report( 'bad relation id  cast=[999999]', $strict, $lenient );

// 2. Unknown vx key — the one injector that blocks.
$scene = census_post( 'sv_scene', 'Census scene (unknown key)' );
$created[] = $scene;
$lenient = census_write( '/wp/v2/sv_scene/' . $scene, [ 'no_such_field' => 1 ], false );
$strict  = census_write( '/wp/v2/sv_scene/' . $scene, [ 'no_such_field' => 1 ], true );
census_report( 'unknown vx key  no_such_field=1', $strict, $lenient );

// 3. Non-numeric value on a number field.
$scene = census_post( 'sv_scene', 'Census scene (non-numeric)' );
$created[] = $scene;
$lenient = census_write( '/wp/v2/sv_scene/' . $scene, [ 'ordinal' => 'not-a-number' ], false );
$strict  = census_write( '/wp/v2/sv_scene/' . $scene, [ 'ordinal' => 'not-a-number' ], true );
census_report( 'non-numeric number  ordinal="not-a-number"', $strict, $lenient );

// 4. Required field left empty.
$character = census_post( 'sv_character', 'Census character (empty required name)' );
$created[] = $character;
$lenient = census_write( '/wp/v2/sv_character/' . $character, [ 'name' => '', 'role' => 'minor' ], false );
$strict  = census_write( '/wp/v2/sv_character/' . $character, [ 'name' => '', 'role' => 'minor' ], true );
census_report( 'empty required  sv_character.name=""', $strict, $lenient );

// 5. Out-of-range number.
$scene = census_post( 'sv_scene', 'Census scene (out of range)' );
$created[] = $scene;
$lenient = census_write( '/wp/v2/sv_scene/' . $scene, [ 'ordinal' => -5 ], false );
$strict  = census_write( '/wp/v2/sv_scene/' . $scene, [ 'ordinal' => -5 ], true );
census_report( 'out-of-range number  ordinal=-5', $strict, $lenient );

echo "\n== conclusion ==\n";
echo "A strict-mode refusal needs a VX warning or error. On this model only an unknown `vx` key produces one.\n";
echo "A bad relation id is not validated for existence or post type: it is accepted silently.\n";

foreach ( $created as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

echo "\nThis probe removed the records it created.\n";
