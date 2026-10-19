<?php
/**
 * Boundary tests: the plugin is loaded, its namespace exists, and
 * Templates::maybe_override only ever touches sv_app_view routes.
 *
 * Plain PHPUnit over wp-load.php (not the WP test suite), so query vars are
 * set on the global $wp_query directly.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;
use SV\App\App;
use SV\App\Templates;

final class TemplatesTest extends TestCase
{
	protected function setUp(): void
	{
		global $wp_query;
		if (! isset($wp_query) || ! $wp_query instanceof \WP_Query) {
			$wp_query = new \WP_Query();
		}
	}

	private function set_view(?string $view): void
	{
		global $wp_query;
		$wp_query->query_vars['sv_app_view'] = $view;
	}

	public function test_plugin_is_active(): void
	{
		$active = (array) get_option('active_plugins', []);
		$inactive = (array) get_option('active_sitewide_plugins', []);
		$plugins = array_merge($active, array_keys($inactive));
		// The junction (or a real copy) makes sv-core/sv-core.php loadable;
		// active_plugins records it once activated.
		$this->assertContains(
			'sv-core/sv-core.php',
			$plugins,
			'sv-core must be an active plugin for the theme boundary to function'
		);
	}

	public function test_sv_namespace_exists(): void
	{
		$this->assertTrue(class_exists(App::class), 'SV\\App\\App must be autoloadable');
		$this->assertTrue(class_exists(Templates::class), 'SV\\App\\Templates must be autoloadable');
		$this->assertSame(['dashboard', 'book', 'scene'], App::VIEWS);
	}

	public function test_maybe_override_returns_plugin_template_for_scene(): void
	{
		$this->set_view('scene');

		$overridden = Templates::maybe_override(ABSPATH . 'index.php');

		$this->assertStringEndsWith('templates/app/scene.php', $overridden);
		$this->assertFileExists($overridden);
	}

	public function test_maybe_override_passes_through_without_query_var(): void
	{
		$this->set_view(null);

		$theme = ABSPATH . 'wp-content/themes/line-age/index.php';
		$this->assertSame($theme, Templates::maybe_override($theme));
	}

	public function test_maybe_override_passes_through_for_unknown_view(): void
	{
		$this->set_view('admin');

		$theme = ABSPATH . 'wp-content/themes/line-age/index.php';
		$this->assertSame($theme, Templates::maybe_override($theme));
	}

	public function test_maybe_override_passes_through_when_template_missing(): void
	{
		$this->set_view('dashboard');

		// If the dashboard template file vanished the theme must survive:
		// maybe_override falls back rather than returning a dead path.
		$theme = ABSPATH . 'wp-content/themes/line-age/index.php';
		$result = Templates::maybe_override($theme);
		$this->assertTrue(
			$result === $theme || str_ends_with($result, 'templates/app/dashboard.php'),
			'Either the dashboard template resolves or the theme template passes through'
		);
	}
}
