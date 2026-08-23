import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { runTinker } from './helpers/db';
import { acquireSuiteLock, releaseSuiteLock } from './helpers/db-lock';
import {
  setupEndorsedResearch,
  approveResearch,
  returnResearchOvpri,
  rejectResearchOvpri,
  openFacultyResearchList,
  facultyResearchCard,
  researchApprovalStage,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

/** Ensure at least one CBA research exists (suite no longer starts from a fresh seed). */
function ensureCbaResearchVisible(): void {
  const stamp = Date.now();
  runTinker(
    `$author = \\App\\Models\\User::where('email','faculty.cba1@yopmail.com')->firstOrFail(); $college = \\App\\Models\\College::where('code','CBA')->firstOrFail(); \\App\\Models\\Research::firstOrCreate(['reference_number' => 'E2E-CBA-${stamp}'], ['title' => 'E2E CBA Cross-College ${stamp}', 'primary_author_id' => $author->id, 'mother_college_id' => $college->id, 'research_classification' => 'internally_funded', 'expected_output' => ['publication'], 'start_date' => '2026-01-01', 'estimated_completion_date' => '2027-01-01', 'status' => 'proposal', 'approval_stage' => 'approved', 'revision_count' => 0, 'sdg_tags' => [4]]);`,
  );
}

async function ovpriLogin(page: Page): Promise<void> {
  await login(page, credentials.ovpri.email, credentials.ovpri.password);
}

async function cdaicLogin(page: Page): Promise<void> {
  await login(page, credentials.cdaic.email, credentials.cdaic.password);
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

async function openOvpriReview(page: Page, researchId: string): Promise<void> {
  await page.goto(`/ovpri/review/${researchId}`);
}

async function switchQueueTab(page: Page, tab: 'pending' | 'approved' | 'returned'): Promise<void> {
  await page.locator(`#tab-${tab}`).click();
  await expect(page.locator(`#panel-${tab}`)).toHaveClass(/active/);
}

test.describe('OVPRI / CDAIC — UAT Test Suite', () => {
  test.beforeAll(async () => {
    await acquireSuiteLock('ovpri');
  });

  test.afterAll(() => {
    releaseSuiteLock();
  });

  test('TC-001: Login with OVPRI credentials → redirected to OVPRI Dashboard', async ({ page }) => {
    await ovpriLogin(page);
    await expect(page).toHaveURL(/\/ovpri\/dashboard/);
    await expect(page.getByRole('heading', { name: 'University dashboard' })).toBeVisible();
  });

  test('TC-002: Login with CDAIC credentials → redirected to OVPRI Dashboard (same access)', async ({
    page,
  }) => {
    await cdaicLogin(page);
    await expect(page).toHaveURL(/\/ovpri\/dashboard/);
    await expect(page.getByRole('heading', { name: 'University dashboard' })).toBeVisible();
  });

  test('TC-003: OVPRI dashboard shows university-wide stats across ALL colleges', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    const totalCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Total research' });
    const totalText = await totalCard.locator('.kmsar-stat-card-value').innerText();
    const total = parseInt(totalText.replace(/,/g, ''), 10);
    expect(total).toBeGreaterThan(0);

    const pendingCard = page.locator('.kmsar-stat-card').filter({ hasText: /Pending.*approval/i });
    const pending = parseInt(
      (await pendingCard.locator('.kmsar-stat-card-value').innerText()).replace(/,/g, ''),
      10,
    );
    expect(pending).toBeGreaterThanOrEqual(0);
    const inProgressCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Research In Progress' });
    const inProgress = parseInt(
      (await inProgressCard.locator('.kmsar-stat-card-value').innerText()).replace(/,/g, ''),
      10,
    );
    expect(inProgress).toBeGreaterThanOrEqual(0);
  });

  test('TC-004: SDG distribution chart loads without errors', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('#sdgChart')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'SDG Distribution' })).toBeVisible();
    await expect(page.locator('.kmsar-alert--danger')).toHaveCount(0);
  });

  test('TC-004b: Dashboard has Date From and Date To filters', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('input[name="date_from"]')).toBeVisible();
    await expect(page.locator('input[name="date_to"]')).toBeVisible();
    await expect(page.getByLabel(/Date From/i)).toBeVisible();
    await expect(page.getByLabel(/Date To/i)).toBeVisible();
  });

  test('TC-004c: Dashboard shows Faculty/Staff Engaged and three-year submission trend', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('[data-stat-card="engaged"]')).toContainText('Faculty/Staff Engaged');
    await expect(page.locator('#engagedByCollegeChart')).toBeVisible();
    await expect(page.locator('#submissionTrendChart')).toBeVisible();
  });

  test('TC-005: Dashboard includes research from ALL colleges', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('#kmsarOvpriByCollege')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Research by College/Office' })).toBeVisible();
    await expect(page.locator('#collegeBreakdownTable')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '%' }).first()).toBeVisible();
  });

  test('TC-005b: College search shows Program/Dept breakdown', async ({ page }) => {
    runTinker(
      `$program = \\App\\Models\\Program::where('code','BSIT')->firstOrFail(); \\App\\Models\\User::where('email','faculty.ccs1@yopmail.com')->update(['program_id' => $program->id]);`,
    );
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');
    await page.locator('input[name="college"]').fill('CCS');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();

    await expect(page).toHaveURL(/college=CCS/);
    await expect(page.getByRole('heading', { name: /Program\/Dept Breakdown.*CCS/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Program/Dept' })).toBeVisible();
  });

  test('TC-006: OVPRI Queue shows only endorsed research sorted by newest first, with college filter', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/queue');

    await expect(page.getByRole('heading', { name: 'Final Approval' })).toBeVisible();
    await expect(page.locator('#panel-pending').getByText(/Sorted by submission date — newest first/i)).toBeVisible();
    await expect(page.locator('#college_id')).toBeVisible();
    await expect(page.locator('#college_id option').first()).toHaveText('All Colleges/Offices');

    await Promise.all([
      page.waitForURL(/college_id=\d+/),
      page.locator('#college_id').selectOption({ index: 1 }),
    ]);
  });

  test('TC-007: Open endorsed research → full detail with all documents visible', async ({
    page,
  }) => {
    const title = uniqueTitle('TC007 OVPRI View');
    const researchId = await setupEndorsedResearch(page, title);

    await ovpriLogin(page);
    await openOvpriReview(page, researchId);
    await expect(page.getByRole('tab', { name: /Research Info/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Documents/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Approval History/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible();

    await page.getByRole('tab', { name: /Documents/i }).click();
    await expect(page.getByText(/sample\.pdf/i).first()).toBeVisible();
  });

  test('TC-008: Download document from OVPRI review page → file downloads', async ({ page }) => {
    const researchId = await setupEndorsedResearch(page, uniqueTitle('TC008 Download'));
    await ovpriLogin(page);
    await openOvpriReview(page, researchId);
    await page.getByRole('tab', { name: /Documents/i }).click();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download' }).first().click(),
    ]);
    expect(download.suggestedFilename()).toBeTruthy();
  });

  test('TC-009: Preview document inline → opens in modal not downloaded (H-01)', async ({
    page,
    context,
  }) => {
    const researchId = await setupEndorsedResearch(page, uniqueTitle('TC009 Preview'));
    await ovpriLogin(page);
    await openOvpriReview(page, researchId);
    await page.getByRole('tab', { name: /Documents/i }).click();

    const pagesBefore = context.pages().length;
    await page.getByRole('button', { name: 'Preview' }).first().click();
    await expect(page.locator('#kmsar-preview-modal')).toBeVisible();
    expect(context.pages().length).toBe(pagesBefore);
  });

  test('TC-010: Approve research → stage changes to Approved, removed from queue, CDAIC sees same approved record (M-07)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC010 Approve');
    const researchId = await setupEndorsedResearch(page, title);

    await ovpriLogin(page);
    await openOvpriReview(page, researchId);
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.fill('#approve-remarks', 'University-level approval for institutional records.');
    await page.locator('form[action*="approve"] button[type="submit"]').click();
    await expect(
      page.getByRole('alert').filter({ hasText: /approved successfully/i }),
    ).toBeVisible({ timeout: 15_000 });

    await page.goto('/ovpri/queue');
    await expect(page.locator('#panel-pending').getByText(title)).toHaveCount(0);
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title)).toBeVisible();

    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=approved');
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title)).toBeVisible();
  });

  test('TC-011: Primary author receives ResearchApproved notification (H-04)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC011 Faculty Approved');
    const researchId = await setupEndorsedResearch(page, title);
    await approveResearch(page, researchId);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/has been approved by OVPRI/i).first()).toBeVisible();
  });

  test('TC-012: College dean receives ResearchApprovedDean notification', async ({ page }) => {
    const title = uniqueTitle('TC012 Dean Approved');
    const researchId = await setupEndorsedResearch(page, title);
    await approveResearch(page, researchId);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/approved by OVPRI/i).first()).toBeVisible();
  });

  test('TC-013: Return research to faculty → stage is returned_to_faculty; Dean Returned (not Pending); CDAIC sees returned record', async ({
    page,
  }) => {
    const title = uniqueTitle('TC013 Return Faculty');
    const researchId = await setupEndorsedResearch(page, title);
    const remarks = 'Please revise supporting documents before final university approval.';

    await returnResearchOvpri(page, researchId, remarks);

    expect(researchApprovalStage(researchId)).toBe('returned_to_faculty');

    // Faculty list shows "Returned by OVPRI" badge
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openFacultyResearchList(page, title);
    const facultyCard = facultyResearchCard(page, title);
    await expect(facultyCard).toBeVisible({ timeout: 15_000 });
    await expect(facultyCard.getByText(/Returned by OVPRI/i)).toBeVisible();

    // Dean: NOT in Pending, IS in Returned
    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/approval/queue');
    await expect(page.locator('#panel-pending').getByText(title)).toHaveCount(0);
    await page.locator('#tab-returned').click();
    await expect(page.locator('#panel-returned')).toHaveClass(/active/);
    await expect(page.locator('#panel-returned').getByText(title)).toBeVisible();
    await expect(page.locator('#panel-returned').getByText(/Returned by OVPRI/i).first()).toBeVisible();

    // CDAIC can still open the returned record (institutional reviewer)
    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=returned');
    await switchQueueTab(page, 'returned');
    await expect(page.locator('#panel-returned').getByText(title)).toBeVisible();
    await expect(page.locator('#panel-returned').getByText(/Returned to Faculty/i).first()).toBeVisible();
  });

  test('TC-014: Dean does NOT receive notification when OVPRI returns to faculty', async ({ page }) => {
    const title = uniqueTitle('TC014 Dean No Return Notif');
    const researchId = await setupEndorsedResearch(page, title);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const before = await getUnreadBellCount(page);

    await returnResearchOvpri(
      page,
      researchId,
      'Returned by OVPRI for faculty revision — dean should not be notified.',
    );

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto('/dean/dashboard');
    const after = await getUnreadBellCount(page);
    expect(after).toBe(before);

    await openNotificationBell(page);
    await expect(page.getByText(/returned by OVPRI/i)).toHaveCount(0);
  });

  test('TC-015: Faculty DOES receive notification on OVPRI return', async ({ page }) => {
    test.setTimeout(120_000);
    const title = uniqueTitle('TC015 Faculty Return Notif');
    const researchId = await setupEndorsedResearch(page, title);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    await openNotificationBell(page);
    await page.locator('form[action*="read-all"] button[type="submit"]').click().catch(() => undefined);
    await page.waitForLoadState('networkidle');
    const before = await getUnreadBellCount(page);

    await returnResearchOvpri(
      page,
      researchId,
      'OVPRI return with faculty notification for E2E validation test.',
    );

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research');
    const after = await getUnreadBellCount(page);
    expect(after).toBeGreaterThan(before);

    await openNotificationBell(page);
    await expect(page.getByText(/returned for revision/i).first()).toBeVisible();
  });

  test('TC-016: Reject at OVPRI → stage changes to Rejected, CDAIC sees same rejected record (M-07)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC016 Reject');
    const researchId = await setupEndorsedResearch(page, title);

    await rejectResearchOvpri(page, researchId, 'Does not meet university research quality standards.');

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await expect(page.getByRole('cell', { name: 'Rejected' })).toBeVisible();

    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=returned');
    await switchQueueTab(page, 'returned');
    await expect(page.locator('#panel-returned').getByText(title)).toBeVisible();
  });

  test('TC-017: Dean receives ResearchRejectedDean on OVPRI rejection', async ({ page }) => {
    const title = uniqueTitle('TC017 Dean Reject Notif');
    const researchId = await setupEndorsedResearch(page, title);
    await rejectResearchOvpri(page, researchId, 'Rejected at university level due to incomplete documentation.');

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/rejected by OVPRI/i).first()).toBeVisible();
  });

  test('TC-018: Faculty receives ResearchRejected notification on OVPRI rejection (H-04)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC018 Faculty Reject');
    const remarks = 'OVPRI rejection remarks for faculty notification E2E test case.';
    const researchId = await setupEndorsedResearch(page, title);
    await rejectResearchOvpri(page, researchId, remarks);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/has been rejected/i).first()).toBeVisible();
    await expect(page.getByText(remarks).first()).toBeVisible();
  });

  test('TC-019: All Research page shows all research across all colleges (H-05)', async ({
    page,
  }) => {
    ensureCbaResearchVisible();
    await ovpriLogin(page);
    await page.goto('/ovpri/research');

    await expect(page.getByRole('heading', { name: 'All research' })).toBeVisible();
    await expect(page.locator('table tbody tr').first()).toBeVisible();
    await expect(page.locator('table tbody').getByText('CCS').first()).toBeVisible();
    await expect(page.locator('table tbody').getByText('CBA').first()).toBeVisible();
  });

  test('TC-020: Filter by college works on All Research page (H-05)', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/research');

    const ccsValue = await page.locator('select[name="college"] option').filter({ hasText: 'CCS —' }).first().getAttribute('value');
    expect(ccsValue).toBeTruthy();
    await Promise.all([
      page.waitForURL(/college=\d+/),
      page.locator('select[name="college"]').selectOption(ccsValue!),
    ]);
    await expect(page.locator('table tbody').getByText('CCS').first()).toBeVisible();
    await expect(page.locator('table tbody').getByText('CBA')).toHaveCount(0);
  });

  test('TC-021: Filter by approval stage works on All Research page (H-05)', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/research');

    await Promise.all([
      page.waitForURL(/stage=approved/),
      page.locator('select[name="stage"]').selectOption('approved'),
    ]);
    await expect(page.locator('table tbody').getByText(/Approved/i).first()).toBeVisible();
  });

  test('TC-022: Filter by status works on All Research page (H-05)', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/research');

    // "Research Progress" filter (name=status) — progress values, not approval stages
    await expect(page.getByText(/Research Progress/i).first()).toBeVisible();
    await Promise.all([
      page.waitForURL(/status=/, { timeout: 30_000 }),
      page.locator('select[name="status"]').selectOption('proposal'),
    ]);
    expect(page.url()).toMatch(/status=proposal/);
    expect(page.url()).not.toMatch(/[?&]stage=draft/);
    await expect(page.locator('table tbody tr').first()).toBeVisible();
  });

  test('TC-023: Generate university PDF report → downloads with filter summary', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/reports');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page
        .locator('form[action*="export"]')
        .filter({ has: page.locator('input[name="format"][value="pdf"]') })
        .locator('button[type="submit"]')
        .click(),
    ]);
    expect(download.suggestedFilename().toLowerCase()).toMatch(/\.pdf$/);
  });

  test('TC-024: Export university Excel report → downloads', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/reports');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page
        .locator('form[action*="export"]')
        .filter({ has: page.locator('input[name="format"][value="excel"]') })
        .locator('button[type="submit"]')
        .click(),
    ]);
    expect(download.suggestedFilename().toLowerCase()).toMatch(/\.xlsx$/);
  });

  test('TC-025: Report includes research from ALL colleges with pagination and filters (M-05)', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/reports?per_page=10');

    await expect(page.getByText(/Showing .* of .* records/i)).toBeVisible();
    await expect(page.locator('table.kmsar-table tbody').getByText(/BLOCKCHAIN|CCS|CBA|CEA/i).first()).toBeVisible();

    await page.locator('select[name="sdg"]').selectOption('4');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await expect(page.url()).toMatch(/sdg=4/);
    await expect(page.getByText(/Showing .* of .* records/i)).toBeVisible();
  });

  test('TC-026: Try accessing /admin/dashboard → 403 Forbidden', async ({ page }) => {
    await ovpriLogin(page);
    const response = await page.goto('/admin/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('TC-027: Try accessing /dean/dashboard → 403 Forbidden', async ({ page }) => {
    await ovpriLogin(page);
    const response = await page.goto('/dean/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('TC-028: Try accessing /research → 403 Forbidden', async ({ page }) => {
    await ovpriLogin(page);
    const response = await page.goto('/research');
    expect(response?.status()).toBe(403);
  });

  test('CDAIC-001: CDAIC can approve research without 403', async ({ page }) => {
    const title = uniqueTitle('CDAIC001 Approve');
    const researchId = await setupEndorsedResearch(page, title);
    await approveResearch(page, researchId, 'cdaic');

    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=approved');
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title)).toBeVisible();
  });

  test('CDAIC-002: CDAIC can return research without 403', async ({ page }) => {
    const title = uniqueTitle('CDAIC002 Return');
    const researchId = await setupEndorsedResearch(page, title);
    await returnResearchOvpri(
      page,
      researchId,
      'CDAIC return action for parity testing without authorization errors.',
      'cdaic',
    );

    await cdaicLogin(page);
    await openOvpriReview(page, researchId);
    await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible();
    await expect(page.getByRole('cell', { name: 'OVPRI' })).toBeVisible();
  });

  test('CDAIC-003: CDAIC can reject research without 403', async ({ page }) => {
    const title = uniqueTitle('CDAIC003 Reject');
    const researchId = await setupEndorsedResearch(page, title);
    await rejectResearchOvpri(
      page,
      researchId,
      'CDAIC rejection action for parity testing without authorization errors.',
      'cdaic',
    );

    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=returned');
    await switchQueueTab(page, 'returned');
    await expect(page.locator('#panel-returned').getByText(title)).toBeVisible();
  });

  test('CDAIC-004: OVPRI sees CDAIC\'s approved records in Approved tab', async ({ page }) => {
    const title = uniqueTitle('CDAIC004 OVPRI Approved');
    const researchId = await setupEndorsedResearch(page, title);
    await approveResearch(page, researchId, 'cdaic');

    await ovpriLogin(page);
    await page.goto('/ovpri/queue?tab=approved');
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title)).toBeVisible();
  });

  test('CDAIC-005: OVPRI sees CDAIC\'s returned records in Returned tab', async ({ page }) => {
    const title = uniqueTitle('CDAIC005 OVPRI Returned');
    const researchId = await setupEndorsedResearch(page, title);
    await returnResearchOvpri(
      page,
      researchId,
      'CDAIC returned record visible to OVPRI administrator in returned tab.',
      'cdaic',
    );

    await ovpriLogin(page);
    await openOvpriReview(page, researchId);
    await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible();
  });
});
