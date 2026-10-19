@AGENTS.md
@INDEX.md
@MAP.md

# PROJECT CONTEXT

An AI-augmented developmental editor for authors writing books and audio
dramas, built as a shared universe whose bible is a file maintained by two
stewards, with a world-event registry that allocates one event per book.
Passion project. Solo developer, WordPress background.

## STACK
- WordPress 6.x, PHP 8.2, MySQL (InnoDB).
- LOCAL: Shader (desktop app, beta, classic themes) — supplies the local WP
  site, built-in browser, and terminal. Code edited in VS Code with Claude Code.
- HOSTED STAGING: InstaWP (MySQL, Apache).
- There is NO Next.js, NO separate SPA, NO WordPress Studio. One WP install.
- VX Framework Pro: CPT + fieldset data modeling, REST `vx` object,
  config-as-code. VX version is PINNED — never suggest upgrades.
- Plugin `sv-core`: ALL logic (routes, migrations, custom tables, jobs,
  exports, continuity checks, AI) AND all app templates (/templates/app) AND
  built client assets (/assets/js/editor.bundle.js).
- Theme: chrome only (header, footer, styles, marketing, steward screens).
  Shader's agent may rewrite the theme freely.
- App pages are served by the PLUGIN via template_include
  (SV\App\Templates::maybe_override).
- **Never edit sv-core from Shader's agent. Never put app templates in the theme.**
- Editor: Tiptap/ProseMirror. TS source in sv-core/client-src, bundled with
  esbuild to sv-core/assets/js/editor.bundle.js, enqueued by the plugin on
  app pages only.
- Composer (approved deps only): phpoffice/phpword, smalot/pdfparser.
  vendor/ is committed — hosting has no Composer. PHP needs zip/dom/xml/mbstring.
- Tests: PHPUnit + WP test suite (PHP), Vitest (client logic), Playwright
  (E2E; baseURL from WP_BASE_URL; login via wp-login.php storageState).

## TOOLING & DEPLOYMENT
- Theme deploy: Shader "Push theme".
- Plugin deploy: bin/deploy-plugin.sh — build, zip INCLUDING vendor/ EXCLUDING
  client-src/node_modules/tests/.git, install on target, run migrations.
  Refuses against a site flagged production unless --i-know-what-im-doing.
- Shader "push content" / "pull content" are FORBIDDEN against any site holding
  real manuscripts. Seed content only on throwaway dev sites.
- CLI: every `wp sv ...` command must ALSO work as `php bin/sv-cli.php ...`
  (bootstraps wp-load.php). Never assume a `wp` binary exists in Shader's
  terminal. Admin fallback: SV ▸ Maintenance page (nonce-protected POSTs).
- VX fieldsets are NEVER edited by clicking in the admin. Edit /vx-config,
  run `wp vx-framework config import`, then `config status --strict` must pass.
  /vx-config is committed and is the source of truth.
- Local runtime facts F1–F5 are recorded in /docs/ENVIRONMENTS.md (wp binary?,
  PHP+extensions, DB engine, .htaccess applies?, static serving/threading).
  Tests tagged [hosted-only] or [hosted-re-run] respect those facts.

## AUTH MODEL (do not change)
- WordPress cookie session; REST via X-WP-Nonce localized to the page.
- No JWT. No tokens in JS-readable storage. No Application Passwords in
  browser code. No Basic auth in front-end code.
- Nonce expiry: on 403 rest_cookie_invalid_nonce, keep the editor buffer,
  emit session-expired, show "reload to continue — your text is safe".

## NON-NEGOTIABLE ARCHITECTURAL DECISIONS
1. Scene prose lives in wp_sv_doc_versions — append-only, never overwritten.
   Every row: content_hash (sha256 of canonicalised JSON) + schema_version.
2. Optimistic concurrency: client sends base_hash; mismatch -> 409 carrying
   the server's current_hash. No last-write-wins anywhere, ever.
3. Every request tenant-scoped by SV\Security::can_access() — first line of
   EVERY sv/v1 route callback — and hooked into vxframework/rest/can_read for
   sv_* post types as defense in depth.
4. Plot beats, ledger, scenes, documents are BOOK-scoped. Never cross books.
5. The uploaded universe bible FILE is the sole authoritative canon. The app
   stores NO structured canon facts (no canon entities, eras, world rules).
6. Bible rule: draft/in_review books follow the universe's LATEST bible
   version; published/retired books are PINNED at transition. A bible update
   NEVER modifies manuscript text.
7. No AI call runs inline in an HTTP request. Action Scheduler only.
8. VX REST creates happen in TWO STEPS (create post, then write vx values)
   with orphan cleanup on vx failure — WP creates the post before VX runs.
