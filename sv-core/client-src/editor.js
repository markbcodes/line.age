/**
 * Editor bundle entry. esbuild bundles this to assets/js/editor.bundle.js.
 * Keep it dependency-free: it loads on every app view, including on the
 * SQLite local where nothing else is installed.
 */

export function boot() {
  const view = document.querySelector('.sv-app-view');
  if (!view) {
    return null;
  }
  view.dataset.svBooted = '1';
  return view.dataset.view ?? null;
}

if (typeof document !== 'undefined' && document.readyState !== 'loading') {
  boot();
} else if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', boot);
}
