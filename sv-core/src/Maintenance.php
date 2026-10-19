<?php
/**
 * Admin-only "SV ▸ Maintenance" screen. Three nonce-protected POST actions —
 * run migrations, list jobs, report Action Scheduler — whose output is
 * printed on the same screen. This is the no-CLI path when wp-cli is absent;
 * it runs the same SV\App\Cli handlers.
 *
 * @package SV_Core
 */

declare(strict_types=1);

namespace SV\App;

use SV\Support\Log;

/**
 * Admin maintenance screen.
 */
final class Maintenance {

	private const NONCE = 'sv_maintenance';

	/**
	 * Registers the admin screen and its POST handlers.
	 */
	public static function register(): void {
		Log::debug( 'Maintenance::register' );

		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_sv_run_migrations', array( self::class, 'handle_migrations' ) );
		add_action( 'admin_post_sv_list_jobs', array( self::class, 'handle_jobs' ) );
		add_action( 'admin_post_sv_as_status', array( self::class, 'handle_as_status' ) );
	}

	/**
	 * Adds the top-level SV menu and its Maintenance submenu.
	 */
	public static function menu(): void {
		add_menu_page(
			'SV',
			'SV',
			'manage_options',
			'sv-maintenance',
			array( self::class, 'render' ),
			'dashicons-admin-tools',
			61
		);
		add_submenu_page( 'sv-maintenance', 'Maintenance', 'Maintenance', 'manage_options', 'sv-maintenance', array( self::class, 'render' ) );
	}

	/**
	 * Prints the screen, including the output of the last action.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		Log::debug( 'Maintenance::render' );

		$output = (string) get_transient( 'sv_maintenance_output' );
		delete_transient( 'sv_maintenance_output' );
		?>
		<div class="wrap">
			<h1>SV ▸ Maintenance</h1>
			<?php if ( '' !== $output ) : ?>
				<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:80em;overflow:auto;"><?php echo esc_html( $output ); ?></pre>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-right:1em;display:inline-block;">
				<input type="hidden" name="action" value="sv_run_migrations">
				<?php wp_nonce_field( self::NONCE ); ?>
				<p><button type="submit" class="button button-primary">Run migrations</button></p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-right:1em;display:inline-block;">
				<input type="hidden" name="action" value="sv_list_jobs">
				<?php wp_nonce_field( self::NONCE ); ?>
				<p><button type="submit" class="button">Job list</button></p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="sv_as_status">
				<?php wp_nonce_field( self::NONCE ); ?>
				<p><button type="submit" class="button">Action Scheduler status</button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Runs migrations and stores the output for the screen.
	 */
	public static function handle_migrations(): void {
		self::guard();
		Log::debug( 'Maintenance::handle_migrations' );
		ob_start();
		$code = Cli::migrate();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-only, printed through esc_html on render.
		echo 'Exit code: ' . $code . "\n";
		self::store_and_redirect();
	}

	/**
	 * Lists jobs and stores the output for the screen.
	 */
	public static function handle_jobs(): void {
		self::guard();
		Log::debug( 'Maintenance::handle_jobs' );
		ob_start();
		Cli::jobs();
		self::store_and_redirect();
	}

	/**
	 * Reports Action Scheduler state and stores the output.
	 */
	public static function handle_as_status(): void {
		self::guard();
		Log::debug( 'Maintenance::handle_as_status' );
		ob_start();
		Cli::as_status();
		self::store_and_redirect();
	}

	/**
	 * Rejects non-admins and requests without a valid nonce.
	 */
	private static function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( self::NONCE );
	}

	/**
	 * Stores the buffered output and redirects back to the screen.
	 */
	private static function store_and_redirect(): void {
		set_transient( 'sv_maintenance_output', ob_get_clean(), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=sv-maintenance' ) );
		exit;
	}
}
