import { expect, Page } from '@playwright/test';
import { login, credentials } from './auth';
import { runTinker } from './db';

const WIZARD_TIMEOUT = 90_000;

export const ResearchWorkflowStatus = {
  PROPOSAL: 'proposal',
  INITIAL_DEAN_REVIEW: 'initial_dean_review',
  INITIAL_OVPRI_REVIEW: 'initial_ovpri_review',
  INITIAL_REJECTED: 'initial_rejected',
  ONGOING: 'ongoing',
  RESEARCH_COMPLETED: 'research_completed',
  FINAL_DEAN_REVIEW: 'final_dean_review',
  FINAL_OVPRI_REVIEW: 'final_ovpri_review',
  FINAL_REJECTED: 'final_rejected',
  RESEARCH_ACCEPTED: 'research_accepted',
} as const;

/** Select the authenticated KMSAR user as the primary author. */
export async function selectCurrentUserAsPrimary(page: Page): Promise<void> {
  const primaryId = page.locator('input[name="primary_author_user_id"]');
  if (await primaryId.inputValue().catch(() => '')) {
    return;
  }

  await page.getByRole('button', { name: 'This is me', exact: true }).click();
  await expect(primaryId).not.toHaveValue('');
}

/** Submit a draft from the Documents wizard. */
export async function submitResearchFromDocuments(
  page: Page,
  researchId: string,
  registrationType: 'new' | 'existing' = 'new',
): Promise<void> {
  if (!new RegExp(`/research/${researchId}/documents`).test(page.url())) {
    await page.goto(`/research/${researchId}/documents`);
  }

  const label = registrationType === 'existing'
    ? 'Register existing research'
    : 'Submit for initial dean review';

  const submit = page.getByRole('button', { name: label, exact: true });
  await submit.scrollIntoViewIfNeeded();
  await expect(submit).toBeVisible({ timeout: WIZARD_TIMEOUT });
  await expect(submit).toBeEnabled({ timeout: WIZARD_TIMEOUT });
  await submit.click();
  await page.waitForURL(new RegExp(`/research/${researchId}$`), { timeout: WIZARD_TIMEOUT });
}

/** Submit an existing-registration record (skips initial review). */
export async function submitExistingResearchFromDocuments(page: Page, researchId: string): Promise<void> {
  await submitResearchFromDocuments(page, researchId, 'existing');
}

async function fillWizardStep1(page: Page, title: string, registrationType: 'new' | 'existing' = 'new'): Promise<void> {
  await page.fill('textarea[name="title"]', title);
  if (await page.locator('select[name="registration_type"]').count()) {
    await page.selectOption('select[name="registration_type"]', registrationType);
  }
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  const statusSelect = page.locator('select[name="status"]');
  if (await statusSelect.count()) {
    await statusSelect.selectOption('proposal');
  }
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
}

/**
 * Create a research via the faculty wizard and submit.
 * Returns the research id.
 */
export async function createAndSubmitResearch(
  page: Page,
  title: string,
  registrationType: 'new' | 'existing' = 'new',
): Promise<string | undefined> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  const createUrl =
    registrationType === 'existing'
      ? '/research/create?registration_type=existing'
      : '/research/create';
  await page.goto(createUrl);
  await page.waitForURL(/\/research\/\d+\/details/, { timeout: WIZARD_TIMEOUT });

  await fillWizardStep1(page, title, registrationType);

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
  await page.getByRole('button', { name: 'Save Document' }).click();
  await expect(page.getByRole('tabpanel').getByText('Document uploaded successfully')).toBeVisible({
    timeout: WIZARD_TIMEOUT,
  });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Uploaded files' })).toBeVisible({ timeout: WIZARD_TIMEOUT });

  const match = page.url().match(/\/research\/(\d+)\//);
  const researchId = match?.[1];

  if (researchId) {
    if (registrationType === 'existing') {
      await submitExistingResearchFromDocuments(page, researchId);
    } else {
      await submitResearchFromDocuments(page, researchId, 'new');
    }
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
  const returnBtn = page.locator('button.kmsar-btn--warning').filter({ hasText: /^Return$/ }).first();
  await expect(returnBtn).toBeVisible({ timeout: 30_000 });
  await returnBtn.click({ force: true });
  await page.locator('#ovpri-return-remarks').waitFor({ state: 'visible', timeout: 15_000 });
  await page.fill('#ovpri-return-remarks', remarks);
  await page.locator('form[action*="return"] button[type="submit"]').click();
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

/** First visible card matching workflow status badge text. */
export function facultyResearchCardByStatus(page: Page, statusLabel: RegExp) {
  return page
    .locator('.kmsar-research-card')
    .filter({ hasText: statusLabel })
    .filter({ visible: true })
    .first();
}

/** @deprecated Use researchWorkflowStatus() */
export function researchApprovalStage(researchId: string): string {
  return researchWorkflowStatus(researchId);
}

/** Current workflow status from DB (`research.status`). */
export function researchWorkflowStatus(researchId: string): string {
  const out = runTinker(
    `echo \\App\\Models\\Research::query()->whereKey(${researchId})->value('status') ?? 'missing';`,
  );
  return out.trim().split(/\r?\n/).pop()?.trim() ?? 'missing';
}

export function setResearchWorkflowStatus(researchId: string, status: string): void {
  runTinker(
    `\\App\\Models\\Research::find(${researchId})?->update(['status' => '${status}']);`,
  );
}

export function seedOutcomeClassifications(): void {
  runTinker(`(new \\Database\\Seeders\\OutcomeClassificationSeeder())->run();`);
}

export async function submitCompletionWithLink(page: Page, researchId: string, link: string): Promise<void> {
  await submitCompletionViaModal(page, researchId, { externalLink: link });
}

/** New registration through initial review to ongoing. */
export async function driveNewRegistrationToOngoing(page: Page, title: string): Promise<string> {
  const researchId = await createAndSubmitResearch(page, title, 'new');
  if (!researchId) {
    throw new Error('Failed to create research for ongoing setup');
  }

  await endorseResearch(page, researchId);
  await approveResearch(page, researchId);
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.ONGOING);

  return researchId;
}

/** Full new-path cycle through first final acceptance. */
export async function driveNewRegistrationToAccepted(page: Page, title: string): Promise<string> {
  const researchId = await driveNewRegistrationToOngoing(page, title);

  await submitCompletionViaModal(page, researchId, { classificationCode: 'completed_not_presented_submitted' });
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_DEAN_REVIEW);

  await endorseResearch(page, researchId);
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_OVPRI_REVIEW);

  await approveResearch(page, researchId);
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.RESEARCH_ACCEPTED);

  return researchId;
}

