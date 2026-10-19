import { describe, it, expect } from 'vitest';
import { boot } from '../../client-src/editor.js';

describe('boot', () => {
  it('returns null when no app view exists', () => {
    document.body.innerHTML = '<div class="entry-content">theme page</div>';
    expect(boot()).toBe(null);
  });

  it('marks the app view as booted and returns its name', () => {
    document.body.innerHTML = '<main class="sv-app-view" data-view="scene"></main>';
    expect(boot()).toBe('scene');
    expect(document.querySelector('.sv-app-view').dataset.svBooted).toBe('1');
  });
});
