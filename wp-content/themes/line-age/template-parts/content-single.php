<article <?php post_class(); ?>>
	<header>
		<h1><?php echo esc_html(get_the_title()); ?></h1>
		<p class="entry-meta">
			<time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
				<?php echo esc_html(get_the_date()); ?>
			</time>
		</p>
	</header>
	<div class="entry-content">
		<?php the_content(); ?>
	</div>
</article>
<?php
if (comments_open() || get_comments_number()) {
	comments_template();
}
