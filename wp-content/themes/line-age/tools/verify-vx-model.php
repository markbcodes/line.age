<?php
/**
 * Development-only verification of this theme's VX model.
 *
 * Usage from this theme directory:
 *
 *   php tools/verify-vx-model.php
 *
 * Run it with Shader's PHP, the one this site runs on: .shader/bin/php in place
 * of php (.shader\bin\php.cmd on Windows). Use php only when that file does
 * not exist.
 *
 * Run `php tools/check.php` first: it catches a vx-config/ file VX skipped or a
 * key a template reads that the model does not define. This then proves the
 * model actually behaves the way the app needs, against the running site:
 *
 *   1. model + schema   — every post type and fieldset resolves, and
 *                         `GET vx-framework/v1/rest/schema` reports the sv_scene
 *                         fields and their types.
 *   2. V1 two-write trap — creating through `/wp/v2/sv_scene` and failing the
 *                         `vx` write leaves an orphan post; the cleanup path in
 *                         tools/vx-write-lib.php removes it.
 *   3. V2 can_read gate  — `vxframework/rest/can_read` withholds a viewer's
 *                         field values, and the tier cache never serves one
 *                         viewer's payload to another.
 *   4. V3 config round trip — exporting the sv_scene fieldset, deleting it from
 *                         vx-config/, and importing the export reproduces the
 *                         same fieldset, then restores the committed file.
 *
 * Exits non-zero while any check fails. Sections 2-4 create and remove their own
 * fixtures (a few `sv_scene` posts and two `sv_model_*` subscribers), and
 * section 4 restores the fieldset file it deletes even when a check fails.
 */

declare(strict_types=1);

use VxFramework\Config\Code_Source_Loader;
use VxFramework\Config\Export\Exporter;
use VxFramework\Config\Resolver;
use VxFramework\Config\State;
use VxFramework\Config\Sync\Bundle_Importer;
use VxFramework\Config\Sync\Group_Storage;
use VxFramework\Config\Sync\Sync_Service;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/vx-write-lib.php';

/**
 * The seven field keys `sv_scene` exposes, and the field type each must
 * resolve to. An export that silently drops a relation, or an import that
 * turns a number into text, shows up here.
 */
const VX_VERIFY_SCENE_FIELDS = [
	'chapter'        => 'number',
	'ordinal'        => 'number',
	'title'          => 'text',
	'pov_character'  => 'post_relation',
	'in_world_start' => 'number',
	'in_world_end'   => 'number',
	'in_world_label' => 'text',
	'cast'           => 'post_relation',
];

/** Every object this theme's vx-config/ defines. */
const VX_VERIFY_POST_TYPES = ['sv_book', 'sv_act', 'sv_chapter', 'sv_scene', 'sv_character', 'sv_location', 'sv_plot_beat'];
const VX_VERIFY_FIELDSETS  = ['sv_book', 'sv_act', 'sv_chapter', 'sv_scene', 'sv_character', 'sv_location', 'sv_plot_beat'];

$verifier = new Vx_Model_Verifier();
$exit     = $verifier->run();
exit($exit);

/**
 * The verification run: fixtures in, assertions out, everything removed again.
 */
final class Vx_Model_Verifier
{
	private int $passed = 0;

	private int $failed = 0;

	/** @var array<int, string> */
	private array $notes = [];

	/** @var array<int, int> Posts this run created, removed at the end. */
	private array $posts = [];

	/** @var array<int, int> Users this run created, removed at the end. */
	private array $users = [];

	/** @var array{file: string, bytes: string}|null The fieldset file section 4 removes. */
	private ?array $backup = null;

	/** Guards `restore_config()` against running twice (the `finally` and the shutdown hook). */
	private bool $config_restored = false;

	/** The administrator every schema read runs as. */
	private int $admin = 0;

	/** @var array<int, array{0: string, 1: callable, 2: int}> Filters this run registered. */
	private array $filters = [];

	private string $temp_dir = '';

