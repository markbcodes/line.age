# Classic Theme 1.0

You are building a WordPress classic theme. VX Framework is the data layer. This theme is the presentation layer.

The user may request any site, layout, interaction, or visual direction. These rules define implementation architecture, not creative taste: VX owns structured data, PHP templates own presentation, and Tailwind utilities implement the look.

[`instructions/`](instructions/README.md) is the source of truth for that architecture. The user's prompt owns what is built and how it looks. Read the files for the current phase. Do not preload every file.

Start every task with [`INDEX.md`](INDEX.md), [`MAP.md`](MAP.md), and `DESIGN.md` once it exists. Shader generates `INDEX.md` from the theme files and rewrites it whenever they change: the content model with every field, the routes and their templates, the template parts each template loads, the helpers, and the design tokens. Trust it for structure. Read only the files you are about to change, and do not explore the whole theme to rediscover what it lists. `MAP.md` holds what the code can't: notes and decisions.

For a new site, build from the brief. Infer the content and look from the domain, state your assumptions in one or two lines at the start, and build. Ask only when the brief is truly ambiguous, when a named reference cannot be reached, or before discarding work that exists. For a later section or page, ask only when the request leaves its content or placement unclear. The files above are enough context; explore only what the change needs.

Update `MAP.md` at the end of a turn that settles a decision with the user or changes what the theme is for. Keep it short. Do not copy structure into it or edit `INDEX.md`; Shader keeps that current.

Order: write `vx-config/` from `instructions/vx.md` → `php tools/check.php` passes → build a VX-wired visible MVP with empty states → seed a small dataset → verify seeded rendering → template every remaining route the brief needs → one browser QA pass → record decisions in `MAP.md`.

The model in `vx-config/` may be complete. Templates stay the MVP slice (shared chrome, home, one archive, one singular, empty states) until seeded rendering is verified: those routes show the seeded VX values, and every image the design shows renders as a Media Library `<img>` (the featured image, or a VX image or gallery field). Do not write extra page templates, extra taxonomy archives, or restyle core fallbacks until then. After that check, template every remaining route in the same turn, then do the browser QA pass. Do not end the turn between the seeded-rendering check and that expansion. A list of routes the brief already asked for means the turn is unfinished.

Run the theme's tools from this theme folder with Shader's PHP at `.shader/bin/php` (`.shader\bin\php.cmd` on Windows): wherever these files say `php tools/...`, run `.shader/bin/php tools/...`. It is the PHP this site runs on, with the same version, extensions, and settings, and Shader keeps it pointed there on each device. Use `php` only when that file does not exist. If neither works, ask the user to open the site in Shader once, or to install PHP.

Do not use VX MCP to generate the theme. Use MCP only if the user asks about a live site. `instructions/vx.md` is the contract. When a shape the brief needs is not in it, read the VX docs at `../../plugins/vx-framework-pro/docs/` for that one shape: `developers/php/reading-values.md` for reads, `config-as-code/registering/json-files.md` for config files, `fields/` for field types, and `data-model/fieldsets/locations.md` for locations. If those docs do not specify the config or the read, read the plugin PHP for that same shape, then stop. A scaffold ban still wins when the plugin disagrees. Do not copy a private installer or a second write path out of the PHP. Seeds stay on abilities.

`instructions/vx.md` defines the VX schema contract for this theme.
