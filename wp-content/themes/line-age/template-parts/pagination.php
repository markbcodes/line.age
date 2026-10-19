<?php
the_posts_pagination(
	[
		'mid_size'  => 1,
		'prev_text' => esc_html__('Previous', 'line-age'),
		'next_text' => esc_html__('Next', 'line-age'),
	]
);
