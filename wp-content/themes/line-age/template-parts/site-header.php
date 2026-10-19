<a href="<?php echo esc_url(home_url('/')); ?>">
	<?php echo esc_html(get_bloginfo('name')); ?>
</a>
<button
	type="button"
	class="site-nav-toggle md:hidden"
	aria-controls="primary-menu"
	aria-expanded="false"
	data-nav-toggle
>
	<?php esc_html_e('Menu', 'line-age'); ?>
</button>
<nav
	id="primary-menu"
	class="site-nav hidden data-[open=true]:block md:block"
	aria-label="<?php echo esc_attr__('Primary', 'line-age'); ?>"
	data-nav-panel
	data-open="false"
>
	<?php
	wp_nav_menu(
		[
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => shader_theme_nav_menu_class(),
			'fallback_cb'    => 'shader_theme_nav_fallback',
		]
	);
	?>
</nav>
