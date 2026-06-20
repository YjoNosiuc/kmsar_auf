import { Page } from '@playwright/test';
import { login, credentials } from './auth';

export async function createAndSubmitResearch(page: Page, title: string): Promise<string | undefined> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
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

  await page.locator('#kmsar-document-file-input').setInputFiles('tests/e2e/fixtures/sample.pdf');
  await page.getByRole('button', { name: 'Save Document' }).click();

  const match = page.url().match(/\/research\/(\d+)\//);
  const researchId = match?.[1];

  if (researchId) {
    await page.goto(`/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await page.waitForURL(/\/research\/\d+$/);
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
  await page.getByRole('button', { name: 'Return', exact: true }).click();
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
