<?php
get_header();

printf(
	'<h1 class="archive-title">%s</h1>',
	sprintf(
		/* translators: %s: search query */
		esc_html__('Search results for “%s”', 'line-age'),
		esc_html(get_search_query())
	)
);

if (have_posts()) {
	while (have_posts()) {
		the_post();
		get_template_part('template-parts/content');
	}
	get_template_part('template-parts/pagination');
} else {
	// The results heading above is already this page's <h1>.
	get_template_part('template-parts/content', 'none', ['heading' => false]);
}

get_footer();
