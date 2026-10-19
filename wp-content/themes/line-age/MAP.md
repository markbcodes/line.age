# line.age map

What the code can't tell the next session: decisions made with the user and what the theme is for. The structure itself is in `INDEX.md`, which Shader keeps current. Keep this file short. `AGENTS.md` says when to update it.

## Notes

What the site is, and intentions or known issues worth carrying between sessions.

- This theme is the presentation layer for an app whose logic lives in the
  `sv-core` plugin, kept in the repository at the Shader project root
  (`sv-core/`, `bin/`, `docs/`, `e2e/`). The theme stays where Shader put it:
  `wp-content/themes/line-age/`.
- The theme carries the **theme ↔ plugin boundary**: the `template_include`
  filter in `functions.php` marked DO NOT REMOVE. It hands `/app` and
  `?sv_app_view=(dashboard|book|scene)` to `SV\App\Templates::maybe_override()`,
  which returns a plugin template from `sv-core/templates/app/`. Remove the
  filter and the app routes silently fall back to theme templates. Rules:
  `docs/OWNERSHIP.md` at the project root.

- `sv-core/vendor/` is **committed on purpose** — the host has no Composer, so
  the plugin ships its dependencies. `bin/build.sh` restores that `--no-dev`
  tree; `bin/verify.sh` installs the dev packages into the same directory, so
  run `bin/build.sh` before committing. Migrations are numbered `.sql` files
  (`{prefix}`/`{charset}` substituted at run time) — see `docs/CLI.md`.
- The hosted Playwright account `sv-e2e` can lose its password when InstaWP
  restores a snapshot. `.env` keeps one password per environment
  (`SV_E2E_PASSWORD`, `SV_E2E_PASSWORD_HOSTED`); re-run
  `php bin/sv-e2e-user.php sv-e2e` to reset either one. Passwords are
  alphanumeric so `.env` stays safe to `source`.

## Decisions

Choices made with the user that the code doesn't explain, e.g. "Releases sort by version, not date".

- **Deploys split by owner.** The plugin ships with `bin/deploy-plugin.sh`
  (build → SSH to the InstaWP site → install zip → migrate); the theme ships
  through Shader's own push/download, because Shader owns the theme and knows
  nothing about `sv-core`. Do not build plugin-deploy machinery for the theme.
- **Hosted target is `https://line-age.instawp.co`** (SSH target in `.env` as
  `SV_DEPLOY_SSH` — never committed). It reports `environment_type=staging` — set
  deliberately, since InstaWP defaults every site to `production` and the
  deploy guard refuses production without `--i-know-what-im-doing`.
- **The theme was seeded onto the host once by hand** (a tar/scp copy
  replicating `shader.theme.json`'s `package.include`). That was a one-off to
  unblock the hosted test run, not a repeatable path — use Shader's push from
  now on so local and hosted don't drift.
- **Published to `github.com/markbcodes/line.age`, `main` only.** Code only: no
  WordPress core, no `wp-config.php`, no database. The old workspace history
  (local branch `workspace`) contains `wp-config.php` and an application
  password — it must never be pushed.

- **The VX model lives only in `vx-config/`.** The 7 post types and 7 fieldsets
  are authored as code there, never in wp-admin; `wp vx-framework config
  export --write --user=1` keeps the files canonical after a config change, and
  `config list` must show all 14 objects `synced`. A *database* copy wins over
  its JSON file, so an admin-UI edit silently shadows the model until
  `config reset <group> <key>`.

- **`tools/verify-vx-model.php` is the model's gate, not `config status`.** A
  deleted `vx-config/` file silently removes that object and
  `config status --strict` still exits 0 (13 objects, no drift, no invalid
  files), so `--strict` proves nothing about a missing object. The model's
  safety net is `tools/check.php` (files VX skipped, keys templates read) plus
  `tools/verify-vx-model.php` (schema, the two-write trap, the gate and its
  cache, the export/import round trip). `bin/verify.sh` runs both.

- **Importers must write through `tools/vx-write-lib.php`.** Creating a
  VX-backed post is two writes — core inserts the row, then VX's
  `register_rest_field` update callback writes `vx` — so a refused `vx` payload
  leaves a post with none of its values. `vx_create_with_vx()` deletes that
  orphan on failure; `vx_delete_orphan()` is the reusable cleanup.

- **VX does not enforce the model's constraints, so the app must.** Confirmed
  against the running site (`spike/`): `required: true` is compiled out of
  validation (`REQUIRED_FIELDS_ENABLED = false`), so the model can never rely
  on VX to reject an empty required field; a relation target that does not
  exist, or is the wrong post type, is dropped *silently* (write 200, no
  warning, no row, reads back `[]`); and the only input that blocks a write at
  all is an unknown `vx` key (`422 unknown_fields`, strict and lenient alike).
  `vx_strict` has nothing to escalate on this model. Importers and templates
  own those checks.

- **`vxframework/rest/can_read` withholds values, never the status.** Hooking
  the filter to deny an entity empties that viewer's `vx` payload; core still
  answers 200, because `show_in_rest` and a published post are core decisions
  VX does not override. With the hook registered, `authenticated`-tier payloads
  are computed live instead of cached, so one viewer's response is never served
  to another — asserted in both directions by `spike/v2-can-read-gate.php` and
  the verifier.

- **Open: the model has no non-editor reader surface.** Every fieldset exports
  `rest: { expose: private }` (VX's secure default, and right for a writer's
  private bible), so a logged-in non-editor always receives `"vx": {}` and the
  `can_read` gate cannot be exercised by one. The V2 proofs raise `sv_scene` to
  `authenticated` at runtime through `vxframework/fieldsets/resolved_fields`
  rather than change the committed model. If the app is meant to serve book
  data over REST to logged-in users, that exposure is a decision to take — and
  it changes what readers can see.
