<?php
get_header();

if (have_posts()) {
	while (have_posts()) {
		the_post();
		get_template_part('template-parts/content', 'single');
	}
} else {
	get_template_part('template-parts/content', 'none');
}

get_footer();