	public function run(): int
	{
		$this->admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID'])[0];
		wp_set_current_user($this->admin);

		$this->temp_dir = trailingslashit(get_temp_dir()) . 'sv-vx-verify-' . wp_generate_password(8, false, false);
		wp_mkdir_p($this->temp_dir);

		register_shutdown_function([$this, 'restore_config']);

		$this->verify_model_and_schema();
		$this->verify_two_write_trap();
		$this->verify_can_read_gate();
		$this->verify_config_roundtrip();

		$this->cleanup();

		wp_set_current_user($this->admin);
		$this->section('result');
		foreach ($this->notes as $note) {
			echo "  note {$note}\n";
		}
		if ($this->failed === 0) {
			echo "  ok   {$this->passed} check(s) passed.\n";
			return 0;
		}

		fwrite(STDERR, "  FAIL {$this->failed} of " . ($this->passed + $this->failed) . " check(s) failed.\n");
		return 1;
	}

	/* ---------------------------------------------------------------------
	 * 1. The model and what the schema says about it.
	 * ------------------------------------------------------------------ */

	private function verify_model_and_schema(): void
	{
		$this->section('1. model + rest/schema');

		$this->check('every modelled post type resolves', $this->missing('post_types', VX_VERIFY_POST_TYPES) === []);
		$this->check('every modelled fieldset resolves', $this->missing('fieldsets', VX_VERIFY_FIELDSETS) === []);

		$schema = $this->schema();
		$this->check('GET /vx-framework/v1/rest/schema returns a JSON object', is_array($schema) && $schema !== []);
		if (! is_array($schema)) {
			return;
		}

		$this->check('schema reports version 1', ($schema['version'] ?? null) === 1, 'version=' . wp_json_encode($schema['version'] ?? null));

		$scene = $this->schema_post_type($schema, 'sv_scene');
		$this->check('schema lists sv_scene as an exposed post type', $scene !== null && ($scene['exposed'] ?? false) === true);
		if ($scene === null) {
			return;
		}

		$types = $this->schema_field_types($scene);
		$this->check(
			'sv_scene exposes exactly its modelled fields, with their types',
			$types === VX_VERIFY_SCENE_FIELDS,
			'got ' . wp_json_encode($types)
		);

		foreach (VX_VERIFY_POST_TYPES as $post_type) {
			$this->check("schema lists {$post_type}", $this->schema_post_type($schema, $post_type) !== null);
		}
	}

	/* ---------------------------------------------------------------------
	 * 2. V1 — the two-write trap and its cleanup.
	 * ------------------------------------------------------------------ */

