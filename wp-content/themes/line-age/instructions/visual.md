# Visual

You own the look. Invent it from the user's brief. Any visual direction, layout, or interaction is allowed. The rules below define how to implement the look, not what the look may be.

This scaffold is architecture: template hierarchy, Tailwind/Vite, hook class names, and a working nav. Keep those mechanics (skip link, keyboard nav, `.entry-content` compatibility, admin-bar handling). The shipped PHP is landmarks only. When you invent the look, add utilities next to those hooks. Do not grow `app.css` into a page or section stylesheet. Replace the appearance. There are no scaffold brand tokens or layout utilities to reuse.

## Lock the look first

Before markup or CSS, write a short `DESIGN.md` at the theme root. Record only what the user asked for.

If the brief names a live site, brand, or other observable reference, fetch and inspect it before writing `DESIGN.md`. When that reference is a live site, screenshot it at a desktop width and a phone width before writing `DESIGN.md`. Fetching the HTML is not that observation. A script-rendered page often returns only a title and a placeholder. Record the observed palette, type, card and chrome pattern, and density. Do not invent a domain stereotype when a named reference exists. Not cloning a name or logo is not permission to ignore the requested look.

When the brief is short and names no reference, also record a look inferred from the domain. Do not apply a Shader house style. A short brief still gets a designed site.

Then build that. Do not restyle from vibes mid-pass. Update `DESIGN.md` only when the system actually changes, including when observation shows the locked look drifted from a named reference.

## Implement with Tailwind

The templates are the stylesheet. Use Tailwind utilities directly in PHP by default for composition: layout, spacing, sizing, responsive behavior, ordinary typography, colors, and ordinary hover, focus, and current states.

- Put design tokens that should produce utilities in this theme's `@theme` block. That is the single token source. Do not recreate the same palette on `:root` as a parallel custom-property set. There are no `--shader-*` tokens.
- Put repeated markup in `template-parts/`.
- Keep hook names (`site-header`, `site-nav`, `entry-content`, `skip-link`) as landmarks and JavaScript targets. Do not invent BEM-style page or section classes such as `.home-hero`, `.stay-card`, or `.section-shell` when they only wrap utility-expressible declarations. That is a parallel custom component stylesheet, and it is a failure.
- Self-host web fonts: put the `woff2` files in `resources/fonts/` and declare them with `@font-face` in `app.css`, so the build copies them into `assets/build/fonts/`. Do not link a font CDN. Keep a font's license in `licenses/` when it requires one.
- Download each font file from Fontsource, one file per weight and style: `https://cdn.jsdelivr.net/fontsource/fonts/<font>@latest/latin-<weight>-<style>.woff2`, for example `inter@latest/latin-400-normal.woff2` or `playfair-display@latest/latin-700-italic.woff2`. Do not look for font files on this computer: installed fonts are rarely licensed for the web.
- Give each `@font-face` `font-display: fallback`, not `swap`: `swap` shows the fallback font first and then visibly swaps. List the one or two files the first screen uses (usually the body and heading weights) in `shader_theme_critical_fonts()` in `functions.php`, so they are preloaded and arrive before the text draws.
- Reserve custom CSS under `@layer components` only for justified exceptions: `@font-face`; WordPress-generated markup you cannot class directly (`wp_nav_menu()`, `.entry-content`, `#wpadminbar`); scaffold hook behavior (skip link, admin-bar offset, nav toggle); and pseudo-elements, `nth-child` layouts, or complex interactive states that utilities cannot express clearly. The one exception to the layer is an override of core WordPress CSS; see the WordPress constraints below.
- Never duplicate a control recipe and let it drift between templates.

## Tailwind v4

Tailwind is installed so you can design in the templates. It runs through `@tailwindcss/vite`.

- Keep the layered imports and `@source "../../**/*.php"` in `resources/css/app.css`. Keep markdown out of the scan. Extend `@source` if templates or class-bearing files are added outside the scanned paths.
- Import Tailwind Preflight. Keep it.
- Keep utility class names statically discoverable in PHP. For variants selected at runtime, use complete class maps rather than string interpolation such as `bg-${color}`.
- Shader rebuilds after class or CSS changes. Inspect `assets/build/app.css` and confirm that utilities used by the templates exist in the compiled output.

