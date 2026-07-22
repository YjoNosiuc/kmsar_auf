import { test, expect, Page } from '@playwright/test';
import * as path from 'path';
import { login, logout, credentials } from './helpers/auth';
import { resetDatabase, runArtisan } from './helpers/db';
import { createAndSubmitResearch, endorseResearch } from './helpers/research';

const FIXTURES = path.resolve('tests/e2e/fixtures');
const USER_VALID = path.join(FIXTURES, 'user_import_valid.xlsx');
const RESEARCH_VALID = path.join(FIXTURES, 'research_import_valid.xlsx');
const SAMPLE_PDF = path.join(FIXTURES, 'sample.pdf');

const TITLE_IMPORT_CCS = 'TEST RESEARCH MACHINE LEARNING FOR CROP DISEASE DETECTION';
const TITLE_IMPORT_CBA = 'TEST RESEARCH BLOCKCHAIN CREDENTIAL VERIFICATION SYSTEM';

/** Shared across serial CACHE-004 / CACHE-005 */
let ovpriTotalBeforeImport = 0;

async function getStatCardValue(page: Page, label: string | RegExp): Promise<number> {
  const card = page.locator('.kmsar-stat-card').filter({ hasText: label });
  const text = await card.locator('.kmsar-stat-card-value').first().innerText();
  return parseInt(text.replace(/,/g, ''), 10);
}

async function submitAsFaculty(
  page: Page,
  email: string,
  password: string,
  title: string,
): Promise<string> {
  await login(page, email, password);
  await page.goto('/research/create');
  await page.waitForURL(/\/research\/\d+\/details/);

  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
  await page.getByRole('button', { name: 'Continue to authors' }).click();
  await page.waitForURL(/\/authors/);
  await page.getByRole('button', { name: 'Continue to documents' }).click();
  await page.waitForURL(/\/documents/);
  await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_PDF);
  await page.getByRole('button', { name: 'Save Document' }).click();

  const match = page.url().match(/\/research\/(\d+)\//);
  const researchId = match?.[1];
  expect(researchId).toBeTruthy();

  await page.goto(`/research/${researchId}`);
  await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
  await page.waitForURL(/\/research\/\d+$/);

  return researchId!;
}

async function uploadImport(page: Page, route: string, filePath: string): Promise<void> {
  await login(page, credentials.admin.email, credentials.admin.password);
  await page.goto(route);
  await page.setInputFiles('input[name="file"]', filePath);
  await page
    .locator('form')
    .filter({ has: page.locator('input[name="file"]') })
    .locator('button[type="submit"]')
    .click();
  await page.waitForLoadState('networkidle');
}

test.describe.configure({ mode: 'serial' });

