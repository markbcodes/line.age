<?php
/**
 * App view: scene. Owned by sv-core; reached via ?sv_app_view=scene.
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
	<title><?php esc_html_e('Scene', 'sv-core'); ?> — <?php bloginfo('name'); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class('sv-app'); ?>>
<main class="sv-app-view" data-view="scene">
	<h1><?php esc_html_e('Scene', 'sv-core'); ?></h1>
	<p><?php esc_html_e('SV app scene view. Skeleton only.', 'sv-core'); ?></p>
</main>
<?php wp_footer(); ?>
</body>
</html>
