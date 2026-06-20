import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login } from './helpers/auth';

test.describe('Research reports', () => {
  test('M-05: dean reports page has SDG, classification, academic year filters', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/reports`);

    await expect(page.locator('h1', { hasText: 'Reports & Analytics' })).toBeVisible();
    await expect(page.locator('select[name="sdg"]')).toBeVisible();
    await expect(page.locator('select[name="research_classification"]')).toBeVisible();
    await expect(page.locator('select[name="academic_year"]')).toBeVisible();
  });

  test('preview shows more than 10 records with pagination', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/reports?per_page=10`);

    const summary = page.getByText(/Showing \d+–\d+ of \d+ records/i);
    await expect(summary).toBeVisible();
    const summaryText = await summary.innerText();
    const totalMatch = summaryText.match(/of ([\d,]+) records/i);
    const total = totalMatch ? parseInt(totalMatch[1].replace(/,/g, ''), 10) : 0;

    if (total > 10) {
      await expect(page.locator('table tbody tr')).toHaveCount(10);
      await expect(page.getByRole('link', { name: /Load more|Next/i })).toBeVisible();
    } else {
      test.skip(true, 'Fewer than 11 seeded records for pagination test');
    }
  });

  test('rejected records excluded by default', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/reports`);

    const includeRejected = page.getByRole('checkbox', {
      name: /Include rejected records/i,
    });
    await expect(includeRejected).not.toBeChecked();

    const rejectedBadges = page.locator('table .kmsar-badge--rejected');
    await expect(rejectedBadges).toHaveCount(0);
  });

  test('PDF export downloads', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/reports`);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/reports/export') && resp.status() === 200,
    );
    await page.getByRole('button', { name: 'PDF', exact: true }).click();
    const response = await responsePromise;
    expect(response.headers()['content-type']).toMatch(/pdf/i);
  });

  test('Excel export downloads', async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/reports`);

    const responsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/reports/export') && resp.status() === 200,
    );
    await page.getByRole('button', { name: 'Excel', exact: true }).click();
    const response = await responsePromise;
    expect(response.headers()['content-type']).toMatch(/spreadsheet|excel|officedocument/i);
  });

  test('OVPRI reports same filters available', async ({ page }) => {
    await login(page, CREDENTIALS.ovpri.email);
    await page.goto(`${BASE_URL}/reports`);

    await expect(page.locator('h1', { hasText: 'Reports & Analytics' })).toBeVisible();
    await expect(page.locator('select[name="sdg"]')).toBeVisible();
    await expect(page.locator('select[name="research_classification"]')).toBeVisible();
    await expect(page.locator('select[name="academic_year"]')).toBeVisible();
    await expect(page.locator('select[name="college_id"]')).toBeVisible();
  });
});
