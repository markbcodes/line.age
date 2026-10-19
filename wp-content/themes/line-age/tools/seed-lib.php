<?php
/**
 * Shader's seeding library for tools/seed.php. Shader owns this file: don't
 * edit it, so a newer version can replace it whole. The site's own records go
 * in tools/seed.php, which requires this file first.
 *
 * Boots WordPress through tools/bootstrap.php (CLI only, administrator
 * authorized), reads the run's options from the command line, and provides the
 * helpers a recipe writes through: execute abilities, add or fill posts and
 * terms by slug, fill option-page values, import media, set the site title and
 * front page, and write nav menus. They read and write through VX abilities
 * (`wp_get_ability(...)->execute(...)`), never with raw WordPress writes, so a
 * dry run writes nothing. seed_done() ends a run and prints its summary.
 */

declare(strict_types=1);


require __DIR__ . '/bootstrap.php';

/** How this run was asked for, from its command line. */
$seed_run = seed_parse_args(array_slice($argv ?? [], 1));

$seed_report = [
	// No successful run yet on this site: one-time site settings are applied.
	'first'   => get_option('shader_seeded') === false,
	// Per post type, taxonomy, option page and media: its names and what happened to its records.
	'groups'  => [],
	// One-off changes: menus, front page, site title.
	'notes'   => [],
	// Stand-in ids for records a dry run would create, so later lines can point at them.
	'planned' => [],
	// Per post type and taxonomy: whether it had records before this run.
	'had'     => [],
	// Records this run creates (or, dry, would create), as "post:{type}/{slug}" or "term:{taxonomy}/{slug}".
	'new'     => [],
];

function seed_fail(string $message): never
{
	fwrite(STDERR, $message . "\n");
	exit(1);
}

/**
 * `--dry-run`, `--no-fill`, and `--reset` (true: everything) or `--reset=a,b`
 * (those keys).
 *
 * @param array<int, string> $args
 * @return array{dry: bool, fill: bool, reset: bool|array<int, string>}
 */
function seed_parse_args(array $args): array
{
	$run = ['dry' => false, 'fill' => true, 'reset' => false];
	foreach ($args as $arg) {
		if ($arg === '--dry-run') {
			$run['dry'] = true;
		} elseif ($arg === '--no-fill') {
			$run['fill'] = false;
		} elseif ($arg === '--reset') {
			$run['reset'] = true;
		} elseif (str_starts_with($arg, '--reset=')) {
			$keys = array_values(array_filter(array_map('trim', explode(',', substr($arg, 8))), 'strlen'));
			if ($keys === []) {
				seed_fail('--reset= needs post types, taxonomies or option pages, such as --reset=post,category.');
			}
			if ($run['reset'] !== true) {
				$run['reset'] = array_values(array_unique(array_merge(is_array($run['reset']) ? $run['reset'] : [], $keys)));
			}
		} else {
			seed_fail("Unknown option {$arg}. Use --dry-run, --no-fill, --reset, or --reset={post type, taxonomy or option page},...");
		}
	}

	if (is_array($run['reset'])) {
		foreach ($run['reset'] as $key) {
			if (! seed_known_key($key)) {
				seed_fail("--reset={$key}: no post type, taxonomy or option page has that key.");
			}
		}
	}

	return $run;
}

function seed_known_key(string $key): bool
{
	if (post_type_exists($key) || taxonomy_exists($key)) {
		return true;
	}
	$ability = wp_get_ability('vx-framework/get-option-page');
	if (! $ability) {
		return false;
	}
	$result = $ability->execute(['key' => $key]);
	return is_array($result) && ($result['found'] ?? false) === true;
}

function seed_dry(): bool
{
	global $seed_run;
	return $seed_run['dry'];
}

/** Whether this run fills empty fields of what exists. `--no-fill` only adds new records. */
function seed_fills(): bool
{
	global $seed_run;
	return $seed_run['fill'];
}

/** Whether this run overwrites the records of `$key` (a post type, taxonomy or option page). */
function seed_resets(string $key): bool
{
	global $seed_run;
	return $seed_run['reset'] === true || (is_array($seed_run['reset']) && in_array($key, $seed_run['reset'], true));
}

/** Whether this run overwrites everything, menus and site settings included. */
function seed_resets_all(): bool
{
	global $seed_run;
	return $seed_run['reset'] === true;
}

/**
 * A stand-in id for a record a dry run would create, so later lines can still
 * point at it. Never written: a write that names one skips VX's dry check.
 */
function seed_placeholder(string $record): int
{
	global $seed_report;
	$seed_report['planned'][$record] = -1000000 - count($seed_report['planned']);
	return $seed_report['planned'][$record];
}

function seed_is_placeholder(int $id): bool
{
	global $seed_report;
	return $id < 0 && in_array($id, $seed_report['planned'], true);
}

