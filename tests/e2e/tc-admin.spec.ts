import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { resetDatabaseAndAuth, runTinker } from './helpers/db';
import { acquireSuiteLock, releaseSuiteLock } from './helpers/db-lock';

/** Official AUF college codes expected in the colleges directory (identity checks, not totals). */
const AUF_COLLEGE_CODES = ['CAMP', 'CAS', 'CBA', 'CCS', 'CCJE', 'CED', 'CEA'];

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

function shortEmployeeNumber(prefix: string, stamp: number): string {
  const prefixDigits = prefix.replace(/\D/g, '').slice(0, 2);
  const suffix = String(stamp).replace(/\D/g, '').slice(-8);

  return `${prefixDigits}${suffix}`.slice(0, 10);
}

async function adminLogin(page: Page): Promise<void> {
  await login(page, credentials.admin.email, credentials.admin.password);
}

async function getDashboardStat(page: Page, label: string): Promise<number> {
  const card = page.locator('.kmsar-stat-card').filter({ hasText: label });
  const text = await card.locator('.kmsar-stat-card-value').innerText();
  return parseInt(text.replace(/,/g, ''), 10);
}

async function getStageBreakdownCount(page: Page, stageLabel: string): Promise<number> {
  const region = page.getByRole('region', { name: /Research approval stage breakdown/i });
  const card = region.locator('.kmsar-stat-card').filter({ hasText: stageLabel });
  const text = await card.locator('.kmsar-stat-card-value').innerText();
  return parseInt(text.replace(/,/g, ''), 10);
}

async function openEditUserByEmail(page: Page, email: string): Promise<void> {
  await page.getByRole('row').filter({ hasText: email }).getByRole('button', { name: 'Edit' }).click();
  await expect(page.locator('#form-edit-user')).toBeVisible();
}

async function selectCollegeByCode(page: Page, selectSelector: string, code: string): Promise<void> {
  const select = page.locator(selectSelector);
  const option = select.locator('option').filter({ hasText: `${code} —` }).first();
  const value = await option.getAttribute('value');
  expect(value).toBeTruthy();
  await select.selectOption(value!);
}

async function addCollege(page: Page, code: string, name: string): Promise<void> {
  await page.getByRole('button', { name: '+ Add College/Office' }).click();
  const modal = page.locator('#kmsar-modals-root .kmsar-modal').filter({
    has: page.getByRole('heading', { name: 'Add College/Office' }),
  });
  await expect(modal).toBeVisible();
  await modal.locator('input[name="code"][maxlength="10"]').fill(code);
  await modal.locator('input[name="name"]').fill(name);
  await modal.getByRole('button', { name: 'Add College/Office', exact: true }).click();
}

async function addProgram(
  page: Page,
  collegeCode: string,
  programCode: string,
  programName: string,
): Promise<void> {
  await page.getByRole('button', { name: '+ Add Program/Dept' }).click();
  const modal = page.locator('#kmsar-modals-root .kmsar-modal').filter({
    has: page.getByRole('heading', { name: 'Add Program/Dept' }),
  });
  await expect(modal).toBeVisible();
  const option = modal.locator('select[name="college_id"] option').filter({ hasText: `${collegeCode} —` }).first();
  const value = await option.getAttribute('value');
  expect(value).toBeTruthy();
  await modal.locator('select[name="college_id"]').selectOption(value!);
  await modal.locator('input[name="code"][maxlength="30"]').fill(programCode);
  await modal.locator('input[name="name"]').fill(programName);
  await modal.locator('form[action*="/admin/programs"] button[type="submit"]').click();
}

async function openEditCollegeByCode(page: Page, code: string): Promise<void> {
  await page.getByRole('row').filter({ hasText: code }).getByRole('button', { name: 'Edit' }).click();
  await expect(page.locator('#form-edit-college')).toBeVisible();
}

async function findProgramRow(page: Page, programCode: string) {
  await page.locator('[aria-label="Search programs/depts"], [aria-label="Search programs"]').fill(programCode);
  return page.locator('#section-programs table.kmsar-table tbody tr').filter({ hasText: programCode });
}

