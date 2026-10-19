import { test as setup, expect } from '@playwright/test';

/**
 * Logs in through wp-login.php once and reuses the session (storageState).
 * Credentials come from the environment; see .env.example (verify.sh loads
 * .env). The state file is gitignored — every dev machine logs in with
 * their own account.
 *
 * Paths are relative to the repo root (where `npx playwright test` runs).
 */

const authFile = 'e2e/.auth/state.json';

const user = process.env.SV_E2E_USER || 'sv-e2e';
const password = process.env.SV_E2E_PASSWORD;

setup('authenticate', async ({ page }) => {
  if (!password) {
    throw new Error('SV_E2E_PASSWORD is not set. Copy .env.example to .env and fill it in.');
  }

  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForURL((url) => !url.pathname.includes('wp-login.php'), { timeout: 15000 });

  // Logged-in pages carry the admin bar; wp-login.php errors do not.
  await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });

  await page.context().storageState({ path: authFile });
});
