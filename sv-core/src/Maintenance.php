<?php
/**
 * Admin-only "SV ▸ Maintenance" screen. Three nonce-protected POST actions —
 * run migrations, list jobs, report Action Scheduler — whose output is
 * printed on the same screen. This is the no-CLI path when wp-cli is absent;
 * it runs the same SV\App\Cli handlers.
 */

declare(strict_types=1);

namespace SV\App;

final class Maintenance
{
	private const NONCE = 'sv_maintenance';

	public static function register(): void
	{
		add_action('admin_menu', [self::class, 'menu']);
		add_action('admin_post_sv_run_migrations', [self::class, 'handle_migrations']);
		add_action('admin_post_sv_list_jobs', [self::class, 'handle_jobs']);
		add_action('admin_post_sv_as_status', [self::class, 'handle_as_status']);
	}

	public static function menu(): void
	{
		add_menu_page(
			'SV',
			'SV',
			'manage_options',
			'sv-maintenance',
			[self::class, 'render'],
			'dashicons-admin-tools',
			61
		);
		add_submenu_page('sv-maintenance', 'Maintenance', 'Maintenance', 'manage_options', 'sv-maintenance', [self::class, 'render']);
	}

	public static function render(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		$output = (string) get_transient('sv_maintenance_output');
		delete_transient('sv_maintenance_output');
		?>
		<div class="wrap">
			<h1>SV ▸ Maintenance</h1>
			<?php if ($output !== '') : ?>
				<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:80em;overflow:auto;"><?php echo esc_html($output); ?></pre>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-right:1em;display:inline-block;">
				<input type="hidden" name="action" value="sv_run_migrations">
				<?php wp_nonce_field(self::NONCE); ?>
				<p><button type="submit" class="button button-primary">Run migrations</button></p>
			</form>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-right:1em;display:inline-block;">
				<input type="hidden" name="action" value="sv_list_jobs">
				<?php wp_nonce_field(self::NONCE); ?>
				<p><button type="submit" class="button">Job list</button></p>
			</form>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="sv_as_status">
				<?php wp_nonce_field(self::NONCE); ?>
				<p><button type="submit" class="button">Action Scheduler status</button></p>
			</form>
		</div>
		<?php
	}

	public static function handle_migrations(): void
	{
		self::guard();
		ob_start();
		$code = Cli::migrate();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-only, printed through esc_html on render.
		echo 'Exit code: ' . $code . "\n";
		self::store_and_redirect();
	}

	public static function handle_jobs(): void
	{
		self::guard();
		ob_start();
		Cli::jobs();
		self::store_and_redirect();
	}

	public static function handle_as_status(): void
	{
		self::guard();
		ob_start();
		Cli::as_status();
		self::store_and_redirect();
	}

	private static function guard(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die('Forbidden');
		}
		check_admin_referer(self::NONCE);
	}

	private static function store_and_redirect(): void
	{
		set_transient('sv_maintenance_output', ob_get_clean(), 60);
		wp_safe_redirect(admin_url('admin.php?page=sv-maintenance'));
		exit;
	}
}
