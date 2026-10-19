# Environments

Two environments for one repo at the Shader project root
(`C:\Users\Flabo\Shader\line-age`).

**Theme path (do not move):** `wp-content/themes/line-age/` — installed by
Shader inside the WordPress tree. The repo-root plugin `/sv-core` is junctioned
into `wp-content/plugins/sv-core`.

**Plugin path:** repo root `/sv-core` → junction → `wp-content/plugins/sv-core`.

---

## 1. Local — Shader (Windows)

- URL `http://line-age.localhost`, WordPress **7.1.3**, theme `line-age` active.
- Server: **PHP built-in server**, not Apache — six worker processes on
  `127.0.0.1:24510-24515`, launched by Shader Desktop:
  `php.exe -c .shader\php.ini -S 127.0.0.1:2451X -t <root> .shader/router.php`
- PHP binary: `C:\Users\Flabo\AppData\Local\Programs\@shaderdesktop\resources\php\win32-x64\php.exe`,
  wrapped by `.shader\bin\php.cmd` (add to PATH manually; `php` is NOT on PATH).
- node `v22.13.0`, npm `10.9.2`. Git Bash at `C:\Program Files\Git\bin\bash.exe`
  (has `unzip`, `curl`; **no `zip`** — zipping goes through `bin/make-zip.php`).
- No `composer` on PATH — scripts fall back to `.cache/composer.phar`.

### Runtime discovery, verbatim (run in Shader's terminal)

**F1 — wp availability**
```
'wp' : The term 'wp' is not recognized as the name of a cmdlet, function, or script file.
```
→ **F1 = no wp-cli locally.** Every `wp sv ...` command needs the fallback
`php bin/sv-cli.php <command>` (same `SV\App\Cli` handlers).

**F2 — PHP version + extensions**
```
PHP 8.3.33 (cli) (built: Jul 28 2026 18:10:26) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.3.33, Copyright (c) Zend Technologies
    with Zend OPcache v8.3.33, Copyright (c), by Zend Technologies
MODULES: [PHP Modules] bcmath calendar Core ctype curl date dom fileinfo filter gd hash iconv intl json libxml mbstring mysqlnd openssl pcre PDO pdo_sqlite Phar random readline Reflection session SimpleXML sodium SPL sqlite3 standard tokenizer xml xmlreader xmlwriter Zend OPcache zip zlib  [Zend Modules] Zend OPcache
```
→ Local PHP **8.3.33**. Notably **no mysqli, no pdo_mysql** (SQLite-only), no
exif/imap/memcache/redis/pcntl/posix/soap/sockets.

**F3 — DB engine** (no `wp db query` — F1=no, so `grep DB_ wp-config.php`)
```
define( 'DB_NAME', 'local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_ENGINE', 'sqlite' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', '.ht.sqlite' );
```
plus drop-in `wp-content/db.php` (2156 bytes) → **SQLite** (sqlite-database-integration).

**F4 — .htaccess proof** (`wp-content/denytest/.htaccess` = `Require all denied`,
`secret.txt` inside, one curl)
```
local denytest status=200
```
→ **.htaccess is ignored locally** (PHP built-in server has no .htaccess
processing). A deny rule cannot be tested or relied on locally.

**F5 — single-threaded check** (two parallel curls at a 3s `sleep()` endpoint)
```
=== baseline: one request ===
single=3s
job1=200 job2=200 elapsed=3.3s
```
→ **Local is NOT single-threaded**: Shader runs six PHP workers
(ports 24510-24515), so two parallel 3s requests finish in 3.3s, not 6s.
Requests can overlap locally — do not assume serialized execution.

---

## 2. Hosted — InstaWP

- Site URL **https://line-age.instawp.co** (HTTPS 200), web root
  **`/web/line-age.instawp.co/public_html`**.
- SSH: `<ssh-user>@<host>` (InstaWP dashboard → SSH Access; the real value lives
  in `.env` as `SV_DEPLOY_SSH`, never in git), key `~/.ssh/instawp_rsa`
  (RSA-4096; the key toggle in InstaWP's SSH panel must be ON — enabling it was
  required before the key was accepted). Known-hosts goes to a temp file, never
  `~/.ssh/known_hosts`:
  `ssh -i ~/.ssh/instawp_rsa -o UserKnownHostsFile=<tmp> $SV_DEPLOY_SSH`
- Underlying hostname **contains "production"**; treat deploys to it
  accordingly (see guard below).
