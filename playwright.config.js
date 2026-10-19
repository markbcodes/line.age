import { defineConfig, devices } from '@playwright/test';

// WP_BASE_URL selects the environment: the Shader local by default, the
// InstaWP site when WP_BASE_URL=https://line-age.instawp.co. See docs/ENVIRONMENTS.md.
const baseURL = process.env.WP_BASE_URL || 'http://line-age.localhost';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'e2e/.auth/state.json',
      },
      dependencies: ['setup'],
      testIgnore: /auth\.setup\.js/,
    },
  ],
});
