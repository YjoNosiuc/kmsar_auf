import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, openNotificationBell, expectFlashSuccess } from './helpers/auth';
import {
  fillResearchStep1,
  openPdfPreviewModal,
  registerResearchThroughWizard,
  startResearchRegistration,
  uniqueTitle,
} from './helpers/research';

test.describe('Faculty research registration', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
  });

  test('register new research (wizard steps 1-3)', async ({ page }) => {
    const title = uniqueTitle('Wizard Registration');
    await registerResearchThroughWizard(page, title);

    await page.goto(`${BASE_URL}/research`);
    await expect(page.getByText(title.toUpperCase())).toBeVisible();
  });

  test('M-03: registering duplicate title shows warning', async ({ page }) => {
    const duplicateTitle =
      'AI-Based Crop Disease Detection Using Convolutional Neural Networks';

    await startResearchRegistration(page);
    await page.locator('#field_title').fill(duplicateTitle);
    await page.locator('select[name="research_classification"]').selectOption('self_funded');
    await page.locator('input[name="expected_output[]"][value="publication"]').check();
    const today = new Date().toISOString().split('T')[0];
    const nextYear = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    await page.locator('input[name="start_date"]').fill(today);
    await page.locator('input[name="estimated_completion_date"]').fill(nextYear);
    await page.locator('select[name="status"]').selectOption('proposal');
    await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
    await page.getByRole('button', { name: 'Continue to authors' }).click();

    await expect(page).toHaveURL(/\/details$/);
    await expect(
      page.getByText(/already an existing research with a similar title/i).first(),
    ).toBeVisible();
  });

  test('H-04: submit research for dean review — notification bell shows confirmation', async ({
    page,
  }) => {
    const title = uniqueTitle('Submit Notification');
    await registerResearchThroughWizard(page, title, { submit: true });

    await openNotificationBell(page);
    await expect(page.getByText(/submission confirmed|submitted/i).first()).toBeVisible();
  });

  test('edit draft research — SDG selection saves correctly', async ({ page }) => {
    const title = uniqueTitle('SDG Edit Test');
    const researchId = await registerResearchThroughWizard(page, title);

    await page.goto(`${BASE_URL}/research/${researchId}/edit`);
    await page.getByRole('button', { name: 'SDG 1', exact: true }).click();
    await page.getByRole('button', { name: 'SDG 13', exact: true }).click();
    await page.getByRole('button', { name: /Save changes/i }).click();
    await expectFlashSuccess(page, /updated|saved/i);

    await page.goto(`${BASE_URL}/research/${researchId}/edit`);
    await expect(page.getByRole('button', { name: 'SDG 1', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('button', { name: 'SDG 13', exact: true })).toHaveAttribute('aria-pressed', 'true');
  });

  test('H-01: view research show page — PDF preview opens in modal not new tab', async ({
    page,
    context,
  }) => {
    const title = uniqueTitle('PDF Preview');
    const researchId = await registerResearchThroughWizard(page, title);

    await page.goto(`${BASE_URL}/research/${researchId}`);
    const pagesBefore = context.pages().length;
    await openPdfPreviewModal(page);
    expect(context.pages().length).toBe(pagesBefore);
    await page.locator('#kmsar-preview-modal button').first().click();
    await expect(page.locator('#kmsar-preview-modal')).toBeHidden();
  });

  test('M-02: co-author sees research in their list with Co-author badge', async ({ page }) => {
    const title = uniqueTitle('Co-Author Visibility');
    await registerResearchThroughWizard(page, title, {
      coAuthorEmail: CREDENTIALS.facultyCoAuthor.email,
    });

    await login(page, CREDENTIALS.facultyCoAuthor.email);
    await page.goto(`${BASE_URL}/research`);
    await expect(page.getByText(title.toUpperCase())).toBeVisible();
    await expect(page.getByText('Co-author', { exact: true }).first()).toBeVisible();
  });
});
