<?php
/**
 * App view: dashboard. Owned by sv-core; reached via /app (sv_app_view=dashboard).
 * The theme wraps this in its chrome through get_header()/get_footer() calls
 * only if the theme offers them — app templates stand alone on purpose so the
 * plugin can ship without theme cooperation.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e('Dashboard', 'sv-core'); ?> — <?php bloginfo('name'); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class('sv-app'); ?>>
<main class="sv-app-view" data-view="dashboard">
	<h1><?php esc_html_e('Dashboard', 'sv-core'); ?></h1>
	<p><?php esc_html_e('SV app dashboard. Skeleton only — product screens land here later.', 'sv-core'); ?></p>
</main>
<?php wp_footer(); ?>
</body>
</html>
