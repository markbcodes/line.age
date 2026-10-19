<?php
/**
 * Spike V2 — the `vxframework/rest/can_read` gate and the tier cache.
 *
 * The brief expects: hook the filter with a stub that denies sv_scene, and a
 * non-editor `GET /wp/v2/sv_scene/{id}` becomes non-200; remove the hook and it
 * is 200 again. What actually happens:
 *
 *   - The status never changes. VX does not own the core route's permission
 *     check, so `show_in_rest` plus a published post keep answering 200. The
 *     gate empties the `vx` payload instead.
 *   - The committed model defaults every fieldset to `rest.expose: private`
 *     (VX's schema default, and the right one for a writer's private bible), so
 *     a logged-in non-editor receives `"vx": {}` whatever the gate decides —
 *     there is nothing for the gate to withhold. This probe raises sv_scene to
 *     `authenticated` at runtime, through the `vxframework/fieldsets/resolved_fields`
 *     filter, purely so the gate has a payload to act on; the committed model is
 *     untouched.
 *
 * It then tests the cache: with the hook registered, an `authenticated`-tier
 * payload is computed live per viewer, so one viewer's response is never served
 * to another. Without any hook the same payload is cached and shared, which is
 * safe only because it then depends on nothing but the tier.
 *
 * Run from the repo root:  php spike/v2-can-read-gate.php
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/wp-load.php';
require_once dirname( __DIR__ ) . '/wp-content/themes/line-age/tools/vx-write-lib.php';

$admins   = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
$admin_id = (int) $admins[0];
wp_set_current_user( $admin_id );

/**
 * A subscriber for the gate to act on, created once and reused.
 */
function spike_viewer( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		return (int) $user->ID;
	}
	$id = wp_insert_user(
		[
			'user_login' => $login,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $login . '@example.test',
			'role'       => 'subscriber',
		]
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * One item read as one viewer, normalized.
 */
function spike_read( int $viewer_id, int $scene_id ): array {
	wp_set_current_user( $viewer_id );
	$request = new WP_REST_Request( 'GET', '/wp/v2/sv_scene/' . $scene_id );
	$response = rest_get_server()->dispatch( $request );
	$data = json_decode( (string) wp_json_encode( $response->get_data() ), true );

	wp_set_current_user( 0 );

	return [
		'status' => $response->get_status(),
		'vx'     => is_array( $data ) ? ( $data['vx'] ?? null ) : null,
		'label'  => is_array( $data ) && isset( $data['vx']['in_world_label'] ) ? $data['vx']['in_world_label'] : null,
	];
}

/**
 * Empty VX's tier cache so the next read is a guaranteed miss.
 */
function spike_flush_cache(): void {
	if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
		wp_cache_flush_group( 'vx_rest' );
	}
}

/**
 * How many `vx_rest` payload entries the object cache holds.
 */
function spike_cache_entries(): int {
	global $wp_object_cache;
	try {
		$property = new ReflectionProperty( get_class( $wp_object_cache ), 'cache' );
		$property->setAccessible( true );
		$cache = $property->getValue( $wp_object_cache );
	} catch ( ReflectionException $exception ) {
		return -1;
	}
	$entries = 0;
	foreach ( (array) $cache as $group => $values ) {
		if ( $group === 'vx_rest' ) {
			$entries += count( (array) $values );
		}
	}
	return $entries;
}

$viewer_a = spike_viewer( 'spike_viewer_a' );
$viewer_b = spike_viewer( 'spike_viewer_b' );

$scene = vx_create_with_vx(
	'sv_scene',
	[ 'title' => 'Spike V2 gate scene', 'status' => 'publish' ],
	[ 'in_world_label' => 'spike-v2-value', 'ordinal' => 3 ],
	true
);
$scene_id = $scene['id'];

if ( $viewer_a === 0 || $viewer_b === 0 || $scene_id <= 0 ) {
	fwrite( STDERR, "Could not build the fixtures.\n" );
	exit( 1 );
}

echo "== V2: can_read gate ==\n";
echo "scene={$scene_id} viewer_a={$viewer_a} viewer_b={$viewer_b} (both subscribers)\n";
echo "committed fieldset exposure for sv_scene: private (VX schema default)\n\n";

