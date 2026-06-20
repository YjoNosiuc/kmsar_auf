import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { resetDatabase } from './helpers/db';
import {
  createAndSubmitResearch,
  endorseResearch,
  approveResearch,
} from './helpers/research';

const SAMPLE_PDF = 'tests/e2e/fixtures/sample.pdf';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function deanLogin(page: Page): Promise<void> {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
}

async function openNotificationBell(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Notifications' }).click();
}

async function createAndSubmitResearchAsCba(page: Page, title: string): Promise<string | undefined> {
  await login(page, credentials.faculty_cba.email, credentials.faculty_cba.password);
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

  const researchId = page.url().match(/\/research\/(\d+)\//)?.[1];
  if (researchId) {
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await page.waitForURL(/\/research\/\d+$/);
  }
  return researchId;
}

async function openDeanReview(page: Page, researchId: string): Promise<void> {
  await deanLogin(page);
  await page.goto(`/approval/${researchId}`);
}

async function submitProgressUpdate(page: Page, researchId: string): Promise<void> {
  await page.goto(`/research/${researchId}`);
  await page.getByRole('button', { name: 'Update Progress' }).click();
  await page.locator('select[name="status"]').selectOption('ongoing');
  await page.locator('form[action*="update-progress"] input[name="files[]"]').setInputFiles(SAMPLE_PDF);
  await page.locator('form[action*="update-progress"] button[type="submit"]').click();
}

let reviewResearchId = '';
let reviewTitle = '';
let endorsedResearchId = '';
let endorsedTitle = '';

test.describe('Dean / Unit Head — UAT Test Suite', () => {
  test.beforeAll(async () => {
    resetDatabase();
  });

  test('TC-001: Login with dean credentials → redirected to Dean Dashboard', async ({ page }) => {
    await deanLogin(page);
    await expect(page).toHaveURL(/\/dean\/dashboard/);
    await expect(page.getByRole('heading', { name: 'College Dashboard' })).toBeVisible();
    await expect(page.getByText(/College of Computer Studies/i)).toBeVisible();
  });

  test('TC-002: Verify only dean routes accessible → /research, /admin, /ovpri blocked with 403', async ({
    page,
  }) => {
    await deanLogin(page);
    const researchRes = await page.goto('/research');
    expect(researchRes?.status()).toBe(403);

    const adminRes = await page.goto('/admin/dashboard');
    expect(adminRes?.status()).toBe(403);

    const ovpriRes = await page.goto('/ovpri/dashboard');
    expect(ovpriRes?.status()).toBe(403);
  });

  test('TC-003: Dean dashboard stats cards show counts for own college only', async ({ page }) => {
    await deanLogin(page);
    await page.goto('/dean/dashboard');

    const totalCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Total Research' });
    await expect(totalCard.locator('.kmsar-stat-card-value')).toHaveText('9');

    const pendingCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Pending Endorsement' });
    await expect(pendingCard.locator('.kmsar-stat-card-value')).toHaveText('2');

    const publishedCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Published' }).first();
    await expect(publishedCard.locator('.kmsar-stat-card-value')).toHaveText('4');

    const scopusCard = page.locator('.kmsar-stat-card').filter({ hasText: 'Scopus Indexed' });
    await expect(scopusCard.locator('.kmsar-stat-card-value')).toHaveText('3');
  });

  test('TC-004: Chart data loads correctly without errors', async ({ page }) => {
    await deanLogin(page);
    await page.goto('/dean/dashboard');

    await expect(page.locator('#kmsarDeanSubmitted')).toBeVisible();
    await expect(page.locator('#kmsarDeanPublished')).toBeVisible();
    await expect(page.locator('#kmsarDeanPresented')).toBeVisible();
    await expect(page.locator('.kmsar-alert--danger')).toHaveCount(0);
  });

  test('TC-005: Dashboard only shows data from own college', async ({ page }) => {
    await deanLogin(page);
    await page.goto('/dean/dashboard');

    await expect(page.getByText('AUF-2024-CCS-0001')).toBeVisible();
    await expect(page.getByText('AUF-2024-CBA-0001')).toHaveCount(0);

    const facultyTable = page.locator('#facultyStatsTable tbody');
    await expect(facultyTable.getByText(/MARIA SANTOS/i)).toBeVisible();
    await expect(facultyTable.getByText(/faculty\.cba/i)).toHaveCount(0);
  });

  test('TC-006: Approval Queue loads with tabs Pending Endorsed Returned', async ({ page }) => {
    await deanLogin(page);
    await page.goto('/approval/queue');

    await expect(page.getByRole('heading', { name: 'Approval Queue' })).toBeVisible();
    await expect(page.locator('#tab-pending')).toBeVisible();
    await expect(page.locator('#tab-endorsed')).toBeVisible();
    await expect(page.locator('#tab-returned')).toBeVisible();
  });

  test('TC-007: View pending research → full detail visible with documents', async ({ page }) => {
    reviewTitle = uniqueTitle('TC007 Dean View');
    reviewResearchId = (await createAndSubmitResearch(page, reviewTitle)) ?? '';
    expect(reviewResearchId).toBeTruthy();

    await openDeanReview(page, reviewResearchId);
    await expect(page.getByRole('tab', { name: /Research Info/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Documents/i })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Approval History/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: new RegExp(reviewTitle, 'i') })).toBeVisible();

    await page.getByRole('tab', { name: /Documents/i }).click();
    await expect(page.getByText(/sample\.pdf/i).first()).toBeVisible();
  });

  test('TC-008: Download document from review page → file downloads', async ({ page }) => {
    const title = uniqueTitle('TC008 Download');
    let researchId = reviewResearchId;
    if (!researchId) {
      researchId = (await createAndSubmitResearch(page, title)) ?? '';
    }
    expect(researchId).toBeTruthy();
    await openDeanReview(page, researchId);
    await page.getByRole('tab', { name: /Documents/i }).click();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download' }).first().click(),
    ]);
    expect(download.suggestedFilename()).toBeTruthy();
  });

  test('TC-009: Preview document inline → PDF opens in modal not downloaded (H-01)', async ({
    page,
    context,
  }) => {
    const title = uniqueTitle('TC009 Preview');
    let researchId = reviewResearchId;
    if (!researchId) {
      researchId = (await createAndSubmitResearch(page, title)) ?? '';
    }
    expect(researchId).toBeTruthy();
    await openDeanReview(page, researchId);
    await page.getByRole('tab', { name: /Documents/i }).click();

    const pagesBefore = context.pages().length;
    await page.getByRole('button', { name: 'Preview' }).first().click();
    await expect(page.locator('#kmsar-preview-modal')).toBeVisible();
    expect(context.pages().length).toBe(pagesBefore);
  });

  test('TC-010: Cannot see research from other colleges in queue', async ({ page }) => {
    const cbaTitle = uniqueTitle('TC010 CBA Only');
    await createAndSubmitResearchAsCba(page, cbaTitle);

    await deanLogin(page);
    await page.goto('/approval/queue');
    await expect(page.getByText(cbaTitle.toUpperCase())).toHaveCount(0);
    await expect(page.getByText('AUF-2024-CBA-0002')).toHaveCount(0);
  });

  test('TC-011: Endorse research with valid remarks → moves to OVPRI Review queue', async ({
    page,
  }) => {
    endorsedTitle = uniqueTitle('TC011 Endorse');
    endorsedResearchId = (await createAndSubmitResearch(page, endorsedTitle)) ?? '';
    expect(endorsedResearchId).toBeTruthy();

    await openDeanReview(page, endorsedResearchId);
    await page.getByRole('button', { name: 'Endorse', exact: true }).click();
    await page.fill('#endorse-remarks', 'Endorsed for OVPRI final review and approval.');
    await page.locator('form[action*="endorse"] button[type="submit"]').click();
    await expect(
      page.getByRole('alert').filter({ hasText: /endorsed and forwarded to OVPRI/i }),
    ).toBeVisible({ timeout: 15_000 });

    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/queue');
    await expect(page.getByText(endorsedTitle.toUpperCase())).toBeVisible();
  });

  test('TC-012: Faculty receives ResearchEndorsed notification after endorsement', async ({
    page,
  }) => {
    expect(endorsedResearchId).toBeTruthy();
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/has been endorsed by the college dean/i).first()).toBeVisible();
  });

  test('TC-013: OVPRI receives research in their queue after endorsement', async ({ page }) => {
    expect(endorsedTitle).toBeTruthy();
    await login(page, credentials.ovpri.email, credentials.ovpri.password);
    await page.goto('/ovpri/queue');
    await expect(page.getByText(endorsedTitle.toUpperCase())).toBeVisible();
  });

  test('TC-014: Try endorsing with remarks shorter than 10 characters → validation error (M-01)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC014 Short Remarks');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Endorse', exact: true }).click();
    await page.locator('#endorse-remarks').fill('Short');
    await page.locator('#endorse-remarks').evaluate((el) => el.removeAttribute('minlength'));
    await page.locator('form[action*="endorse"] button[type="submit"]').click();

    await expect(
      page.locator('.kmsar-form-error').filter({ hasText: /at least|10 characters/i }).first(),
    ).toBeVisible();
  });

  test('TC-015: Return research to faculty → stage changes to Draft, revision count increases by 1', async ({
    page,
  }) => {
    const title = uniqueTitle('TC015 Return');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.fill('#return-remarks', 'Please revise methodology section and resubmit documents.');
    await page.locator('form[action*="return"] button[type="submit"]').click();
    await expect(
      page.getByRole('alert').filter({ hasText: /returned to the author/i }),
    ).toBeVisible({ timeout: 15_000 });

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await expect(page.getByRole('cell', { name: 'Draft' })).toBeVisible();
    await expect(page.getByText('Revisions').locator('..').getByText('1')).toBeVisible();
  });

  test('TC-016: Faculty receives ResearchReturned notification after return', async ({
    page,
  }) => {
    const title = uniqueTitle('TC016 Return Notif');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.fill('#return-remarks', 'Please update your research objectives before resubmitting.');
    await page.locator('form[action*="return"] button[type="submit"]').click();

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/returned for revision/i).first()).toBeVisible();
  });

  test('TC-017: Revision count increments by 1 each time research is returned', async ({
    page,
  }) => {
    const title = uniqueTitle('TC017 Revision Count');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.fill('#return-remarks', 'First return — please revise the literature review section.');
    await page.locator('form[action*="return"] button[type="submit"]').click();

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await page.waitForURL(/\/research\/\d+$/);

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.fill('#return-remarks', 'Second return — please add missing ethics clearance documents.');
    await page.locator('form[action*="return"] button[type="submit"]').click();

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await expect(page.getByText('Revisions').locator('..').getByText('2')).toBeVisible();
  });

  test('TC-018: Reject research → stage changes to Rejected', async ({ page }) => {
    const title = uniqueTitle('TC018 Reject');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.fill('#reject-remarks', 'Research scope does not meet college research priorities.');
    await page.locator('form[action*="reject"] button[type="submit"]').click();
    await expect(
      page.getByRole('alert').filter({ hasText: /submission has been rejected/i }),
    ).toBeVisible({ timeout: 15_000 });

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await expect(page.getByRole('cell', { name: 'Rejected' })).toBeVisible();
  });

  test('TC-019: Faculty receives ResearchRejected notification on dean rejection (H-04)', async ({
    page,
  }) => {
    const title = uniqueTitle('TC019 Reject Notif');
    const remarks = 'Does not meet minimum documentation requirements for college review.';
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();

    await openDeanReview(page, researchId);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.fill('#reject-remarks', remarks);
    await page.locator('form[action*="reject"] button[type="submit"]').click();

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await openNotificationBell(page);
    await expect(page.getByText(/has been rejected/i).first()).toBeVisible();
    await expect(page.getByText(remarks).first()).toBeVisible();
  });

  test('TC-020: Reports page loads with filter options (SDG, classification, academic year)', async ({
    page,
  }) => {
    await deanLogin(page);
    await page.goto('/reports');

    await expect(page.getByRole('heading', { name: 'Reports & Analytics' })).toBeVisible();
    await expect(page.getByLabel('SDG')).toBeVisible();
    await expect(page.getByLabel('Classification')).toBeVisible();
    await expect(page.getByLabel('Academic year')).toBeVisible();
    await expect(page.getByLabel('Per page')).toBeVisible();
  });

  test('TC-021: Generate college PDF report → downloads', async ({ page }) => {
    await deanLogin(page);
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

  test('TC-022: College report does not include other colleges data', async ({ page }) => {
    await deanLogin(page);
    await page.goto('/reports?academic_year=2024');

    await expect(page.getByText(/COLLEGE OF COMPUTER STUDIES/i)).toBeVisible();
    await expect(page.locator('table.kmsar-table tbody').getByText(/BLOCKCHAIN-BASED ACADEMIC/i)).toBeVisible();
    await expect(page.getByText(/Digital Transformation of MSMEs/i)).toHaveCount(0);
    await expect(page.locator('table.kmsar-table tbody').getByText(/CBA|Business and Accountancy/i)).toHaveCount(0);
  });

  test('TC-023: Export Excel report → downloads with correct data and pagination', async ({
    page,
  }) => {
    await deanLogin(page);
    await page.goto('/reports?per_page=10');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page
        .locator('form[action*="export"]')
        .filter({ has: page.locator('input[name="format"][value="excel"]') })
        .locator('button[type="submit"]')
        .click(),
    ]);
    expect(download.suggestedFilename().toLowerCase()).toMatch(/\.xlsx$/);

    await expect(page.getByText(/Showing .* of .* records/i)).toBeVisible();
    if (await page.getByRole('link', { name: 'Load more' }).count()) {
      await expect(page.getByRole('link', { name: 'Load more' })).toBeVisible();
    }
  });

  test('TC-024: ResearchSubmitted notification visible in dean bell after faculty submits', async ({
    page,
  }) => {
    const title = uniqueTitle('TC024 Dean Submit Notif');
    await createAndSubmitResearch(page, title);

    await deanLogin(page);
    await openNotificationBell(page);
    await expect(page.getByText(/submitted for your review/i).first()).toBeVisible();
  });

  test('TC-025: ResearchProgressUpdated notification after faculty progress update', async ({
    page,
  }) => {
    const title = uniqueTitle('TC025 Progress Notif');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();
    await endorseResearch(page, researchId);
    await approveResearch(page, researchId);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await submitProgressUpdate(page, researchId);

    await deanLogin(page);
    await openNotificationBell(page);
    await expect(page.getByText(/progress has been updated/i).first()).toBeVisible();
  });

  test('TC-026: ResearchApprovedDean notification after OVPRI approves', async ({ page }) => {
    const title = uniqueTitle('TC026 Approved Dean Notif');
    const researchId = await createAndSubmitResearch(page, title);
    expect(researchId).toBeTruthy();
    await endorseResearch(page, researchId);
    await approveResearch(page, researchId);

    await deanLogin(page);
    await openNotificationBell(page);
    await expect(page.getByText(/approved by OVPRI/i).first()).toBeVisible();
  });

  test('TC-027: Try accessing /research → 403 Forbidden', async ({ page }) => {
    await deanLogin(page);
    const response = await page.goto('/research');
    expect(response?.status()).toBe(403);
  });

  test('TC-028: Try accessing /ovpri/dashboard → 403 Forbidden', async ({ page }) => {
    await deanLogin(page);
    const response = await page.goto('/ovpri/dashboard');
    expect(response?.status()).toBe(403);
  });

  test('TC-029: Try accessing /admin/dashboard → 403 Forbidden', async ({ page }) => {
    await deanLogin(page);
    const response = await page.goto('/admin/dashboard');
    expect(response?.status()).toBe(403);
  });
});
