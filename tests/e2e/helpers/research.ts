import { expect, Page } from '@playwright/test';
import { login, credentials } from './auth';

const WIZARD_TIMEOUT = 90_000;

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

  // Ensure "I am the primary author" is checked (default path — no Employee/Student form)
  const primaryCheckbox = page.locator('.authors-primary-author-toggle input[type="checkbox"]');
  if (await primaryCheckbox.count()) {
    await primaryCheckbox.check();
  }

  // Employee tab exists as "Employee" (renamed from "Employee / Researcher") — only needed if unchecked
  const employeeTab = page.getByRole('tab', { name: /^Employee$/i });
  if (await employeeTab.isVisible().catch(() => false)) {
    // Primary author form is open; keep checkbox path instead
    await primaryCheckbox.check();
  }

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
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await page.waitForURL(/\/research\/\d+$/, { timeout: WIZARD_TIMEOUT });
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

/** Clear My Research search/filters and optionally search by title. */
export async function openFacultyResearchList(page: Page, searchTitle?: string): Promise<void> {
  await page.goto('/research');
  await page.waitForLoadState('domcontentloaded');

  const search = page.locator('#faculty-research-search');
  const stage = page.locator('#faculty-research-stage');
  const status = page.locator('#faculty-research-status');

  if (await search.isVisible().catch(() => false)) {
    await search.fill('');
  }
  if (await stage.isVisible().catch(() => false)) {
    await stage.selectOption('');
  }
  if (await status.isVisible().catch(() => false)) {
    await status.selectOption('');
  }
  if (searchTitle && (await search.isVisible().catch(() => false))) {
    await search.fill(searchTitle);
  }
}

/** Research card on My Research list (works with Alpine search/filter UI). */
export function facultyResearchCard(page: Page, title: string) {
  // border-left style uniquely marks each submission card (avoid ancestor divs)
  return page.locator('div[style*="border-left"]').filter({ hasText: title }).first();
}

/** First card matching approval-stage badge text (e.g. Dean Review). */
export function facultyResearchCardByStage(page: Page, stageLabel: RegExp) {
  return page.locator('div[style*="border-left"]').filter({ hasText: stageLabel }).first();
}