test.describe('Dashboard cache invalidation — UAT', () => {
  test.beforeAll(() => {
    resetDatabase();
  });

  test('CACHE-001: After faculty submits research, dean pending count updates immediately', async ({
    page,
  }) => {
    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const before = await getStatCardValue(page, /Pending Endorsement/i);

    const title = `CACHE-001 ${Date.now()}`;
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const after = await getStatCardValue(page, /Pending Endorsement/i);
    expect(after).toBe(before + 1);
  });

  test('CACHE-002: CBA submit does not increase CCS dean pending count', async ({ page }) => {
    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const before = await getStatCardValue(page, /Pending Endorsement/i);

    const title = `CACHE-002 CBA ${Date.now()}`;
    await submitAsFaculty(page, credentials.faculty_cba.email, credentials.faculty_cba.password, title);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const after = await getStatCardValue(page, /Pending Endorsement/i);
    expect(after).toBe(before);
  });

  test('CACHE-003: After dean endorses research, OVPRI pending count updates immediately', async ({
    page,
  }) => {
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/dashboard');
    const before = await getStatCardValue(page, /Pending approval/i);

    const title = `CACHE-003 ${Date.now()}`;
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    // Warm/submit path should bump OVPRI pending (dean_review counts as pending)
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/dashboard');
    const afterSubmit = await getStatCardValue(page, /Pending approval/i);
    expect(afterSubmit).toBe(before + 1);

    await endorseResearch(page, researchId!);

    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/dashboard');
    const afterEndorse = await getStatCardValue(page, /Pending approval/i);
    // Still pending (now ovpri_review) — must not drop back to stale pre-submit cache
    expect(afterEndorse).toBe(before + 1);

    await page.goto('/ovpri/queue');
    await expect(page.getByText(title, { exact: false })).toBeVisible({ timeout: 15_000 });
  });

  test('CACHE-004: After research import, dean dashboard shows updated count immediately', async ({
    page,
  }) => {
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/dashboard');
    ovpriTotalBeforeImport = await getStatCardValue(page, /Total research/i);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const beforeTotal = await getStatCardValue(page, /Total Research/i);
    const beforeScopus = await getStatCardValue(page, /Scopus Indexed/i);

    await uploadImport(page, '/admin/import/users', USER_VALID);
    await uploadImport(page, '/admin/import/research', RESEARCH_VALID);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const afterTotal = await getStatCardValue(page, /Total Research/i);
    const afterScopus = await getStatCardValue(page, /Scopus Indexed/i);

    expect(afterTotal).toBe(beforeTotal + 1);
    expect(afterScopus).toBe(beforeScopus + 1);
    await expect(page.getByText(TITLE_IMPORT_CCS, { exact: false })).toBeVisible();
  });

  test('CACHE-005: After research import, OVPRI dashboard shows updated stats immediately', async ({
    page,
  }) => {
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/dashboard');
    const afterTotal = await getStatCardValue(page, /Total research/i);
    expect(afterTotal).toBe(ovpriTotalBeforeImport + 2);

    await page.goto('/ovpri/research');
    const stage = page.locator('select[name="stage"]');
    if (await stage.count()) {
      await stage.selectOption('approved');
      await page.waitForLoadState('networkidle');
    }
    await expect(page.getByText(TITLE_IMPORT_CCS, { exact: false })).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText(TITLE_IMPORT_CBA, { exact: false })).toBeVisible({ timeout: 15_000 });
  });

  test('CACHE-006: Dean academic year filter updates immediately after faculty submits', async ({
    page,
  }) => {
    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard?academic_year=2026');
    const before = await getStatCardValue(page, /Pending Endorsement/i);

    const title = `CACHE-006 ${Date.now()}`;
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard?academic_year=2026');
    const after = await getStatCardValue(page, /Pending Endorsement/i);
    expect(after).toBe(before + 1);
  });

  test('CACHE-007: After revise and resubmit of rejected research, dean pending increases', async ({
    page,
  }) => {
    test.setTimeout(120_000);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const before = await getStatCardValue(page, /Pending Endorsement/i);

    const title = `CACHE-007 ${Date.now()}`;
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const afterSubmit = await getStatCardValue(page, /Pending Endorsement/i);
    expect(afterSubmit).toBe(before + 1);

    runArtisan(
      `tinker --execute="\\App\\Models\\Research::find(${researchId})->update(['approval_stage' => 'rejected']);"`,
    );
    runArtisan('cache:clear');

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const afterReject = await getStatCardValue(page, /Pending Endorsement/i);
    expect(afterReject).toBe(before);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await page.getByRole('button', { name: 'Revise', exact: true }).click();
    await expect(page).toHaveURL(/\/research\/\d+\/edit/, { timeout: 20_000 });

    await page.goto(`/research/${researchId}`);
    const resubmit = page
      .locator('.kmsar-page-header-actions')
      .getByRole('button', { name: 'Submit for Review', exact: true });
    await expect(resubmit).toBeVisible({ timeout: 15_000 });
    await resubmit.click();
    await expect(page.getByRole('alert').filter({ hasText: /submitted for dean review/i })).toBeVisible({
      timeout: 20_000,
    });

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const afterResubmit = await getStatCardValue(page, /Pending Endorsement/i);
    expect(afterResubmit).toBe(before + 1);
  });
});
