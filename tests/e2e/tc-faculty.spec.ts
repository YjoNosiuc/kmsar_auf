import { test, expect, Page } from '@playwright/test';
import { login, logout, credentials } from './helpers/auth';
import { resetDatabase, runTinker } from './helpers/db';
import { createAndSubmitResearch } from './helpers/research';

const SAMPLE_PDF = 'tests/e2e/fixtures/sample.pdf';
const SAMPLE_TXT = 'tests/e2e/fixtures/sample.txt';
const DUPLICATE_SEED_TITLE =
  'AI-Based Crop Disease Detection Using Convolutional Neural Networks';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function fillStep1(
  page: Page,
  title: string,
  classification = 'internally_funded',
): Promise<void> {
  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', classification);
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
}

async function startWizardStep1(page: Page): Promise<string> {
  await page.goto('/research/create');
  await page.waitForURL(/\/research\/(\d+)\/details/);
  return page.url().match(/\/research\/(\d+)\//)?.[1] ?? '';
}

async function openNotificationBell(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Notifications' }).click();
}

async function getUnreadBellCount(page: Page): Promise<number> {
  const dot = page.locator('.kmsar-navbar-notif-dot');
  if (!(await dot.count())) {
    return 0;
  }
  const text = (await dot.innerText()).trim();
  if (text === '9+') {
    return 9;
  }
  return parseInt(text, 10) || 0;
}

test.describe('Faculty — UAT Test Suite', () => {
  test.beforeAll(async () => {
    resetDatabase();
  });

  test('TC-001: login page loads with email and password fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[name="login"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('TC-002: login with valid faculty credentials redirects to research list', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await expect(page).toHaveURL(/\/research\/?$/);
    await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
  });

  test('TC-003: login with wrong password shows error message', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="login"]', credentials.faculty_ccs.email);
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByText(/credentials do not match/i)).toBeVisible();
  });

  test('TC-004: login with inactive account shows error message', async ({ page }) => {
    runTinker('App\\Models\\User::where(\'email\', \'faculty.ccs3@auf.edu.ph\')->update([\'is_active\' => false]);');
    await page.goto('/login');
    await page.fill('input[name="login"]', 'faculty.ccs3@auf.edu.ph');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByText(/This account is inactive/i)).toBeVisible();
  });

  test('TC-005: logout redirects to login and back button does not show protected page', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await expect(page).toHaveURL(/\/research/);
    await logout(page);
    await page.goBack();
    await page.reload();
    await expect(page.locator('input[name="login"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('TC-006: view profile page loads with name and email', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/profile');
    await expect(page.getByRole('heading', { name: 'My Profile' })).toBeVisible();
    await expect(page.locator('#profile_first_name')).toBeVisible();
    await expect(page.locator('#profile_email')).toHaveValue(credentials.faculty_ccs.email);
  });

  test('TC-007: update name is stored in UPPERCASE', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/profile');
    const newFirst = `E2E${Date.now()}`.slice(0, 12);
    await page.fill('#profile_first_name', newFirst);
    await page.click('button:has-text("Save changes")');
    await expect(page.getByText('Profile updated successfully')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('#profile_first_name')).toHaveValue(newFirst.toUpperCase());
  });

  test('TC-008: change password — old password no longer works', async ({ page }) => {
    const newPassword = `Pass${Date.now()}!`;
    await login(page, credentials.faculty_cba.email, credentials.faculty_cba.password);
    await page.goto('/profile');
    await page.fill('#profile_current_password', credentials.faculty_cba.password);
    await page.fill('#profile_password', newPassword);
    await page.fill('#profile_password_confirmation', newPassword);
    await page.click('button:has-text("Change password")');
    await expect(page.getByText('Password changed successfully')).toBeVisible({ timeout: 15_000 });

    await logout(page);
    await page.goto('/login');
    await page.fill('input[name="login"]', credentials.faculty_cba.email);
    await page.fill('input[name="password"]', credentials.faculty_cba.password);
    await page.click('button[type="submit"]');
    await expect(page.getByText(/credentials do not match/i)).toBeVisible();

    await page.fill('input[name="password"]', newPassword);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/research/);

    runTinker(`use Illuminate\\Support\\Facades\\Hash; App\\Models\\User::where('email', 'faculty.cba1@auf.edu.ph')->update(['password' => Hash::make('password')]);`);
  });

  test('TC-009: click New Research redirects to Wizard Step 1', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.click('a:has-text("Register new")');
    await expect(page).toHaveURL(/\/research\/\d+\/details/);
    await expect(page.getByText(/Step 1 of 3/i)).toBeVisible();
  });

  test('TC-010: fill Step 1 and proceed to Step 2 — thesis_dissertation classification available', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await expect(
      page.locator('select[name="research_classification"] option[value="thesis_dissertation"]'),
    ).toHaveCount(1);
    await fillStep1(page, uniqueTitle('TC010 Step1'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await expect(page).toHaveURL(/\/authors/);
  });

  test('TC-011: submit Step 1 with missing required fields shows validation error', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await expect(page.locator('.kmsar-form-error, .kmsar-alert--danger').first()).toBeVisible();
  });

  test('TC-012: fill Step 2 add self as primary author and proceed to Step 3', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC012 Authors'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await expect(page.getByText('Primary Author', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await expect(page).toHaveURL(/\/documents/);
    await expect(page.getByText(/Step 3 of 3/i)).toBeVisible();
  });

  test('TC-013: add co-author by employee number — research appears in co-author list', async ({
    page,
  }) => {
    const title = uniqueTitle('TC013 CoAuthor');
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, title);
    await page.getByRole('button', { name: 'Continue to authors' }).click();

    await page.getByRole('button', { name: '+ Add co-author' }).click();
    const card = page.locator('.authors-coauthor-card').last();
    await card.getByRole('tab', { name: 'Employee' }).click();
    await card.locator('[id^="coauthor_emp_en_"]').fill('AUF-0022');
    await card.locator('[id^="coauthor_emp_em_"]').fill('faculty.ccs2@auf.edu.ph');
    await card.locator('[id^="coauthor_emp_fn_"]').fill('JUAN');
    await card.locator('[id^="coauthor_emp_ln_"]').fill('DELA CRUZ');
    await card.locator('[id^="coauthor_emp_mc_"]').selectOption({ index: 1 });

    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_PDF);
    await page.getByRole('button', { name: 'Save Document' }).click();
    await expect(page.getByText(/uploaded successfully|Document uploaded/i).first()).toBeVisible({
      timeout: 15_000,
    });

    await logout(page);
    await login(page, 'faculty.ccs2@auf.edu.ph', 'password');
    await page.goto('/research');
    await expect(page.getByText(title)).toBeVisible();
    await expect(page.getByText('Co-author', { exact: true }).first()).toBeVisible();
  });

  test('TC-014: view Step 3 document upload page — upload area visible', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const researchId = await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC014 Docs'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await expect(page.locator('.kmsar-dropzone, label.kmsar-dropzone').first()).toBeVisible();
    await expect(page.locator('#kmsar-document-file-input')).toBeAttached();
    expect(researchId).toBeTruthy();
  });

  test('TC-015: upload valid PDF — appears in list with standardized filename', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC015 PDF'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_PDF);
    await page.getByRole('button', { name: 'Save Document' }).click();
    await expect(page.getByText(/uploaded successfully|Document uploaded/i).first()).toBeVisible({
      timeout: 15_000,
    });
    await expect(page.getByText(/sample\.pdf/i).first()).toBeVisible();
  });

  test('TC-016: upload non-PDF file — rejected with validation error', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC016 Invalid'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_TXT);
    await page.getByRole('button', { name: 'Save Document' }).click();
    await expect(page.locator('.kmsar-alert--danger, .kmsar-form-error').first()).toBeVisible({
      timeout: 15_000,
    });
  });

  test('TC-017: add external link — saved and visible in list', async ({ page }) => {
    const link = 'https://doi.org/10.1000/e2e-test-link';
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC017 Link'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.locator('form[action*="documents"]').getByRole('tab', { name: 'Add Link' }).click();
    await page.fill('input[name="external_link"]', link);
    await page.getByRole('button', { name: 'Save Document' }).click();
    await expect(page.getByText(/saved successfully|Document saved/i).first()).toBeVisible({
      timeout: 15_000,
    });
    await expect(page.getByText(link).first()).toBeVisible();
  });

  test('TC-018: submit research with document — stage changes to For Dean Review', async ({
    page,
  }) => {
    const title = uniqueTitle('TC018 Submit');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();
    await page.goto(`/research/${researchId}`);
    await expect(page.getByRole('cell', { name: 'Dean Review' })).toBeVisible();
  });

  test('TC-019: submit research with NO documents — blocked with error', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const researchId = await startWizardStep1(page);
    await fillStep1(page, uniqueTitle('TC019 No Docs'));
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await expect(
      page.getByText(/at least one document is required|document is required/i).first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('TC-020: duplicate title warning shown when same title registered again', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, DUPLICATE_SEED_TITLE);
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await expect(
      page.getByText(/already an existing research with a similar title/i).first(),
    ).toBeVisible();
  });

  test('TC-021: notification bell shows submission confirmation after submit', async ({
    page,
  }) => {
    const title = uniqueTitle('TC021 Notif');
    await createAndSubmitResearch(page, title);
    await openNotificationBell(page);
    await expect(page.getByText(/submission confirmed|submitted/i).first()).toBeVisible();
  });

  test('TC-022: research list shows own research plus co-authored with badge', async ({
    page,
  }) => {
    const title = uniqueTitle('TC022 List');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    runTinker(
      `$r = App\\Models\\Research::find(${researchId}); $u = App\\Models\\User::where('email', 'faculty.ccs2@auf.edu.ph')->first(); App\\Models\\ResearchAuthor::updateOrCreate(['research_id' => $r->id, 'user_id' => $u->id], ['name' => 'JUAN DELA CRUZ', 'email' => 'faculty.ccs2@auf.edu.ph', 'is_primary' => false, 'can_edit' => true]);`,
    );

    await page.goto('/research');
    const ownCard = page.locator('div[style*="border-left:4px"]').filter({ hasText: title }).first();
    await expect(ownCard).toBeVisible();
    await expect(ownCard.getByText('Co-author', { exact: true })).toHaveCount(0);

    await logout(page);
    await login(page, 'faculty.ccs2@auf.edu.ph', 'password');
    await page.goto('/research');
    const coAuthoredCard = page.locator('div[style*="border-left:4px"]').filter({ hasText: title }).first();
    await expect(coAuthoredCard).toBeVisible();
    await expect(coAuthoredCard.getByText('Co-author', { exact: true })).toBeVisible();
  });

  test('TC-023: open research record — full detail loads with timeline authors documents', async ({
    page,
  }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    await page.getByRole('link', { name: 'View research' }).first().click();
    await expect(page.getByRole('tab', { name: /Research info/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Documents/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Approval history/i })).toBeVisible();
    await page.getByRole('tab', { name: /Documents/i }).click();
    await expect(page.locator('table tbody tr').first()).toBeVisible();
  });

  test('TC-024: edit research in Draft stage — changes saved', async ({ page }) => {
    const title = uniqueTitle('TC024 Edit Draft');
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const researchId = await startWizardStep1(page);
    await fillStep1(page, title);
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();

    const updatedTitle = uniqueTitle('TC024 Updated');
    await page.goto(`/research/${researchId}/edit`);
    await page.fill('textarea[name="title"]', updatedTitle);
    await page.click('button:has-text("Save changes")');
    await expect(page.getByText(/updated|saved/i).first()).toBeVisible({ timeout: 15_000 });
    await page.goto('/research');
    await expect(page.getByText(updatedTitle)).toBeVisible();
  });

  test('TC-025: try to edit research in Dean Review — edit not available', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    const card = page.locator('div[style*="border-left:4px"]').filter({ hasText: 'dean review' }).first();
    await card.getByRole('link', { name: /View research/i }).click();
    await expect(page.getByRole('link', { name: /^Edit$/i })).toHaveCount(0);
    await expect(page.locator('.kmsar-page-header-actions a:has-text("Edit")')).toHaveCount(0);
  });

  test('TC-026: delete research in Draft stage — removed from list', async ({ page }) => {
    const title = uniqueTitle('TC026 Delete');
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await startWizardStep1(page);
    await fillStep1(page, title);
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.goto('/research');
    const card = page.locator('div[style*="border-left:4px"]').filter({ hasText: title });
    page.once('dialog', (dialog) => dialog.accept());
    await card.getByRole('button', { name: 'Delete' }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(title)).toHaveCount(0);
  });

  test('TC-027: try to delete research not in Draft — delete not available', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    const card = page.locator('div[style*="border-left:4px"]').filter({ hasText: 'dean review' }).first();
    await expect(card.getByRole('button', { name: 'Delete' })).toHaveCount(0);
  });

  test('TC-028: after rejection Revise button appears and clicking it returns to Draft', async ({
    page,
  }) => {
    const title = uniqueTitle('TC028 Rejected');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();
    runTinker(
      `App\\Models\\Research::find(${researchId})->update(['approval_stage' => 'rejected']);`,
    );

    await page.goto(`/research/${researchId}`);
    await page.getByRole('button', { name: 'Revise', exact: true }).click();
    await expect(page.getByText(/returned to draft/i).first()).toBeVisible({ timeout: 15_000 });
    await expect(page).toHaveURL(/\/research\/\d+\/edit/);
  });

  test('TC-029: after approval submit progress update — stage moves to Dean Review', async ({
    page,
  }) => {
    const title = uniqueTitle('TC029 Progress');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    runTinker(
      `App\\Models\\Research::find(${researchId})->update(['approval_stage' => 'approved', 'status' => 'proposal']);`,
    );

    await page.goto(`/research/${researchId}`);
    await page.getByRole('button', { name: 'Update Progress' }).click();
    await page.locator('select[name="status"]').selectOption('ongoing');
    await page.locator('form[action*="update-progress"] input[name="files[]"]').setInputFiles(SAMPLE_PDF);
    await page.locator('form[action*="update-progress"] button[type="submit"]').click();
    await expect(
      page.getByRole('alert').filter({ hasText: /Progress updated|re-endorsement/i }),
    ).toBeVisible({ timeout: 15_000 });
    await expect(page.getByRole('cell', { name: 'Dean Review' })).toBeVisible();
  });

  test('TC-030: try progress update on non-approved research — blocked', async ({ page }) => {
    const title = uniqueTitle('TC030 No Progress');
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const researchId = await startWizardStep1(page);
    await fillStep1(page, title);
    await page.getByRole('button', { name: 'Continue to authors' }).click();
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.goto(`/research/${researchId}`);
    await expect(page.getByRole('button', { name: 'Update Progress' })).toHaveCount(0);
  });

  test('TC-031: notifications page lists all received alerts with timestamps', async ({
    page,
  }) => {
    const title = uniqueTitle('TC031 Notif Page');
    await createAndSubmitResearch(page, title);
    await page.goto('/notifications');
    await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible();
    await expect(page.locator('a').filter({ hasText: /submission|submitted|confirmed/i }).first()).toBeVisible();
    await expect(page.getByText(/\d{1,2}:\d{2}|ago|AM|PM/i).first()).toBeVisible();
  });

  test('TC-032: mark notification as read — unread count decreases', async ({ page }) => {
    const title = uniqueTitle('TC032 Mark Read');
    await createAndSubmitResearch(page, title);
    const before = await getUnreadBellCount(page);

    await openNotificationBell(page);
    const link = page.locator('a[onclick*="markRead"]').first();
    const onclick = await link.getAttribute('onclick');
    const notifId = onclick?.match(/markRead\('([^']+)'/)?.[1];
    expect(notifId).toBeTruthy();

    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    await page.request.post(`/notifications/${notifId}/read`, {
      headers: { 'X-CSRF-TOKEN': csrf ?? '' },
    });
    await page.reload();

    const after = await getUnreadBellCount(page);
    expect(after).toBeLessThanOrEqual(before);
    if (before > 0) {
      expect(after).toBeLessThan(before);
    }
  });

  test('TC-033: mark all notifications as read — bell count resets to 0', async ({ page }) => {
    const title = uniqueTitle('TC033 Mark All');
    await createAndSubmitResearch(page, title);
    await openNotificationBell(page);
    await page.locator('form[action*="read-all"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(await getUnreadBellCount(page)).toBe(0);
  });

  test('TC-034: try accessing /dean/dashboard — 403 Forbidden', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/dean/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('TC-035: try accessing /ovpri/dashboard — 403 Forbidden', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/ovpri/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('TC-036: try accessing /admin/dashboard — 403 Forbidden', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const response = await page.goto('/admin/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('H-01: PDF preview opens in modal not new tab', async ({ page, context }) => {
    const title = uniqueTitle('H01 PDF Modal');
    const researchId = await createAndSubmitResearch(page, title);
    await page.goto(`/research/${researchId}`);
    await page.getByRole('tab', { name: /Documents/i }).click();
    const pagesBefore = context.pages().length;
    await page.getByRole('button', { name: 'Preview' }).first().click();
    await expect(page.locator('#kmsar-preview-modal')).toBeVisible();
    expect(context.pages().length).toBe(pagesBefore);
  });
});