	private function verify_two_write_trap(): void
	{
		$this->section('2. V1 two-write trap');

		// Core owns creation; VX owns the values, through a register_rest_field
		// update callback that runs *after* the insert. A rejected `vx` payload
		// therefore leaves the post behind.
		$created = vx_create_with_vx('sv_scene', ['title' => 'VX verify orphan', 'status' => 'publish'], [], false);
		$this->posts[] = $created['id'];
		$this->check('POST /wp/v2/sv_scene creates the post', $created['status'] === 201 && $created['id'] > 0, 'status=' . $created['status'] . ' id=' . $created['id']);
		if ($created['id'] <= 0) {
			return;
		}

		$orphan_id = $created['id'];
		$this->check('the created post exists', get_post($orphan_id) !== null);

		// A key no fieldset defines: the failure an importer hits when a payload
		// is replayed against a renamed or removed field.
		$rejected = vx_dispatch(
			'POST',
			'/wp/v2/sv_scene/' . $orphan_id,
			['vx' => ['no_such_field' => 1], 'vx_strict' => 1]
		);

		$this->check('the vx write is refused', ! $rejected['ok'] && $rejected['status'] === 422, 'status=' . $rejected['status']);
		$this->check('the refusal is reported as unknown_fields', $rejected['code'] === 'unknown_fields', 'code=' . $rejected['code']);

		$this->check('the post survives the refused vx write (the orphan)', get_post($orphan_id) !== null);
		$this->check(
			'the orphan holds none of the requested values',
			get_post_meta($orphan_id, 'in_world_label', true) === ''
		);

		$this->check('the cleanup path deletes the orphan', vx_delete_orphan($orphan_id) === true);
		$this->check('the orphan is gone', get_post($orphan_id) === null);
		$this->check('deleting it again is a no-op', vx_delete_orphan($orphan_id) === true);

		// The same trap, closed: the helper removes what it created.
		$closed = vx_create_with_vx(
			'sv_scene',
			['title' => 'VX verify closed trap', 'status' => 'publish'],
			['no_such_field' => 1],
			true
		);
		$this->check('the helper reports the failed import', ! $closed['ok'] && $closed['status'] === 422);
		$this->check('the helper reports the orphan deleted', $closed['orphan_deleted'] === true);
		$this->check('the helper left no post behind', get_post($closed['id']) === null);

		// The brief's named injector, measured. VX does not check that a relation
		// target exists or is of the target post type: it filters the value out of
		// the write, silently, and answers 200.
		$scene = vx_create_with_vx(
			'sv_scene',
			['title' => 'VX verify relation targets', 'status' => 'publish'],
			['in_world_label' => 'relation-target probe'],
			true
		);
		$this->posts[] = $scene['id'];
		$this->check('the relation probe scene was created with its values', $scene['ok'] === true);
		if ($scene['id'] <= 0) {
			return;
		}

		$ghost = vx_dispatch('POST', '/wp/v2/sv_scene/' . $scene['id'], ['vx' => ['cast' => [999999]], 'vx_strict' => 1]);
		$this->check('a non-existent relation target is accepted, not refused', $ghost['status'] === 200, 'status=' . $ghost['status']);
		$this->notes[] = 'a relation id that does not exist (or is the wrong post type) is dropped silently: write 200, no warning, value [] on read.';

		$read = vx_dispatch('GET', '/wp/v2/sv_scene/' . $scene['id']);
		$read = json_decode((string) wp_json_encode($read['data']), true);
		$cast = is_array($read) && array_key_exists('cast', (array) ($read['vx'] ?? [])) ? $read['vx']['cast'] : null;
		$this->check('the dropped relation target is absent on read', $cast === [], 'cast=' . wp_json_encode($cast));

		$rows = $this->relation_rows($scene['id']);
		if ($rows !== null) {
			$this->check('no relation row was written for the ghost target', $rows === 0, 'rows=' . $rows);
		}
	}

	/* ---------------------------------------------------------------------
	 * 3. V2 — the can_read gate and the tier cache.
	 * ------------------------------------------------------------------ */

