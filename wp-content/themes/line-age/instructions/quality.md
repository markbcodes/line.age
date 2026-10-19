# Browser QA

Check that the architecture works. Critique appearance against the brief and `DESIGN.md`. If the brief named a live reference, compare the result to that reference too. If `DESIGN.md` drifted from it, fix `DESIGN.md` and the templates together.

Before treating the theme as finished, check every route the header, footer, or a filter links to, at a desktop width and a phone width, including search and 404. If the browser will not go below about 500px, render the page inside a 390px frame. Screenshot a route only after its content images have decoded, and compare those shots with the reference screenshots from `visual.md`.

In Shader, in its chat or in one of its terminals, run Shader's `check` tool on each of those routes at widths 1280 and 390 and fix everything it reports; it reads the PHP error log and waits for images. Then use its `screenshot` tool, with `fullPage` for long pages, for the appearance review, and its `inspect` tool to measure an element instead of screenshotting it. Its `outline` tool lists what a person can see and use on a page, each with a reference, and every one of these tools takes `steps` (click, hover, type, press, scroll, wait) done before it looks: use them to check the phone menu opens and closes on Escape, hovers, tabs, dialogs and form errors. It opens the site logged out, so check edit screens another way. Where Shader's tools aren't available, as outside Shader, open the same routes in a browser at the same widths instead.

Open one edit screen for each post type and for a page. Every field on that screen is one that record's template reads.

An agent cannot drive Shader's embedded preview; the user can watch it.

## After the build

This full pass is for finishing a build. For later edits, match the checking to the change, because the user already sees small edits in the preview. After a text, wording, colour or spacing tweak, don't screenshot; if PHP changed, one `check` of that page is enough. After a layout or template change, `check` the pages it affects at both widths, and screenshot only when the look is the point of the change. Run a full pass again after a broad redesign or when the user asks.

Confirm:

- Skip link, header, primary nav, main landmark, and footer are present
- The shipped menu still sets `aria-expanded` and closes on Escape: at 390, `outline` with steps to click the menu button, then press Escape
- Logged-in admin bar does not cover the header at a desktop width or a phone width. At 600px and below the bar is absolute, so a positioned `body` places it on top of the header. A sticky header leaves no gap above it after scrolling on a phone
- Singular post and page: block-authored content keeps headings, lists, quotes, images, buttons, and alignment
- Archive and search: context heading, excerpts, and pagination
- Seeded VX values appear in the templates that read them; empty states remain readable
- Every image the design shows renders as a Media Library `<img>`: the featured image, or a VX image or gallery field. Do not require `srcset`.
- Motion must not hide content. Do not clip, zero-opacity, or translate content off-screen unless JavaScript has already marked the document ready, and reveal anything already in view without waiting on intersection.
- Nothing may move or resize once the page has appeared, until the person scrolls or acts: no layout shift. Give images and embeds their width and height or an aspect ratio, preload the fonts the first screen uses, and start scroll-driven effects (parallax, drift, zoom) from where the layout puts things, so their first frame changes nothing. Shader's `check` reports what moved after the page appeared.

Keep breakpoint-dependent selectors scoped to the state and viewport they style. Do not rely only on compiled class names in `app.css`.

## Source composition

Inspect representative PHP templates and `resources/css/app.css`. Confirm the templates contain the layout, spacing, color, and type utilities. Custom CSS may contain only the exceptions in `visual.md`. If a page or section class only restates utility-expressible declarations, move those declarations back into PHP during the focused refinement pass.

Inspect the representative routes in a browser. Critique the result against the brief and `DESIGN.md`. If the brief named a live reference, compare the result to that reference too. If `DESIGN.md` drifted, fix `DESIGN.md` and the templates together. Make one focused refinement pass, and recheck only the routes that change.
