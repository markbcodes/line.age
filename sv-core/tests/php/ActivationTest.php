<?php
/**
 * Activation: the hook is registered, SV_DEBUG follows WP_DEBUG, and running
 * the activation routine produces no output (and therefore no warnings).
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;
use SV\App\Activation;

final class ActivationTest extends TestCase
{
	public function test_activation_hook_is_registered(): void
	{
		$this->assertNotFalse(
			has_action( 'activate_sv-core/sv-core.php' ),
			'sv-core has no activation hook'
		);
	}

	public function test_sv_debug_follows_wp_debug(): void
	{
		$this->assertTrue( defined( 'WP_DEBUG' ) && WP_DEBUG, 'This test expects WP_DEBUG to be on' );
		$this->assertTrue( SV_DEBUG, 'SV_DEBUG is not following WP_DEBUG' );
	}

	public function test_activation_runs_without_output(): void
	{
		ob_start();
		Activation::run();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'Activation produced output (a warning would appear here)' );
	}
}