/** Submit completion / progress update via the show-page modal. */
export async function submitCompletionViaModal(
  page: Page,
  researchId: string,
  options: {
    classificationCode?: string;
    classificationCodes?: string[];
    externalLink?: string;
    assertPrefilledCodes?: string[];
  } = {},
): Promise<void> {
  const classificationCodes = options.classificationCodes
    ?? (options.classificationCode ? [options.classificationCode] : ['completed_not_presented_submitted']);
  const externalLink = options.externalLink ?? `https://example.com/completion-${Date.now()}`;

  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto(`/research/${researchId}`);

  const submitBtn = page.getByRole('button', { name: /Submit completion|Update outcomes & resubmit/i }).first();
  await expect(submitBtn).toBeVisible({ timeout: WIZARD_TIMEOUT });
  await submitBtn.click();

  await expect(page.locator('[aria-labelledby="kmsar-update-progress-title"]')).toBeVisible({
    timeout: WIZARD_TIMEOUT,
  });

  if (options.assertPrefilledCodes?.length) {
    await assertCompletionModalPrefilledClassifications(page, options.assertPrefilledCodes);
  }

  for (const code of classificationCodes) {
    await page.locator(`input[name="outcome_classifications[]"][value="${code}"]`).check();
  }

  await page.locator('form[action*="update-progress"] button.kmsar-tab').filter({ hasText: /Add Link/i }).click();
  await page.locator('form[action*="update-progress"] input[name="external_links[]"]').first().fill(externalLink);
  await page.locator('form[action*="update-progress"] button[type="submit"]').click();
  await page.waitForURL(new RegExp(`/research/${researchId}$`), { timeout: WIZARD_TIMEOUT });
}

/** Final-rejected resubmit via the same modal (update-progress → resubmitFinal). */
export async function resubmitFinalViaModal(
  page: Page,
  researchId: string,
  options: {
    classificationCode?: string;
    classificationCodes?: string[];
    externalLink?: string;
    assertPrefilledCodes?: string[];
  } = {},
): Promise<void> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto(`/research/${researchId}`);

  await page.getByRole('button', { name: 'Update outcomes & resubmit', exact: true }).click();
  await expect(page.locator('[aria-labelledby="kmsar-update-progress-title"]')).toBeVisible({
    timeout: WIZARD_TIMEOUT,
  });

  if (options.assertPrefilledCodes?.length) {
    await assertCompletionModalPrefilledClassifications(page, options.assertPrefilledCodes);
  }

  const classificationCodes = options.classificationCodes
    ?? (options.classificationCode ? [options.classificationCode] : ['completed_not_presented_submitted']);

  for (const code of classificationCodes) {
    await page.locator(`input[name="outcome_classifications[]"][value="${code}"]`).check();
  }

  const externalLink = options.externalLink ?? `https://example.com/final-resubmit-${Date.now()}`;
  await page.locator('form[action*="update-progress"] button.kmsar-tab').filter({ hasText: /Add Link/i }).click();
  await page.locator('form[action*="update-progress"] input[name="external_links[]"]').first().fill(externalLink);
  await page.getByRole('button', { name: 'Resubmit for final review', exact: true }).click();
  await page.waitForURL(new RegExp(`/research/${researchId}$`), { timeout: WIZARD_TIMEOUT });
}

