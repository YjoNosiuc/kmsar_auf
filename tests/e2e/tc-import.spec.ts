import { test, expect, Page, Browser } from '@playwright/test';
import * as path from 'path';
import { login, logout, credentials } from './helpers/auth';
import { resetDatabase, runArtisan, runTinker } from './helpers/db';

const FIXTURES = path.resolve('tests/e2e/fixtures');
const USER_VALID = path.join(FIXTURES, 'user_import_valid.xlsx');
const USER_DUP = path.join(FIXTURES, 'user_import_duplicate.xlsx');
const USER_INVALID_COLLEGE = path.join(FIXTURES, 'user_import_invalid_college.xlsx');
const RESEARCH_VALID = path.join(FIXTURES, 'research_import_valid.xlsx');
const RESEARCH_DUP = path.join(FIXTURES, 'research_import_duplicate.xlsx');
const RESEARCH_MISSING_USER = path.join(FIXTURES, 'research_import_missing_user.xlsx');
const RESEARCH_WITH_COAUTHORS = path.join(FIXTURES, 'research_import_with_coauthors.xlsx');
const RESEARCH_INVALID_COAUTHOR = path.join(FIXTURES, 'research_import_invalid_coauthor.xlsx');
const NON_XLSX = path.join(FIXTURES, 'sample.txt');

const TITLE_1 = 'TEST RESEARCH MACHINE LEARNING FOR CROP DISEASE DETECTION';
const TITLE_2 = 'TEST RESEARCH BLOCKCHAIN CREDENTIAL VERIFICATION SYSTEM';
const TITLE_TWO_CO = 'TEST RESEARCH WITH TWO COAUTHORS MACHINE LEARNING';
const TITLE_ONE_CO = 'TEST RESEARCH WITH ONE COAUTHOR VIEW ONLY';
const TITLE_NO_CO = 'TEST RESEARCH WITH NO COAUTHORS';
const TITLE_INVALID_CO = 'TEST RESEARCH INVALID COAUTHOR MACHINE LEARNING';

const importedFaculty = {
  one: { email: 'testfaculty1@auf.edu.ph', password: 'password', name: 'TEST FACULTY ONE' },
  two: { email: 'testfaculty2@auf.edu.ph', password: 'password', name: 'TEST FACULTY TWO' },
  three: { email: 'testfaculty3@auf.edu.ph', password: 'password', name: 'TEST FACULTY THREE' },
};

async function adminLogin(page: Page): Promise<void> {
  await login(page, credentials.admin.email, credentials.admin.password);
}

async function uploadImport(page: Page, filePath: string): Promise<void> {
  await page.setInputFiles('input[name="file"]', filePath);
  await page.locator('form').filter({ has: page.locator('input[name="file"]') }).locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

function countUsersByEmail(email: string): number {
  const out = runTinker(
    `echo \\App\\Models\\User::where('email','${email}')->count();`,
  );
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : -1;
}

function countResearchByTitle(title: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Research::whereRaw('LOWER(title) = ?', [strtolower('${title}')])->count();`,
  );
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : -1;
}

function userExistsViaArtisan(email: string): boolean {
  const out = runArtisan(
    `tinker --execute="echo \\App\\Models\\User::where('email','${email}')->exists() ? 'YES' : 'NO';"`,
  );
  return /YES/.test(out);
}

function countCoAuthorsForTitle(title: string): number {
  const out = runTinker(
    `echo \\App\\Models\\ResearchAuthor::where('is_primary', false)->whereIn('research_id', \\App\\Models\\Research::whereRaw('LOWER(title) = ?', [strtolower('${title}')])->pluck('id'))->count();`,
  );
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : -1;
}

function setResearchStageByTitle(title: string, stage: string): void {
  runArtisan(
    `tinker --execute="\\App\\Models\\Research::whereRaw('LOWER(title) = ?', [strtolower('${title}')])->update(['approval_stage' => '${stage}']);"`,
  );
}

function researchIdByTitle(title: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Research::whereRaw('LOWER(title) = ?', [strtolower('${title}')])->value('id') ?? 0;`,
  );
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : 0;
}