9. No conditional logic on any field written by the importer or AI (hidden
   fields are silently skipped on save).
10. in_world_start/end are floats, read with ->float(). NEVER ->int().
11. No new npm/composer dependency without asking first.
12. WP/VX errors are deliberately generic — SV\Support\Log ([SV] prefix,
    gated on SV_DEBUG) is the diagnostic surface. Log at start and end of
    every public function when SV_DEBUG is on.
13. Rich text fields are HTML; Code fields replace whole values; containers
    replace whole — scene prose must NEVER be stored in any VX field.
    wp_sv_doc_versions only.
14. Audit: VX's own writes log themselves; OUR custom writes do not. Every
    custom mutating route writes audit_log itself.

## WORLD EVENT REGISTRY (governs the shared universe)
15. wp_sv_universe_events holds a RESERVATION SLOT, not canon content.
    summary_short is VARCHAR(500), ONE line, validated with the message
    "One line. The detail lives in the bible file."
    bible_anchor points into the bible file (e.g. "§4.2 / p.38").
    If anyone starts writing a paragraph into summary_short, the registry is
    becoming a canon model — reject it.
16. Exclusivity is enforced by TWO unique keys, never by application checks:
      uq_event_active (event_id, active_flag)   -- one active claim per event
      uq_book_active  (book_id, active_flag)    -- one active claim per book
    active_flag = 1 while active, NULL once released/consumed.
    Claims are a SINGLE ATOMIC INSERT; on duplicate key match the INDEX NAME.
    Never SELECT-then-INSERT.
17. event_key is immutable after creation. Titles may be renamed (logged);
    keys never change.
18. Lifecycle: claim -> active; book published -> consumed (TERMINAL);
    release -> released, and the event becomes available again.
    RELEASE IS MANUAL ONLY. No timers, no auto-release. Stewards get a
    read-only stale-claim report instead.
19. A book may claim only while status IN ('draft','in_review').
    A consumed claim cannot be force-released, even if the book is retired.
20. Claim visibility: universes.claim_visibility ('book_title' default,
    'hidden'). Non-steward payloads must NOT contain the claiming author's
    name, workspace id, or email. Shape the response; never rely on hiding it
    in the UI.
21. A book's event is linked into the manuscript via scenes.event_claim_id.
    An unlinked claim is a warning (check 16), not a blocker.
22. VX does NOT validate relations, AND does not enforce required:true, AND
    number fields accept any value. Treat every VX field as untyped.
    - Required fields are enforced by OUR validation, never assumed.
    - Numeric fields are validated + cast by US before write; read with
      ->float()/->int() defensively.
23. VX RELATION WRITES ARE SILENTLY LOSSY. Verified contract (base-driver.php:111
    -> relation-table-strategy.php:290 -> relation-constraints.php:34 ->
    relations-table.php:163):
      - set_value(int $entity_id, array $field, $value): bool
      - Returns TRUE (no warning, no error, ok:true at Runtime_Service) when a
        target id is dropped for: nonexistent post, wrong post type, OR AN ID
        THE CURRENT USER CANNOT READ.
      - Returns true after dropping ALL targets — indistinguishable from a
        deliberate clear (empty list is a successful DELETE).
      - Returns false ONLY for infrastructure failures (bad relation_key, bad
        kind, entity id <= 0, DB error) — and even false is swallowed when
        storage_values_match() says the stored value already equals the request.
      - Nothing escapes as an exception; sync_* and the runtime catch Throwable.
    THEREFORE:
      a) NEVER branch on the return value. Read the field back and compare.
      b) ALL relation writes go through sv_write_relations() — pre-validate
         (exists, post type, same book, user_can('read'), cardinality, no self),
         write, read back, and return 422 relation_dropped on any diff.
      c) Every job that writes relations MUST carry initiated_by in its payload,
         and SV\Jobs\Runner MUST wp_set_current_user() before the handler.
         Without a user, private targets are unreadable and EVERY relation
         drops while reporting success. CLI equivalents require --user.
      d) sv_verify_relations() is the reusable comparator; `wp sv verify-relations`
         is the sweep. Report drift via Log + audit_log; never repair silently.
    Never write relations through vx REST, the REST `vx` object, or any
    ability — every path funnels through the same lossy driver.
    e) wp sv verify-relations is REPORT-ONLY when scheduled. --fix is MANUAL ONLY,
     enforced in code: requires an interactive TTY, refuses when SV_IN_JOB,
     requires --reason recorded to audit_log, and requires explicit confirmation.
     --fix repairs only fields whose targets still pass pre-validation; where a
     target no longer exists or belongs to another book it reports "manual" and
     SKIPS. It must never invent a replacement target, re-derive relations from
     prose, or resolve a dropped reference by choosing for the author.
     No scheduled job may call --fix. Drift is ESCALATED (logged, audited,
     stewards notified as one digest), never auto-repaired.
    This is mandatory in the metadata route, the importer, and any AI write.
