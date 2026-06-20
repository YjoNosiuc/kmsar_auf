import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, openNotificationBell, expectFlashSuccess } from './helpers/auth';
import {
  openDeanQueueResearch,
  openPdfPreviewModal,
  openResearchByTitle,
  registerResearchThroughWizard,
  uniqueTitle,
} from './helpers/research';

test.describe('College dean approval workflow', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.dean.email);
  });

  test('dean dashboard loads with correct college stats', async ({ page }) => {
    await page.goto(`${BASE_URL}/dean/dashboard`);
    await expect(page.locator('h1.kmsar-h1', { hasText: 'College Dashboard' })).toBeVisible();
    await expect(page.getByText(/College of Computer Studies/i)).toBeVisible();
    await expect(page.getByText('Total Research', { exact: true })).toBeVisible();
    await expect(page.getByText('Pending Endorsement')).toBeVisible();
  });

  test('approval queue shows pending research', async ({ page }) => {
    await page.goto(`${BASE_URL}/approval/queue`);
    await expect(page.locator('h1.kmsar-h1', { hasText: 'Approval Queue' })).toBeVisible();
    await expect(page.getByText(/Pending Endorsement/i)).toBeVisible();
  });

  test('H-01: PDF preview opens in modal on dean review page', async ({ page, context }) => {
    await page.goto(`${BASE_URL}/approval/queue`);
    const reviewLink = page.locator('a.queue-card-action-primary').first();
    if (!(await reviewLink.count())) {
      test.skip(true, 'No pending research in dean queue for PDF preview');
    }
    await reviewLink.click();

    const preview = page.getByRole('button', { name: 'Preview' }).first();
    if (!(await preview.isVisible())) {
      test.skip(true, 'No previewable documents on review page');
    }

    const pagesBefore = context.pages().length;
    await openPdfPreviewModal(page);
    expect(context.pages().length).toBe(pagesBefore);
  });

  test('M-01: endorse with less than 10 char remarks shows validation error', async ({ page }) => {
    const title = uniqueTitle('Dean Endorse Validation');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Endorse', exact: true }).click();
    await page.locator('#endorse-remarks').fill('Short');
    await page.locator('#endorse-remarks').evaluate((el) => el.removeAttribute('minlength'));
    await page.locator('form[action*="endorse"] button[type="submit"]').click();

    await expect(page.locator('.kmsar-form-error').filter({ hasText: /at least|10 characters/i }).first()).toBeVisible();
  });

  test('endorse with valid remarks moves research to OVPRI queue', async ({ page }) => {
    const title = uniqueTitle('Dean Endorse Success');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Endorse', exact: true }).click();
    await page.locator('#endorse-remarks').fill('Endorsed for OVPRI final review and approval.');
    await page.locator('form[action*="endorse"] button[type="submit"]').click();
    await expectFlashSuccess(page, /endorsed and forwarded to OVPRI/i);

    await login(page, CREDENTIALS.ovpri.email);
    await page.goto(`${BASE_URL}/ovpri/queue`);
    await expect(page.getByText(title.toUpperCase())).toBeVisible();
  });

  test('return research — faculty gets notification', async ({ page }) => {
    const title = uniqueTitle('Dean Return Flow');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.locator('#return-remarks').fill('Please revise methodology section and resubmit documents.');
    await page.locator('form[action*="return"] button[type="submit"]').click();
    await expectFlashSuccess(page, /returned to the author/i);

    await login(page, CREDENTIALS.faculty.email);
    await openNotificationBell(page);
    await expect(page.getByText(/returned|revision/i).first()).toBeVisible();
  });

  test('H-04: reject research — faculty gets rejection notification', async ({ page }) => {
    const title = uniqueTitle('Dean Reject Flow');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.locator('#reject-remarks').fill('Research scope does not meet college research priorities.');
    await page.locator('form[action*="reject"] button[type="submit"]').click();
    await expectFlashSuccess(page, /submission has been rejected/i);

    await login(page, CREDENTIALS.faculty.email);
    await openNotificationBell(page);
    await expect(page.getByText(/rejected/i).first()).toBeVisible();
  });

  test('H-03: rejected research — faculty can click Revise', async ({ page }) => {
    const title = uniqueTitle('Faculty Revise Button');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.locator('#reject-remarks').fill('Does not meet minimum documentation requirements for review.');
    await page.locator('form[action*="reject"] button[type="submit"]').click();

    await login(page, CREDENTIALS.faculty.email);
    await openResearchByTitle(page, title);
    await page.getByRole('button', { name: 'Revise', exact: true }).click();
    await expect(page.getByText(/returned to draft/i).first()).toBeVisible({ timeout: 15_000 });
  });

  test('after revise and resubmit appears in Pending tab not Rejected tab', async ({ page }) => {
    const title = uniqueTitle('Revise Resubmit Pending');
    await login(page, CREDENTIALS.faculty.email);
    const researchId = await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.locator('#reject-remarks').fill('Please address ethics clearance before resubmission.');
    await page.locator('form[action*="reject"] button[type="submit"]').click();

    await login(page, CREDENTIALS.faculty.email);
    await openResearchByTitle(page, title);
    await page.getByRole('button', { name: 'Revise', exact: true }).click();
    await expect(page.getByText(/returned to draft/i).first()).toBeVisible({ timeout: 15_000 });

    await page.goto(`${BASE_URL}/research/${researchId}`);
    await page.locator('.kmsar-page-header-actions form[action*="submit"] button[type="submit"]').click();
    await expectFlashSuccess(page, /submitted for dean review/i);

    await login(page, CREDENTIALS.dean.email);
    await page.goto(`${BASE_URL}/approval/queue`);
    await page.getByRole('tab', { name: /Pending Endorsement/i }).click();
    await expect(page.getByText(title.toUpperCase())).toBeVisible();

    await page.getByRole('tab', { name: /Returned \/ Rejected/i }).click();
    await expect(page.getByText(title.toUpperCase())).not.toBeVisible();
  });
});
