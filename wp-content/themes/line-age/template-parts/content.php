<article <?php post_class(); ?>>
	<h2>
		<a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a>
	</h2>
	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div>
</article>
