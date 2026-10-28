import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

async function login(page: import('@playwright/test').Page, email: string, password: string) {
  await page.goto('/');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', password);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/home$/);
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('PasswordPolicy screenshots', () => {
  test.use({ viewport: { width: 1280, height: 1000 } });

  test('password-expired-flash — expired user gets the expiry notice', async ({ page }) => {
    await login(page, 'expired@example.com', 'expired123');
    await expect(page.locator('.alert, .flash, [class*="alert"]').filter({ hasText: /expired|caducad/i }).first()).toBeVisible();
    await page.screenshot({ path: resolve(outDir, 'password-expired-flash.png'), fullPage: true });
  });

  test('demo-users-management — users with password status', async ({ page }) => {
    await login(page, 'admin@example.com', 'admin123');
    await page.goto('/user/');
    await expect(page.locator('table').getByText(/Expiring Soon/i).first()).toBeVisible();
    await page.screenshot({ path: resolve(outDir, 'demo-users-management.png'), fullPage: true });
  });
});
