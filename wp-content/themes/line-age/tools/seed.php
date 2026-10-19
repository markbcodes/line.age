<?php
/**
 * Development-only sample content seeder: this site's recipe.
 *
 * In Shader, Claude runs it with Shader's seed tool, which shows the person
 * what it will add before it runs. From this theme directory:
 *
 *   php tools/seed.php                 add what's missing, fill what's empty
 *   php tools/seed.php --dry-run       say what a run would do; write nothing
 *   php tools/seed.php --no-fill       only add new records; fill nothing that
 *                                      exists
 *   php tools/seed.php --reset={keys}  overwrite these post types, taxonomies
 *                                      or option pages with the recipe
 *   php tools/seed.php --reset         overwrite everything, menus and site
 *                                      settings included
 *
 * Run it with Shader's PHP, the one this site runs on: .shader/bin/php in place
 * of php (.shader\bin\php.cmd on Windows). Use php only when that file does
 * not exist.
 *
 * The seed adds sample content and never changes a value that is set: a record
 * that already exists only gets its empty fields filled, so edits made on the
 * site survive a re-run. Only --reset overwrites.
 *
 * tools/seed-lib.php is Shader's library: it boots WordPress and provides the
 * helpers (seed_ability(), seed_upsert_post(), seed_upsert_term(),
 * seed_option_values(), seed_media(), seed_site_identity(),
 * seed_set_front_page(), seed_nav_menu()). Don't edit it. Write this site's
 * records below the require, through those helpers, and end with seed_done().
 * Run `php tools/check.php` first so a skipped vx-config/ file cannot pass for
 * a seeding failure.
 */

declare(strict_types=1);

require __DIR__ . '/seed-lib.php';

// This site's records go here, replacing the stub below.

fwrite(STDERR, "Seed stub: replace this file's final section with VX ability writes for this site's model, then call seed_done().\n");
fwrite(STDERR, "Use seed_ability(), seed_upsert_post(), seed_upsert_term(), seed_option_values(), seed_media(), seed_site_identity(), seed_set_front_page(), and seed_nav_menu(). This untouched stub is not a successful seed.\n");
exit(1);
