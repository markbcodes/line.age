<p><?php echo esc_html(get_bloginfo('name')); ?></p>
<?php if (has_nav_menu('footer')) : ?>
	<nav aria-label="<?php echo esc_attr__('Footer', 'line-age'); ?>">
		<?php
		wp_nav_menu(
			[
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => shader_theme_nav_menu_class(),
				'fallback_cb'    => false,
			]
		);
		?>
	</nav>
<?php endif; ?>
