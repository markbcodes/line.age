<?php
/**
 * The empty state.
 *
 * Archive and search templates print their own <h1> before falling through to
 * this part, so they pass `heading => false` and the message renders as body
 * copy. Contexts with no heading of their own let it stay the page's <h1>.
 *
 * @var array{heading?: bool} $args
 */

// Escaped once here so both branches below can share it.
$shader_message = is_search()
	? esc_html__('No results match that search.', 'line-age')
	: esc_html__('Nothing to show yet.', 'line-age');

$shader_heading = ! isset($args['heading']) || $args['heading'] !== false;
?>
<section>
	<?php if ($shader_heading) : ?>
		<h1><?php echo $shader_message; ?></h1>
	<?php else : ?>
		<p><?php echo $shader_message; ?></p>
	<?php endif; ?>
</section>