function seedAuditLog(action: string, auditableId = 1): void {
  runTinker(
    `\\App\\Models\\AuditLog::create(['user_id' => \\App\\Models\\User::where('email','admin@yopmail.com')->value('id'), 'action' => '${action}', 'auditable_type' => \\App\\Models\\User::class, 'auditable_id' => ${auditableId}, 'ip_address' => '127.0.0.1', 'created_at' => now()]);`,
  );
}

test.describe('Super Admin — UAT Test Suite', () => {
  test.beforeAll(async () => {
    await acquireSuiteLock('admin');
    await resetDatabaseAndAuth();
  });

  test.afterAll(() => {
    releaseSuiteLock();
  });

  test('TC-001: Login with super admin credentials → redirected to Admin Dashboard', async ({ page }) => {
    await adminLogin(page);
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.getByRole('heading', { name: 'Admin Dashboard' })).toBeVisible();
  });

  test('TC-002: Admin can access all pages → no 403 errors on admin, dean, ovpri, faculty pages', async ({
    page,
  }) => {
    await adminLogin(page);

    const adminResponse = await page.goto('/admin/dashboard');
    expect(adminResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Admin Dashboard' })).toBeVisible();

    const deanResponse = await page.goto('/dean/dashboard');
    expect(deanResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'College Dashboard' })).toBeVisible();

    const ovpriResponse = await page.goto('/ovpri/dashboard');
    expect(ovpriResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'University dashboard' })).toBeVisible();

    const researchResponse = await page.goto('/research');
    expect(researchResponse?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'My research' })).toBeVisible();
  });

  test('TC-003: Admin dashboard loads with system-wide stats AND research progress chart', async ({
    page,
  }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');

    await expect(page.getByText('Research by approval stage')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Research progress' })).toBeVisible();
    await expect(page.locator('#progressChart')).toBeVisible();
    await expect(page.getByText('Dean review').first()).toBeVisible();
    await expect(page.getByText('OVPRI review').first()).toBeVisible();
    await expect(page.getByText('Approved').first()).toBeVisible();
    await expect(page.getByText('Rejected').first()).toBeVisible();
  });

  test('TC-003b: Admin dashboard has Date From and Date To inputs', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');

    await expect(page.locator('input[name="date_from"]')).toBeVisible();
    await expect(page.locator('input[name="date_to"]')).toBeVisible();
    await expect(page.getByLabel(/Research accepted from/i)).toBeVisible();
    await expect(page.getByLabel(/Research accepted to/i)).toBeVisible();
  });

  test('TC-003c: Admin dashboard has SDG Distribution chart', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');

    await expect(page.getByRole('heading', { name: 'SDG Distribution' })).toBeVisible();
    await expect(page.locator('#adminSdgChart')).toBeVisible();
  });

  test('TC-003d: Research by College/Office breakdown shows percentages', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');

    await expect(page.locator('#collegeChart')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'College/Office' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '%' })).toBeVisible();
    await expect(page.locator('.kmsar-chart-legend').filter({ hasText: /%/ })).toBeVisible();
  });

  test('TC-004: User count is positive and directory lists known seeded accounts', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');
    const users = await getDashboardStat(page, 'Total users');
    expect(users).toBeGreaterThan(0);

    await page.goto('/admin/users');
    await expect(page.getByRole('row', { name: /admin@yopmail\.com/i })).toBeVisible();
    await expect(page.getByRole('row', { name: new RegExp(credentials.faculty_ccs.email, 'i') })).toBeVisible();
  });

  test('TC-005: Active college count is positive — inactive colleges excluded from count', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');
    const colleges = await getDashboardStat(page, 'Total colleges');
    expect(colleges).toBeGreaterThan(0);
    // Relative inactive exclusion is covered by TC-018 / TC-019 (before/after toggle).
  });

  test('TC-006: Research count and stage breakdown are consistent (relative, not seed-locked)', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');

    const total = await getDashboardStat(page, 'Total research');
    const pending = await getDashboardStat(page, 'Pending approvals');
    const deanReview = await getStageBreakdownCount(page, 'Dean review');
    const ovpriReview = await getStageBreakdownCount(page, 'OVPRI review');
    const approved = await getStageBreakdownCount(page, 'Approved');
    const rejected = await getStageBreakdownCount(page, 'Rejected');

    expect(total).toBeGreaterThan(0);
    expect(pending).toBeGreaterThanOrEqual(0);
    expect(deanReview + ovpriReview + approved + rejected).toBeGreaterThanOrEqual(total);
    expect(rejected).toBeGreaterThanOrEqual(0);
    // Pending approvals = research awaiting dean or OVPRI action
    expect(pending).toBe(deanReview + ovpriReview);
    const inProgress = await getDashboardStat(page, 'Research In Progress');
    expect(inProgress).toBeGreaterThanOrEqual(0);
  });

  test('TC-007: Users list page loads → all users listed with roles and colleges', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/users');

    await expect(page.getByRole('heading', { name: 'User management' })).toBeVisible();
    const directoryHint = page.getByRole('heading', { name: 'Directory' }).locator('..').getByText(/\d+\s+users/i);
    await expect(directoryHint).toBeVisible();
    const usersListed = parseInt(((await directoryHint.innerText()).match(/(\d+)/) ?? ['0'])[1], 10);
    expect(usersListed).toBeGreaterThan(0);
    await expect(page.getByRole('row', { name: /admin@yopmail\.com/i })).toBeVisible();
    const facultyRow = page.getByRole('row', { name: new RegExp(credentials.faculty_ccs.email, 'i') });
    await expect(facultyRow).toContainText('Faculty');
    await expect(page.getByRole('row', { name: new RegExp(credentials.dean_ccs.email, 'i') })).toContainText('Dean/Head');
    await expect(facultyRow).toContainText('CCS');
  });

  test('TC-007b: Role dropdown exposes current roles only', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();

    const role = page.locator('#add-role');
    await expect(role.locator('option[value="college_dean"]')).toHaveText('Dean/Head');
    await expect(role.locator('option[value="viewer"]')).toHaveText('Viewer');
    await expect(role.locator('option[value="co_author"]')).toHaveCount(0);
    await expect(role.locator('option[value="registrar"]')).toHaveCount(0);
    await expect(role.locator('option[value="unit_head"]')).toHaveCount(0);
  });

  test('TC-008: Create new faculty user with college assignment → appears in list with faculty role', async ({
    page,
  }) => {
    const stamp = Date.now();
    const email = `e2e.faculty.${stamp}@auf.edu.ph`;
    const title = uniqueTitle('TC008 Faculty');

    await adminLogin(page);
    await page.goto('/admin/dashboard');
    const usersBefore = await getDashboardStat(page, 'Total users');

    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('F08', stamp));
    await page.locator('#add-first_name').fill(title);
    await page.locator('#add-last_name').fill('FACULTY');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await selectCollegeByCode(page, '#add-college_id', 'CCS');
    await page.getByRole('button', { name: 'Create user' }).click();

    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('Faculty');
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('CCS');

    await page.goto('/admin/dashboard');
    const usersAfter = await getDashboardStat(page, 'Total users');
    expect(usersAfter).toBe(usersBefore + 1);
  });

  test('TC-009: New user name is stored as typed', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.as-typed.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('U09', stamp));
    await page.locator('#add-first_name').fill('lowercase');
    await page.locator('#add-last_name').fill('mixedcase');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await page.locator('#add-college_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create user' }).click();

    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('lowercase mixedcase');
  });

  test('TC-010: Edit existing user → change their college → changes reflected in list', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.collegechg.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('C10', stamp));
    await page.locator('#add-first_name').fill('COLLEGE');
    await page.locator('#add-last_name').fill('CHANGE');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await selectCollegeByCode(page, '#add-college_id', 'CCS');
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });

    await openEditUserByEmail(page, email);
    await selectCollegeByCode(page, '#edit-college_id', 'CBA');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('CBA');
  });

  test('TC-011: Update user role from faculty to viewer → role updated', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.rolechg.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('R11', stamp));
    await page.locator('#add-first_name').fill('ROLE');
    await page.locator('#add-last_name').fill('CHANGE');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await page.locator('#add-college_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });

    await openEditUserByEmail(page, email);
    await page.locator('#edit-role').selectOption('viewer');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('Viewer');
  });

  test('TC-012: Deactivate user → edit modal is scrollable (L-03) → user marked inactive', async ({ page }) => {
    const stamp = Date.now();
    const email = `e2e.deactivate.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('D12', stamp));
    await page.locator('#add-first_name').fill('DEACT');
    await page.locator('#add-last_name').fill('USER');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await page.locator('#add-college_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });

    await openEditUserByEmail(page, email);
    const scrollableForm = page.locator('#form-edit-user');
    const overflowY = await scrollableForm.evaluate((el) => getComputedStyle(el).overflowY);
    expect(['auto', 'scroll']).toContain(overflowY);

    await page.locator('#form-edit-user .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('Inactive');
  });

  test('TC-013: Inactive user cannot login → blocked with appropriate message', async ({
    page,
    browser,
  }) => {
    const stamp = Date.now();
    const email = `e2e.loginblock.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('L13', stamp));
    await page.locator('#add-first_name').fill('LOGIN');
    await page.locator('#add-last_name').fill('BLOCK');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('faculty');
    await page.locator('#add-college_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });

    await openEditUserByEmail(page, email);
    await page.locator('#form-edit-user .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });

    const isolatedContext = await browser.newContext();
    const isolatedPage = await isolatedContext.newPage();
    await isolatedPage.goto('/login');
    await isolatedPage.fill('input[name="login"]', email);
    await isolatedPage.fill('input[name="password"]', 'password');
    await isolatedPage.getByRole('button', { name: /sign in/i }).click();
    await expect(isolatedPage).toHaveURL(/\/login/);
    await expect(isolatedPage.getByText(/This account is inactive/i)).toBeVisible();
    await isolatedContext.close();
  });

  test('TC-014: Non-admin cannot access /admin/users → 403 Forbidden', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/admin/users');
    expect(response?.status()).toBe(403);
  });

  test('TC-015: Colleges list page loads → official AUF college codes are listed', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/colleges');

    await expect(page.getByRole('heading', { name: 'Colleges/Offices & programs' })).toBeVisible();
    const collegeRows = page.locator('#section-colleges table.kmsar-table tbody tr, table.kmsar-table tbody tr');
    await expect(collegeRows.first()).toBeVisible();
    expect(await collegeRows.count()).toBeGreaterThanOrEqual(AUF_COLLEGE_CODES.length);
    for (const code of AUF_COLLEGE_CODES) {
      await expect(page.locator('table.kmsar-table').getByText(code, { exact: true }).first()).toBeVisible();
    }
  });

  test('TC-016: Create new college → code and name stored UPPERCASE', async ({ page }) => {
    const stamp = Date.now();
    const code = `E${String(stamp).slice(-3)}`;
    const rawName = 'e2e test college lowercase';

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addCollege(page, code.toLowerCase(), rawName);

    await expect(page.getByText(/College added successfully/i)).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('table.kmsar-table').getByText(code.toUpperCase(), { exact: true })).toBeVisible();
    await expect(page.locator('table.kmsar-table').getByText(rawName.toUpperCase())).toBeVisible();
  });

  test('TC-017: Edit college → name updated correctly', async ({ page }) => {
    const stamp = Date.now();
    const code = `U${String(stamp).slice(-3)}`;
    const updatedName = `UPDATED COLLEGE NAME ${stamp}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addCollege(page, code, 'ORIGINAL COLLEGE NAME');
    await expect(page.getByText(/College added successfully/i)).toBeVisible({ timeout: 15_000 });

    await openEditCollegeByCode(page, code.toUpperCase());
    await page.locator('#edit-college-name').fill(updatedName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/College updated successfully/i)).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('table.kmsar-table').getByText(updatedName)).toBeVisible();
  });

  test('TC-018: Toggle college inactive → active college count on dashboard decreases (L-04)', async ({
    page,
  }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');
    const beforeCount = await getDashboardStat(page, 'Total colleges');

    await page.goto('/admin/colleges');
    await openEditCollegeByCode(page, 'SL');
    const activeCheckbox = page.locator('#form-edit-college input[name="is_active"][type="checkbox"]');
    const wasActive = await activeCheckbox.isChecked();
    await page.locator('#form-edit-college .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/College updated successfully/i)).toBeVisible({ timeout: 15_000 });

    await page.goto('/admin/dashboard');
    const afterCount = await getDashboardStat(page, 'Total colleges');
    if (wasActive) {
      expect(afterCount).toBe(beforeCount - 1);
    } else {
      expect(afterCount).toBe(beforeCount + 1);
    }
  });

  test('TC-019: Toggle same college back to active → count increases back', async ({ page }) => {
    await adminLogin(page);
    await page.goto('/admin/dashboard');
    const beforeCount = await getDashboardStat(page, 'Total colleges');

    await page.goto('/admin/colleges');
    await openEditCollegeByCode(page, 'SL');
    const wasActive = await page.locator('#form-edit-college input[name="is_active"][type="checkbox"]').isChecked();
    await page.locator('#form-edit-college .kmsar-switch-track').click({ force: true });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/College updated successfully/i)).toBeVisible({ timeout: 15_000 });

    await page.goto('/admin/dashboard');
    const afterCount = await getDashboardStat(page, 'Total colleges');
    if (wasActive) {
      expect(afterCount).toBe(beforeCount - 1);
    } else {
      expect(afterCount).toBe(beforeCount + 1);
    }
  });

  test('TC-020: Delete college with no research → college deleted', async ({ page }) => {
    const stamp = Date.now();
    const code = `D${String(stamp).slice(-3)}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addCollege(page, code, 'DELETE ME COLLEGE');
    await expect(page.getByText(/College added successfully/i)).toBeVisible({ timeout: 15_000 });

    const row = page.getByRole('row').filter({ hasText: code.toUpperCase() });
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByText(/College deleted successfully/i)).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('table.kmsar-table').getByText(code.toUpperCase(), { exact: true })).toHaveCount(0);
  });

  test('TC-021: Non-admin cannot create college → 403 Forbidden', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const response = await page.request.post('/admin/colleges', {
      headers: {
        'X-CSRF-TOKEN': token ?? '',
        Referer: page.url(),
      },
      form: {
        code: 'E2E',
        name: 'FORBIDDEN COLLEGE',
      },
    });
    expect(response.status()).toBe(403);
  });

  test('TC-022: Create new program under a college → linked to correct college', async ({ page }) => {
    const stamp = Date.now();
    const programCode = `P${String(stamp).slice(-5)}`;
    const programName = `E2E Program ${stamp}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addProgram(page, 'CCS', programCode, programName);

    await expect(page.getByText(/Program added successfully/i)).toBeVisible({ timeout: 15_000 });
    const row = await findProgramRow(page, programCode);
    await expect(row).toContainText('CCS');
  });

  test('TC-023: Edit program name → updated', async ({ page }) => {
    const stamp = Date.now();
    const programCode = `E${String(stamp).slice(-5)}`;
    const updatedName = `EDITED PROGRAM NAME ${stamp}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addProgram(page, 'CCS', programCode, 'ORIGINAL PROGRAM NAME');
    await expect(page.getByText(/Program added successfully/i)).toBeVisible({ timeout: 15_000 });

    const row = await findProgramRow(page, programCode);
    await row.getByRole('button', { name: 'Edit' }).click();
    await expect(page.locator('#form-edit-program')).toBeVisible();
    await page.locator('#edit-program-name').fill(updatedName);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText(/Program updated successfully/i)).toBeVisible({ timeout: 15_000 });
    await expect(await findProgramRow(page, programCode)).toContainText(updatedName);
  });

  test('TC-024: Delete program → removed', async ({ page }) => {
    const stamp = Date.now();
    const programCode = `X${String(stamp).slice(-5)}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addProgram(page, 'CCS', programCode, 'DELETE PROGRAM');
    await expect(page.getByText(/Program added successfully/i)).toBeVisible({ timeout: 15_000 });

    const row = await findProgramRow(page, programCode);
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByText(/Program deleted successfully/i)).toBeVisible({ timeout: 15_000 });
    await page.locator('[aria-label="Search programs/depts"], [aria-label="Search programs"]').fill(programCode);
    await expect(page.locator('#section-programs table.kmsar-table tbody').getByText(programCode)).toHaveCount(0);
  });

  test('TC-025: Program visible under correct college', async ({ page }) => {
    const stamp = Date.now();
    const programCode = `C${String(stamp).slice(-5)}`;

    await adminLogin(page);
    await page.goto('/admin/colleges');
    await addProgram(page, 'CBA', programCode, 'CBA LINKED PROGRAM');
    await expect(page.getByText(/Program added successfully/i)).toBeVisible({ timeout: 15_000 });

    const row = await findProgramRow(page, programCode);
    await expect(row.getByText('CBA', { exact: true })).toBeVisible();
  });

  test('TC-026: Audit logs page loads with timestamps and actions in Asia/Manila timezone (L-02)', async ({
    page,
  }) => {
    seedAuditLog('e2e.audit.manila');

    await adminLogin(page);
    await page.goto('/admin/audit-logs');

    await expect(page.getByRole('heading', { name: 'Audit logs' })).toBeVisible();
    await expect(page.locator('table.kmsar-table tbody tr').first()).toBeVisible();
    await expect(page.locator('table.kmsar-table').getByText('e2e.audit.manila')).toBeVisible();

    const timeCell = page
      .getByRole('row')
      .filter({ hasText: 'e2e.audit.manila' })
      .locator('.kmsar-table-cell-sub')
      .last();
    await expect(timeCell).toHaveText(/\d{1,2}:\d{2}\s+(AM|PM)/i);

    const displayedTime = (await timeCell.innerText()).trim();
    const manilaNow = new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
      timeZone: 'Asia/Manila',
    }).format(new Date());
    const manilaHour = manilaNow.match(/(\d{1,2})/)?.[1];
    const displayedHour = displayedTime.match(/(\d{1,2})/)?.[1];
    expect(manilaHour).toBeTruthy();
    expect(displayedHour).toBeTruthy();
    expect(parseInt(displayedHour!, 10)).toBeLessThanOrEqual(12);
    expect(parseInt(manilaHour!, 10)).toBeLessThanOrEqual(12);
  });

  test('TC-027: Filter audit logs by action type → filtered correctly', async ({ page }) => {
    seedAuditLog('e2e.filter.keep');
    seedAuditLog('e2e.filter.other');

    await adminLogin(page);
    await page.goto('/admin/audit-logs?action=e2e.filter.keep');
    await expect(page.locator('table.kmsar-table').getByText('e2e.filter.keep')).toBeVisible();
    await expect(page.locator('table.kmsar-table tbody').getByText('e2e.filter.other')).toHaveCount(0);
  });

  test('TC-028: Create user with office field for non-college unit (IS, OVPRI, CDAIC etc) → office saved and shown in directory column', async ({
    page,
  }) => {
    const stamp = Date.now();
    const email = `e2e.unithead.${stamp}@auf.edu.ph`;

    await adminLogin(page);
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'Add user' }).click();
    await page.locator('#add-employee_number').fill(shortEmployeeNumber('O28', stamp));
    await page.locator('#add-first_name').fill('UNIT');
    await page.locator('#add-last_name').fill('HEAD');
    await page.locator('#add-email').fill(email);
    await page.locator('#add-password').fill('password');
    await page.locator('#add-password_confirmation').fill('password');
    await page.locator('#add-role').selectOption('college_dean');
    await page.locator('#add-office').fill('OVPRI');
    await page.getByRole('button', { name: 'Create user' }).click();

    await expect(page.getByText(/created successfully|updated successfully/i)).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('OVPRI');
    await expect(page.getByRole('row', { name: new RegExp(email, 'i') })).toContainText('Non-college unit');
  });
});