/** @param mixed $value */
function seed_has_placeholder($value): bool
{
	if (is_int($value)) {
		return seed_is_placeholder($value);
	}
	if (is_array($value)) {
		foreach ($value as $item) {
			if (seed_has_placeholder($item)) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Run an ability. On a dry run a write only checks its input (`dry_run`), and
 * fails the run the same way a refused write would. `$cleanup` gets a failed
 * response before the run stops.
 *
 * @param array<string, mixed> $input
 * @param (callable(array<string, mixed>): void)|null $cleanup
 * @return array<string, mixed>
 */
function seed_ability(string $name, array $input, ?callable $cleanup = null): array
{
	$ability = wp_get_ability($name);
	if (! $ability) {
		seed_fail("Ability {$name} is not registered.");
	}

	$annotations = $ability->get_meta_item('annotations', []);
	$writes      = ! (is_array($annotations) && ! empty($annotations['readonly']));
	if ($writes && seed_dry()) {
		if (seed_has_placeholder($input)) {
			// It names a record this run would create; the real run checks it.
			return ['ok' => true, 'dry_run' => true, 'valid' => true];
		}
		$input['dry_run'] = true;
	}

	$result = $ability->execute($input);
	if ($result instanceof WP_Error) {
		seed_fail($name . ': ' . $result->get_error_message());
	}
	if (! is_array($result)) {
		seed_fail($name . ' returned an unexpected response.');
	}
	if ((isset($result['ok']) && $result['ok'] === false) || (! empty($result['dry_run']) && ($result['valid'] ?? true) === false)) {
		if ($cleanup !== null) {
			$cleanup($result);
		}
		seed_fail($name . ': ' . seed_describe_failure($result));
	}

	return $result;
}

/**
 * The message, code, and per-field errors of a failed ability response.
 *
 * VX puts the useful detail in `errors` (one entry per rejected field); the
 * top-level `message` alone often only says that something failed.
 *
 * @param array<string, mixed> $result
 */
function seed_describe_failure(array $result): string
{
	$message = isset($result['message']) && is_string($result['message']) ? $result['message'] : '';
	if (isset($result['code']) && is_string($result['code']) && $result['code'] !== '') {
		$message .= ($message !== '' ? ' ' : '') . '[' . $result['code'] . ']';
	}
	if ($message === '' && ! empty($result['dry_run'])) {
		$message = 'These values would be refused.';
	}

	$lines = [];
	foreach ((array) ($result['errors'] ?? []) as $error) {
		if (! is_array($error)) {
			continue;
		}
		$path    = isset($error['path']) && is_scalar($error['path']) ? (string) $error['path'] : '';
		$detail  = isset($error['message']) && is_scalar($error['message']) ? (string) $error['message'] : (string) wp_json_encode($error);
		$lines[] = '  - ' . ($path !== '' ? $path . ': ' : '') . $detail;
	}

	if ($message === '' && $lines === []) {
		return (string) wp_json_encode($result);
	}

	return $message . ($lines !== [] ? "\n" . implode("\n", $lines) : '');
}

/**
 * Count one record of a group: `created`, `filled` (with the fields filled,
 * and the record's name for the summary), `reset` or `kept`.
 *
 * @param array{0: string, 1: string} $names Singular and plural, as the summary says them.
 */
function seed_count(string $group, array $names, string $kind, string $what, int $fields = 0, string $record = ''): void
{
	global $seed_report;

	$seed_report['groups'][$group] ??= [
		'names'   => $names,
		'kind'    => $kind,
		'created' => 0,
		'filled'  => 0,
		'fields'  => 0,
		'reset'   => 0,
		'kept'    => 0,
		'records' => [],
	];
	++$seed_report['groups'][$group][$what];
	$seed_report['groups'][$group]['fields'] += $fields;
	if ($what === 'filled' && $record !== '') {
		$seed_report['groups'][$group]['records'][] = $record;
	}
}

/** Whether a post type or taxonomy (`post:{type}`, `term:{taxonomy}`) had no records before this run. */
function seed_was_empty(string $group): bool
{
	global $seed_report;
	seed_remember_had($group);
	return $seed_report['had'][$group] === false;
}

/**
 * Note, before the run writes any, whether a post type or taxonomy already has
 * records. Adding to one that was empty touches nothing of the site's.
 */
function seed_remember_had(string $group): void
{
	global $seed_report;

	if (isset($seed_report['had'][$group])) {
		return;
	}
	[$kind, $key] = explode(':', $group, 2);
	if ($kind === 'post') {
		$counts = (array) wp_count_posts($key);
		unset($counts['auto-draft']);
		$seed_report['had'][$group] = array_sum(array_map('intval', $counts)) > 0;
	} else {
		$seed_report['had'][$group] = (int) wp_count_terms(['taxonomy' => $key, 'hide_empty' => false]) > 0;
	}
}

/**
 * Whether the run changes anything the site already had: it fills or resets
 * a record, adds records to a post type or taxonomy that has some, or
 * changes a menu or site setting. Sample media doesn't count.
 */
function seed_touches_existing(): bool
{
	global $seed_report;

	foreach ($seed_report['notes'] as $note) {
		if ($note['touches']) {
			return true;
		}
	}
	foreach ($seed_report['groups'] as $group => $counts) {
		if ($counts['kind'] === 'media') {
			continue;
		}
		if ($counts['filled'] > 0 || $counts['reset'] > 0) {
			return true;
		}
		if ($counts['created'] > 0 && ($seed_report['had'][$group] ?? true)) {
			return true;
		}
	}
	return false;
}

/**
 * Whether the run adds anything new apart from sample media: records, or
 * menu items and menus that link to new things.
 */
function seed_adds_new(): bool
{
	global $seed_report;

	foreach ($seed_report['notes'] as $note) {
		if (! $note['touches']) {
			return true;
		}
	}
	foreach ($seed_report['groups'] as $counts) {
		if ($counts['kind'] !== 'media' && $counts['created'] > 0) {
			return true;
		}
	}
	return false;
}

/** A one-off change for the summary. `$touches`: it changes something the site already has. */
function seed_note(string $note, bool $touches = true): void
{
	global $seed_report;
	$seed_report['notes'][] = ['text' => $note, 'touches' => $touches];
}

/** @return array{0: string, 1: string} */
function seed_post_type_names(string $type): array
{
	$object = get_post_type_object($type);
	return $object ? [(string) $object->labels->singular_name, (string) $object->labels->name] : [$type, $type];
}

/** @return array{0: string, 1: string} */
function seed_taxonomy_names(string $taxonomy): array
{
	$object = get_taxonomy($taxonomy);
	return $object ? [(string) $object->labels->singular_name, (string) $object->labels->name] : [$taxonomy, $taxonomy];
}

/**
 * The recipe's values that are empty on the record, as VX reads it. VX leaves
 * an empty field out of `values` and lists it in `omitted` with reason
 * `empty`. A field it leaves out for any other reason (not readable, too
 * large) is kept: what can't be seen isn't overwritten. A key VX doesn't know
 * is passed on, so the strict write names it.
 *
 * @param array<string, mixed> $values
 * @return array<string, mixed>
 */
function seed_empty_values(string $read, int $id, array $values): array
{
	if ($values === []) {
		return [];
	}

	$result = seed_ability($read, ['id' => $id, 'fields' => array_map('strval', array_keys($values))]);
	$held   = array_keys((array) ($result['values'] ?? []));
	foreach ((array) ($result['omitted'] ?? []) as $omitted) {
		if (is_array($omitted) && isset($omitted['field']) && ($omitted['reason'] ?? '') !== 'empty') {
			$held[] = (string) $omitted['field'];
		}
	}

	return array_diff_key($values, array_flip($held));
}

/** @param mixed $value */
function seed_is_empty($value): bool
{
	return $value === null || $value === '' || $value === [] || ($value instanceof stdClass && (array) $value === []);
}

/**
 * The core props of the recipe that are empty on the post. Title, status,
 * slug, author, date, parent and order are the post's own once it exists.
 *
 * @param array<string, mixed> $props
 * @return array<string, mixed>
 */
function seed_empty_post_props(WP_Post $post, array $props): array
{
	$fill = [];
	foreach ($props as $key => $value) {
		$empty = match ($key) {
			'excerpt'        => trim((string) $post->post_excerpt) === '',
			'content'        => trim((string) $post->post_content) === '',
			'featured_image' => (int) get_post_thumbnail_id($post) === 0,
			'template'       => in_array(get_page_template_slug($post), ['', 'default', false], true),
			default          => false,
		};
		if ($empty && $value !== '' && $value !== null && $value !== 0 && $value !== 'default') {
			$fill[$key] = $value;
		}
	}
	return $fill;
}

/**
 * Add a post by slug, or fill its empty fields if it exists.
 *
 * A missing post is created with everything. An existing one (trashed
 * included) keeps every value it has: only empty excerpt, content, featured
 * image, template and VX fields are filled. `--reset` overwrites it instead.
 *
 * @param array<string, mixed> $props Core columns accepted by vx-framework/create-post and
 *                                    vx-framework/update-post. `title` is required; use
 *                                    `featured_image` for the post's primary image and
 *                                    `template` for a page template file. `front_page`
 *                                    => true makes a page the front page before its
 *                                    values are written, so a fieldset shown only on
 *                                    the front page takes them.
 * @param array<string, mixed> $values VX field values. Do not put title here.
 * @return int The post's id. On a dry run, a stand-in for a post it would create.
 */
function seed_upsert_post(string $type, string $slug, array $props = [], array $values = []): int
{
	global $seed_report;

	if (! isset($props['title']) || ! is_string($props['title']) || trim($props['title']) === '') {
		seed_fail("seed_upsert_post({$type}, {$slug}) needs a 'title' in \$props.");
	}

	$front = ! empty($props['front_page']);
	unset($props['front_page']);

	$names = seed_post_type_names($type);
	seed_remember_had('post:' . $type);
	$found = get_posts(
		[
			'post_type'      => $type,
			'name'           => $slug,
			'post_status'    => array_values(array_diff(get_post_stati(), ['auto-draft', 'inherit'])),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		]
	);

	$core = array_merge(
		[
			'slug'   => $slug,
			'status' => 'publish',
		],
		$props
	);

	if ($found === [] && $front && $values !== []) {
		return seed_create_front_page($type, $slug, $core, $values, $names);
	}

	if ($found === []) {
		$input = array_merge(['post_type' => $type], $core);
		if ($values !== []) {
			$input['values'] = $values;
			$input['strict'] = true;
		}

		$seed_report['new']["post:{$type}/{$slug}"] = true;
		$result = seed_ability(
			'vx-framework/create-post',
			$input,
			static function (array $failed): void {
				// VX keeps a post whose values it refused, as a draft. A later run would only
				// fill it, never publish it, so it goes; the fixed recipe creates it again.
				if ((int) ($failed['id'] ?? 0) > 0) {
					wp_delete_post((int) $failed['id'], true);
				}
			}
		);
		seed_count('post:' . $type, $names, 'post', 'created');
		$id = seed_dry() ? seed_placeholder("post:{$type}/{$slug}") : (int) ($result['id'] ?? 0);
		if ($id === 0) {
			seed_fail("vx-framework/create-post did not return an id for {$slug}.");
		}
		if ($front) {
			seed_set_front_page($id);
		}
		return $id;
	}

	$post = $found[0];
	$id   = (int) $post->ID;
	// Before any values are written, so a fieldset shown only on the front page takes them.
	if ($front) {
		seed_set_front_page($id);
	}

	if (seed_resets($type)) {
		// The status goes last, so a post whose values are rejected is never made live empty.
		$status = $core['status'];
		unset($core['status']);
		seed_ability('vx-framework/update-post', array_merge(['id' => $id], $core));
		if ($values !== []) {
			seed_ability('vx-framework/set-post-values', ['id' => $id, 'values' => $values, 'strict' => true]);
		}
		if ($post->post_status !== $status) {
			seed_ability('vx-framework/update-post', ['id' => $id, 'status' => $status]);
		}
		seed_count('post:' . $type, $names, 'post', 'reset');
		return $id;
	}

	if (! seed_fills()) {
		seed_count('post:' . $type, $names, 'post', 'kept');
		return $id;
	}

	$props = seed_empty_post_props($post, $props);
	$empty = seed_empty_values('vx-framework/get-post-values', $id, $values);
	if ($props !== []) {
		seed_ability('vx-framework/update-post', array_merge(['id' => $id], $props));
	}
	if ($empty !== []) {
		seed_ability('vx-framework/set-post-values', ['id' => $id, 'values' => $empty, 'strict' => true]);
	}
	$filled = count($props) + count($empty);
	seed_count('post:' . $type, $names, 'post', $filled > 0 ? 'filled' : 'kept', $filled, (string) $post->post_title);
	return $id;
}

/**
 * Create the page `seed_upsert_post()` was asked to make the front page, with
 * values: created as a draft, made the front page, then given its values and
 * status. A fieldset shown only on the front page applies to it only by then.
 * When its values are refused, the draft goes and the front page is put back,
 * so the fixed recipe runs as a first seed again.
 *
 * @param array<string, mixed> $core
 * @param array<string, mixed> $values
 * @param array{0: string, 1: string} $names
 */
function seed_create_front_page(string $type, string $slug, array $core, array $values, array $names): int
{
	global $seed_report;

	$status = $core['status'];
	$seed_report['new']["post:{$type}/{$slug}"] = true;
	$result = seed_ability('vx-framework/create-post', array_merge(['post_type' => $type], $core, ['status' => 'draft']));
	$id     = seed_dry() ? seed_placeholder("post:{$type}/{$slug}") : (int) ($result['id'] ?? 0);
	if ($id === 0) {
		seed_fail("vx-framework/create-post did not return an id for {$slug}.");
	}

	$before = [get_option('show_on_front'), get_option('page_on_front')];
	seed_set_front_page($id);
	seed_ability(
		'vx-framework/set-post-values',
		['id' => $id, 'values' => $values, 'strict' => true],
		static function () use ($id, $before): void {
			wp_delete_post($id, true);
			update_option('show_on_front', $before[0]);
			update_option('page_on_front', $before[1]);
		}
	);
	if ($status !== 'draft') {
		seed_ability('vx-framework/update-post', ['id' => $id, 'status' => $status]);
	}
	seed_count('post:' . $type, $names, 'post', 'created');
	return $id;
}

/**
 * Add a term by slug, or fill its empty description and fields if it exists.
 * Its name is its own once it exists. `--reset` overwrites it instead.
 *
 * @param array<string, mixed> $values VX field values.
 * @return int The term's id. On a dry run, a stand-in for a term it would create.
 */
function seed_upsert_term(string $taxonomy, string $slug, string $name, string $description = '', array $values = []): int
{
	global $seed_report;

	$names    = seed_taxonomy_names($taxonomy);
	seed_remember_had('term:' . $taxonomy);
	$existing = get_term_by('slug', $slug, $taxonomy);

	if (! $existing instanceof WP_Term) {
		$input = [
			'taxonomy'    => $taxonomy,
			'name'        => $name,
			'slug'        => $slug,
			'description' => $description,
		];
		if ($values !== []) {
			$input['values'] = $values;
			$input['strict'] = true;
		}

		$seed_report['new']["term:{$taxonomy}/{$slug}"] = true;
		$result = seed_ability('vx-framework/create-term', $input);
		seed_count('term:' . $taxonomy, $names, 'term', 'created');
		if (seed_dry()) {
			return seed_placeholder("term:{$taxonomy}/{$slug}");
		}
		$id = (int) ($result['id'] ?? 0);
		if ($id <= 0) {
			seed_fail("vx-framework/create-term did not return an id for {$slug}.");
		}
		return $id;
	}

	$id = (int) $existing->term_id;

	if (seed_resets($taxonomy)) {
		seed_ability(
			'vx-framework/update-term',
			[
				'id'          => $id,
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
			]
		);
		if ($values !== []) {
			seed_ability('vx-framework/set-term-values', ['id' => $id, 'values' => $values, 'strict' => true]);
		}
		seed_count('term:' . $taxonomy, $names, 'term', 'reset');
		return $id;
	}

	if (! seed_fills()) {
		seed_count('term:' . $taxonomy, $names, 'term', 'kept');
		return $id;
	}

	$filled = 0;
	if ($description !== '' && trim((string) $existing->description) === '') {
		seed_ability('vx-framework/update-term', ['id' => $id, 'description' => $description]);
		++$filled;
	}
	$empty = seed_empty_values('vx-framework/get-term-values', $id, $values);
	if ($empty !== []) {
		seed_ability('vx-framework/set-term-values', ['id' => $id, 'values' => $empty, 'strict' => true]);
	}
	$filled += count($empty);
	seed_count('term:' . $taxonomy, $names, 'term', $filled > 0 ? 'filled' : 'kept', $filled, (string) $existing->name);
	return $id;
}

/**
 * Fill an option page's empty values through VX, validated like any other
 * write. Values that are set are kept; `--reset` overwrites them.
 *
 * @param array<string, mixed> $values VX field values keyed by field key.
 */
function seed_option_values(string $page, array $values): void
{
	if ($values === []) {
		return;
	}

	$read    = seed_ability('vx-framework/get-option-page-values', ['key' => $page, 'fields' => array_map('strval', array_keys($values))]);
	$label   = isset($read['label']) && is_string($read['label']) && $read['label'] !== '' ? $read['label'] : $page;
	$current = (array) ($read['values'] ?? []);

	if (seed_resets($page)) {
		seed_ability('vx-framework/set-option-page-values', ['key' => $page, 'values' => $values, 'strict' => true]);
		seed_count('option:' . $page, [$label, $label], 'option', 'reset');
		return;
	}

	$empty = [];
	foreach (seed_fills() ? $values : [] as $key => $value) {
		if (! array_key_exists((string) $key, $current) || seed_is_empty($current[(string) $key])) {
			$empty[$key] = $value;
		}
	}
	if ($empty !== []) {
		seed_ability('vx-framework/set-option-page-values', ['key' => $page, 'values' => $empty, 'strict' => true]);
	}
	seed_count('option:' . $page, [$label, $label], 'option', $empty !== [] ? 'filled' : 'kept', count($empty));
}

/**
 * Import a file from tools/seed-media/ into the Media Library once.
 *
 * Idempotent: a previously imported file is found again by its seed key, and
 * only gets alt text if it has none. A failed import leaves any existing
 * attachment intact and fails the run.
 *
 * @return int The attachment id. On a dry run, a stand-in for a file it would import.
 */
function seed_media(string $filename, string $alt = '', string $title = ''): int
{
	static $imported = [];

	$filename = basename($filename);
	$key      = 'shader-seed:' . $filename;
	$names    = ['sample file', 'sample files'];

	if (isset($imported[$key])) {
		return $imported[$key];
	}

	$existing = get_posts(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_key'       => '_shader_seed_media',
			'meta_value'     => $key,
		]
	);

	if ($existing !== []) {
		$id = (int) $existing[0]->ID;
		$fill = $alt !== '' && (seed_resets_all() || (seed_fills() && trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) === ''));
		if ($fill && ! seed_dry()) {
			update_post_meta($id, '_wp_attachment_image_alt', $alt);
		}
		$imported[$key] = $id;
		seed_count('media', $names, 'media', $fill ? 'filled' : 'kept', $fill ? 1 : 0);
		return $id;
	}

	$source = dirname(__FILE__) . '/seed-media/' . $filename;
	if (! is_readable($source)) {
		seed_fail("Seed media file is missing: {$source}");
	}

	seed_count('media', $names, 'media', 'created');
	if (seed_dry()) {
		$imported[$key] = seed_placeholder($key);
		return $imported[$key];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$temp = wp_tempnam($filename);
	if (! $temp || ! copy($source, $temp)) {
		seed_fail("Could not stage seed media: {$filename}");
	}

	$id = media_handle_sideload(
		[
			'name'     => $filename,
			'tmp_name' => $temp,
		],
		0,
		$title !== '' ? $title : null
	);

	if (is_wp_error($id)) {
		@unlink($temp);
		seed_fail('Media import failed for ' . $filename . ': ' . $id->get_error_message());
	}

	update_post_meta((int) $id, '_shader_seed_media', $key);
	if ($alt !== '') {
		update_post_meta((int) $id, '_wp_attachment_image_alt', $alt);
	}

	$imported[$key] = (int) $id;

	return (int) $id;
}

/**
 * Set the site title and tagline. Only on the site's first seed (or a full
 * `--reset`): after that they are the site's own.
 */
function seed_site_identity(string $title, string $tagline = ''): void
{
	global $seed_report;

	if (! $seed_report['first'] && ! seed_resets_all()) {
		return;
	}

	$changes = [];
	if ($title !== '' && $title !== get_option('blogname')) {
		$changes['blogname'] = $title;
	}
	if ($tagline !== '' && $tagline !== get_option('blogdescription')) {
		$changes['blogdescription'] = $tagline;
	}
	if ($changes === []) {
		return;
	}

	if (! seed_dry()) {
		foreach ($changes as $option => $value) {
			update_option($option, $value);
		}
	}
	seed_note($tagline !== '' ? 'Set the site title and tagline' : 'Set the site title');
}

/**
 * Show a static page on the front page, and optionally set the posts page.
 * Only on the site's first seed (or a full `--reset`), or while the site
 * still shows its latest posts there: after that the choice is the site's.
 */
function seed_set_front_page(int $page_id, int $posts_page_id = 0): void
{
	global $seed_report;

	if ($page_id === 0 || ($page_id < 0 && ! seed_is_placeholder($page_id))) {
		seed_fail('seed_set_front_page requires a page id.');
	}

	$chosen = get_option('show_on_front') === 'page';
	if (($chosen || ! seed_fills()) && ! $seed_report['first'] && ! seed_resets_all()) {
		return;
	}
	if (
		$chosen
		&& (int) get_option('page_on_front') === $page_id
		&& ($posts_page_id <= 0 || (int) get_option('page_for_posts') === $posts_page_id)
	) {
		return;
	}

	if (! seed_dry()) {
		update_option('show_on_front', 'page');
		update_option('page_on_front', $page_id);
		if ($posts_page_id > 0) {
			update_option('page_for_posts', $posts_page_id);
		}
	}
	seed_note('Set the front page');
}

/**
 * Create a nav menu and assign it to a theme location, or add the items it is
 * missing.
 *
 * An existing menu is never cleared, reordered or renamed: an item whose link
 * target isn't in it yet is appended, under its parent if that is there. Its
 * location is set only while the location has no menu. A full `--reset`
 * rebuilds the menu from the recipe.
 *
 * Each item links to exactly one target, plus an optional title and an
 * optional children list of the same shape:
 *
 *   ['page' => '{page-slug}']                   a Page, by slug or path (about/team)
 *   ['post_type_archive' => '{post_type}']      a post type's archive
 *   ['term' => ['{taxonomy}', '{term-slug}']]   a term, by taxonomy and slug
 *   ['title' => '{Label}', 'url' => '{url}']    a custom link (title required)
 *
 * Prefer the object targets: WordPress marks them current, and they keep
 * working when a slug or the permalink structure changes. An object item
 * without a title uses the object's own name: a post type archive its plural
 * name, such as "Events".
 *
 * @param array<int, array<string, mixed>> $items
 * @return int The menu's id; 0 on a dry run that would create it.
 */
function seed_nav_menu(string $location, string $name, array $items): int
{
	$menu = wp_get_nav_menu_object($name);

	if (! $menu) {
		$count = seed_nav_menu_count($items);
		// A new menu takes only an empty place, so it changes nothing the site shows.
		seed_note("Create the {$name} menu with {$count} " . ($count === 1 ? 'item' : 'items'), false);
		if (seed_dry()) {
			return 0;
		}
		$menu_id = wp_create_nav_menu($name);
		if ($menu_id instanceof WP_Error) {
			seed_fail('Could not create nav menu ' . $name . ': ' . $menu_id->get_error_message());
		}
		$menu_id = (int) $menu_id;
		if ($menu_id <= 0) {
			seed_fail("Could not create nav menu {$name}.");
		}
		$position = 1;
		seed_nav_menu_items($menu_id, $items, 0, $position);
		seed_nav_menu_location($location, $menu_id, seed_resets_all());
		return $menu_id;
	}

	$menu_id  = (int) $menu->term_id;
	$existing = wp_get_nav_menu_items($menu_id) ?: [];

	if (seed_resets_all()) {
		seed_note("Rebuild the {$name} menu");
		if (! seed_dry()) {
			foreach ($existing as $item) {
				wp_delete_post((int) $item->ID, true);
			}
			$position = 1;
			seed_nav_menu_items($menu_id, $items, 0, $position);
		}
		seed_nav_menu_location($location, $menu_id, true);
		return $menu_id;
	}

	$position = 1;
	foreach ($existing as $item) {
		$position = max($position, (int) $item->menu_order + 1);
	}
	$added = ['new' => 0, 'other' => 0];
	seed_nav_menu_merge($menu_id, $items, 0, $existing, $position, $added);
	// Links to records and post types this run adds change nothing the menu had; any other link does.
	$total = $added['new'] + $added['other'];
	if ($total > 0) {
		seed_note("Add {$total} " . ($total === 1 ? 'item' : 'items') . " to the {$name} menu", $added['other'] > 0);
	}
	if (seed_fills() && seed_nav_menu_location($location, $menu_id, false)) {
		seed_note("Show the {$name} menu in its place");
	}
	return $menu_id;
}

/**
 * Assign a menu to a theme location: always when `$always`, otherwise only
 * while the location has no menu. Whether it changed.
 */
function seed_nav_menu_location(string $location, int $menu_id, bool $always): bool
{
	$locations = get_theme_mod('nav_menu_locations');
	if (! is_array($locations)) {
		$locations = [];
	}
	$current = (int) ($locations[$location] ?? 0);
	if ($current === $menu_id || (! $always && $current > 0 && wp_get_nav_menu_object($current))) {
		return false;
	}
	if (! seed_dry()) {
		$locations[$location] = $menu_id;
		set_theme_mod('nav_menu_locations', $locations);
	}
	return true;
}

/** @param array<int, mixed> $items */
function seed_nav_menu_count(array $items): int
{
	$count = 0;
	foreach ($items as $item) {
		if (is_array($item)) {
			$count += 1 + seed_nav_menu_count(isset($item['children']) && is_array($item['children']) ? $item['children'] : []);
		}
	}
	return $count;
}

/**
 * Add the recipe items an existing menu doesn't link to yet, counting those
 * that link to something this run adds (`new`) apart from the rest (`other`).
 * `--no-fill` adds only the new ones.
 *
 * @param array<int, mixed>       $items
 * @param array<int, WP_Post>     $existing
 * @param array{new: int, other: int} $added
 */
function seed_nav_menu_merge(int $menu_id, array $items, int $parent_id, array $existing, int &$position, array &$added): void
{
	foreach ($items as $item) {
		if (! is_array($item)) {
			continue;
		}
		$title  = isset($item['title']) && is_string($item['title']) ? $item['title'] : '';
		$target = seed_nav_menu_target($item, $title);
		$match  = $target === null ? null : seed_nav_menu_find($existing, $target, $title);
		if ($match === null) {
			$new = seed_nav_item_is_new($item);
			if ($new || seed_fills()) {
				$added[$new ? 'new' : 'other'] += seed_nav_menu_items($menu_id, [$item], $parent_id, $position);
			}
			continue;
		}
		$children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];
		if ($children !== []) {
			seed_nav_menu_merge($menu_id, $children, (int) $match->ID, $existing, $position, $added);
		}
	}
}

/**
 * Whether a menu item links to something this run adds: a page or term it
 * creates, or the archive of a post type that had no records.
 *
 * @param array<string, mixed> $item
 */
function seed_nav_item_is_new(array $item): bool
{
	global $seed_report;

	if (isset($item['page']) && is_string($item['page'])) {
		return isset($seed_report['new']['post:page/' . $item['page']]);
	}
	if (isset($item['post_type_archive']) && is_string($item['post_type_archive'])) {
		return seed_was_empty('post:' . $item['post_type_archive']);
	}
	if (isset($item['term']) && is_array($item['term']) && count($item['term']) === 2) {
		[$taxonomy, $slug] = array_map('strval', array_values($item['term']));
		return isset($seed_report['new']["term:{$taxonomy}/{$slug}"]);
	}
	return false;
}

/**
 * The menu item that links to `$target`, wherever it was moved in the menu. A
 * custom link is also found by its label, so one whose address was edited
 * isn't added again.
 *
 * @param array<int, WP_Post>      $existing
 * @param array<string, int|string> $target
 */
function seed_nav_menu_find(array $existing, array $target, string $title): ?WP_Post
{
	foreach ($existing as $item) {
		if ((string) $item->type !== $target['menu-item-type']) {
			continue;
		}
		$same = match ($target['menu-item-type']) {
			'custom'            => untrailingslashit((string) $item->url) === untrailingslashit((string) $target['menu-item-url'])
				|| ($title !== '' && (string) $item->title === $title),
			'post_type_archive' => (string) $item->object === $target['menu-item-object'],
			default             => (string) $item->object === $target['menu-item-object'] && (int) $item->object_id === (int) $target['menu-item-object-id'],
		};
		if ($same) {
			return $item;
		}
	}
	return null;
}

/**
 * Write items, and their children, to a menu. How many items that is.
 *
 * @param array<int, mixed> $items
 */
function seed_nav_menu_items(int $menu_id, array $items, int $parent_id, int &$position): int
{
	$added = 0;
	foreach ($items as $item) {
		if (! is_array($item)) {
			continue;
		}

		$title    = isset($item['title']) && is_string($item['title']) ? $item['title'] : '';
		$target   = seed_nav_menu_target($item, $title);
		$children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];

		if (seed_dry()) {
			$added += 1 + seed_nav_menu_count($children);
			continue;
		}
		if ($target === null) {
			seed_fail('Nav item could not be resolved to what it links to: ' . wp_json_encode($item));
		}

		// WordPress names an untitled archive link "Event Archives"; a menu says "Events".
		if ($title === '' && $target['menu-item-type'] === 'post_type_archive') {
			$object = get_post_type_object((string) $target['menu-item-object']);
			$title  = $object ? (string) $object->labels->name : '';
		}

		$item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array_merge(
				$target,
				[
					'menu-item-title'     => $title,
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $position,
					'menu-item-parent-id' => $parent_id,
				]
			)
		);
		++$position;
		++$added;

		if ($item_id instanceof WP_Error) {
			seed_fail('Could not add nav item ' . $title . ': ' . $item_id->get_error_message());
		}

		if ($children !== []) {
			$added += seed_nav_menu_items($menu_id, $children, (int) $item_id, $position);
		}
	}
	return $added;
}

