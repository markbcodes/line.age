<?php
/**
 * The guard logic for bin/deploy-plugin.sh must refuse production without
 * the override flag. Tests the pure decision function used by the script.
 */

declare(strict_types=1);

namespace SV\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/bin/deploy-guard.php';

final class DeployGuardTest extends TestCase
{
	public function test_refuses_production(): void
	{
		$this->assertFalse(\SVDeployGuard::allows('production', false));
	}

	public function test_refuses_unknown_environment_type(): void
	{
		$this->assertFalse(\SVDeployGuard::allows('', false));
	}

	public function test_allows_local_and_staging(): void
	{
		$this->assertTrue(\SVDeployGuard::allows('local', false));
		$this->assertTrue(\SVDeployGuard::allows('staging', false));
		$this->assertTrue(\SVDeployGuard::allows('development', false));
	}

	public function test_override_allows_production(): void
	{
		$this->assertTrue(\SVDeployGuard::allows('production', true));
		$this->assertTrue(\SVDeployGuard::allows('', true));
	}
}