- WordPress **7.1.3**, active theme **line-age** (deployed from Shader; the
  theme always ships through Shader's Push theme, never from this repo).
- `wp_get_environment_type()` = **`staging`** — set on 19 Oct 2026 with
  `wp config set WP_ENVIRONMENT_TYPE staging`, because InstaWP defaults to
  `production` and sets nothing in wp-config. `WP_DEBUG=no`,
  `DISABLE_WP_CRON=no`. `wp-config.php` is writable.

### Runtime discovery, verbatim

**F1 — wp availability**
```
/usr/bin/wp
WP-CLI root dir: phar://wp-cli.phar/vendor/wp-cli/wp-cli
WP_CLI phar path: phar:///usr/bin/wp
```
→ **F1 = wp-cli present** on the hosted site.

**F2 — PHP version + extensions**
```
PHP 8.2.34 (cli) (built: Sep 24 2026 14:34:53) (NTS)
MODULES: [PHP Modules] apcu bcmath bz2 calendar Core ctype curl date dom exif FFI fileinfo filter ftp gd gettext hash iconv igbinary imagick imap intl json ldap libxml mbstring memcache mysqli mysqlnd openssl pcntl pcre PDO pdo_mysql pdo_sqlite Phar posix pspell random readline redis Reflection session shmop SimpleXML soap sockets sodium SPL sqlite3 standard sysvmsg sysvsem sysvshm tokenizer xml xmlreader xmlwriter xsl Zend OPcache zip zlib  [Zend Modules] Zend OPcache
```
→ Hosted PHP **8.2.34** — **0.1 major behind local 8.3.33** (parity item 1:
write for 8.2, test on 8.3). Has mysqli + pdo_mysql (local does not).

**F3 — DB engine**
```
define( 'DB_NAME', '<redacted — read it from wp-config.php on the host>' );
define( 'DB_USER', '<redacted — read it from wp-config.php on the host>' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
db_version=11.8.9   (via $wpdb->db_version(); "MariaDB from 11.8.9-MariaDB")
```
→ **MariaDB 11.8.9** (wp db query via socket errors out — a jailed-shell quirk;
use `wp eval` with `$wpdb` instead). `charset=utf8mb4` reported by $wpdb.

**F4 — .htaccess proof**
```
2.4-deny status=200      ('Require all denied')
2.2-deny status=200      ('deny from all')
bogus-directive status=200   (invalid directive, would 500 if parsed)
server: nginx
x-powered-by: InstaWP
web_php=8.2.34 sapi=fpm-fcgi server=Apache/2.4.58 (Ubuntu) mod_fcgid/2.3.9 OpenSSL/3.0.13
```
→ **.htaccess is completely inert on the hosted site** — even a syntactically
bogus directive returns 200, so no `.htaccess` in any directory is read
(nginx fronts the stack; `AllowOverride` never applies). **Consequence: the
"bible file deny rule" cannot be an .htaccess rule.** It must be enforced in
PHP (app-level allow check) or via an nginx rule in the InstaWP dashboard.
The WordPress rewrite block in `.htaccess` exists but rewrites are effectively
handled before it (pretty permalinks work through nginx).

**F5 — single-threaded check**
Hosted runs `nginx + php-fpm` (parallel by nature) and there is no
single-worker constraint; no test endpoint needed — nginx/fpm pools overlap
requests. System cron: **no `crontab` command, no `/etc/cron.d`, no
`/var/spool/cron`** in the jailed shell; `DISABLE_WP_CRON=no` → **WP-Cron
fires on page hits** (visitors spawn `wp-cron.php`). Recorded as the cron
decision: page-hit WP-Cron, no system cron available from this shell.

Other hosted facts: `uploads WRITABLE`; `.htaccess` exists (523 bytes,
WordPress block) but inert (F4); InstaWP must-use plugins present
(`0-iwp-cli-cache-invalidate`, `0-iwp-flyingpress-runner-lock`,
`0-iwp-upgrade-concurrency-guard`); Action Scheduler **absent**; **no sendmail**
(`/usr/sbin/sendmail: not found`) — `wp_mail` fails silently, so no
email-dependent flows (password resets to real inboxes, notifications) can be
tested here; table prefix is **`iwp3f42_`** (not `wp_`).

### Hosted test account (Playwright)

`sv-e2e` (administrator) is created by `bin/sv-e2e-user.php`, which is
idempotent — it resets the password on every run and prints `SV_E2E_PASSWORD=`
for `.env`. It must sit one level under the WP root (it does
`dirname(__DIR__) . '/wp-load.php'`), so on the host: copy it to
`<web root>/bin/`, run `php bin/sv-e2e-user.php sv-e2e`, then delete it and put
the printed line in `.env`.

**Learned 19 Oct 2026:** the hosted password had silently reverted and the
hosted Playwright run failed in `auth.setup.js`. The login page states the cause
outright — *"The password you entered for the username sv-e2e is incorrect."*
An InstaWP snapshot restore puts the older password back, so `.env` can drift;
re-run the command above.

---

## 3. Parity checklist — verified 19 Oct 2026 on the InstaWP site

| # | Item | Local | Hosted | Verdict |
|---|------|-------|--------|---------|
| 1 | PHP + extensions | 8.3.33, no mysqli | 8.2.34, full set | **Differs** — target PHP 8.2 syntax; local lacks mysqli so local code must not hard-require it |
| 2 | DB engine | SQLite (db.php drop-in) | MariaDB 11.8.9; `@@default_storage_engine` = **InnoDB** (`SHOW ENGINES`: support=`DEFAULT`), measured 19 Oct 2026 | **Differs** — migrations are portable `.sql` with `{prefix}`/`{charset}` placeholders and no engine-specific SQL (see [CLI.md](CLI.md)) |
| 3 | `wp_sv_*` migrations run at least once | ran locally (`0001_create_sv_jobs`, SQLite); tracked in `wp_sv_migrations` | **ran 19 Oct 2026** (`wp sv migrate`; `iwp3f42_sv_jobs` exists under the hosted prefix `iwp3f42_`, `ENGINE=InnoDB` confirmed by dropping it and re-running the `.sql` from scratch); deploy created `iwp3f42_sv_migrations` and backfilled `0001` from the old `sv_migrations` option | run `wp sv migrate` on host after every deploy |
| 4 | .htaccess overrides allowed | n/a (built-in server ignores) | **No — inert (bogus directive → 200)** | **FAIL** — deny rules must be app-level PHP, not .htaccess |
| 5 | HTTPS | http only (localhost) | https 200 | differs, expected |
| 6 | Cron | n/a | no system cron in shell; WP-Cron on page hits | decision recorded above |
| 7 | Uploads writable | yes | yes | match |
| 8 | Snapshot/backup schedule | n/a | **no automated backups listed** (Security → Backups, checked 19 Oct 2026); automated backups are plan-driven and stored off-host | **FAIL** — take an on-demand backup before risky changes (3 slots) or keep an independent copy; see [Backups](#backups-verified-19-oct-2026) |
| 9 | Deploy paths | theme = Shader Push theme; plugin = `bin/deploy-plugin.sh` | same | match |
| 10 | Shader push/pull content | dev sites **only** | dev sites **only** | rule, not an env property |

### Deploy guard

`bin/deploy-plugin.sh` queries the target's `wp_get_environment_type()` over
SSH **first** and calls `bin/deploy-guard.php`, which refuses `production`
(or an unreadable value) unless `--i-know-what-im-doing` is passed. The guard
exists to protect real production, so an unreadable value counts as production.

This site reported `production` out of the box (InstaWP sets no
`WP_ENVIRONMENT_TYPE`). It was switched once, on 19 Oct 2026:

    wp config set WP_ENVIRONMENT_TYPE staging      # on the host

so deploys here now pass the guard unmodified. If InstaWP re-provisions the
site, the value resets and deploys fail loudly until it is set again — intended,
not a bug.

### Backups (verified 19 Oct 2026)

InstaWP *snapshots* and hosted-site *backups* are different products.
Snapshots capture a site for relaunching or copying (paid, manual). Backups are
the restore points for a live site, and they are **not user-scheduled**: the
frequency follows the plan — Sandbox monthly, Starter weekly,
Plus/Pro/Turbo/Elite daily — and every paid plan adds 3 on-demand slots.
Backups are written to separate object storage, so the jailed shell cannot see
them. Checked and confirmed: no backup directory under `/web`, no
backup/plan options in the database, no plan metadata beyond
`/etc/instawp-php` (`INSTAWP_PHP_DEFAULT`).

**Result: the dashboard's Backups screen shows no automated backups for this
site (reported 19 Oct 2026).** Treat the site as unprotected:

- Before a risky change: site → **Security → Backups → Backup Now** (max 3
  on-demand backups; delete an older one to free a slot).
- The plugin code is in git and reproducible with `bin/build.sh`, but the
  **database is not** — manuscripts and content have no off-platform copy.
  Export it (`wp db export`) before destructive work, or snapshot the site.
- InstaWP's own guidance: keep at least one independent copy outside their
  infrastructure, because their backups sit in the same provider region.

### Tooling quirks

- Shell scripts are bash; on this Windows box run them as
  `& "C:\Program Files\Git\bin\bash.exe" bin/build.sh` (`bash` on PATH is a
  broken WSL shim). `bin/php-env.sh` resolves PHP (PATH → Shader's php.exe
  wrapper) and is sourced by all three scripts.
- `SV_PHP` env var overrides PHP resolution everywhere.
- PowerShell mangles `$?`/`$code` inside double-quoted bash `-c` strings —
  single-quote the whole argument.
