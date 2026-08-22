import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { login, credentials } from './helpers/auth';

const errorsDir = path.resolve('resources/views/errors');

function errorTemplate(code: number): string {
  return fs.readFileSync(path.join(errorsDir, `${code}.blade.php`), 'utf8');
}

test.describe('Friendly error pages — UAT', () => {
  test('ERR-001: 403 page shows Access Denied with KMSAR navy header', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/dean/dashboard');
    expect(response?.status()).toBe(403);
    await expect(page.getByRole('heading', { name: 'Access Denied' })).toBeVisible();
    await expect(page.locator('.top-brand')).toHaveText('KMSAR');
    await expect(page.locator('.top')).toHaveCSS('background-color', 'rgb(30, 58, 138)');
  });

  test('ERR-002: 404 page shows Page Not Found with KMSAR branding', async ({ page }) => {
    const response = await page.goto(`/missing-page-${Date.now()}`);
    expect(response?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Page Not Found' })).toBeVisible();
    await expect(page.locator('.top-brand')).toHaveText('KMSAR');
  });

  test('ERR-003: 419 page contract shows Session Expired and Login Again', async () => {
    const source = errorTemplate(419);
    expect(source).toContain('<h1>Session Expired</h1>');
    expect(source).toContain('Login Again');
    expect(source).toContain('KMSAR');
  });

  test('ERR-004: 500 page contract shows Something Went Wrong with KMSAR branding', async () => {
    const source = errorTemplate(500);
    expect(source).toContain('<h1>Something Went Wrong</h1>');
    expect(source).toContain('top-brand">KMSAR');
  });

  test('ERR-005: 503 page contract shows System Unavailable with KMSAR branding', async () => {
    const source = errorTemplate(503);
    expect(source).toContain('<h1>System Unavailable</h1>');
    expect(source).toContain('top-brand">KMSAR');
  });

  test('ERR-006: 403 page has role-aware dashboard link', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/dean/dashboard');
    await expect(page.getByRole('link', { name: 'Go to Dashboard' })).toHaveAttribute('href', /\/research$/);
  });

  test('ERR-007: 404 page contract includes Go Back action for authenticated users', async () => {
    const source = errorTemplate(404);
    expect(source).toContain('&larr; Go Back');
    expect(source).toContain('@if ($isLoggedIn)');
  });

  test('ERR-008: 419 Login Again button links to login', async () => {
    const source = errorTemplate(419);
    expect(source).toContain('href="{{ $loginUrl }}"');
    expect(source).toContain('Login Again');
  });

  test('ERR-009: Unauthenticated protected route redirects to login', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/research');
    await expect(page).toHaveURL(/\/login/);
    await context.close();
  });

  test('ERR-010: Faculty accessing dean route gets friendly 403', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/dean/dashboard');
    expect(response?.status()).toBe(403);
    await expect(page.getByText('403', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Access Denied' })).toBeVisible();
  });
});