	private function verify_can_read_gate(): void
	{
		$this->section('3. V2 can_read gate + cache');

		$viewer_a = $this->subscriber('sv_model_viewer_a');
		$viewer_b = $this->subscriber('sv_model_viewer_b');
		if ($viewer_a === 0 || $viewer_b === 0) {
			$this->check('both subscriber fixtures exist', false);
			return;
		}

		$scene = vx_create_with_vx(
			'sv_scene',
			['title' => 'VX verify gate scene', 'status' => 'publish'],
			['in_world_label' => 'gate-value', 'ordinal' => 4],
			true
		);
		$this->posts[] = $scene['id'];
		$this->check('the gate fixture scene carries its values', $scene['ok'] === true);
		if ($scene['id'] <= 0) {
			return;
		}

		// The committed model defaults every fieldset to `private` (VX's secure
		// default), so a logged-in non-editor receives `"vx": {}` whatever the
		// gate decides: there is nothing for the gate to withhold. Raise sv_scene
		// to `authenticated` for this section only, so the gate has a payload to
		// act on and the cache has one to leak.
		$this->add_filter('vxframework/fieldsets/resolved_fields', static function (array $fields, $fieldset): array {
			if (! is_object($fieldset) || $fieldset->get_key() !== 'sv_scene') {
				return $fields;
			}
			foreach ($fields as $index => $field) {
				if (is_array($field)) {
					$fields[$index]['rest'] = ['access' => 'authenticated'];
				}
			}
			return $fields;
		}, 10, 2);

		// Control: with no access hook registered the authenticated-tier payload
		// *is* cached. Without this, "nothing was cached under the hook" would be
		// unprovable.
		$this->flush_vx_cache();
		$control = $this->reader_payload($viewer_a, $scene['id']);
		$this->check('a non-editor normally receives the values', $control['label'] === 'gate-value', 'label=' . wp_json_encode($control['label']));
		$this->check('without a hook the authenticated payload is cached', $this->vx_cache_entries() > 0, 'entries=' . wp_json_encode($this->vx_cache_entries()));

		// The gate, as the brief describes it: deny sv_scene for one viewer. The
		// filter context carries no post type, so the stub resolves it.
		$deny_for_a = static function ($decision, $context) use ($viewer_a) {
			if ((int) ($context['viewer_id'] ?? 0) !== $viewer_a) {
				return $decision;
			}
			$kind = (string) ($context['entity_kind'] ?? '');
			$id   = (int) ($context['entity_id'] ?? 0);
			return ($kind === 'post' && $id > 0 && get_post_type($id) === 'sv_scene') ? false : $decision;
		};
		$this->add_filter('vxframework/rest/can_read', $deny_for_a, 10, 2);

		$this->flush_vx_cache();
		$denied = $this->reader_payload($viewer_a, $scene['id']);
		$this->check('the gate withholds the values from the denied viewer', $denied['label'] === null, 'label=' . wp_json_encode($denied['label']));
		$this->notes[] = 'the gate empties the vx payload; it does not change the HTTP status (core still answers 200, because show_in_rest and publish are core decisions).';

		$this->check('under the hook the authenticated payload is not cached', $this->vx_cache_entries() === 0, 'entries=' . wp_json_encode($this->vx_cache_entries()));

		// The cache-poisoning assertion: the second viewer must not receive the
		// first viewer's withheld response.
		$allowed = $this->reader_payload($viewer_b, $scene['id']);
		$this->check('a second viewer is not served the denied viewer\'s payload', $allowed['label'] === 'gate-value', 'label=' . wp_json_encode($allowed['label']));

		// And the other direction: the denied payload was not cached, so lifting
		// the decision for the same viewer takes effect on the next read.
		$this->remove_filter('vxframework/rest/can_read');
		$this->add_filter('vxframework/rest/can_read', static fn ($decision) => $decision);
		$lifted = $this->reader_payload($viewer_a, $scene['id']);
		$this->check('the withheld response was not served again after the gate lifted', $lifted['label'] === 'gate-value', 'label=' . wp_json_encode($lifted['label']));

		// Removing the hook restores the plain read for both viewers.
		$this->remove_filter('vxframework/rest/can_read');
		$restored = $this->reader_payload($viewer_a, $scene['id']);
		$this->check('removing the hook restores the values', $restored['label'] === 'gate-value', 'label=' . wp_json_encode($restored['label']));
		$this->check('the read still answers 200 without the hook', $restored['status'] === 200, 'status=' . $restored['status']);

		$this->remove_filter('vxframework/fieldsets/resolved_fields');
	}

	/* ---------------------------------------------------------------------
	 * 4. V3 — the config round trip.
	 * ------------------------------------------------------------------ */