/**
 * The wp_update_nav_menu_item() fields for one item's link target. Null on a
 * dry run when the target is a record that run would create.
 *
 * @param array<string, mixed> $item
 * @return array<string, int|string>|null
 */
function seed_nav_menu_target(array $item, string $title): ?array
{
	global $seed_report;

	if (isset($item['page']) && is_string($item['page'])) {
		$page = get_page_by_path($item['page']);
		if (! $page instanceof WP_Post && ! str_contains($item['page'], '/')) {
			// get_page_by_path() only matches a child page by its full path; its slug alone finds it too.
			$page = get_posts(
				[
					'post_type'      => 'page',
					'name'           => $item['page'],
					'post_status'    => array_values(array_diff(get_post_stati(), ['auto-draft', 'inherit'])),
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				]
			)[0] ?? null;
		}
		if (! $page instanceof WP_Post) {
			if (isset($seed_report['new']['post:page/' . $item['page']])) {
				return null;
			}
			seed_fail("Nav item page '{$item['page']}' does not exist. Seed the page before the menu.");
		}
		return [
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => (int) $page->ID,
		];
	}

	if (isset($item['post_type_archive']) && is_string($item['post_type_archive'])) {
		$type = $item['post_type_archive'];
		$object = get_post_type_object($type);
		if (! $object || ! $object->has_archive) {
			seed_fail("Nav item post_type_archive '{$type}' is not a post type with an archive. Run php tools/check.php.");
		}
		return [
			'menu-item-type'   => 'post_type_archive',
			'menu-item-object' => $type,
		];
	}

	if (isset($item['term']) && is_array($item['term']) && count($item['term']) === 2) {
		[$taxonomy, $slug] = array_map('strval', array_values($item['term']));
		$term = get_term_by('slug', $slug, $taxonomy);
		if (! $term instanceof WP_Term) {
			if (isset($seed_report['new']["term:{$taxonomy}/{$slug}"])) {
				return null;
			}
			seed_fail("Nav item term '{$taxonomy}/{$slug}' does not exist. Seed the term before the menu.");
		}
		return [
			'menu-item-type'      => 'taxonomy',
			'menu-item-object'    => $taxonomy,
			'menu-item-object-id' => (int) $term->term_id,
		];
	}

	if (isset($item['url']) && is_string($item['url']) && $item['url'] !== '') {
		if ($title === '') {
			seed_fail("Nav item {$item['url']} needs a title.");
		}
		return [
			'menu-item-type' => 'custom',
			'menu-item-url'  => $item['url'],
		];
	}

	seed_fail('Nav item needs one of page, post_type_archive, term, or url: ' . wp_json_encode($item));
}

