# VX config exports — committed, import only

This directory holds VX Framework config exports for the **plugin's** content
(screens, fields, dictionaries that sv-core owns). Config is committed here
and imported into a site; nothing here is edited by hand on a live site.

The **theme** has its own VX config at
`wp-content/themes/line-age/vx-config/` — that is Shader's, managed by the
theme agent. Do not move it, and do not merge the two: theme config is
presentation/records, this directory is app config.

Import (either environment):

    # local (no wp-cli: use the CLI fallback)
    php bin/sv-cli.php <command>

    # hosted
    wp <vx-import-command>

_No exports yet — the plugin is a skeleton. Drop exported JSON here as the
app's content model takes shape._