## WordPress constraints

- Hook names (`site-header`, `site-nav`, `entry-content`, `skip-link`) are landmarks and JavaScript targets, not a look.
- Keep the skip link, keyboard menu (`aria-expanded`, Escape), and admin-bar offset if chrome is fixed or sticky. The phone menu opens through utilities on the nav: `hidden data-[open=true]:block md:block`, with `md:hidden` on the toggle. When you restyle the nav, keep all three states: hidden by default, a `data-[open=true]:` display for the open phone menu, and an `md:` display for desktop. The shipped offset only matches `.sticky`, `.fixed`, or `.is-sticky` so a `relative` header is not shifted over the page. At 600px and below the shipped CSS drops the sticky offset to `0`, because the bar scrolls away there. The offset and the wide/full alignment rules sit outside `@layer` in `app.css` on purpose, so a `top-0` utility or core's figure margin cannot override them; keep them there. The skip link must remain visible when focused, including below the admin bar.
- Leave `body` unpositioned. At 600px and below WordPress sets `#wpadminbar` to `position: absolute` (between 600px and 782px it stays fixed, 46px tall), and that bar is a direct child of `body`. A `relative`, `absolute`, `fixed`, or `sticky` body becomes its positioning parent, so the bar drops by the admin-bar margin and covers the header. Put a positioning parent on an element inside the body.
- Style `wp_nav_menu()` output through this theme's navigation states. WordPress marks every `#anchor` item as current on the page it points into, so a one-page menu shows all of them as current. Style the current state only on items that link to another page, or have a script mark the section in view.
- Core WordPress CSS (`wp-block-library`, per-block styles, `global-styles`, the admin bar) is unlayered. Unlayered CSS beats every Tailwind utility and every `@layer components` rule on any property both set, whatever the specificity. When a utility or component rule does nothing on WordPress or block markup, core is winning. Override that one property with a rule outside any `@layer`, scoped under `.entry-content` or the hook class, or use Tailwind's important modifier (`mt-8!`). Do not move ordinary styling out of the layers.
- Dequeue only `classic-theme-styles`. Keep `wp-block-library` and `global-styles` so block-authored post and page content retains its styles. Block buttons still get core's dark default from `global-styles` (`.wp-block-button__link`, `.wp-element-button`). Restyle `.entry-content .wp-block-button__link` outside any `@layer` to match the locked button recipe.
- Do not put margin utilities on `figure`. Gutenberg’s unlayered `:where(figure)` rule sets `margin: 0 0 1em` and wins over them. Space from a parent.
- Put post/page body compatibility on `.entry-content` so Preflight does not flatten lists, headings, images, and spacing. The shipped layer restores list markers, heading sizes, link underlines, and a `1em` gap between blocks.
- Wide and full block alignment breaks out of `.entry-content` in the shipped CSS. It assumes a centered `.entry-content`; change the `40rem` half-width in `app.css` to match the locked content width.
- WordPress adds class names that Tailwind also treats as utilities: `sticky` on sticky posts and `columns-3` (and similar) on galleries. If the theme uses that utility anywhere, it also applies to that markup.
- Beyond the Preflight repairs shipped in `app.css`, do not restyle `#wpadminbar`.
- Motion must not hide content. Do not clip, zero-opacity, or translate content off-screen unless JavaScript has already marked the document ready, and reveal anything already in view without waiting on intersection.
- Nothing may move or resize once the page has appeared, until the person scrolls or acts: no layout shift. Give images and embeds their width and height or an aspect ratio, preload the fonts the first screen uses, and start scroll-driven effects (parallax, drift, zoom) from where the layout puts things, so their first frame changes nothing. Shader's `check` reports what moved after the page appeared.

Leave browser inspection to `quality.md` after seeded rendering is verified.
