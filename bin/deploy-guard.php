<?php
/**
 * bin/deploy-guard.php <environment-type> [--i-know-what-im-doing]
 *
 * The production guard for bin/deploy-plugin.sh, implemented in PHP so it is
 * unit-testable (sv-core/tests/php/DeployGuardTest.php). Reads the target's
 * wp_get_environment_type() over SSH; refuses "production" unless the override
 * flag is passed. Also refuses unknown/empty values: an unreadable target is
 * not a safe target.
 *
 * Exit codes: 0 = allowed, 1 = refused, 2 = usage error.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	exit(2);
}

// Only run the CLI flow when invoked directly (bin/deploy-guard.php ...);
// when included by tests, just define SVDeployGuard below.
$isMain = isset($_SERVER['SCRIPT_FILENAME'])
	&& realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__;

if ($isMain) {

$args = array_slice($argv, 1);
$override = in_array('--i-know-what-im-doing', $args, true);
$positional = array_values(array_filter($args, static fn(string $a): bool => ! str_starts_with($a, '--')));

$environmentType = $positional[0] ?? '';

$allowed = \SVDeployGuard::allows($environmentType, $override);

if (! $allowed) {
	fwrite(STDERR, "REFUSED: target environment_type is '{$environmentType}'.\n");
	fwrite(STDERR, "Deploying to production requires --i-know-what-im-doing.\n");
	exit(1);
}

echo "guard: allowed (environment_type='{$environmentType}')\n";
exit(0);
} // if ($isMain)

/**
 * Pure decision logic, shared with the PHPUnit test. Kept free of WordPress
 * so it runs anywhere PHP does.
 */
final class SVDeployGuard
{
	public static function allows(string $environmentType, bool $override): bool
	{
		if ($override) {
			return true;
		}
		// Empty/unreadable environment type is treated as production:
		// an unknown target is not a safe target.
		if ($environmentType === '' || $environmentType === 'production') {
			return false;
		}
		return true;
	}
}
