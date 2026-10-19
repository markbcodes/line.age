# line.age

The **line.age** site: a Shader-built WordPress classic theme plus the app
plugin and the dev/deploy tooling around it. One repository, laid out as the
site actually runs.

## Layout

| Path | What it is |
|---|---|
| `wp-content/themes/line-age/` | The theme — presentation. Shader owns and packages this. |
| `sv-core/` | The app plugin — the `/app` routes, migrations, CLI, REST, admin screen. |
| `vx-config/` | VX config exported for import (committed, import-only). |
| `bin/` | Build, deploy, verify, and CLI scripts. |
| `e2e/` | Playwright smoke tests. |
| `docs/` | Ownership rules, environment facts, CLI contract. |

The write-up for each is in `docs/`:

- [`docs/OWNERSHIP.md`](docs/OWNERSHIP.md) — the theme ↔ plugin boundary
- [`docs/ENVIRONMENTS.md`](docs/ENVIRONMENTS.md) — local (Shader) vs hosted (InstaWP), with the recorded runtime facts
- [`docs/CLI.md`](docs/CLI.md) — why every command exists in two forms

## Develop

```bash
npm install          # once
npm run build        # bundle the plugin's editor asset
bin/verify.sh        # phpunit + vitest + playwright, fail-fast
```

`bin/build.sh` produces the installable plugin zip at
`dist/sv-core-<version>.zip`.

`sv-core/vendor/` is **committed on purpose**: the hosting has no Composer, so
the deployed plugin carries its own dependencies. `bin/build.sh` runs
`composer install --no-dev` before zipping, which is also what restores the
committable tree — `bin/verify.sh` installs phpunit's dev packages into that
same directory. Run `bin/build.sh` before committing `sv-core/` and the
committed vendor always matches the artifact that ships.

## Deploy

```bash
bin/deploy-plugin.sh          # plugin -> hosted site over SSH
```

The theme ships separately, through Shader's own push — not from here.

## Requirements

PHP 8.2+ (the hosted site runs 8.2; local dev runs 8.3), Node 20+, and
Composer — optional, since the scripts fall back to a downloaded
`.cache/composer.phar`. On Windows, run the `bin/*.sh` scripts through Git
Bash.

## Local setup

Copy `.env.example` to `.env` and fill it in — it is gitignored and holds the
local site URL plus the Playwright account used by the smoke tests.
