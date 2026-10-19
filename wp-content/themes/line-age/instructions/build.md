# Build

## What Shader relies on

Shader's build, packaging, export, and deploy work only while these stay in place. Change anything else freely.

- `vite.config.js` at the theme root. Without it Shader does not build the theme, and project export and import refuse it. Shader builds through Vite's API with this config, not through npm scripts, so `vite` must resolve from the theme's own `node_modules`.
- Vite `outDir` is `assets/build`, producing `app.css` and `app.js`. `functions.php` enqueues those two files.
- `shader.theme.json` with a non-empty `package.include` covering `**/*.php`, `style.css`, `vx-config/**`, and `assets/build/**`. Without it the theme cannot be packaged or deployed.
- `style.css` with a `Theme Name` header. WordPress and Shader only recognize the theme through it.
- A `vx-config/` folder. Project export and import require it.
- `tools/check.php`, `tools/seed.php`, and `tools/seed-lib.php`, the seeding library `tools/seed.php` requires. The chat runs `php tools/check.php` without asking. It seeds through Shader's `seed` tool, which runs `tools/seed.php`.
- `.shader-theme.json` is Shader's marker for the theme. Leave it as it is.

## Working with the build

Shader builds this theme automatically. After CSS or JS changes, verify `assets/build/app.css` rather than running npm. The compiled file must contain the utilities the templates use.

The theme is still a normal npm project. With system Node 20+ installed, `npm install` and `npm run build` work exactly as before, in Cursor or any terminal. Shader ships the Vite + Tailwind set it staged; adding a new package still needs system Node and npm. Each theme owns its own ~34MB `node_modules`.

```bash
npm install
npm run dev
npm run build
```

`shader.theme.json` is the external deployment and package contract. Local Shader theme copies intentionally retain development files (`instructions/`, `tools/`, `resources/`, `DESIGN.md`, and Node sources) so agents can keep working. Those files are excluded from a production package.

The existing `"**/*.php"` include already covers PHP added under normal directories such as `inc/` and `template-parts/`. Review the include/exclude rules only when you add a new runtime asset type or a location the current globs do not cover. Keep `style.css`, `languages/`, `licenses/`, `vx-config/`, `assets/build/`, and `assets/editor.css` in the production package.

The front end is one scanned `assets/build/app.css` and one footer `app.js`. Do not add per-template Vite entries or extra enqueues unless a page needs a real isolated library.

System npm may not exist, so do not depend on new packages. Write interactions as small vanilla modules in `resources/js/` and import them from `app.js`. If a library is essential, vendor its ES module file into `resources/js/vendor/` with its license in `licenses/`, and import it.

Keep `vite.config.js`, `package.json`, and Vite `outDir` as `assets/build` (`app.css` / `app.js`). Shader rebuilds from those. Keep `shader.theme.json`. Update include/exclude when adding a new runtime folder; do not invent a second package story. Do not delete `node_modules` to clean up. Extra packages need system npm; Shader will only recopy its staged tree.

If the preview is unstyled or stale after a save, read `.shader/build.log` in this theme: Shader writes the result of every build there, with the error when one failed. Fix that error. If the build succeeded, `assets/build/app.css` should contain the new utilities; if it does not, restore the Vite contract — do not switch bundlers.
