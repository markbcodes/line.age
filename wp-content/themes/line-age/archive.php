<?php
get_header();

the_archive_title('<h1 class="archive-title">', '</h1>');
the_archive_description('<div class="archive-description">', '</div>');

if (have_posts()) {
	while (have_posts()) {
		the_post();
		get_template_part('template-parts/content');
	}
	get_template_part('template-parts/pagination');
} else {
	// The archive title above is already this page's <h1>.
	get_template_part('template-parts/content', 'none', ['heading' => false]);
}

get_footer();
