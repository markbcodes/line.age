<?php
/**
 * A Page. Deliberately not the same as content-single.php: a Page is not a
 * dated entry, so it carries no published date. Keep the two differentiated as
 * they gain context-specific parts.
 */
?>
<article <?php post_class(); ?>>
	<header>
		<h1><?php echo esc_html(get_the_title()); ?></h1>
	</header>
	<div class="entry-content">
		<?php the_content(); ?>
	</div>
</article>
