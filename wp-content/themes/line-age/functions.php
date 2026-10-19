<?php
/**
 * line.age — Shader classic theme bootstrap.
 *
 * Keep this file as a registrar. Domain logic belongs in VX config; presentation
 * belongs in templates and template-parts.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

require_once get_template_directory() . '/inc/helpers.php';

/**
 * DO NOT REMOVE — theme/plugin boundary.
 *
 * sv-core owns the app: /app and ?sv_app_view=(dashboard|book|scene) render
 * plugin templates from sv-core/templates/app/. Everything else passes
 * through untouched. Removing this line silently breaks the app routes.
 * Ownership rules: docs/OWNERSHIP.md in the repository root.
 */
add_filter(
	'template_include',
	fn($t) => class_exists(\SV\App\Templates::class)
		? \SV\App\Templates::maybe_override($t)
		: $t
);

function shader_theme_asset_url(string $relative): string {
	return get_template_directory_uri() . '/' . ltrim($relative, '/');
}

function shader_theme_enqueue_assets(): void {
	$css = get_template_directory() . '/assets/build/app.css';
	if (is_readable($css)) {
		wp_enqueue_style(
			'shader-theme',
			shader_theme_asset_url('assets/build/app.css'),
			[],
			(string) filemtime($css)
		);
	}

	$js = get_template_directory() . '/assets/build/app.js';
	if (is_readable($js) && filesize($js) > 32) {
		wp_enqueue_script(
			'shader-theme',
			shader_theme_asset_url('assets/build/app.js'),
			[],
			(string) filemtime($js),
			true
		);
	}
}
add_action('wp_enqueue_scripts', 'shader_theme_enqueue_assets');

/**
 * The `woff2` files from resources/fonts/ that the first screen uses, e.g. `['inter-regular.woff2']`.
 * They're preloaded, so text shows in its own font from the start.
 */
function shader_theme_critical_fonts(): array {
	return [];
}

function shader_theme_preload_fonts(): void {
	foreach (shader_theme_critical_fonts() as $file) {
		$relative = 'assets/build/fonts/' . basename((string) $file);
		if (is_readable(get_template_directory() . '/' . $relative)) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url(shader_theme_asset_url($relative))
			);
		}
	}
}
add_action('wp_head', 'shader_theme_preload_fonts', 1);

function shader_theme_dequeue_core_styles(): void {
	wp_dequeue_style('classic-theme-styles');
}
add_action('wp_enqueue_scripts', 'shader_theme_dequeue_core_styles', 100);

function shader_theme_setup(): void {
	load_theme_textdomain('line-age', get_template_directory() . '/languages');
	add_theme_support('title-tag');
	add_theme_support('automatic-feed-links');
	add_theme_support('post-thumbnails');
	add_theme_support('responsive-embeds');
	add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
	// The editor only offers wide and full alignment when the theme opts in.
	// app.css breaks .alignwide and .alignfull out of a centered .entry-content;
	// without this opt-in those rules are unreachable.
	add_theme_support('align-wide');
	add_theme_support('editor-styles');
	add_editor_style('assets/editor.css');
	register_nav_menus(
		[
			'primary' => 'Primary',
			'footer'  => 'Footer',
		]
	);
}
add_action('after_setup_theme', 'shader_theme_setup');

function shader_theme_nav_menu_class(): string {
	return 'menu-primary';
}

function shader_theme_nav_fallback(): void {
	echo '<ul class="' . esc_attr(shader_theme_nav_menu_class()) . '">';
	wp_list_pages(
		[
			'title_li' => '',
			'depth'    => 1,
		]
	);
	echo '</ul>';
}
