# line.age

Classic Theme 1.0. VX Framework owns the data model; this theme is the front end. Option pages, dictionaries, and some field types need an active VX Pro license to be editable in wp-admin: choose one when creating the site in Shader, or activate it on VX's license screen.

## Start

1. Activate the theme in Shader.
2. Open this folder however you work — Shader's terminal, any editor, or Cursor.
3. Fill `vx-config/` from the brief, write the PHP templates, then seed representative content through `tools/seed.php`. Do that yourself or with an agent. This folder is architecture only — no starter brand. The look follows the brief.

## Develop assets

Shader builds CSS and JS automatically. Each theme gets its own ~34MB `node_modules` on first build. Adding a new package still needs system Node and npm.

With system Node 20+, you can also work on the theme outside Shader:

```bash
npm install
npm run dev
npm run build
```

Compiled CSS and JS land in `assets/build/`. The theme already ships an initial build so it activates before the first in-app rebuild.

## Layout

- `instructions/` — agent workflow; edit this folder to change how themes are built
- `vx-config/` — post types, taxonomies, fieldsets, option pages, dictionaries
- `tools/check.php` — CLI-only VX config check; run after every `vx-config/` change
- `tools/seed.php` — CLI-only sample-content seeder: this site's records; the shipped stub exits nonzero until replaced
- `tools/seed-lib.php` — Shader's seeding library that `tools/seed.php` requires; not edited by hand
- PHP templates and `template-parts/` — WordPress template hierarchy
- `resources/` — Tailwind/Vite sources; the build is installed, the look is not
- `shader.theme.json` — build and package contract

See `instructions/` for the model-first workflow. `AGENTS.md` is only for agents; it points there.
