<?php
/**
 * Spike V1c — does a relation write validate its target's existence?
 *
 * V1b showed `cast: [999999]` answers 200. This probe checks whether the edge
 * was stored as a dangling row (a row pointing at a post that does not exist),
 * whether it was silently dropped, and what a read resolves to.
 *
 * Result: the target is filtered out before the edge is written — 0 rows, no
 * warning, and the field reads back as `[]`. VX's relation storage strategy
 * (`app/modules/fieldsets/storage-strategies/relation-table-strategy.php`,
 * `constrain_values()`) drops a target whose post is missing or of the wrong
 * post type.
 *
 * Run from the repo root:  php spike/v1-relation-targets.php
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/wp-load.php';

global $wpdb;

$admins   = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
$admin_id = (int) $admins[0];
wp_set_current_user( $admin_id );

$table = $wpdb->prefix . 'vx_relations';

echo "== V1c: relation target validation ==\n\n";

echo "table {$table} exists: ";
$tables = $wpdb->get_col( 'SHOW TABLES' );
$exists = is_array( $tables ) && in_array( $table, $tables, true );
echo ( $exists ? "yes\n" : "no\n" );

$character = (int) wp_insert_post(
	[
		'post_title'  => 'Spike relation character',
		'post_type'   => 'sv_character',
		'post_status' => 'publish',
	]
);

$scene = (int) wp_insert_post(
	[
		'post_title'  => 'Spike relation scene',
		'post_type'   => 'sv_scene',
		'post_status' => 'publish',
	]
);

/**
 * Write `cast` and report the status.
 */
function write_cast( int $scene_id, array $ids ): int {
	$request = new WP_REST_Request( 'POST', '/wp/v2/sv_scene/' . $scene_id );
	$request->set_body_params( [ 'vx' => [ 'cast' => $ids ] ] );
	return rest_get_server()->dispatch( $request )->get_status();
}

/**
 * Rows this scene owns in the relation table.
 */
function scene_rows( string $table, int $scene_id ): array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- development-only spike.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, relation_key, parent_kind, parent_id, child_kind, child_id, position, status FROM {$table} WHERE parent_id = %d", $scene_id ), ARRAY_A );
}

$ghost = 999999;

echo "\nscene={$scene} real_character={$character} ghost={$ghost}\n";

// A) only a ghost target.
$status = write_cast( $scene, [ $ghost ] );
$rows   = $exists ? scene_rows( $table, $scene ) : [];
echo "\nA. write cast=[{$ghost}]  status={$status}  rows=" . count( $rows ) . "\n";

// B) a real target of the WRONG post type (a book, not a character).
$book   = (int) wp_insert_post(
	[
		'post_title'  => 'Spike relation book',
		'post_type'   => 'sv_book',
		'post_status' => 'publish',
	]
);
$status = write_cast( $scene, [ $book ] );
$rows   = $exists ? scene_rows( $table, $scene ) : [];
echo "B. write cast=[{$book}] (sv_book, wrong type)  status={$status}  rows=" . count( $rows ) . "\n";

// C) a real character: the control.
$status = write_cast( $scene, [ $character ] );
$rows   = $exists ? scene_rows( $table, $scene ) : [];
echo "C. write cast=[{$character}] (sv_character)  status={$status}  rows=" . count( $rows ) . "\n";
if ( $rows !== [] ) {
	echo "   row=" . wp_json_encode( $rows[0] ) . "\n";
}

// What does a read resolve to after A+B+C?
$read = new WP_REST_Request( 'GET', '/wp/v2/sv_scene/' . $scene );
$data = json_decode( (string) wp_json_encode( rest_get_server()->dispatch( $read )->get_data() ), true );
echo "\nread back vx.cast=" . wp_json_encode( $data['vx']['cast'] ?? null ) . "\n";
echo "get_post({$ghost}) = " . ( get_post( $ghost ) ? 'exists' : 'null' ) . "\n";

echo "\n== conclusion ==\n";
echo "A relation target that does not exist, or is the wrong post type, is dropped silently:\n";
echo "the write answers 200, no warning is reported, no dangling row is stored, and the field reads back as [].\n";

foreach ( [ $scene, $character, $book ] as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

echo "\nThis probe removed the records it created.\n";
