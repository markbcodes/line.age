# VX

This file defines the VX contract for this theme. Do not use VX MCP to generate the theme. When a shape the brief needs is not in this file, read the VX docs at `../../plugins/vx-framework-pro/docs/` for that one shape: `developers/php/reading-values.md` for reads, `config-as-code/registering/json-files.md` for config files, `fields/` for field types, and `data-model/fieldsets/locations.md` for locations. If those docs do not specify the config or the read, read the plugin PHP for that same shape, then stop. A scaffold ban still wins when the plugin disagrees. Do not copy a private installer or a second write path out of the PHP.

Prefer JSON files in `vx-config/`. Use PHP `vxframework/register` + `Blueprint` only when the user asks or when a definition cannot be expressed as JSON.

Resolution is `database override > PHP registration > JSON file`. Structure travels with the theme. Content and secrets do not.

## Data model (`vx-config/`)

One file per object:

```text
vx-config/
├── post-types/
├── taxonomies/
├── fieldsets/
├── option-pages/
└── dictionaries/
```

Envelope:

```json
{
  "$schema": "vx-config/v1",
  "type": "fieldset",
  "key": "book_details",
  "config": {}
}
```

`type` is one of `post_type`, `taxonomy`, `fieldset`, `option_page`, `dictionary`. Template names: `single-{post_type}.php`, `archive-{post_type}.php`, `taxonomy-{taxonomy}.php`.

Keys use lowercase letters, digits, `_`, and `-`, and the file name is the key. WordPress caps post type keys at 20 characters and taxonomy and option page keys at 32. Do not use a WordPress query var as a post type or taxonomy key: `author`, `name`, `type`, `year`, `page`, `order`, `category`, `tag`, `s`, and the like.

Option pages, dictionaries, and the `post_relation`, `user_relation`, `markdown`, `files`, `table`, `location`, `area`, `content_sections`, `recurring_date`, and `clone` field types are VX Pro features. Use them when the site needs them. Without an active VX Pro license their values still store and render, but option pages and dictionaries do not appear in wp-admin, and the post editor shows "This field requires VX Pro" in place of those fields. `php tools/check.php` reports that; pass it on to the user.

## JSON examples

Use these shapes. Invent keys for the requested site; do not copy this catalog as a starter model. Every referenced key below is defined.

