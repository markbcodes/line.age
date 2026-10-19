# Ownership: plugin vs theme

This repository is one git repo at the Shader project root
(`C:\Users\Flabo\Shader\line-age`). Two code owners inside it:

## sv-core (plugin) — logic + app templates + built assets. Shader agent: hands off.

- Lives at the repo root in `/sv-core`, linked into WordPress as
  `wp-content/plugins/sv-core` via a directory junction (the repo layout keeps
  the plugin at root; WordPress only loads plugins from wp-content/plugins).
- Owns: app logic (`/src`), migrations (`/migrations`), the app templates
  (`/templates/app`), built JS (`/assets`, produced from `/client-src` by
  `bin/build.sh`), tests (`/tests`).
- **Never edit sv-core from Shader's agent. Never put app templates in the
  theme.** The theme agent must not touch anything under `/sv-core`.
- Every `wp sv ...` command exists in BOTH forms: WP-CLI (`sv-core/src/WpCli.php`,
  used on the hosted site) and `php bin/sv-cli.php <command>` (used on the
  Shader local, which has no `wp` binary — F1 in ENVIRONMENTS.md). Both call
  the same `SV\App\Cli` handlers. A new command added to one without the other
  is a bug; add both in the same commit.
- The Maintenance admin screen (`SV ▸ Maintenance`) is the third, no-CLI path:
  same handlers, nonce-protected POSTs.

## theme (line.age) — chrome, marketing, steward screens. Agent may rewrite.

- Lives where Shader puts it: `wp-content/themes/line-age/`. Do not move it.
- Owns: site chrome (header/footer), marketing pages, steward screens, VX
  config for theme content (`wp-content/themes/line-age/vx-config/`).
- The theme's only contact point with the plugin is the `template_include`
  filter in `functions.php` (marked "DO NOT REMOVE"): it delegates to
  `SV\App\Templates::maybe_override()`, which returns a plugin template only
  for `?sv_app_view=(dashboard|book|scene)` and passes every other request
  through untouched.

## The boundary in one sentence

The plugin renders the app; the theme renders everything else; the
`template_include` filter is the door between them, and the theme may not
close it.
