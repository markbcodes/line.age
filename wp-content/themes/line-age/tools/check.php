<?php
/**
 * Development-only VX config check.
 *
 * Usage from this theme directory, after every change under vx-config/ and
 * after template changes that read new keys:
 *
 *   php tools/check.php
 *
 * Run it with Shader's PHP, the one this site runs on: .shader/bin/php in place
 * of php (.shader\bin\php.cmd on Windows). Use php only when that file does
 * not exist.
 *
 * VX skips a vx-config/ file that fails validation instead of failing loudly,
 * ignores the parts of a file that are invalid or name a field type nothing
 * registers, and lets a database copy of an object (made when someone edits it
 * in wp-admin) win over its JSON file. Each way a config edit silently does
 * nothing. This asks VX for every such conflict and prints them, warnings
 * included (VX Pro is always active here, so a warning is a real mistake),
 * checks that every
 * field key the templates read is defined in vx-config/, reports Pro features
 * the model uses while no VX Pro license is active, then flushes permalinks so
 * new post type and taxonomy URLs resolve. Exits nonzero while any conflict or
 * undefined key remains.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$theme_dir  = dirname(__DIR__);
$config_dir = $theme_dir . '/vx-config';
$problems   = 0;

$ability = wp_get_ability('vx-framework/get-config-conflicts');
if (! $ability) {
	fwrite(STDERR, "Ability vx-framework/get-config-conflicts is not registered. Is VX Framework Pro active?\n");
	exit(1);
}

$result = $ability->execute([]);
if ($result instanceof WP_Error) {
	fwrite(STDERR, 'vx-framework/get-config-conflicts: ' . $result->get_error_message() . "\n");
	exit(1);
}

$conflicts = is_array($result) && isset($result['conflicts']) && is_array($result['conflicts'])
	? $result['conflicts']
	: [];

foreach ($conflicts as $conflict) {
	if (! is_array($conflict)) {
		continue;
	}

	$kind     = (string) ($conflict['kind'] ?? 'conflict');
	$severity = ($conflict['severity'] ?? '') === 'warning' ? ', warning' : '';
	$target   = trim((string) ($conflict['group'] ?? '') . '/' . (string) ($conflict['key'] ?? ''), '/');
	$source   = isset($conflict['source']) && is_string($conflict['source']) ? $conflict['source'] : '';

	fwrite(STDERR, "\n[{$kind}{$severity}] {$target}" . ($source !== '' ? " ({$source})" : '') . "\n");

	foreach (['errors' => '', 'warnings' => 'warning: '] as $list => $prefix) {
		foreach ((array) ($conflict[$list] ?? []) as $entry) {
			if (is_array($entry)) {
				$path = (string) ($entry['path'] ?? '');
				fwrite(STDERR, '  - ' . $prefix . ($path !== '' ? $path . ': ' : '') . (string) ($entry['message'] ?? '') . "\n");
			}
		}
	}

	if (isset($conflict['detail']) && is_string($conflict['detail'])) {
		fwrite(STDERR, '  ' . $conflict['detail'] . "\n");
	}

	if ($kind === 'drift') {
		fwrite(STDERR, "  Someone edited this in wp-admin, so the database copy wins over the JSON file. Ask the user which version to keep; do not discard either on your own.\n");
	} elseif (isset($conflict['resolution']) && is_string($conflict['resolution'])) {
		fwrite(STDERR, '  ' . $conflict['resolution'] . "\n");
	}

	++$problems;
}

$model = check_read_model($config_dir);

foreach (check_undefined_template_keys($theme_dir, $model['keys']) as $undefined) {
	fwrite(STDERR, "\n[undefined_key] {$undefined['file']}:{$undefined['line']} reads '{$undefined['key']}', which no fieldset in vx-config/ defines.\n");
	fwrite(STDERR, "  Add the field to vx-config/, or read a key that exists. Do not invent keys.\n");
	++$problems;
}

check_report_license($model);

$flush = wp_get_ability('vx-framework/flush-permalinks');
if ($flush) {
	$flushed = $flush->execute([]);
	if ($flushed instanceof WP_Error) {
		fwrite(STDERR, 'vx-framework/flush-permalinks: ' . $flushed->get_error_message() . "\n");
		exit(1);
	}
}

if ($problems > 0) {
	fwrite(STDERR, "\n{$problems} VX problem(s). Fix them before editing templates or seeding.\n");
	exit(1);
}

echo "VX config OK. Permalinks flushed.\n";

/**
 * Every field key and field type in this theme's fieldsets, at any depth, plus
 * whether it defines option pages or dictionaries.
 *
 * @return array{keys: array<string, true>, types: array<string, true>, option_pages: bool, dictionaries: bool}
 */
function check_read_model(string $config_dir): array
{
	$model = [
		'keys'         => [],
		'types'        => [],
		'option_pages' => glob($config_dir . '/option-pages/*.json') !== [],
		'dictionaries' => glob($config_dir . '/dictionaries/*.json') !== [],
	];

	foreach (glob($config_dir . '/fieldsets/*.json') ?: [] as $file) {
		$decoded = json_decode((string) file_get_contents($file), true);
		$fields  = is_array($decoded) ? ($decoded['config']['fields'] ?? []) : [];
		check_collect_fields(is_array($fields) ? $fields : [], $model);
	}

	return $model;
}