export async function assertCompletionModalPrefilledClassifications(
  page: Page,
  codes: string[],
): Promise<void> {
  for (const code of codes) {
    await expect(page.locator(`input[name="outcome_classifications[]"][value="${code}"]`)).toBeChecked();
  }
}

export function researchHasAuditTransitionToStatus(researchId: string, status: string): boolean {
  const out = runTinker(
    `$exists=\\App\\Models\\AuditLog::query()->where('auditable_type', \\App\\Models\\Research::class)->where('auditable_id', ${researchId})->where('new_values->status', '${status}')->exists(); echo $exists ? 'yes' : 'no';`,
  );

  return out.trim().split(/\r?\n/).pop()?.trim() === 'yes';
}

export async function returnResearchDean(page: Page, researchId: string, remarks: string): Promise<void> {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
  await page.goto(`/approval/${researchId}`);
  await page.getByRole('button', { name: 'Return', exact: true }).click();
  await page.fill('#return-remarks', remarks);
  await page.locator('form[action*="return"] button[type="submit"]').click();
}

export async function rejectResearchDean(page: Page, researchId: string, remarks: string): Promise<void> {
  await returnResearchDean(page, researchId, remarks);
}

/** Registration wizard is blocked; show page has no Edit Details. */
export async function assertRegistrationFieldsLocked(page: Page, researchId: string): Promise<void> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);

  const detailsResponse = await page.goto(`/research/${researchId}/details`);
  expect(detailsResponse?.status()).toBe(403);

  await page.goto(`/research/${researchId}`);
  await expect(page.getByRole('button', { name: 'Edit Details' })).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Continue to documents' })).toHaveCount(0);
}

/** Outcome modal exposes editable classification + document fields. */
export async function assertOutcomeFieldsEditable(page: Page, researchId: string): Promise<void> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto(`/research/${researchId}`);

  const openBtn = page.getByRole('button', { name: /Update outcomes & resubmit|Submit completion/i }).first();
  await expect(openBtn).toBeVisible({ timeout: WIZARD_TIMEOUT });
  await openBtn.click();

  await expect(page.locator('input[name="outcome_classifications[]"]').first()).toBeEnabled();
  await page.locator('form[action*="update-progress"] button.kmsar-tab').filter({ hasText: /Add Link/i }).click();
  await expect(page.locator('form[action*="update-progress"] input[name="external_links[]"]').first()).toBeVisible();
}

export function researchFinalReviewCount(researchId: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Research::query()->whereKey(${researchId})->value('final_review_count') ?? 0;`,
  );
  return parseInt(out.trim().split(/\r?\n/).pop()?.trim() ?? '0', 10);
}

export function researchOutcomeClassificationCodes(researchId: string): string[] {
  const out = runTinker(
    `echo json_encode(\\App\\Models\\Research::find(${researchId})?->outcomeClassifications()->pluck('code')->values()->all() ?? []);`,
  );
  const line = out.trim().split(/\r?\n/).pop()?.trim() ?? '[]';

  return JSON.parse(line) as string[];
}

export function syncOutcomeClassificationsForResearch(researchId: string, codes: string[]): void {
  const json = JSON.stringify(codes);
  runTinker(
    `$r=\\App\\Models\\Research::find(${researchId}); $ids=\\App\\Models\\OutcomeClassification::query()->whereIn('code', json_decode('${json}', true))->pluck('id'); $r->outcomeClassifications()->sync($ids);`,
  );
}

export function countFinalCycleApprovals(
  researchId: string,
  action: 'returned' | 'rejected',
  stage?: 'dean' | 'ovpri',
): number {
  const stageClause = stage ? `->where('stage', '${stage}')` : '';
  const out = runTinker(
    `echo \\App\\Models\\Approval::query()->where('research_id', ${researchId})->where('review_cycle', 'final')${stageClause}->whereIn('action', ['${action}'])->count();`,
  );

  return parseInt(out.trim().split(/\r?\n/).pop()?.trim() ?? '0', 10);
}

export function latestFinalReviewIteration(researchId: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Approval::query()->where('research_id', ${researchId})->where('review_cycle', 'final')->whereNotNull('final_review_iteration')->max('final_review_iteration') ?? 0;`,
  );

  return parseInt(out.trim().split(/\r?\n/).pop()?.trim() ?? '0', 10);
}

export function assertSingleResearchRecord(researchId: string): void {
  const out = runTinker(`echo \\App\\Models\\Research::query()->whereKey(${researchId})->count();`);
  expect(parseInt(out.trim().split(/\r?\n/).pop()?.trim() ?? '0', 10)).toBe(1);
}
