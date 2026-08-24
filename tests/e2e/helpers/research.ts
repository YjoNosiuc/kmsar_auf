import { expect, Page } from '@playwright/test';
import { login, credentials } from './auth';
import { runTinker } from './db';

const WIZARD_TIMEOUT = 90_000;

/** Select the authenticated KMSAR user as the primary author. */
export async function selectCurrentUserAsPrimary(page: Page): Promise<void> {
  const primaryId = page.locator('input[name="primary_author_user_id"]');
  if (await primaryId.inputValue().catch(() => '')) {
    return;
  }

  await page.getByRole('button', { name: 'This is me', exact: true }).click();
  await expect(primaryId).not.toHaveValue('');
}

/** Submit a draft from the Documents wizard, where the submit action now lives. */
export async function submitResearchFromDocuments(page: Page, researchId: string): Promise<void> {
  if (!new RegExp(`/research/${researchId}/documents`).test(page.url())) {
    await page.goto(`/research/${researchId}/documents`);
  }

  const submit = page.getByRole('button', { name: 'Submit for Dean Review', exact: true });
  await expect(submit).toBeVisible({ timeout: WIZARD_TIMEOUT });
  await expect(submit).toBeEnabled();
  await submit.click();
  await page.waitForURL(new RegExp(`/research/${researchId}$`), { timeout: WIZARD_TIMEOUT });
}

/**
 * Create a research via the faculty wizard and submit for dean review.
 * Returns the research id.
 */
export async function createAndSubmitResearch(page: Page, title: string): Promise<string | undefined> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto('/research/create');
  await page.waitForURL(/\/research\/\d+\/details/, { timeout: WIZARD_TIMEOUT });

  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();

  await Promise.all([
    page.waitForURL(/\/authors/, { timeout: WIZARD_TIMEOUT }),
    page.getByRole('button', { name: 'Continue to authors' }).click(),
  ]);

  await selectCurrentUserAsPrimary(page);

  await Promise.all([
    page.waitForURL(/\/documents/, { timeout: WIZARD_TIMEOUT }),
    page.getByRole('button', { name: 'Continue to documents' }).click(),
  ]);

  await page.locator('#kmsar-document-file-input').setInputFiles('tests/e2e/fixtures/sample.pdf');
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByRole('button', { name: 'Save Document' }).click(),
  ]);

  const match = page.url().match(/\/research\/(\d+)\//);
  const researchId = match?.[1];

  if (researchId) {
    await submitResearchFromDocuments(page, researchId);
  }

  return researchId;
}

export async function endorseResearch(page: Page, researchId: string) {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
  await page.goto(`/approval/${researchId}`);
  await page.getByRole('button', { name: 'Endorse', exact: true }).click();
  await page.fill('#endorse-remarks', 'Research is well documented and ready for OVPRI review.');
  await page.locator('form[action*="endorse"] button[type="submit"]').click();
}

export async function approveResearch(
  page: Page,
  researchId: string,
  actor: 'ovpri' | 'cdaic' = 'ovpri',
) {
  const cred = actor === 'cdaic' ? credentials.cdaic : credentials.ovpri;
  await login(page, cred.email, cred.password);
  await page.goto(`/ovpri/review/${researchId}`);
  await page.getByRole('button', { name: 'Approve', exact: true }).click();
  await page.fill('#approve-remarks', 'Approved by OVPRI for institutional research records.');
  await page.locator('form[action*="approve"] button[type="submit"]').click();
}

export async function returnResearchOvpri(
  page: Page,
  researchId: string,
  remarks: string,
  actor: 'ovpri' | 'cdaic' = 'ovpri',
) {
  const cred = actor === 'cdaic' ? credentials.cdaic : credentials.ovpri;
  await login(page, cred.email, cred.password);
  await page.goto(`/ovpri/review/${researchId}`);
  await page.waitForLoadState('domcontentloaded');
  const returnBtn = page.locator('button.kmsar-btn--warning').filter({ hasText: /^Return$/ }).first();
  await expect(returnBtn).toBeVisible({ timeout: 30_000 });
  await returnBtn.click({ force: true });
  await page.locator('#ovpri-return-remarks').waitFor({ state: 'visible', timeout: 15_000 });
  await page.fill('#ovpri-return-remarks', remarks);
  await page.locator('form[action*="return"] button[type="submit"]').click();
  // OVPRI return → returned_to_faculty (faculty revises; dean sees Returned tab only).
  await expect(page.getByText(/returned to the faculty/i).first()).toBeVisible({ timeout: 15_000 });
}

export async function rejectResearchOvpri(
  page: Page,
  researchId: string,
  remarks: string,
  actor: 'ovpri' | 'cdaic' = 'ovpri',
) {
  const cred = actor === 'cdaic' ? credentials.cdaic : credentials.ovpri;
  await login(page, cred.email, cred.password);
  await page.goto(`/ovpri/review/${researchId}`);
  await page.getByRole('button', { name: 'Reject', exact: true }).click();
  await page.fill('#ovpri-reject-remarks', remarks);
  await page.locator('form[action*="reject"] button[type="submit"]').click();
}

export async function setupEndorsedResearch(page: Page, title: string): Promise<string> {
  const researchId = await createAndSubmitResearch(page, title);
  if (!researchId) {
    throw new Error('Failed to create research for endorsement');
  }
  await endorseResearch(page, researchId);
  return researchId;
}

/** Open My Research, applying an optional title/reference search on the server. */
export async function openFacultyResearchList(page: Page, searchTitle?: string): Promise<void> {
  const url = searchTitle
    ? `/research?search=${encodeURIComponent(searchTitle)}`
    : '/research';
  await page.goto(url);
  await page.waitForLoadState('domcontentloaded');
}

/** Visible research card on My Research. */
export function facultyResearchCard(page: Page, title: string) {
  return page.locator('.kmsar-research-card').filter({ hasText: title }).filter({ visible: true });
}

/** First visible card matching approval-stage badge text (e.g. Dean Review). */
export function facultyResearchCardByStage(page: Page, stageLabel: RegExp) {
  return page
    .locator('.kmsar-research-card')
    .filter({ hasText: stageLabel })
    .filter({ visible: true })
    .first();
}

/** Current approval_stage from DB (used after OVPRI return → returned_to_faculty). */
export function researchApprovalStage(researchId: string): string {
  const out = runTinker(
    `echo \\App\\Models\\Research::find(${researchId})?->approval_stage ?? 'missing';`,
  );
  return out.trim().split(/\r?\n/).pop()?.trim() ?? 'missing';
}
