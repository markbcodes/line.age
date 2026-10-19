# Templates

Use the WordPress template hierarchy and `get_template_part()`. `functions.php` is a registrar only.

## Hierarchy

Start with the MVP slice (shared chrome, home, one archive, one singular, empty states). During that slice, do not link a route that has no designed template. After seeded rendering is verified, add a template for every post type, taxonomy, and page the navigation reaches, then restyle the core fallbacks to the locked look. Add type-specific files when the model needs them:

| File | Use when |
| --- | --- |
| `front-page.php` | A static homepage is the designed landing |
| `home.php` | The posts index is the designed landing or blog index |
| `single-{type}.php` | A VX post type needs its own singular |
| `archive-{type}.php` | A VX post type needs its own archive |
| `taxonomy-{taxonomy}.php` | A VX taxonomy archive needs its own layout |
| `templates/{name}.php` | A page needs its own layout and fields (About, Contact). Start the file with a `Template Name:` header, target it with the `page_template` condition, and assign it in the seed with `template` (`vx.md`) |
| `page.php`, `index.php`, `search.php`, `404.php` | Core fallbacks; they do not need VX keys. Restyle them to the locked look after the seeded-rendering check. |

Page-specific editable content belongs to the WordPress Page it describes. Attach fieldsets to the `page` post type, narrowed to the pages that use them (a `page_template` condition, or `page_type` for the front page), and have the relevant page template read the current page record. This includes a static homepage rendered through `front-page.php`. Do not create an option page to hold content for a page. Use option pages only for site-wide settings reused across multiple pages, such as contact details, social links, and global branding.

Keep domain queries and VX reads in the template or template part that renders them.

Visible copy in a template is never bare text in the HTML. Decide where each piece lives by who rewords it:

- Wording the site owner would reword is a field: section headings, intros, notes, and button or call-to-action labels. Put it in the page's fieldset when it belongs to one page, and in an option page when it shows on every page (header, footer, the heading over opening hours). The owner then changes it in WP Admin; anything else needs a change to the theme.
- Interface wording a visitor never thinks of as content is a translation call with the theme's text domain: "Skip to content", "Search", "Menu", "Closed", "to", screen-reader text, empty states, form labels, and the placeholder a field shows while it is empty. Keep each translated string one complete phrase rather than pieces joined around markup.

```php
// A heading the owner rewords: a field, with interface wording as its placeholder.
echo esc_html(shader_text(shader_record(), 'story_heading', __('Our story', 'line-age')));
// Interface wording: a translation call.
esc_html_e('Closed', 'line-age');
```

## Read VX in the template

Guard empty values. Do not invent keys. Templates must stay readable before the first seed, before taxonomies or option pages exist, and when the VX facade class is missing. Facades can be null: `\VxFramework\Post::get()`, `\VxFramework\Term::get()`, `\VxFramework\User::get()`, and `\VxFramework\Option_Page::get()` return `?self`. Guard the class, then the facade, then the field. Do not assume an option page or taxonomy is registered on the first request.

Default reads go through `inc/helpers.php`:

```php
$record = shader_record();
echo '<p>' . esc_html(shader_text($record, 'subtitle', __('No subtitle yet.', 'line-age'))) . '</p>';
the_post_thumbnail('large');
echo esc_html(shader_date($record, 'published_on', 'F j, Y'));

foreach (shader_rows($record, 'editions') as $row) {
	echo esc_html(shader_text($row, 'name', ''));
}

echo esc_html(shader_option('catalog_settings', 'support_phone', ''));
echo shader_image_tag(shader_option_page('catalog_settings'), 'logo', 'medium');

$person = shader_record($author_id);
echo shader_image_tag($person, 'portrait', 'large');
```

Those helpers implement this contract. A post's primary image is its featured image; render it with `the_post_thumbnail()` or `get_the_post_thumbnail()`. For a VX image field, pass `id()` into `wp_get_attachment_image()`. See `vx.md`.

```php
if (! class_exists('\\VxFramework\\Post')) {
	echo '<p>' . esc_html__('No subtitle yet.', 'line-age') . '</p>';
} else {
	$record = \VxFramework\Post::get(get_the_ID());

	if (! $record || $record->field('subtitle')->is_empty()) {
		echo '<p>' . esc_html__('No subtitle yet.', 'line-age') . '</p>';
	} else {
		echo '<p>' . esc_html($record->field('subtitle')->string()) . '</p>';
	}

	$portrait = $record?->field('portrait')->image();
	if ($portrait) {
		echo wp_get_attachment_image($portrait->id(), 'large');
	}
}
```

## Structure

- Extract reusable fragments into `template-parts/`.
- Differentiate singular, page, index, archive, search, empty, and 404 rendering. Do not route every context through one linked heading.
- Keep a skip link and `<main id="content">` landmark in the shared header/footer.
- Escape titles, permalinks, and the site name. Use WordPress image functions and generated sizes. For VX image and gallery fields, pass `Media_Value::id()` into `wp_get_attachment_image()` as in `vx.md`. Do not default to `<img src="…url()…">`. Never call `get_id()` on `Media_Value`.
- Forms: a local Shader site sends no mail. Build the form's design, but do not write mail handlers or store submissions outside VX. Ask the user how submissions should be handled.
- Do not invent a page-builder, custom block library, or second routing system. Do not use Content Sections, Clone, or dynamic tags as theme architecture. A single `content_sections` field is allowed when the brief asks editors to stack a fixed set of layouts on one record. The template switches on the layout key. Clone stays out of theme config.
