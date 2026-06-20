import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { resetDatabase } from './helpers/db';
import {
  setupEndorsedResearch,
  approveResearch,
  returnResearchOvpri,
  rejectResearchOvpri,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
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

let reviewResearchId = '';

test.describe('OVPRI / CDAIC — UAT Test Suite', () => {
  test.beforeAll(async () => {
    resetDatabase();
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
    await expect(totalCard.locator('.kmsar-stat-card-value')).toHaveText('22');
  });

  test('TC-004: SDG distribution chart loads without errors', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('#sdgChart')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'SDG Distribution' })).toBeVisible();
    await expect(page.locator('.kmsar-alert--danger')).toHaveCount(0);
  });

  test('TC-005: Dashboard includes research from ALL colleges', async ({ page }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/dashboard');

    await expect(page.locator('#kmsarOvpriByCollege')).toBeVisible();
    await expect(page.getByText(/Research by college/i)).toBeVisible();
    await expect(page.locator('.kmsar-chart-legend, .kmsar-legend-item').first()).toBeVisible();
  });

  test('TC-006: OVPRI Queue shows only endorsed research sorted by newest first, with college filter', async ({
    page,
  }) => {
    await ovpriLogin(page);
    await page.goto('/ovpri/queue');

    await expect(page.getByRole('heading', { name: 'Final Approval' })).toBeVisible();
    await expect(page.locator('#panel-pending').getByText(/Sorted by submission date — newest first/i)).toBeVisible();
    await expect(page.locator('#college_id')).toBeVisible();

    await Promise.all([
      page.waitForURL(/college_id=\d+/),
      page.locator('#college_id').selectOption({ index: 1 }),
    ]);
  });

  test('TC-007: Open endorsed research → full detail with all documents visible', async ({
    page,
  }) => {
    const title = uniqueTitle('TC007 OVPRI View');
    reviewResearchId = await setupEndorsedResearch(page, title);

    await ovpriLogin(page);
    await openOvpriReview(page, reviewResearchId);
    await expect(page.getByRole('tab', { name: /Research Info/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Documents/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Approval History/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible();

    await page.getByRole('tab', { name: /Documents/i }).click();
    await expect(page.getByText(/sample\.pdf/i).first()).toBeVisible();
  });

  test('TC-008: Download document from OVPRI review page → file downloads', async ({ page }) => {
    let researchId = reviewResearchId;
    if (!researchId) {
      researchId = await setupEndorsedResearch(page, uniqueTitle('TC008 Download'));
    }
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
    let researchId = reviewResearchId;
    if (!researchId) {
      researchId = await setupEndorsedResearch(page, uniqueTitle('TC009 Preview'));
    }
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
    await expect(page.locator('#panel-pending').getByText(title.toUpperCase())).toHaveCount(0);
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title.toUpperCase())).toBeVisible();

    await cdaicLogin(page);
    await page.goto('/ovpri/queue?tab=approved');
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title.toUpperCase())).toBeVisible();
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

  test('TC-013: Return research to dean → stage moves back to dean_review, CDAIC sees same returned record (M-07)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC013 Return Dean');
    const researchId = await setupEndorsedResearch(page, title);
    const remarks = 'Please revise supporting documents before final university approval.';

    await returnResearchOvpri(page, researchId, remarks);

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await page.goto(`/approval/${researchId}`);
    await expect(page.getByRole('cell', { name: 'Dean Review' })).toBeVisible();

    await cdaicLogin(page);
    await openOvpriReview(page, researchId);
    await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible();
    await expect(page.getByRole('cell', { name: 'OVPRI' })).toBeVisible();
  });

  test('TC-014: Dean receives ResearchReturnedToDean notification', async ({ page }) => {
    const title = uniqueTitle('TC014 Dean Return Notif');
    const researchId = await setupEndorsedResearch(page, title);
    await returnResearchOvpri(
      page,
      researchId,
      'Returned by OVPRI for additional college-level review and endorsement.',
    );

    await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/returned by OVPRI/i).first()).toBeVisible();
  });

  test('TC-015: Faculty does NOT receive notification on OVPRI return', async ({ page }) => {
    const title = uniqueTitle('TC015 No Faculty Notif');
    const researchId = await setupEndorsedResearch(page, title);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const before = await getUnreadBellCount(page);

    await returnResearchOvpri(
      page,
      researchId,
      'OVPRI return without faculty notification for E2E validation test.',
    );

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const after = await getUnreadBellCount(page);
    expect(after).toBe(before);

    await openNotificationBell(page);
    await expect(page.getByText(/returned by OVPRI/i)).toHaveCount(0);
    await expect(page.getByText(title.toUpperCase()).filter({ hasText: /returned for revision/i })).toHaveCount(0);
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
    await expect(page.locator('#panel-returned').getByText(title.toUpperCase())).toBeVisible();
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

    await Promise.all([
      page.waitForURL(/status=/),
      page.locator('select[name="status"]').selectOption('draft'),
    ]);
    expect(page.url()).toMatch(/status=draft/);
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
    await expect(page.locator('#panel-approved').getByText(title.toUpperCase())).toBeVisible();
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
    await expect(page.locator('#panel-returned').getByText(title.toUpperCase())).toBeVisible();
  });

  test('CDAIC-004: OVPRI sees CDAIC\'s approved records in Approved tab', async ({ page }) => {
    const title = uniqueTitle('CDAIC004 OVPRI Approved');
    const researchId = await setupEndorsedResearch(page, title);
    await approveResearch(page, researchId, 'cdaic');

    await ovpriLogin(page);
    await page.goto('/ovpri/queue?tab=approved');
    await switchQueueTab(page, 'approved');
    await expect(page.locator('#panel-approved').getByText(title.toUpperCase())).toBeVisible();
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