	private function verify_config_roundtrip(): void
	{
		$this->section('4. V3 config round trip');

		$group = 'fieldsets';
		$key   = 'sv_scene';
		$file  = Code_Source_Loader::source_of($group, $key);

		$this->check('the sv_scene fieldset is backed by a JSON file', is_string($file) && is_file($file), is_string($file) ? $file : 'no source file');
		if (! is_string($file) || ! is_file($file)) {
			return;
		}

		$this->backup = ['file' => $file, 'bytes' => (string) file_get_contents($file)];
		$this->check('the fieldset starts `synced`', State::of($group, $key) === State::SYNCED, 'state=' . State::of($group, $key));

		// Export: the envelope `wp vx-framework config export fieldsets sv_scene` prints.
		$exported = Exporter::export_json($group, $key);
		$this->check('the fieldset exports to a JSON envelope', ($exported['ok'] ?? false) === true, (string) ($exported['message'] ?? ''));
		if (($exported['ok'] ?? false) !== true) {
			return;
		}

		$file_out = $this->temp_dir . '/sv_scene.json';
		file_put_contents($file_out, (string) $exported['json']);

		$before = $this->schema_post_type($this->schema(), 'sv_scene');
		$this->check('the schema reports sv_scene before the round trip', $before !== null);
		$this->check(
			'the schema carries the sv_scene fieldset before the round trip',
			$this->schema_fieldset_keys($before) === ['sv_scene'],
			wp_json_encode($this->schema_fieldset_keys($before))
		);

		try {
			// Delete the fieldset. Its state is `synced`, so the file is its only
			// source: removing the file removes the fieldset from the model.
			unlink($file);
			$this->flush_resolver();

			$this->check('deleting the file removes sv_scene from the model', ! array_key_exists($key, Resolver::get_group($group)));
			// The post type stays registered for the request, so its resource
			// entry remains; what must be gone is the fieldset under it.
			$this->check(
				'the schema no longer reports the sv_scene fieldset',
				$this->schema_fieldset_keys($this->schema_post_type($this->schema(), 'sv_scene')) === [],
				wp_json_encode($this->schema_fieldset_keys($this->schema_post_type($this->schema(), 'sv_scene')))
			);

			// Import, through the same path `wp vx-framework config import <file>`
			// takes for a single-envelope file.
			$parsed = Bundle_Importer::parse_upload($file_out, basename($file_out));
			$this->check(
				'the export parses as a one-object import bundle',
				($parsed['ok'] ?? false) === true && count((array) ($parsed['items'] ?? [])) === 1,
				(string) ($parsed['message'] ?? '')
			);
			if (($parsed['ok'] ?? false) !== true || count((array) ($parsed['items'] ?? [])) !== 1) {
				return;
			}

			$imported = Sync_Service::import($parsed['items'][0]);
			$this->check('the envelope imports cleanly', ($imported['ok'] ?? false) === true, (string) ($imported['message'] ?? ''));

			$after = $this->schema_post_type($this->schema(), 'sv_scene');
			$this->check('the schema reports sv_scene again after the import', $after !== null);

			$this->check(
				'the imported fieldset is identical (version + field list)',
				$this->schema_identity($before) === $this->schema_identity($after),
				"\n         before=" . wp_json_encode($this->schema_identity($before))
					. "\n         after =" . wp_json_encode($this->schema_identity($after))
			);
		} finally {
			$this->restore_config();
		}

		$this->check('the committed fieldset file is restored byte-for-byte', (string) file_get_contents($file) === $this->backup['bytes']);
		$this->check('the fieldset is `synced` again', State::of($group, $key) === State::SYNCED, 'state=' . State::of($group, $key));

		$status = Sync_Service::status();
		$this->check(
			'the config status reports no problem, invalid file or failed definition',
			(array) ($status['problems'] ?? []) === [] && (array) ($status['invalid'] ?? []) === [] && (array) ($status['failed'] ?? []) === [],
			wp_json_encode(['problems' => $status['problems'] ?? null, 'invalid' => $status['invalid'] ?? null, 'failed' => $status['failed'] ?? null])
		);

		$this->check(
			'the schema still reports the modelled sv_scene fields',
			$this->schema_field_types($this->schema_post_type($this->schema(), 'sv_scene')) === VX_VERIFY_SCENE_FIELDS
		);
	}

	/**
	 * Put the fieldset file back exactly as committed, and drop any database copy
	 * the round trip created. Safe to call twice: the `finally` in section 4 and
	 * the shutdown guard both call it, and only the first call acts.
	 */
	public function restore_config(): void
	{
		if ($this->config_restored || $this->backup === null) {
			return;
		}
		$this->config_restored = true;

		$backup = $this->backup;

		if (! is_file($backup['file'])) {
			file_put_contents($backup['file'], $backup['bytes']);
		}
		$this->flush_resolver();

		$state = State::of('fieldsets', 'sv_scene');
		if ($state === State::OVERRIDDEN) {
			Sync_Service::reset('fieldsets', 'sv_scene');
		} elseif ($state === State::DB_NATIVE) {
			// `reset()` only accepts an override; this is the imported copy.
			Group_Storage::clear('fieldsets', 'sv_scene');
		}

		// The committed bytes are the contract; a partial write above must not
		// survive.
		file_put_contents($backup['file'], $backup['bytes']);
		$this->flush_resolver();
	}