async function openResearchByTitle(page: Page, title: string): Promise<void> {
  const id = researchIdByTitle(title);
  expect(id).toBeGreaterThan(0);
  await page.goto(`/research/${id}`);
  await expect(page.getByRole('heading', { name: title, exact: false })).toBeVisible({ timeout: 15_000 });
}

test.describe.configure({ mode: 'serial' });

test.describe('Import Data — UAT Test Suite', () => {
  test.beforeAll(() => {
    resetDatabase();
  });

  test.beforeEach(async ({ page }) => {
    await adminLogin(page);
  });

  test('IMPORT-001: Import Data link visible in admin sidebar under Administration', async ({ page }) => {
    await page.goto('/admin/dashboard');
    const link = page.locator('a.kmsar-nav-item', { hasText: 'Import Data' });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', /\/admin\/import\/users/);
  });

  test('IMPORT-002: GET /admin/import/users loads correctly with upload form', async ({ page }) => {
    await page.goto('/admin/import/users');
    await expect(page.getByRole('heading', { name: /Import Faculty Users/i })).toBeVisible();
    await expect(page.locator('form[enctype="multipart/form-data"]')).toBeVisible();
    await expect(page.locator('input[name="file"][accept*=".xlsx"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /Import Users/i })).toBeVisible();
  });

  test('IMPORT-003: GET /admin/import/research loads correctly with upload form', async ({ page }) => {
    await page.goto('/admin/import/research');
    await expect(page.getByRole('heading', { name: /Import Research Records/i })).toBeVisible();
    await expect(page.locator('form[enctype="multipart/form-data"]')).toBeVisible();
    await expect(page.locator('input[name="file"][accept*=".xlsx"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /Import Research/i })).toBeVisible();
  });

  test('IMPORT-004: Upload non-xlsx file → validation error shown', async ({ page }) => {
    await page.goto('/admin/import/users');
    await uploadImport(page, NON_XLSX);
    await expect(page.locator('.kmsar-alert--danger')).toContainText(/xlsx|file|must|type|mimes|valid/i);
  });

  test('IMPORT-005: Upload valid user_import_valid.xlsx → 3 users imported successfully', async ({ page }) => {
    await page.goto('/admin/import/users');
    await uploadImport(page, USER_VALID);
    await expect(page.locator('.kmsar-alert--success')).toContainText(/3 users imported successfully/i);
  });

  test('IMPORT-006: Verify imported users exist in database (admin users list)', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page.getByText(importedFaculty.one.email)).toBeVisible();
    await expect(page.getByText(importedFaculty.two.email)).toBeVisible();
    await expect(page.getByText(importedFaculty.three.email)).toBeVisible();
    expect(userExistsViaArtisan(importedFaculty.one.email)).toBe(true);
    expect(countUsersByEmail(importedFaculty.one.email)).toBe(1);
    expect(countUsersByEmail(importedFaculty.two.email)).toBe(1);
    expect(countUsersByEmail(importedFaculty.three.email)).toBe(1);
  });

  test('IMPORT-007: Verify imported users can login with default password', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, importedFaculty.one.email, importedFaculty.one.password);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).toHaveURL(/\/research/);
    await context.close();
  });

  test('IMPORT-008: Upload user_import_duplicate.xlsx → duplicate row skipped', async ({ page }) => {
    await page.goto('/admin/import/users');
    await uploadImport(page, USER_DUP);
    await expect(page.locator('table.kmsar-table')).toBeVisible();
    await expect(page.locator('table.kmsar-table')).toContainText(/already exists|Email already exists/i);
    await expect(page.locator('table.kmsar-table')).toContainText(importedFaculty.one.email);
  });

  test('IMPORT-009: Upload user_import_invalid_college.xlsx → invalid college skipped', async ({ page }) => {
    await page.goto('/admin/import/users');
    await uploadImport(page, USER_INVALID_COLLEGE);
    await expect(page.locator('table.kmsar-table')).toContainText(/College code not found|active colleges/i);
    await expect(page.locator('table.kmsar-table')).toContainText(/INVALID/i);
  });

  test('IMPORT-010: Upload valid research_import_valid.xlsx → 2 research records imported', async ({ page }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_VALID);
    await expect(page.locator('.kmsar-alert--success')).toContainText(/2 research records imported successfully/i);
    expect(countResearchByTitle(TITLE_1)).toBe(1);
    expect(countResearchByTitle(TITLE_2)).toBe(1);
  });

  test('IMPORT-011: Verify imported research appears in faculty research list', async ({ page }) => {
    await logout(page);
    await login(page, importedFaculty.one.email, importedFaculty.one.password);
    await page.goto('/research');
    await expect(page.getByText(TITLE_1, { exact: false })).toBeVisible();
  });

  test('IMPORT-012: Verify imported research appears in dean dashboard for correct college', async ({ page }) => {
    runArtisan('cache:clear');
    await logout(page);
    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    await expect(page.getByRole('heading', { name: /College Dashboard/i })).toBeVisible();
    await expect(page.getByText(TITLE_1, { exact: false })).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText(/TEST FACULTY ONE/i).first()).toBeVisible();
  });

  test('IMPORT-013: Verify imported research appears in OVPRI dashboard / all research', async ({ page }) => {
    await logout(page);
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/research');
    // Filter by approved stage to surface imported rows reliably across pagination
    const stage = page.locator('select[name="stage"]');
    if (await stage.count()) {
      await stage.selectOption('approved');
      await page.waitForLoadState('networkidle');
    }
    await expect(page.getByText(TITLE_1, { exact: false })).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText(TITLE_2, { exact: false })).toBeVisible({ timeout: 15_000 });
  });

  test('IMPORT-014: Upload research_import_duplicate.xlsx → duplicate title skipped', async ({ page }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_DUP);
    await expect(page.locator('table.kmsar-table')).toContainText(/Title already exists/i);
    expect(countResearchByTitle(TITLE_1)).toBe(1);
  });

  test('IMPORT-015: Upload research_import_missing_user.xlsx → missing user skipped', async ({ page }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_MISSING_USER);
    await expect(page.locator('table.kmsar-table')).toContainText(/not found in users table|Primary author email/i);
    await expect(page.locator('table.kmsar-table')).toContainText('nonexistent@auf.edu.ph');
  });

  test('IMPORT-016: Run user import twice with same file → no duplicate users', async ({ page }) => {
    const before = countUsersByEmail(importedFaculty.one.email);
    await page.goto('/admin/import/users');
    await uploadImport(page, USER_VALID);
    await expect(page.locator('table.kmsar-table')).toContainText(/Email already exists/i);
    expect(countUsersByEmail(importedFaculty.one.email)).toBe(before);
    expect(countUsersByEmail(importedFaculty.two.email)).toBe(1);
    expect(countUsersByEmail(importedFaculty.three.email)).toBe(1);
  });

  test('IMPORT-017: Run research import twice with same file → no duplicate research', async ({ page }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_VALID);
    await expect(page.locator('table.kmsar-table')).toContainText(/Title already exists/i);
    expect(countResearchByTitle(TITLE_1)).toBe(1);
    expect(countResearchByTitle(TITLE_2)).toBe(1);
  });

  test('IMPORT-018: Faculty imported via import can login and register new research normally', async ({
    browser,
  }: {
    browser: Browser;
  }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, importedFaculty.one.email, importedFaculty.one.password);

    const title = `IMPORT-018 NEW RESEARCH ${Date.now()}`;
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
    await page.locator('#kmsar-document-file-input').setInputFiles(path.join(FIXTURES, 'sample.pdf'));
    await page.getByRole('button', { name: 'Save Document' }).click();

    const match = page.url().match(/\/research\/(\d+)\//);
    const researchId = match?.[1];
    expect(researchId).toBeTruthy();
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await page.waitForURL(/\/research\/\d+$/);

    await page.goto('/research');
    await expect(page.getByText(title, { exact: false })).toBeVisible();
    await context.close();
  });

  test('IMPORT-019: Upload research_import_with_coauthors.xlsx → 3 research records imported', async ({
    page,
  }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_WITH_COAUTHORS);
    await expect(page.locator('.kmsar-alert--success')).toContainText(
      /3 research records imported successfully/i,
    );
    expect(countResearchByTitle(TITLE_TWO_CO)).toBe(1);
    expect(countResearchByTitle(TITLE_ONE_CO)).toBe(1);
    expect(countResearchByTitle(TITLE_NO_CO)).toBe(1);
  });

  test('IMPORT-020: Research with 2 co-authors — both see research with Co-author badge', async ({
    page,
  }) => {
    await logout(page);
    await login(page, importedFaculty.two.email, importedFaculty.two.password);
    await page.goto('/research');
    const card = page.locator('div').filter({ hasText: TITLE_TWO_CO }).first();
    await expect(card.getByText(TITLE_TWO_CO, { exact: false })).toBeVisible();
    await expect(card.getByText('Co-author', { exact: true })).toBeVisible();

    await logout(page);
    await login(page, importedFaculty.three.email, importedFaculty.three.password);
    await page.goto('/research');
    const card3 = page.locator('div').filter({ hasText: TITLE_TWO_CO }).first();
    await expect(card3.getByText(TITLE_TWO_CO, { exact: false })).toBeVisible();
    await expect(card3.getByText('Co-author', { exact: true })).toBeVisible();
  });

  test('IMPORT-021: Co-author with can_edit=1 — Edit button visible', async ({ page }) => {
    // Edit is only rendered for draft stage
    setResearchStageByTitle(TITLE_TWO_CO, 'draft');

    await logout(page);
    await login(page, importedFaculty.two.email, importedFaculty.two.password);
    await openResearchByTitle(page, TITLE_TWO_CO);
    await expect(
      page.locator('.kmsar-page-header-actions').getByRole('link', { name: 'Edit', exact: true }),
    ).toBeVisible();
  });

  test('IMPORT-022: Co-author with can_edit=0 — Edit button NOT visible', async ({ page }) => {
    setResearchStageByTitle(TITLE_ONE_CO, 'draft');

    await logout(page);
    await login(page, importedFaculty.one.email, importedFaculty.one.password);
    await openResearchByTitle(page, TITLE_ONE_CO);
    await expect(page.getByText('Co-author', { exact: true }).first()).toBeVisible();
    await expect(
      page.locator('.kmsar-page-header-actions').getByRole('link', { name: 'Edit', exact: true }),
    ).toHaveCount(0);
  });

  test('IMPORT-023: Primary author sees research including ones with co-authors', async ({
    page,
  }) => {
    await logout(page);
    await login(page, importedFaculty.one.email, importedFaculty.one.password);
    await page.goto('/research');
    await expect(page.getByText(TITLE_TWO_CO, { exact: false })).toBeVisible();
    await expect(page.getByText(TITLE_1, { exact: false })).toBeVisible();
  });

  test('IMPORT-024: Invalid co-author email — research imported, co-author skipped', async ({
    page,
  }) => {
    await page.goto('/admin/import/research');
    await uploadImport(page, RESEARCH_INVALID_COAUTHOR);
    await expect(page.locator('.kmsar-alert--success')).toContainText(
      /1 research records? imported successfully/i,
    );
    await expect(page.locator('table.kmsar-table')).toContainText(/Co-author email not found/i);
    await expect(page.locator('table.kmsar-table')).toContainText('nonexistent@auf.edu.ph');
    expect(countResearchByTitle(TITLE_INVALID_CO)).toBe(1);
    expect(countCoAuthorsForTitle(TITLE_INVALID_CO)).toBe(0);
  });

  test('IMPORT-025: Research with no co-authors — no co-author records created', async ({
    page,
  }) => {
    expect(countResearchByTitle(TITLE_NO_CO)).toBe(1);
    expect(countCoAuthorsForTitle(TITLE_NO_CO)).toBe(0);

    await logout(page);
    await login(page, importedFaculty.three.email, importedFaculty.three.password);
    await page.goto('/research');
    await expect(page.getByText(TITLE_NO_CO, { exact: false })).toBeVisible();
  });
});