/** "3 Events", "1 Event". */
function seed_quantity(int $count, array $names): string
{
	return $count . ' ' . ($count === 1 ? $names[0] : $names[1]);
}

/**
 * ": Opening Night, Jazz Brunch and 6 more", so the person knows whose
 * fields are filled.
 *
 * @param array<int, string> $records
 */
function seed_record_names(array $records): string
{
	$records = array_values(array_filter($records, static fn (string $name): bool => trim($name) !== ''));
	if ($records === []) {
		return '';
	}
	$shown = array_slice($records, 0, 2);
	$more  = count($records) - count($shown);
	$list  = $more > 0 ? implode(', ', $shown) . " and {$more} more" : implode(' and ', $shown);
	return ": {$list}";
}

/**
 * What this run did, or would do, one line per change, for the person.
 *
 * @return array<int, string>
 */
function seed_lines(): array
{
	global $seed_report;

	$lines = [];
	foreach ($seed_report['groups'] as $group) {
		$names  = $group['names'];
		$fields = seed_quantity($group['fields'], ['empty field', 'empty fields']);
		if ($group['kind'] === 'media') {
			if ($group['created'] > 0) {
				$lines[] = 'Import ' . seed_quantity($group['created'], $names) . ' to the Media Library';
			}
			if ($group['filled'] > 0) {
				$lines[] = 'Add alt text to ' . seed_quantity($group['filled'], $names);
			}
			continue;
		}
		if ($group['kind'] === 'option') {
			if ($group['filled'] > 0) {
				$lines[] = "Fill {$fields} in {$names[0]}";
			}
			if ($group['reset'] > 0) {
				$lines[] = "Overwrite {$names[0]} with the sample values";
			}
			continue;
		}
		if ($group['created'] > 0) {
			$lines[] = 'Create ' . seed_quantity($group['created'], $names);
		}
		if ($group['filled'] > 0) {
			$lines[] = "Fill {$fields} on " . seed_quantity($group['filled'], $names) . seed_record_names($group['records']);
		}
		if ($group['reset'] > 0) {
			$lines[] = 'Overwrite ' . seed_quantity($group['reset'], $names) . ' with the sample content';
		}
	}

	return array_merge($lines, array_column($seed_report['notes'], 'text'));
}