	/* ---------------------------------------------------------------------
	 * Helpers.
	 * ------------------------------------------------------------------ */

	private function section(string $title): void
	{
		echo "\n== {$title} ==\n";
	}

	private function check(string $label, bool $ok, string $detail = ''): void
	{
		if ($ok) {
			++$this->passed;
			echo "  ok   {$label}\n";
			return;
		}

		++$this->failed;
		echo "  FAIL {$label}" . ($detail !== '' ? "\n         {$detail}" : '') . "\n";
	}

	/**
	 * @param array<int, string> $keys
	 * @return array<int, string> The keys the resolver does not define.
	 */
	private function missing(string $group, array $keys): array
	{
		$defined = Resolver::get_group($group);
		return array_values(array_filter($keys, static fn (string $key): bool => ! array_key_exists($key, $defined)));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function schema(): array
	{
		// The schema route is editor-only, and a section may leave a subscriber
		// logged in, so read it as the administrator.
		$previous = get_current_user_id();
		wp_set_current_user($this->admin);
		$response = vx_dispatch('GET', '/vx-framework/v1/rest/schema');
		wp_set_current_user($previous);

		return is_array($response['data']) ? $response['data'] : [];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>|null
	 */
	private function schema_post_type(array $schema, string $post_type): ?array
	{
		foreach ((array) ($schema['resources']['post_types'] ?? []) as $entry) {
			if (is_array($entry) && ($entry['key'] ?? '') === $post_type) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed>|null $resource
	 * @return array<int, string> The fieldset keys under a resource entry.
	 */
	private function schema_fieldset_keys(?array $resource): array
	{
		$keys = [];
		foreach ((array) ($resource['fieldsets'] ?? []) as $fieldset) {
			if (is_array($fieldset) && is_string($fieldset['key'] ?? null)) {
				$keys[] = $fieldset['key'];
			}
		}

		return $keys;
	}

	/**
	 * @param array<string, mixed>|null $resource
	 * @return array<string, string> Field key => field type, in schema order.
	 */
	private function schema_field_types(?array $resource): array
	{
		$types = [];
		foreach ((array) ($resource['fieldsets'] ?? []) as $fieldset) {
			foreach ((array) ($fieldset['fields'] ?? []) as $field) {
				if (is_array($field) && is_string($field['key'] ?? null)) {
					$types[$field['key']] = (string) ($field['type'] ?? '');
				}
			}
		}

		return $types;
	}

	/**
	 * The part of a schema entry a round trip must preserve: the fieldset's
	 * exposure and its ordered field key => type list.
	 *
	 * @param array<string, mixed>|null $resource
	 * @return array<string, mixed>
	 */
	private function schema_identity(?array $resource): array
	{
		$expose = null;
		foreach ((array) ($resource['fieldsets'] ?? []) as $fieldset) {
			if (is_array($fieldset) && ($fieldset['key'] ?? '') === 'sv_scene') {
				$expose = (string) ($fieldset['expose'] ?? '');
			}
		}

		return [
			'resource' => [
				'key'     => $resource['key'] ?? null,
				'label'   => $resource['label'] ?? null,
				'exposed' => $resource['exposed'] ?? null,
			],
			'expose'   => $expose,
			'fields'   => $this->schema_field_types($resource),
		];
	}

	/**
	 * Read one scene as one viewer, returning what the gate let through.
	 *
	 * @return array{status: int, label: mixed}
	 */
	private function reader_payload(int $viewer_id, int $scene_id): array
	{
		wp_set_current_user($viewer_id);
		$response = vx_dispatch('GET', '/wp/v2/sv_scene/' . $scene_id);
		wp_set_current_user(0);

		$data = json_decode((string) wp_json_encode($response['data']), true);
		$data = is_array($data) ? $data : [];

		return [
			'status' => $response['status'],
			'label'  => array_key_exists('in_world_label', (array) ($data['vx'] ?? [])) ? $data['vx']['in_world_label'] : null,
		];
	}

	private function subscriber(string $login): int
	{
		$existing = get_user_by('login', $login);
		if ($existing) {
			return (int) $existing->ID;
		}

		$user_id = wp_insert_user(
			[
				'user_login' => $login,
				'user_pass'  => wp_generate_password(32),
				'user_email' => $login . '@verify.invalid',
				'role'       => 'subscriber',
			]
		);

		if (is_wp_error($user_id)) {
			return 0;
		}

		$this->users[] = (int) $user_id;
		return (int) $user_id;
	}

	/**
	 * Relation rows a scene owns, or null when VX does not use a relation table
	 * here (the check is then skipped rather than guessed).
	 */
	private function relation_rows(int $scene_id): ?int
	{
		global $wpdb;

		$table = $wpdb->prefix . 'vx_relations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- development-only verifier.
		$tables = $wpdb->get_col('SHOW TABLES');
		if (! is_array($tables) || ! in_array($table, $tables, true)) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- development-only verifier.
		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE parent_id = %d", $scene_id));
	}

	/**
	 * Register a filter, remembering it so cleanup can remove it.
	 */
	private function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted = 1): void
	{
		add_filter($hook, $callback, $priority, $accepted);
		$this->filters[] = [$hook, $callback, $priority];
	}

	private function remove_filter(string $hook): void
	{
		foreach ($this->filters as $index => $registered) {
			if ($registered[0] === $hook) {
				remove_filter($registered[0], $registered[1], $registered[2]);
				unset($this->filters[$index]);
			}
		}
	}

	private function flush_resolver(): void
	{
		Code_Source_Loader::flush();
		Resolver::flush();
	}

	/**
	 * Empty VX's tier cache so a later read is a guaranteed miss.
	 */
	private function flush_vx_cache(): void
	{
		if (function_exists('wp_cache_flush_group') && wp_cache_supports('flush_group')) {
			wp_cache_flush_group('vx_rest');
		}
	}

	/**
	 * How many `vx_rest` cache entries exist, read out of the object cache. Null
	 * when the cache internals are not readable.
	 */
	private function vx_cache_entries(): ?int
	{
		global $wp_object_cache;

		if (! is_object($wp_object_cache)) {
			return null;
		}

		try {
			$property = new ReflectionProperty(get_class($wp_object_cache), 'cache');
			$property->setAccessible(true);
			$cache = $property->getValue($wp_object_cache);
		} catch (ReflectionException $exception) {
			return null;
		}

		if (! is_array($cache)) {
			return null;
		}

		$entries = 0;
		foreach ($cache as $group => $values) {
			// The tier cache itself: `vx_rest`. The stamp companion group
			// (`vx_rest_stamp`) holds no payload, so it is not counted.
			if ($group === 'vx_rest') {
				$entries += count((array) $values);
			}
		}

		return $entries;
	}

	/**
	 * Remove every fixture this run created, so a verification pass does not
	 * leave sample records or test accounts behind.
	 */
	private function cleanup(): void
	{
		foreach (array_filter($this->filters) as $registered) {
			remove_filter($registered[0], $registered[1], $registered[2]);
		}
		$this->filters = [];

		foreach (array_reverse($this->posts) as $post_id) {
			vx_delete_orphan($post_id);
		}
		$this->posts = [];

		if ($this->users !== []) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ($this->users as $user_id) {
				wp_delete_user($user_id);
			}
			$this->users = [];
		}

		if ($this->temp_dir !== '' && is_dir($this->temp_dir)) {
			foreach ((array) glob($this->temp_dir . '/*') as $file) {
				unlink($file);
			}
			rmdir($this->temp_dir);
		}
	}
}