24. vxframework/rest/can_read / can_write WITHHOLD PAYLOAD; they cannot deny
    the status code. They are defense-in-depth only. The real gate is
    SV\Security::require() on every /sv/v1/ route. Never rely on a hook for
    authorization.
25. VX REST `expose` stays `private` for every fieldset. The app never serves
    book data over VX REST to logged-in users. All app traffic goes through
    /sv/v1/  with cookie + X-WP-Nonce and relation writes go through sv_write_relations().
26. The VX schema gate is tools/verify-vx-model.php, NOT `config status
    --strict` (which passes with objects missing). verify-vx-model.php must:
    - assert the committed model manifest of expected objects (count + keys)
    - assert each object's schema is byte-identical to the committed export
    - run in bin/verify.sh
    After any approved model change: export, edit manifest, verify, commit.

## DATA MODEL (authoritative)
universes(id, name, current_bible_version_id, claim_visibility ENUM)
universe_stewards(universe_id, user_id)            -- exactly 2
bible_versions(id, universe_id, version_no, file_path, file_name, mime,
               file_bytes, changelog_note NOT NULL, extracted_text,
               extracted_word_count, token_estimate, uploaded_by, uploaded_at)
universe_events(id, universe_id, event_key, title, era_label, in_world_start,
               in_world_end, summary_short VARCHAR(500) NOT NULL,
               bible_anchor, status ENUM('available','retired'),
               created_by, created_at)
event_claims(id, event_id, book_id, workspace_id, claimed_by, claimed_at,
               status ENUM('active','released','consumed'), active_flag,
               released_at, released_by, release_reason, consumed_at)
workspaces(id, universe_id NULL, name, owner_id)
workspace_members(workspace_id, user_id, role)     -- Owner|Editor|Viewer
books(id, workspace_id, title, status, pinned_bible_version_id NULL, genre,
      pov, tense, target_words, era_label, banned_words_json,
      spelling_variant, serial_comma)
   status IN ('draft','in_review','published','retired')
acts(id, book_id, ordinal, title)
chapters(id, book_id, act_id, ordinal, title, status)
scenes(id, chapter_id, ordinal, title, pov_character_id, in_world_start,
       in_world_end, in_world_label, cast_json, event_claim_id NULL)
documents(id, scene_id, format, created_at)
doc_versions(id, document_id, version, content_json, content_hash,
             schema_version, word_count, created_by, created_at)
characters(id, book_id, name, aliases_json, bio, baseline_motivation, role)
locations(id, book_id, name, description, era_notes)
plot_beats(id, book_id, act_id, ordinal, title, description, status,
           payoff_scene_id)  -- planned|promised|paid_off|abandoned
character_ledger(id, book_id, character_id, scene_id, doc_version, action,
                 in_world_time, span_start, span_end, confidence, status,
                 locked, edited_by, edited_at, model, prompt_version, created_at)
glossary(id, book_id, term, pronunciation, definition)
known_names(id, book_id NULL, name, source)        -- manual|harvested|bible
finding_dismissals(id, book_id, fingerprint, user_id, created_at)
jobs(id, type, status, payload, result, attempts, max_attempts, error,
     cost_usd, created_at)
usage_events(id, workspace_id, kind, tokens_in, tokens_out, cost_usd, created_at)
audit_log(id, workspace_id, actor_id, action, entity, before, after, at)
fixture_manifest(id, scenario, defect_key, entity_type, entity_id)

## CONVENTIONS
- PHP: PSR-4, namespace SV\, prefix sv_. REST namespace sv/v1.
- Tables wp_sv_. Migrations numbered .sql, idempotent, via `wp sv migrate`.
- TS strict. Client source in sv-core/client-src.
- Errors: {code, message, data:{status}} with correct HTTP status.
- Debug: SV_DEBUG -> SV\Support\Log -> wp-content/debug.log.
  ?sv_debug=1 (admins only) appends _debug {ms, queries} to sv/v1 responses.

RULES
- Write the tests before or alongside the implementation. Never after.
- Run the tests yourself and paste the full output before declaring done.
- Do not add dependencies without asking first.
- Do not modify files outside the scope I named.
- Do not implement anything not in this prompt, even if it seems related.
- If a requirement is ambiguous, stop and ask one question.
- Every new REST route: permission_callback + a tenant-scope test.
- Every new table: idempotent, re-runnable migration, InnoDB.
- Every VX fieldset change: edit /vx-config, import, `config status --strict`.