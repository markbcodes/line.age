# Performance

## WordPress and VX queries

- Prefer the main query. Add a secondary `WP_Query` only when the template needs a distinct list.
- When a secondary query does not paginate, set `'no_found_rows' => true`.
- Paginate archives and search. Do not load unbounded post sets.
- Avoid `posts_per_page => -1` except for tiny, bounded sets you can justify.
- Cache expensive option or repeat lookups for the request.
- Before a loop that reads VX fields on each post (an archive, a related-posts block), pass the posts to `\VxFramework\Post::prime()` once: `\VxFramework\Post::prime($wp_query->posts)` or the array from `Post::query()`. The loop's field and relation reads then come from the cache instead of a query per post.

## Images

- Render content images with WordPress image functions so generated sizes, `srcset`, `sizes`, lazy loading, width, and height are available. Pass `$media->id()` into `wp_get_attachment_image()`. Do not call `get_id()` on media.
- Use WordPress core sizes (`thumbnail`, `medium`, `large`, `full`). Crop and aspect ratio in CSS (`object-cover`). Do not call `add_image_size()`.
- Do not treat a single-`src` `<img>` as a failure. Seed photos are often too small for a fat `srcset`. An `<img>` from a VX image field is enough for step 5.
- Import production content imagery into the Media Library. Never hotlink stock-library assets.
