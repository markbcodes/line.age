# Workflow

You are building a WordPress **classic theme** whose backend is **VX Framework**. VX is the application and data layer. This theme is only the presentation layer.

The user will describe a site. Infer the domain model, encode it as VX config-as-code, then generate PHP templates that consume that exact model. Seed representative content after a VX-wired visible MVP exists. Do not invent a parallel data layer in custom tables, raw post meta, or hardcoded copy where VX should own the structure.

The user may request any kind of site, layout, interaction, or visual direction. The prompt decides what is built and how it looks; these files decide how it is modeled, rendered, built, seeded, and packaged. Do not treat architecture rules as a reason to simplify, conventionalize, or visually restrain the requested concept. These files do not supply a design doctrine.

Complete these steps in order:

1. Interpret the requested site. Infer entities, relationships, taxonomies, option-page settings, field shapes, and product decisions such as archive ordering.
2. Write that backend into `vx-config/` from `vx.md` in this folder. The model may be complete even when later routes wait. Every post type, option page, and fieldset needs its own fitting `icon_svg`, never a repeat or an example from `vx.md`; turn the block editor off where a post type is mostly fields (both in `vx.md`). After every `vx-config/` change, run `php tools/check.php` from the theme directory. VX skips an invalid config file without an error, so an edit can silently do nothing. The check prints every skipped file and conflict and every key a template reads that `vx-config/` does not define, then flushes permalinks. Fix what it prints and rerun until it passes, before templates or the seeder depend on the new types.
3. Write `DESIGN.md` from `visual.md` before markup or CSS. If the brief names a live reference, observe it before locking the look. Then build a VX-wired visible MVP: shared chrome, home, one representative archive, one singular, and empty states. Do not build the rest of the brief yet. Do not write extra page templates (About, contact-only pages), extra taxonomy archives, or restyle core fallbacks until step 5 passes. Templates read VX keys during this pass and stay readable when records are absent. Keep `functions.php` and required helpers syntactically valid after every save — the user is watching Shader’s preview, and a fatal hides the build. After the first class or CSS changes, Shader rebuilds automatically — verify `assets/build/app.css` rather than running `npm run build` so the preview is not an unstyled tree. Follow `templates.md`, `visual.md`, `performance.md`, `blocks.md`, and `build.md` together. This scaffold has no brand, tokens, or layout language.
4. Unless the user opts out, write `tools/seed.php` and run it with Shader's `seed` tool (`seeding.md`), with the smallest coherent dataset: normally 3–5 primary records plus required related and settings records. Templates may already render empty states before the first successful seed.
5. Verify that the seeded records appear in the existing templates: the seeded VX values render, and every image the design shows renders as a Media Library `<img>` (the featured image, or a VX image or gallery field). Do not rebuild the templates. Never duplicate field values in static markup. Do not spend a turn counting HTTP 200s or landmarks.
6. Expand beyond the MVP only after step 5 passes, and in the same turn. Template every remaining route the brief needs: each post type, taxonomy, and page the designed navigation reaches, plus search and 404. For a named reference, that is the reference’s main pages, not every legal footer link. Extend the seeder when those pages need records. Do not end the turn between the seeded-rendering check and this step. A list of routes the brief already asked for means the turn is unfinished.
7. Follow `quality.md` for one browser QA pass on that full set. Recheck only the routes that change. Judge appearance against the brief, not a Shader quality bar.
8. Record in `MAP.md` what the site is for and any decisions made with the user. Shader keeps the structure in `INDEX.md`.

Hard bans: no parallel data layer, no hardcoded VX values, no invented field keys. Domain templates and VX-driven queries must reference a key that exists in `vx-config/`. Core `page.php`, `index.php`, `search.php`, and `404.php` do not need VX keys. That does not mean leave them unstyled. Restyle them in step 6.

Example end state for “build a yoga classes site”:

- VX owns classes, instructors, schedules, relationships, fields, taxonomies, and business settings.
- Generated archives, singles, and components read that model through VX facades.
- A development-only PHP seeder creates representative posts, terms, relationships, option values, and Media Library attachments in the database.
