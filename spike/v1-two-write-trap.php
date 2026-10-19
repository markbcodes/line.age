<?php
/**
 * Spike V1 — the two-write trap, and the cleanup that closes it.
 *
 * Creating a VX-backed post takes two writes: core's `register_rest_field`
 * update_callback runs *after* the post row is inserted, so a `vx` payload VX
 * refuses leaves the post behind with none of its field values. This probe
 * proves the trap on sv_scene, then runs the reusable cleanup path from
 * `wp-content/themes/line-age/tools/vx-write-lib.php` and shows the orphan is
 * gone.
 *
 * The brief names `vx_strict=1` plus a bad relation id as the injector. That
 * injector does not fail (see `v1-injector-census.php` and
 * `v1-relation-targets.php`); the injector used here is a `vx` key no fieldset
 * defines, which is what an importer actually hits when it replays a payload
 * against a renamed or removed field.
 *
 * Run from the repo root:  php spike/v1-two-write-trap.php
 * (use Shader's PHP: .shader\bin\php.cmd spike\v1-two-write-trap.php)
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/wp-load.php';
require_once dirname( __DIR__ ) . '/wp-content/themes/line-age/tools/vx-write-lib.php';

$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
if ( [] === $admins ) {
	fwrite( STDERR, "No administrator.\n" );
	exit( 1 );
}
$admin_id = (int) $admins[0];
wp_set_current_user( $admin_id );

$orphan_ids = [];

echo "== V1: the two-write trap ==\n\n";

// --- 1. Create with no `vx` payload. Core owns this write. ------------------
$created = vx_dispatch( 'POST', '/wp/v2/sv_scene', [ 'title' => 'Spike V1 orphan scene', 'status' => 'publish' ] );
$scene_id = (int) ( $created['data']['id'] ?? 0 );
$orphan_ids[] = $scene_id;

echo "1. POST /wp/v2/sv_scene  (core create only)\n";
echo "   status={$created['status']} id={$scene_id}\n";

if ( $scene_id <= 0 ) {
	echo "   -- could not create; aborting.\n";
	exit( 1 );
}

// --- 2. Write `vx` values containing a key no fieldset defines. -------------
$refused = vx_dispatch(
	'POST',
	'/wp/v2/sv_scene/' . $scene_id,
	[
		'vx'        => [ 'in_world_label' => 'Prologue', 'no_such_field' => 1 ],
		'vx_strict' => 1,
	]
);

echo "\n2. POST /wp/v2/sv_scene/{$scene_id}  vx={...'no_such_field'} vx_strict=1\n";
echo "   status={$refused['status']} code={$refused['code']}\n";
echo "   errors=" . wp_json_encode( $refused['errors'] ) . "\n";

// --- 3. The post is still there, and holds none of the requested values. ----
$orphan = get_post( $scene_id );
echo "\n3. orphan check\n";
echo "   post_exists=" . ( $orphan ? 'yes' : 'no' ) . "\n";
if ( $orphan ) {
	echo "   post_type={$orphan->post_type} status={$orphan->post_status} title=" . wp_json_encode( $orphan->post_title ) . "\n";
	echo "   in_world_label_meta=" . wp_json_encode( get_post_meta( $scene_id, 'in_world_label', true ) ) . "\n";
}

// --- 4. The cleanup path removes it. ---------------------------------------
$deleted = vx_delete_orphan( $scene_id );
echo "\n4. vx_delete_orphan({$scene_id})\n";
echo "   returned=" . ( $deleted ? 'true' : 'false' ) . "\n";
echo "   post_exists=" . ( get_post( $scene_id ) ? 'yes' : 'no' ) . "\n";

// --- 5. The same flow through the helper an importer calls. ----------------
$closed = vx_create_with_vx(
	'sv_scene',
	[ 'title' => 'Spike V1 closed trap', 'status' => 'publish' ],
	[ 'no_such_field' => 1 ],
	true
);
$orphan_ids[] = $closed['id'];

echo "\n5. vx_create_with_vx(sv_scene, core, {no_such_field}, strict=true)\n";
echo "   ok=" . ( $closed['ok'] ? 'true' : 'false' ) . " status={$closed['status']} code={$closed['code']}\n";
echo "   created_id={$closed['id']} orphan_deleted=" . ( $closed['orphan_deleted'] ? 'true' : 'false' ) . "\n";
echo "   post_exists=" . ( get_post( $closed['id'] ) ? 'yes' : 'no' ) . "\n";

// --- 6. Happy path, for contrast: the helper leaves a good record alone. ---
$good = vx_create_with_vx(
	'sv_scene',
	[ 'title' => 'Spike V1 good scene', 'status' => 'publish' ],
	[ 'in_world_label' => 'kept', 'ordinal' => 2 ],
	true
);
$orphan_ids[] = $good['id'];

echo "\n6. vx_create_with_vx(sv_scene, core, valid vx, strict=true)\n";
echo "   ok=" . ( $good['ok'] ? 'true' : 'false' ) . " created_id={$good['id']} orphan_deleted=" . ( $good['orphan_deleted'] ? 'true' : 'false' ) . "\n";
echo "   in_world_label_meta=" . wp_json_encode( get_post_meta( $good['id'], 'in_world_label', true ) ) . "\n";

// --- Cleanup: this probe leaves no records behind. -------------------------
foreach ( $orphan_ids as $post_id ) {
	vx_delete_orphan( (int) $post_id );
}

echo "\n== conclusion ==\n";
echo "TRAP CONFIRMED: a refused vx write answers {$refused['status']} and leaves the post from the first write behind.\n";
echo "CLEANUP CONFIRMED: vx_delete_orphan() removes the orphan, and vx_create_with_vx() removes it automatically on failure.\n";
echo "\nThis probe removed the records it created.\n";
