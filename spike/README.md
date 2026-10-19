# spike — VX behaviour probes

Throwaway probes that answered three questions about VX before the app was
built on it. They run against this site's real WordPress and its real database,
create their own fixtures, and remove everything they created.

Run them from the repo root with Shader's PHP
(`.shader\bin\php.cmd spike\<file>.php` on Windows, `.shader/bin/php` elsewhere)
or as bash (`bash spike/v3-config-roundtrip.sh`).

| File | What it proves |
| --- | --- |
| `v1-two-write-trap.php` | Creating a VX-backed post is two writes. When the second (`vx`) write is refused, the post from the first survives with none of its values — the orphan. Then `tools/vx-write-lib.php` removes it, automatically and by hand. |
| `v1-injector-census.php` | Which inputs actually fail a `vx` write, strict and lenient. Exactly one does: an unknown `vx` key. |
| `v1-relation-targets.php` | A relation target that does not exist, or is the wrong post type, is dropped silently: 200, no warning, no row, reads back `[]`. |
| `v2-can-read-gate.php` | `vxframework/rest/can_read` withholds a viewer's `vx` payload without changing the status, and the tier cache never serves one viewer's response to another. |
| `v3-config-roundtrip.sh` | The real WP-CLI round trip: `export --all` → delete `fieldsets/sv_scene.json` → `import --plan` → `import` → `config status --strict` → `export --write` → `config reset`. Restores the file and the model state whatever happens. |
| `v3-schema-dump.php` | Prints the `sv_scene` entry of `GET vx-framework/v1/rest/schema`; used by the round-trip script and for the manual paste. |

`wp-content/themes/line-age/tools/verify-vx-model.php` is the same checks as an
automated pass/fail gate, wired into `bin/verify.sh`. The probes are for
reading the evidence; the verifier is for catching a regression.

## What the brief expected, and what VX actually does

Two of the three behaviours in the brief rest on assumptions the running plugin
does not hold. Both deviations are recorded in `MAP.md`; the probes print the
evidence.

- **V1's injector.** The brief names `vx_strict=1` plus a bad relation id. That
  combination answers **200** both ways and writes nothing — it is not a failure
  at all. The one blocking injector on this model is an unknown `vx` key, which
  answers `422 unknown_fields` in strict *and* lenient mode.
- **V2's status.** The brief expects a non-editor's `GET /wp/v2/sv_scene/{id}`
  to become non-200 under the hook. VX does not own the core route's permission
  check, so the status stays **200** with and without the hook; the gate empties
  the `vx` payload instead. Separately, every fieldset defaults to
  `rest.expose: private`, so a logged-in non-editor already receives `"vx": {}`
  — the probe raises `sv_scene` to `authenticated` at runtime, through
  `vxframework/fieldsets/resolved_fields`, purely so the gate has a payload to
  withhold.
- **V3 held.** The fieldset survives the round trip unchanged, and the committed
  file comes back byte-identical. One thing it surfaced: deleting a
  `vx-config/` file silently removes the object and `config status --strict`
  still exits 0.
