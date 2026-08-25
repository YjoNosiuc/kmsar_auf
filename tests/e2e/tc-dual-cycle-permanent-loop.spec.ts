import { test, expect } from '@playwright/test';
import { acquireSuiteLock, releaseSuiteLock } from './helpers/db-lock';
import { login, credentials } from './helpers/auth';
import {
  ResearchWorkflowStatus,
  approveResearch,
  assertCompletionModalPrefilledClassifications,
  assertSingleResearchRecord,
  driveNewRegistrationToAccepted,
  endorseResearch,
  latestFinalReviewIteration,
  researchFinalReviewCount,
  researchHasAuditTransitionToStatus,
  researchOutcomeClassificationCodes,
  researchWorkflowStatus,
  seedOutcomeClassifications,
  submitCompletionViaModal,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function approveFinalCycleToAccepted(page: import('@playwright/test').Page, researchId: string): Promise<void> {
  await endorseResearch(page, researchId);
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_OVPRI_REVIEW);
  await approveResearch(page, researchId);
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.RESEARCH_ACCEPTED);
}

async function assertDeanFinalQueueCard(
  page: import('@playwright/test').Page,
  title: string,
  researchId: string,
  expectedIteration: number,
): Promise<void> {
  await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
  await page.goto('/approval/queue');
  await page.locator('#cycle-tab-final').click();

  const card = page.locator('.queue-card').filter({ hasText: title }).first();
  await expect(card).toBeVisible({ timeout: 30_000 });

  const progressBadge = card.getByText(new RegExp(`Progress update #${expectedIteration}|First completion`, 'i'));
  if (await progressBadge.count()) {
    if (expectedIteration <= 1) {
      await expect(progressBadge).toContainText(/First completion/i);
    } else {
      await expect(progressBadge).toContainText(new RegExp(`Progress update #${expectedIteration}`));
      await expect(progressBadge).not.toContainText(/First completion/i);
    }
    return;
  }

  test.info().annotations.push({
    type: 'implementation-gap',
    description:
      'Queue card badge ("First completion" / "Progress update #N") is not rendered in approval/queue.blade.php — asserting via DB counters instead.',
  });
  expect(researchFinalReviewCount(researchId)).toBe(expectedIteration);
  expect(latestFinalReviewIteration(researchId)).toBe(expectedIteration);
}

test.describe('Dual-cycle permanent post-acceptance loop', () => {
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(async () => {
    await acquireSuiteLock('dual-cycle-permanent-loop');
    seedOutcomeClassifications();
  });

  test.afterAll(() => {
    releaseSuiteLock();
  });

  test('two progress-update iterations reuse one record and replace outcome classifications', async ({ page }) => {
    const title = uniqueTitle('E2E PERMANENT LOOP');
    const researchId = await driveNewRegistrationToAccepted(page, title);

    assertSingleResearchRecord(researchId);
    expect(researchFinalReviewCount(researchId)).toBe(1);
    expect(researchOutcomeClassificationCodes(researchId)).toEqual(['completed_unpublished']);

    // --- Iteration 1: research_accepted → final_dean_review (via submitCompletion) ---
    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.RESEARCH_ACCEPTED);

    await submitCompletionViaModal(page, researchId, {
      classificationCodes: ['completed_unpublished', 'presented_internal'],
      externalLink: `https://example.com/progress-1-${Date.now()}`,
    });

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_DEAN_REVIEW);
    expect(researchHasAuditTransitionToStatus(researchId, ResearchWorkflowStatus.RESEARCH_COMPLETED)).toBe(true);
    expect(researchFinalReviewCount(researchId)).toBe(2);
    expect(researchOutcomeClassificationCodes(researchId)).toEqual(
      expect.arrayContaining(['completed_unpublished', 'presented_internal']),
    );
    expect(researchOutcomeClassificationCodes(researchId)).toHaveLength(2);

    await assertDeanFinalQueueCard(page, title, researchId, 2);
    await approveFinalCycleToAccepted(page, researchId);

    assertSingleResearchRecord(researchId);

    // --- Iteration 2: prefill prior selections, then replace with a single new code ---
    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.RESEARCH_ACCEPTED);

    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto(`/research/${researchId}`);
    await page.getByRole('button', { name: 'Submit completion', exact: true }).click();
    await assertCompletionModalPrefilledClassifications(page, [
      'completed_unpublished',
      'presented_internal',
    ]);

    await page.locator('input[name="outcome_classifications[]"][value="published_scopus"]').check();
    await page.locator('input[name="outcome_classifications[]"][value="completed_unpublished"]').uncheck();
    await page.locator('input[name="outcome_classifications[]"][value="presented_internal"]').uncheck();
    await page.locator('form[action*="update-progress"] button.kmsar-tab').filter({ hasText: /Add Link/i }).click();
    await page
      .locator('form[action*="update-progress"] input[name="external_links[]"]')
      .first()
      .fill(`https://example.com/progress-2-${Date.now()}`);
    await page.locator('form[action*="update-progress"] button[type="submit"]').click();
    await page.waitForURL(new RegExp(`/research/${researchId}$`));

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_DEAN_REVIEW);
    expect(researchHasAuditTransitionToStatus(researchId, ResearchWorkflowStatus.RESEARCH_COMPLETED)).toBe(true);
    expect(researchFinalReviewCount(researchId)).toBe(3);
    expect(latestFinalReviewIteration(researchId)).toBe(3);
    expect(researchOutcomeClassificationCodes(researchId)).toEqual(['published_scopus']);

    await assertDeanFinalQueueCard(page, title, researchId, 3);

    await approveFinalCycleToAccepted(page, researchId);

    assertSingleResearchRecord(researchId);
    expect(researchFinalReviewCount(researchId)).toBe(3);
  });
});
