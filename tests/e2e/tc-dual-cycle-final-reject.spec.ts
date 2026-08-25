import { test, expect } from '@playwright/test';
import { acquireSuiteLock, releaseSuiteLock } from './helpers/db-lock';
import {
  ResearchWorkflowStatus,
  approveResearch,
  assertOutcomeFieldsEditable,
  assertRegistrationFieldsLocked,
  assertSingleResearchRecord,
  countFinalCycleApprovals,
  driveNewRegistrationToOngoing,
  endorseResearch,
  rejectResearchDean,
  rejectResearchOvpri,
  researchFinalReviewCount,
  researchHasAuditTransitionToStatus,
  researchOutcomeClassificationCodes,
  researchWorkflowStatus,
  resubmitFinalViaModal,
  returnResearchDean,
  returnResearchOvpri,
  seedOutcomeClassifications,
  submitCompletionViaModal,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function driveToFinalDeanReview(page: import('@playwright/test').Page, title: string): Promise<string> {
  const researchId = await driveNewRegistrationToOngoing(page, title);

  await submitCompletionViaModal(page, researchId, { classificationCode: 'completed_unpublished' });
  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_DEAN_REVIEW);
  expect(researchHasAuditTransitionToStatus(researchId, ResearchWorkflowStatus.RESEARCH_COMPLETED)).toBe(true);
  expect(researchFinalReviewCount(researchId)).toBe(1);

  return researchId;
}

async function facultyReviseOutcomesAndResubmit(
  page: import('@playwright/test').Page,
  researchId: string,
  nextClassificationCode: string,
): Promise<void> {
  await assertOutcomeFieldsEditable(page, researchId);

  await resubmitFinalViaModal(page, researchId, {
    classificationCode: nextClassificationCode,
    externalLink: `https://example.com/resubmit-${Date.now()}`,
  });

  expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_DEAN_REVIEW);
  expect(researchHasAuditTransitionToStatus(researchId, ResearchWorkflowStatus.RESEARCH_COMPLETED)).toBe(true);
  expect(researchOutcomeClassificationCodes(researchId)).toEqual([nextClassificationCode]);
}

test.describe('Dual-cycle final-review rejection', () => {
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async () => {
    await acquireSuiteLock('dual-cycle-final-reject');
    seedOutcomeClassifications();
  });

  test.afterAll(() => {
    releaseSuiteLock();
  });

  test('dean return at final_dean_review → faculty locked registration, editable outcomes, resubmit', async ({
    page,
  }) => {
    const title = uniqueTitle('E2E FINAL REJECT DEAN');
    const researchId = await driveToFinalDeanReview(page, title);
    const countBeforeReject = researchFinalReviewCount(researchId);

    await returnResearchDean(
      page,
      researchId,
      'Please revise outcome classifications and supporting documents before final endorsement.',
    );

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_REJECTED);
    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    expect(countFinalCycleApprovals(researchId, 'returned', 'dean')).toBe(1);

    await assertRegistrationFieldsLocked(page, researchId);
    await facultyReviseOutcomesAndResubmit(page, researchId, 'presented_internal');

    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    assertSingleResearchRecord(researchId);
  });

  test('ovpri return at final_ovpri_review → same rejection semantics and faculty resubmit loop', async ({
    page,
  }) => {
    const title = uniqueTitle('E2E FINAL REJECT OVPRI');
    const researchId = await driveToFinalDeanReview(page, title);
    const countBeforeReject = researchFinalReviewCount(researchId);

    await endorseResearch(page, researchId);
    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_OVPRI_REVIEW);

    await returnResearchOvpri(
      page,
      researchId,
      'Final outcome package needs clearer classification evidence before university approval.',
    );

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_REJECTED);
    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    expect(countFinalCycleApprovals(researchId, 'returned', 'ovpri')).toBe(1);

    await assertRegistrationFieldsLocked(page, researchId);
    await facultyReviseOutcomesAndResubmit(page, researchId, 'published_non_indexed');

    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    assertSingleResearchRecord(researchId);
  });

  test('dean reject at final_dean_review records final-cycle approval without incrementing count', async ({
    page,
  }) => {
    const title = uniqueTitle('E2E FINAL REJECT DEAN HARD');
    const researchId = await driveToFinalDeanReview(page, title);
    const countBeforeReject = researchFinalReviewCount(researchId);

    await rejectResearchDean(page, researchId, 'Final submission does not meet college research standards.');

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_REJECTED);
    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    expect(countFinalCycleApprovals(researchId, 'rejected', 'dean')).toBe(1);
  });

  test('ovpri reject at final_ovpri_review records final-cycle approval without incrementing count', async ({
    page,
  }) => {
    const title = uniqueTitle('E2E FINAL REJECT OVPRI HARD');
    const researchId = await driveToFinalDeanReview(page, title);

    await endorseResearch(page, researchId);
    const countBeforeReject = researchFinalReviewCount(researchId);

    await rejectResearchOvpri(page, researchId, 'University-level final rejection due to incomplete documentation.');

    expect(researchWorkflowStatus(researchId)).toBe(ResearchWorkflowStatus.FINAL_REJECTED);
    expect(researchFinalReviewCount(researchId)).toBe(countBeforeReject);
    expect(countFinalCycleApprovals(researchId, 'rejected', 'ovpri')).toBe(1);
  });
});
