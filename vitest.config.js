import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    include: ['sv-core/tests/js/**/*.test.js'],
    environment: 'jsdom',
  },
});
