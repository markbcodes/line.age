import { test, expect } from '@playwright/test';

test('logged-in GET /app returns 200 and renders the plugin dashboard', async ({ page }) => {
  const response = await page.goto('/app');
  expect(response.status()).toBe(200);
  await expect(page.locator('.sv-app-view[data-view="dashboard"]')).toBeVisible();
});

test('logged-in SV Maintenance page renders', async ({ page }) => {
  const response = await page.goto('/wp-admin/admin.php?page=sv-maintenance');
  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'SV ▸ Maintenance' })).toBeVisible();
});

test('anonymous GET /app redirects to wp-login.php', async ({ browser }) => {
  // browser.newContext() inherits the project's storageState (verified: auth
  // cookies were present before any navigation) — pass an explicitly empty
  // one or this context is not anonymous at all.
  const anon = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const page = await anon.newPage();
  const response = await page.goto('/app', { waitUntil: 'domcontentloaded' });
  // auth_redirect() sends a 302 to wp-login.php; Playwright follows it.
  expect(page.url()).toContain('wp-login.php');
  expect([200, 302]).toContain(response.status());
  await anon.close();
});
