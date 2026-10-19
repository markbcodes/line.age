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
