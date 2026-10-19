<?php
/**
 * Guarded VX reads for this theme.
 *
 * Every helper here must stay readable before VX exists, before the first seed,
 * and when a record is missing. Presentation belongs in templates.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The VX facade for a post, or null when VX or the record is unavailable.
 */
function shader_record(?int $post_id = null): ?object {
	if (! class_exists('\\VxFramework\\Post')) {
		return null;
	}

	$post_id = $post_id ?? (int) get_the_ID();
	if ($post_id <= 0) {
		return null;
	}

	return \VxFramework\Post::get($post_id);
}

/**
 * A field value as a string, guarded at every step.
 */
function shader_text(?object $record, string $key, string $fallback = ''): string {
	if (! $record || ! method_exists($record, 'field')) {
		return $fallback;
	}

	$value = $record->field($key)->string();

	return $value !== '' ? $value : $fallback;
}

/**
 * A date, datetime, or time field formatted with a PHP date format.
 */
function shader_date(?object $record, string $key, string $format, string $fallback = ''): string {
	if (! $record || ! method_exists($record, 'field')) {
		return $fallback;
	}

	$formatted = $record->field($key)->date()?->format($format) ?? '';

	return $formatted !== '' ? $formatted : $fallback;
}

/**
 * The attachment id behind a VX media value, or 0.
 *
 * Media_Value exposes id(), not get_id().
 */
function shader_media_id(?object $media): int {
	if (! $media || ! method_exists($media, 'id')) {
		return 0;
	}

	return (int) $media->id();
}

/**
 * Render an image field through WordPress so srcset and sizes are available.
 *
 * @param array<string, mixed> $attr
 */
function shader_image_tag(?object $record, string $key, string $size = 'large', array $attr = []): string {
	if (! $record || ! method_exists($record, 'field')) {
		return '';
	}

	$media = $record->field($key)->image();
	$id    = shader_media_id(is_object($media) ? $media : null);

	if ($id <= 0) {
		return '';
	}

	$html = wp_get_attachment_image($id, $size, false, $attr);

	return is_string($html) ? $html : '';
}

/**
 * A gallery or files field as a list of media values.
 *
 * @return array<int, object>
 */
function shader_media_list(?object $record, string $key): array {
	if (! $record || ! method_exists($record, 'field')) {
		return [];
	}

	$media = $record->field($key)->media_all();

	if ($media instanceof Traversable) {
		$media = iterator_to_array($media, false);
	}

	if (! is_array($media)) {
		return [];
	}

	return array_values(array_filter($media, 'is_object'));
}

/**
 * Repeater and table-type field rows as objects.
 *
 * rows() returns Field_Row objects. Read a child with shader_text().
 *
 * @return array<int, object>
 */
function shader_rows(?object $record, string $key): array {
	if (! $record || ! method_exists($record, 'field')) {
		return [];
	}

	$rows = $record->field($key)->rows();

	if ($rows instanceof Traversable) {
		$rows = iterator_to_array($rows, false);
	}

	if (! is_array($rows)) {
		return [];
	}

	return array_values(array_filter($rows, 'is_object'));
}

/**
 * The VX facade for an option page, or null when VX or the page is unavailable.
 *
 * Pass it to the other helpers for non-text fields, e.g.
 * shader_image_tag(shader_option_page('site_settings'), 'logo').
 */
function shader_option_page(string $page): ?object {
	if (! class_exists('\\VxFramework\\Option_Page')) {
		return null;
	}

	return \VxFramework\Option_Page::get($page);
}

/**
 * An option-page field as a string, guarded when VX or the page is missing.
 */
function shader_option(string $page, string $key, string $fallback = ''): string {
	return shader_text(shader_option_page($page), $key, $fallback);
}