/**
 * @param array<int, mixed> $fields
 * @param array{keys: array<string, true>, types: array<string, true>} $model
 */
function check_collect_fields(array $fields, array &$model): void
{
	foreach ($fields as $field) {
		if (! is_array($field)) {
			continue;
		}
		if (isset($field['key']) && is_string($field['key'])) {
			$model['keys'][$field['key']] = true;
		}
		if (isset($field['type']) && is_string($field['type'])) {
			$model['types'][$field['type']] = true;
		}
		if (isset($field['fields']) && is_array($field['fields'])) {
			check_collect_fields($field['fields'], $model);
		}
		foreach ((array) ($field['layouts'] ?? []) as $layout) {
			if (is_array($layout) && isset($layout['fields']) && is_array($layout['fields'])) {
				check_collect_fields($layout['fields'], $model);
			}
		}
	}
}

/**
 * Field keys the theme's PHP reads through VX that vx-config/ does not define.
 *
 * Looks for `->field('key')`, `->has_field('key')`, the key argument of the
 * inc/helpers.php readers, and `shader_option('page', 'key')`. Comments are
 * skipped, so a docblock example is not a read. Development tools and
 * dependencies are skipped.
 *
 * @param array<string, true> $defined
 * @return array<int, array{file: string, line: int, key: string}>
 */
function check_undefined_template_keys(string $theme_dir, array $defined): array
{
	$patterns = [
		'/->(?:has_)?field\(\s*[\'"]([A-Za-z0-9_-]+)[\'"]/',
		'/\bshader_(?:text|date|image_tag|media_list|rows)\(\s*[^,()]*(?:\([^()]*\))?[^,()]*,\s*[\'"]([A-Za-z0-9_-]+)[\'"]/',
		'/\bshader_option\(\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]([A-Za-z0-9_-]+)[\'"]/',
	];
	$skip = ['tools', 'node_modules', 'vendor', 'assets', 'resources', 'vx-config', 'instructions', 'languages'];

	$undefined = [];
	$files = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($theme_dir, FilesystemIterator::SKIP_DOTS),
			static fn (SplFileInfo $item): bool => ! ($item->isDir() && in_array($item->getFilename(), $skip, true))
				&& ! str_starts_with($item->getFilename(), '.')
		)
	);

	foreach ($files as $file) {
		if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
			continue;
		}
		$relative = ltrim(substr($file->getPathname(), strlen($theme_dir)), '/\\');
		foreach (check_code_lines((string) file_get_contents($file->getPathname())) as $index => $line) {
			foreach ($patterns as $pattern) {
				if (preg_match_all($pattern, $line, $matches) < 1) {
					continue;
				}
				foreach ($matches[1] as $key) {
					if (! isset($defined[$key])) {
						$undefined[] = ['file' => $relative, 'line' => $index + 1, 'key' => $key];
					}
				}
			}
		}
	}

	return $undefined;
}

/**
 * A PHP file's lines with comments blanked out, so line numbers still match.
 *
 * @return array<int, string>
 */
function check_code_lines(string $source): array
{
	$code = '';
	foreach (token_get_all($source) as $token) {
		if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
			$code .= str_repeat("\n", substr_count($token[1], "\n"));
			continue;
		}
		$code .= is_array($token) ? $token[1] : $token;
	}

	return explode("\n", $code);
}

/**
 * Warn, without failing, when the model uses Pro features no license covers.
 *
 * Values still store and render; what the site owner loses is the wp-admin
 * screens and pickers for those features.
 *
 * @param array{types: array<string, true>, option_pages: bool, dictionaries: bool} $model
 */
function check_report_license(array $model): void
{
	$status = wp_get_ability('vx-framework/get-system-status');
	$report = $status ? $status->execute([]) : null;
	if (! is_array($report) || ! isset($report['capabilities']) || ! is_array($report['capabilities'])) {
		return;
	}

	$capabilities = $report['capabilities'];
	$locked       = [];
	if ($model['option_pages'] && ($capabilities['module:option_pages'] ?? true) === false) {
		$locked[] = 'option pages';
	}
	if ($model['dictionaries'] && ($capabilities['module:dictionary'] ?? true) === false) {
		$locked[] = 'dictionaries';
	}
	foreach (array_keys($model['types']) as $type) {
		if (($capabilities['field:' . $type] ?? true) === false) {
			$locked[] = $type . ' fields';
		}
	}

	if ($locked === []) {
		return;
	}

	fwrite(STDERR, "\n[license] This model uses VX Pro features with no active VX Pro license: " . implode(', ', $locked) . ".\n");
	fwrite(STDERR, "  The front end and seeding still work, but until a VX Pro license is activated on this site (chosen when Shader creates a site, or later on VX's license screen in wp-admin), option pages and dictionaries do not appear in wp-admin and the post editor shows \"This field requires VX Pro\" in place of these fields. Tell the user; this is not a config error.\n");
}