/**
 * Print what the run did (or, on a dry run, would do), then a JSON report for
 * Shader. Call once, last, after every write.
 */
function seed_done(): void
{
	global $seed_report, $seed_run;

	$totals = ['created' => 0, 'fields' => 0, 'reset' => 0, 'kept' => 0];
	foreach ($seed_report['groups'] as $group) {
		$totals['created'] += $group['created'];
		$totals['fields']  += $group['fields'];
		$totals['reset']   += $group['reset'];
		$totals['kept']    += $group['kept'];
	}
	$lines = seed_lines();

	if (! seed_dry()) {
		update_option('shader_seeded', gmdate('c'), false);
	}

	printf(
		"%s: %d %s, %s %s, %d kept%s.\n",
		seed_dry() ? 'Seed dry run, nothing written' : 'Seed complete',
		$totals['created'],
		seed_dry() ? 'to create' : 'created',
		seed_quantity($totals['fields'], ['field', 'fields']),
		seed_dry() ? 'to fill' : 'filled',
		$totals['kept'],
		$totals['reset'] > 0 ? ', ' . $totals['reset'] . ' reset' : ''
	);
	foreach ($lines as $line) {
		echo '- ' . $line . "\n";
	}
	// Last, on its own line: what Shader shows the person before a run.
	echo wp_json_encode(
		[
			'first'   => $seed_report['first'],
			// Whether Shader asks the person first: only when the run changes what the site has.
			'touches' => seed_touches_existing(),
			// Whether there is something new to add on its own, with --no-fill.
			'adds'    => seed_adds_new(),
			'dry'     => $seed_run['dry'],
			'reset'   => $seed_run['reset'],
			'lines'   => $lines,
			'created' => $totals['created'],
			'filled'  => $totals['fields'],
			'kept'    => $totals['kept'],
		]
	) . "\n";
}
