<?php
/**
 * Comments for a singular entry. Landmarks only; the look follows the brief.
 */

if (post_password_required()) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if (have_comments()) : ?>
		<h2>
			<?php
			printf(
				/* translators: %s: number of comments */
				esc_html(_n('%s comment', '%s comments', get_comments_number(), 'line-age')),
				esc_html(number_format_i18n(get_comments_number()))
			);
			?>
		</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				[
					'style'      => 'ol',
					'short_ping' => true,
				]
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if (! comments_open() && get_comments_number()) : ?>
		<p><?php esc_html_e('Comments are closed.', 'line-age'); ?></p>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