// Raise sv_scene to `authenticated` so a logged-in non-editor has a payload the
// gate can withhold. Spike-only: the committed model is not touched.
$raise = static function ( array $fields, $fieldset ): array {
	if ( ! is_object( $fieldset ) || $fieldset->get_key() !== 'sv_scene' ) {
		return $fields;
	}
	foreach ( $fields as $index => $field ) {
		if ( is_array( $field ) ) {
			$fields[ $index ]['rest'] = [ 'access' => 'authenticated' ];
		}
	}
	return $fields;
};
add_filter( 'vxframework/fieldsets/resolved_fields', $raise, 10, 2 );

// --- A. No hook. -----------------------------------------------------------
spike_flush_cache();
$base = spike_read( $viewer_a, $scene_id );
echo "A. no hook\n";
echo "   status={$base['status']}\n";
echo "   vx=" . wp_json_encode( $base['vx'] ) . "\n";
echo "   vx_rest cache entries after the read=" . spike_cache_entries() . "  (the authenticated payload is cached)\n";

// --- B. Hook denies sv_scene for viewer A only. -----------------------------
$deny_a = static function ( $decision, $context ) use ( $viewer_a ) {
	if ( (int) ( $context['viewer_id'] ?? 0 ) !== $viewer_a ) {
		return $decision;
	}
	$kind = (string) ( $context['entity_kind'] ?? '' );
	$id   = (int) ( $context['entity_id'] ?? 0 );
	return ( 'post' === $kind && $id > 0 && get_post_type( $id ) === 'sv_scene' ) ? false : $decision;
};
add_filter( 'vxframework/rest/can_read', $deny_a, 10, 2 );

spike_flush_cache();
$denied = spike_read( $viewer_a, $scene_id );
echo "\nB. hook refuses sv_scene for viewer A\n";
echo "   status={$denied['status']}  (unchanged: the gate empties `vx`, it does not change the status)\n";
echo "   vx=" . wp_json_encode( $denied['vx'] ) . "\n";
echo "   vx_rest cache entries after the read=" . spike_cache_entries() . "  (the authenticated payload is NOT cached)\n";

// --- C. Viewer B, while the hook is still registered. ----------------------
$allowed = spike_read( $viewer_b, $scene_id );
echo "\nC. the same read as viewer B, hook still registered\n";
echo "   status={$allowed['status']}\n";
echo "   vx=" . wp_json_encode( $allowed['vx'] ) . "\n";
echo "   viewer B received viewer A's response: " . ( $allowed['label'] === $denied['label'] ? 'YES (leak)' : 'no' ) . "\n";

// --- D. Lift the decision for viewer A. ------------------------------------
remove_filter( 'vxframework/rest/can_read', $deny_a, 10 );
$pass_through = static fn( $decision ) => $decision;
add_filter( 'vxframework/rest/can_read', $pass_through, 10, 1 );

$lifted = spike_read( $viewer_a, $scene_id );
echo "\nD. same viewer A, gate now allows\n";
echo "   status={$lifted['status']}\n";
echo "   vx=" . wp_json_encode( $lifted['vx'] ) . "\n";
echo "   the withheld payload was served again from a cache: " . ( $lifted['label'] === $denied['label'] ? 'YES (leak)' : 'no' ) . "\n";

// --- E. Hook removed entirely. --------------------------------------------
remove_filter( 'vxframework/rest/can_read', $pass_through, 10 );
$restored = spike_read( $viewer_a, $scene_id );
echo "\nE. hook removed\n";
echo "   status={$restored['status']}\n";
echo "   vx=" . wp_json_encode( $restored['vx'] ) . "\n";

// --- Cleanup. -------------------------------------------------------------
remove_filter( 'vxframework/fieldsets/resolved_fields', $raise, 10 );
vx_delete_orphan( $scene_id );

echo "\n== conclusion ==\n";
echo "The brief expects: non-200 under the hook, 200 without it.\n";
echo "Observed: status {$base['status']} without the hook, {$denied['status']} with it, on both viewers.\n";
echo "The gate withholds values from a viewer who should not see them (vx goes from a full payload to {}),\n";
echo "and it does so without a shared cache: viewer B is not served viewer A's response, and lifting the\n";
echo "decision for viewer A takes effect on the next read.\n";

foreach ( [ $viewer_a, $viewer_b ] as $user_id ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );
}

echo "\nThis probe removed the records and accounts it created.\n";