Every post type, option page, and fieldset must set its own `icon_svg`, so each one is recognisable at a glance in wp-admin and the VX dashboard. Pick an icon that names what the object holds: a calendar for event dates, a map pin for an address, a receipt for pricing. No two objects in a theme share an icon, and the SVGs in this file are only examples of the format: do not reuse them. Take icons from one free icon pack per theme so they match. Default to [Lucide](https://lucide.dev/icons/) (each icon's raw SVG is at `https://unpkg.com/lucide-static/icons/<name>.svg`; fetch every icon the model needs in one command); use Tabler Icons, Heroicons, or Phosphor when the user names one. Paste the raw SVG into `icon_svg` and keep its `currentColor` (for Lucide, `fill="none"` and `stroke="currentColor"`). Do not set `icon` (that is a Media Library attachment id and is not portable). Do not use Dashicons class names.

Post types:

```json
{
  "$schema": "vx-config/v1",
  "type": "post_type",
  "key": "book",
  "config": {
    "singular_name": "Book",
    "plural_name": "Books",
    "description": "Titles in the catalog",
    "show_in_rest": "enabled",
    "post_title": "enabled",
    "post_editor": "enabled",
    "post_thumbnail": "enabled",
    "post_excerpt": "enabled",
    "enable_archive_page": "enabled",
    "custom_permalink_base": true,
    "permalink_base": "books",
    "icon_svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 5v16\"/><path d=\"M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z\"/></svg>"
  }
}
```

```json
{
  "$schema": "vx-config/v1",
  "type": "post_type",
  "key": "person",
  "config": {
    "singular_name": "Person",
    "plural_name": "People",
    "show_in_rest": "enabled",
    "post_title": "enabled",
    "post_editor": "enabled",
    "enable_archive_page": "enabled",
    "custom_permalink_base": true,
    "permalink_base": "people",
    "block_editor": "disabled",
    "icon_svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg>"
  }
}
```

`permalink_base` only applies with `"custom_permalink_base": true`. Without it, URLs use the post type key.

`block_editor` picks the editor for a post type with a body (`post_editor`). Leave it out (the block editor) where people write long, rich content: articles, case studies, story-like pages. Set `"block_editor": "disabled"` for the classic editor where a record is mostly VX fields and its body is a short plain description, as with people, locations, events, products, or testimonials. The fields then sit right under the title instead of below a block canvas, which is quicker to fill in. A record with no free text at all sets `"post_editor": "disabled"` instead. Keep `show_in_rest` enabled either way, and keep the block editor on posts and pages.

Taxonomy:

```json
{
  "$schema": "vx-config/v1",
  "type": "taxonomy",
  "key": "genre",
  "config": {
    "singular_name": "Genre",
    "plural_name": "Genres",
    "post_types": ["book"],
    "hierarchical": "enabled",
    "public": "enabled",
    "show_in_rest": "enabled",
    "show_admin_column": "enabled"
  }
}
```

Option page:

```json
{
  "$schema": "vx-config/v1",
  "type": "option_page",
  "key": "catalog_settings",
  "config": {
    "label": "Catalog settings",
    "menu_type": "top_level",
    "menu_title": "Catalog",
    "capability": "manage_options",
    "autoload": true,
    "active": true,
    "icon_svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/></svg>"
  }
}
```

Dictionary:

```json
{
  "$schema": "vx-config/v1",
  "type": "dictionary",
  "key": "formats",
  "config": {
    "label": "Formats",
    "entries": [
      { "value": "hardcover", "label": "Hardcover" },
      { "value": "paperback", "label": "Paperback" }
    ]
  }
}
```

Fieldset on `book`:

```json
{
  "$schema": "vx-config/v1",
  "type": "fieldset",
  "key": "book_details",
  "config": {
    "label": "Book details",
    "active": true,
    "icon_svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M13.4 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.4\"/><path d=\"M2 6h4\"/><path d=\"M2 10h4\"/><path d=\"M2 14h4\"/><path d=\"M2 18h4\"/><path d=\"M21.378 5.626a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z\"/></svg>",
    "location": {
      "groups": [
        { "object": "post_type", "targets": ["book"] }
      ]
    },
    "fields": [
      { "type": "text", "key": "subtitle", "label": "Subtitle" },
      { "type": "number", "key": "pages", "label": "Pages", "min": 1 },
      { "type": "date", "key": "published_on", "label": "Published on" },
      { "type": "switcher", "key": "featured", "label": "Featured" },
      { "type": "url", "key": "buy_url", "label": "Buy URL" },
      {
        "type": "select",
        "key": "format",
        "label": "Format",
        "choices_source": "dictionary",
        "dictionary": "formats"
      },
      {
        "type": "taxonomy",
        "key": "genres",
        "label": "Genres",
        "taxonomy": "genre",
        "allow_create": true
      },
      {
        "type": "select_post",
        "key": "related_title",
        "label": "Related title",
        "post_types": ["book"],
        "multiple": false
      },
      {
        "type": "post_relation",
        "key": "authors",
        "label": "Authors",
        "relation_key": "book_authors",
        "relation_type": "has_many",
        "post_types": ["person"]
      },
      {
        "type": "repeater",
        "key": "editions",
        "label": "Editions",
        "dynamic_row_label": "name",
        "fields": [
          { "type": "text", "key": "name", "label": "Name" },
          { "type": "number", "key": "year", "label": "Year" }
        ]
      }
    ]
  }
}
```

Paired fieldset on `person`:

```json
{
  "$schema": "vx-config/v1",
  "type": "fieldset",
  "key": "person_details",
  "config": {
    "label": "Person details",
    "active": true,
    "icon_svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M13 19a4 4 0 00-8 0\"/><path d=\"M16 10h2\"/><path d=\"M16 14h2\"/><circle cx=\"9\" cy=\"12\" r=\"3\"/><rect x=\"2\" y=\"5\" width=\"20\" height=\"14\" rx=\"2\"/></svg>",
    "location": {
      "groups": [
        { "object": "post_type", "targets": ["person"] }
      ]
    },
    "fields": [
      { "type": "text", "key": "role", "label": "Role" },
      { "type": "image", "key": "portrait", "label": "Portrait" },
      {
        "type": "post_relation",
        "key": "books",
        "label": "Books",
        "relation_key": "book_authors",
        "relation_type": "belongs_to_many",
        "post_types": ["book"]
      }
    ]
  }
}
```

A record's primary image is its featured image: enable `post_thumbnail` on the post type and seed it with `featured_image`. Add an `image` or `gallery` field only for other images, such as a gallery, a portrait on a type without a featured image, or a logo on an option page. Do not add an image field that repeats the featured image.

Attach a fieldset to an option page with `"object": "option_page"` and that page key in `targets`. Attach to users with `"object": "user"`. Location `object` values used here: `post_type`, `taxonomy`, `option_page`, `user`.

Start with one fieldset per location. Split by topic only when a type or option page has more than about ten fields, so the admin form stays scannable. Each fieldset is its own metabox (or tab on an option page). Give each fieldset a short label and its own fitting `icon_svg` (see the icon rule above).

A fieldset on `page` appears on every page. A field that the template for that record does not read does not belong on the edit screen. Do not label a shared field "front page only." Narrow a post-type location with `conditions`. Each condition is `{ source, operator, value }` on the group, with `advanced_enabled: true` and `advanced_action` of `show` or `hide`.

- `page_type`: `front_page`, `posts_page`, `top_level`, `has_parent`, `has_children`. Operators `is_any_of` and `is_none_of`.
- `page_template`: template files as the theme names them (`templates/about.php`), or `default` for pages without one. Same operators.
- `specific_post` and `post_parent`: `{ post_type, slugs }`, where a slug with `/` is a page path (`company/about`). Same operators. In theme config always use `slugs`, never `ids`: ids are site data.
- `post_status`: `publish`, `future`, `draft`, `pending`, `private`, `trash`. Same operators.
- `taxonomy_term`: `{ taxonomy, terms }`. Operators `contains_any`, `contains_all`, `is_none_of`. Term ids are site data, so use this only when the seeder creates those terms.
- On a taxonomy: `specific_term` and `term_parent`. Same operators.
- On a user: `user_role`. Same operators.
- On any object: `viewer_role` with those operators, and `viewer_capability` with `contains_any`, `contains_all`, and `is_none_of`. They decide who sees the fieldset in the editor; template reads return the same values to every visitor.

Do not use `specific_user` in theme config. It stores database ids.

Home-only fields show on the front page. Other pages use `is_none_of`.

```json
{
  "location": {
    "groups": [
      {
        "object": "post_type",
        "targets": ["page"],
        "advanced_enabled": true,
        "advanced_action": "show",
        "conditions": [
          { "source": "page_type", "operator": "is_any_of", "value": ["front_page"] }
        ]
      }
    ]
  }
}
```

A page with its own layout and fields (About, Contact) gets a page template: `templates/about.php` with a `Template Name: About` header. Its fieldset targets that template, the seed assigns it with `template`, and the editor can pick it for any page:

```json
{
  "location": {
    "groups": [
      {
        "object": "post_type",
        "targets": ["page"],
        "advanced_enabled": true,
        "advanced_action": "show",
        "conditions": [
          { "source": "page_template", "operator": "is_any_of", "value": ["templates/about.php"] }
        ]
      }
    ]
  }
}
```

## Common field types

| Type | Config notes |
| --- | --- |
| `text`, `textarea`, `email`, `url`, `phone` | Scalar strings. `phone` stores E.164. |
| `rich_text`, `markdown` | Formatted strings. Render with `\VxFramework\render_rich_text()` / `\VxFramework\render_markdown()`. |
| `number` | Optional `min` / `max`. Decimals are kept. |
| `date` | `YYYY-MM-DD`. |
| `datetime` | `YYYY-MM-DD HH:MM:SS`. |
| `color` | CSS color string. |
| `switcher` | Boolean. |
| `select`, `radio` | Inline `choices` as `{ value, label }`, or `choices_source: "dictionary"` plus `dictionary`. |
| `multiselect`, `checkboxes` | Multiple choice values. |
| `image`, `video` | Single media field. |
| `gallery`, `files` | List of attachments. |
| `taxonomy` | `taxonomy` key; on posts this sets real term assignments. |
| `select_post`, `select_user` | `post_types` / `roles`. `multiple: true` allows several; `max_count` caps them. |
| `post_relation`, `user_relation` | Pair both sides with the same `relation_key`. One side is `has_one` or `has_many`. The other is `belongs_to_one` or `belongs_to_many`. |
| `group` | Named child fields in `fields`. |
| `repeater`, `table` | Rows of child fields in `fields`. On `repeater`, set `dynamic_row_label` to the child key that should title each row. |
| `time` | A time of day. Distinct from `date` and `datetime`. |
| `date_range` | `{ start, end }`. |
| `recurring_date` | A schedule with repeat rules. |
| `location` | An address and map point. Read with `\VxFramework\geo_component()` / `\VxFramework\geo_format()`, guarded with `function_exists()`. |
| `area` | A map region. |
| `list` | A list of strings. |
| `audio`, `svg` | A single media field. |
| `password`, `code` | Scalar strings. Render code with `\VxFramework\render_code()`. |
| `slider`, `stepper`, `range_slider` | Numeric inputs. |
| `button_group` | A choice control. |
| `ui_heading`, `ui_notice`, `ui_spacer`, `ui_html` | Editor chrome. They store no content. |

A field can show or hide from a sibling in the same fieldset. A hidden field is omitted from the save. Its stored value stays. Operators follow the source: text uses `equals` and `is_empty`, a switcher uses `is_true` / `is_false`, a choice uses `is_any_of`, a date uses `before` / `after`.

```json
{
  "conditional_logic": {
    "enabled": true,
    "action": "show",
    "groups": [
      { "match": "all", "rules": [ { "field": "format", "operator": "equals", "value": "film" } ] }
    ]
  }
}
```

Do not use Content Sections, Clone, or dynamic tags as theme architecture. Archives, singles, and chrome stay templates. A single `content_sections` field is allowed when the brief asks editors to stack a fixed set of layouts on one record. The template switches on the layout key. Clone stays out of theme config.

Top-level field keys must be unique among fieldsets that share a location target.

## PHP reads

```php
$post = \VxFramework\Post::get($post_id);
$term = \VxFramework\Term::get($term_id);
$user = \VxFramework\User::get($user_id);
$page = \VxFramework\Option_Page::get('catalog_settings');

$phone = $page?->field('support_phone')->string() ?? '';
```

Read option-page values through `Option_Page::get()`, `shader_option()`, or `shader_option_page()` for non-text fields. Do not use `\VxFramework\get()` for them: it reads VX's own configuration, not option-page values.

`get()` returns `null` when the record or option page is missing. Guard the facade before `->field()`. Unknown keys never throw. They return an empty field value.

Identity helpers:

```php
$post->get_id();
$post->get_title();
$post->get_link();
$post->get_status();
$term->get_id();
$term->get_label();
$term->get_slug();
```

Prefer the main query, then `\VxFramework\Post::get(get_the_ID())`. `\VxFramework\Post::query()`, `\VxFramework\Term::query()`, and `\VxFramework\Post::find()` wrap `get_posts` / `get_terms`. Use them only for a distinct list. `query()` takes `get_posts` arguments. A scalar field is post meta under its field key, so ordering by `starts_at` uses that key. Relations are not meta keys.

```php
$post->has_field('subtitle');
$post->field('subtitle')->exists();
$post->field('subtitle')->filled();
$post->field('subtitle')->is_empty();
$post->field('subtitle')->string(); // '' when missing
```

Prefer `value()` for the field's natural PHP shape. Named accessors force a type:

```php
$post->field('subtitle')->string();
$post->field('pages')->int();
$post->field('featured')->bool();
$post->field('format')->option();
$post->field('format')->option_label();
$person->field('portrait')->image()?->id();
$person->field('portrait')->image()?->url('large');
$person->field('portrait')->image()?->alt();
$post->field('gallery')->media_all();
$post->field('published_on')->date()?->format('F j, Y');
$post->field('authors')->posts();
$post->field('authors')->post();
$post->field('authors')->ids();
$post->field('author')->user();
$post->field('author')->users();
$post->field('genres')->terms();
$post->field('genres')->term();
$post->field('tags')->options();
$post->field('tags')->option_labels();
$user->get_display_name();
$user->get_email();
$post->field('seo')->group()?->field('meta_title')->string();
```

`date()` reads `date`, `datetime`, and `time` fields in the site timezone; `shader_date($record, $key, $format)` is the guarded form. `terms()` and `term()` read a taxonomy field. `post()` is one related post and `posts()` is many. `user()` and `users()` read users. `options()` and `option_labels()` read a multi-choice field. `option()` stays the single-choice read.

A content-sections row is a `Section_Row`. Branch with `layout()`, then read that layout's fields with `field()`.

```php
foreach ($post->field('page_sections')->rows() as $section) {
	if ($section->layout() === 'hero') {
		echo esc_html($section->field('headline')->string());
	}
}
```

A `location` value is an address array: take `$value = $post->field('venue')->value()`, then read a part with `\VxFramework\geo_component($value, 'locality')` or format it with `\VxFramework\geo_format($value, '{street}, {locality}')`. Both exist only where the Location field does, so check `function_exists('\VxFramework\geo_format')` first. A `date_range` value is `{ start, end }`. A `list` value is a list of strings.

Do not call `save_fieldset_values()`, `\VxFramework\set()`, `\VxFramework\config()`, or dynamic-tag `Expression::render()`. Seeds keep writing through abilities.

`value()` shapes: text-like → `string`; number → `int|float|null`; switcher → `bool`; select → `['value' => …, 'label' => …]`; image → `?Media_Value`; gallery → `Media_Value[]`; relations → facade or array of facades; group → associative array; repeater → list of arrays.

`rows()` returns `Field_Row` objects. Read each child with `field($key)`. `value()` on a repeater is the list of arrays. Do not array-access a row.

```php
foreach ($post->field('editions')->rows() as $row) {
	echo esc_html($row->field('name')->string());
}
```

`image()` / `media()` return `?Media_Value`. `media_all()` / `gallery()` return `Media_Value[]`. On `Media_Value` use `id()`, `url($size = 'full')`, and `alt()`. `title()` and `caption()` are optional extras. Never call `get_id()` on `Media_Value`; that name is Post/Term only.

Render content images with WordPress image functions. Pass `id()` into `wp_get_attachment_image()`. `url()` / `alt()` stay valid; they are not the default for content images.

```php
$portrait = $person->field('portrait')->image();
if ($portrait) {
	echo wp_get_attachment_image($portrait->id(), 'large');
}

foreach ($record->field('gallery')->media_all() as $image) {
	echo wp_get_attachment_image($image->id(), 'large');
}
```

Render rich text with `\VxFramework\render_rich_text($post->field('body')->string())`. Render markdown with `\VxFramework\render_markdown($post->field('notes')->string())`. Escape ordinary strings yourself.

## Ability payloads

The seed calls abilities in-process after WordPress boots; its rules live in `seeding.md`. The payloads below are the same wherever they are called.

### Changing content on a built site

When the user asks to change content (move an event's date, add records, delete samples, update a setting), use Shader's `vx` tool. Do not edit the seed or write a PHP script. Find records and their values with `vx-framework/query-posts` and `vx-framework/get-post-values`, then write with `set-post-values`, `update-post`, `create-post`, `delete-post`, the term writes, or `set-option-page-values`. Put related changes in one call: the user approves them on one card, and `vx-framework/undo-change` with the `undo_batch_id` from the result reverses them together. The tool refuses model changes, which belong in `vx-config/`. Outside Shader, call the same abilities in-process.

```php
wp_get_ability('vx-framework/create-post')->execute([
  'post_type'      => 'book',
  'title'          => 'Night Catalog',
  'slug'           => 'night-catalog',
  'status'         => 'publish',
  'featured_image' => $cover_attachment_id,
  'values'         => [
    'subtitle' => 'A first printing',
    'pages'    => 240,
    'featured' => true,
    'format'   => 'hardcover',
  ],
  'strict'         => true,
]);

wp_get_ability('vx-framework/update-post')->execute([
  'id'             => $id,
  'title'          => 'Night Catalog',
  'slug'           => 'night-catalog',
  'status'         => 'publish',
  'featured_image' => $cover_attachment_id,
]);

wp_get_ability('vx-framework/update-post')->execute([
  'id'       => $about_page_id,
  'template' => 'templates/about.php',
]);

wp_get_ability('vx-framework/set-post-values')->execute([
  'id'     => $id,
  'values' => [ 'subtitle' => 'Revised subtitle' ],
  'strict' => true,
]);

wp_get_ability('vx-framework/set-post-values')->execute([
  'id'     => $person_id,
  'values' => [ 'portrait' => $portrait_attachment_id ],
  'strict' => true,
]);

wp_get_ability('vx-framework/create-term')->execute([
  'taxonomy' => 'genre',
  'name'     => 'Fiction',
  'slug'     => 'fiction',
  'strict'   => true,
]);

wp_get_ability('vx-framework/update-term')->execute([
  'id'   => $term_id,
  'name' => 'Fiction',
  'slug' => 'fiction',
]);

wp_get_ability('vx-framework/set-term-values')->execute([
  'id'     => $term_id,
  'values' => [ 'blurb' => 'Made-up stories' ],
  'strict' => true,
]);

wp_get_ability('vx-framework/set-option-page-values')->execute([
  'key'    => 'catalog_settings',
  'values' => [ 'support_phone' => '+15555550100' ],
  'strict' => true,
]);

wp_get_ability('vx-framework/flush-permalinks')->execute([]);
```

`vx-framework/create-post` / `vx-framework/update-post` write WordPress columns (`title`, `content`, `excerpt`, `slug`, `status`, `author`, `date`, `parent`, `menu_order`, `featured_image`, `template`). `template` must be a template the theme offers, so write the template file first. Field values go in `values` on create, or through `vx-framework/set-post-values`. Do not put `title` in `values`.

## Read / write asymmetry

Writes do not accept the richer read objects.

- Choice fields: write the choice `value` string (or an array of values). Reads return `{ value, label }`.
- Image / video / icon: write one attachment id. Reads return a media object.
- Gallery / files: write an array of attachment ids.
- `select_post` / `select_user` / relations: write an array of ids. Single-mode fields cap at one id. Both paired sides read the same stored edges, so write a relation once, from the `has_*` side. Each write replaces that side's whole list, so writing the other side too with a different list removes edges.
- Taxonomy fields: write an array of term ids.
- Group: write `{ child_key: value, … }`. Omitted children are cleared.
- Repeater / table: write an array of row objects. The array replaces stored rows.
- Number: write a number. Decimals are kept.
- Date field: write `YYYY-MM-DD`, a calendar day. Date-time field: `YYYY-MM-DD HH:MM:SS`, wall-clock time in the site timezone.
- Post `date` property on `vx-framework/create-post` / `vx-framework/update-post`: an ISO-8601 value without an offset is read as UTC. Local time there schedules the post in the future.
- Phone: write E.164 (`+` and 7–15 digits).
