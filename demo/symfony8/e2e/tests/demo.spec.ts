import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — PasswordPolicy demo happy path (login → expiry status → users). */
async function login(page: import('@playwright/test').Page, email: string, password: string) {
  await page.goto('/');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', password);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/home$/);
}

test.describe('PasswordPolicy demo', () => {
  test('login page lists demo credentials', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('input[name="_username"]')).toBeVisible();
    await expect(page.getByText('expired@example.com').first()).toBeVisible();
  });

  test('expired user sees the expiry flash on the home page', async ({ page }) => {
    await login(page, 'expired@example.com', 'expired123');
    await expect(page.locator('.alert, .flash, [class*="alert"]').filter({ hasText: /expired|caducad/i }).first()).toBeVisible();
  });

  test('users management shows password status badges', async ({ page }) => {
    await login(page, 'admin@example.com', 'admin123');
    await page.goto('/user/');
    const table = page.locator('table');
    await expect(table.getByText(/Expiring Soon/i).first()).toBeVisible();
    await expect(table.getByText(/Expired/).first()).toBeVisible();
    await expect(table.getByText('Active', { exact: true }).first()).toBeVisible();
  });
});
