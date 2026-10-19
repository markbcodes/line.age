# CLI: every `wp sv ...` command exists twice — on purpose

Two hosts, two entry points, **one implementation**:

| Environment | Entry point | Why |
|---|---|---|
| Hosted (InstaWP) | `wp sv <command>` — `sv-core/src/WpCli.php` | wp-cli is installed (`/usr/bin/wp`) |
| Local (Shader) | `php bin/sv-cli.php <command>` | no `wp` binary on PATH (F1 in [ENVIRONMENTS.md](ENVIRONMENTS.md)) |
| Neither / browser | `SV ▸ Maintenance` admin screen — `sv-core/src/Maintenance.php` | nonce-protected POSTs, output printed on the page |

Both CLI entry points are thin wrappers: they parse/echo and delegate to the
same `SV\App\Cli` handlers (`sv-core/src/Cli.php`). The admin screen calls the
same handlers. Logic lives in `Cli` only.

## The rule

**Every future `wp sv ...` command must exist in BOTH forms — add the WP-CLI
method, the `bin/sv-cli.php` dispatch case, and (if it produces output worth
seeing) the Maintenance button, in the same commit.** A command that exists in
only one form is a bug.

## Current commands

    migrate     Run pending wp_sv_* migrations (records in the sv_migrations option)
    jobs        List the wp_sv_jobs table (empty state: "Job list is empty.")
    as-status   Report Action Scheduler state (absent here: "not installed")

Examples:

    wp sv migrate                # hosted
    php bin/sv-cli.php migrate   # local (from the repository root)

## Hosted quirks (learned 19 Oct 2026)

- WP-CLI registers class methods under their **literal** name: the method
  `as_status` appears as `wp sv as_status`, not `as-status`. App::register()
  therefore also registers an `sv as-status` alias so the dashed spelling in
  this document works on both entry points. `bin/sv-cli.php` accepts both too.
- The hosted table prefix is **`iwp3f42_`**, not `wp_` — never hard-code
  `wp_sv_*`; use `$wpdb->prefix` (all handlers already do). A
  `SHOW TABLES LIKE 'wp_sv%'` sanity check on the host will find nothing.
