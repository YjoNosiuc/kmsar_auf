import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, openNotificationBell, expectFlashSuccess } from './helpers/auth';
import {
  openDeanQueueResearch,
  openOvpriQueueResearch,
  openPdfPreviewModal,
  registerResearchThroughWizard,
  uniqueTitle,
} from './helpers/research';

async function endorseToOvpri(page: import('@playwright/test').Page, title: string): Promise<void> {
  await login(page, CREDENTIALS.faculty.email);
  await registerResearchThroughWizard(page, title, { submit: true });

  await login(page, CREDENTIALS.dean.email);
  await openDeanQueueResearch(page, title);
  await page.getByRole('button', { name: 'Endorse', exact: true }).click();
  await page.locator('#endorse-remarks').fill('Endorsed by college dean for university-level review.');
  await page.locator('form[action*="endorse"] button[type="submit"]').click();
}

test.describe('OVPRI / CDAIC approval workflow', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDENTIALS.ovpri.email);
  });

  test('OVPRI dashboard loads with university-wide stats', async ({ page }) => {
    await page.goto(`${BASE_URL}/ovpri/dashboard`);
    await expect(page.getByRole('heading', { name: /University dashboard/i })).toBeVisible();
    await expect(page.getByText('Total research')).toBeVisible();
    await expect(page.getByText('Pending approval')).toBeVisible();
    await expect(page.getByText('Published')).toBeVisible();
  });

  test('H-05: All Research page shows records from all colleges with filters', async ({ page }) => {
    await page.goto(`${BASE_URL}/ovpri/research`);
    await expect(page.getByRole('heading', { name: /All research/i })).toBeVisible();
    await expect(page.getByText('Filter research')).toBeVisible();
    await expect(page.locator('select[name="college"]')).toBeVisible();
    await expect(page.locator('select[name="stage"]')).toBeVisible();
    await expect(page.locator('table tbody tr').first()).toBeVisible();
  });

  test('OVPRI queue sorted by newest first', async ({ page }) => {
    await page.goto(`${BASE_URL}/ovpri/queue`);
    await expect(page.locator('#panel-pending').getByText(/Sorted by submission date — newest first/i)).toBeVisible();
  });

  test('college filter on queue works', async ({ page }) => {
    await page.goto(`${BASE_URL}/ovpri/queue`);
    await Promise.all([
      page.waitForURL(/college_id=\d+/),
      page.locator('#college_id').selectOption({ index: 1 }),
    ]);
  });

  test('H-01: PDF preview opens in modal on OVPRI review page', async ({ page, context }) => {
    await page.goto(`${BASE_URL}/ovpri/queue`);
    const viewLink = page.locator('#panel-pending a[href*="/ovpri/review/"]').first();
    if (!(await viewLink.count())) {
      test.skip(true, 'No items in OVPRI queue for preview test');
    }
    await viewLink.click();

    const preview = page.getByRole('button', { name: 'Preview' }).first();
    if (!(await preview.isVisible())) {
      test.skip(true, 'No previewable documents');
    }

    const pagesBefore = context.pages().length;
    await openPdfPreviewModal(page);
    expect(context.pages().length).toBe(pagesBefore);
  });

  test('approve research — faculty and dean get notifications', async ({ page }) => {
    const title = uniqueTitle('OVPRI Approve');
    await endorseToOvpri(page, title);

    await login(page, CREDENTIALS.ovpri.email);
    await openOvpriQueueResearch(page, title);
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.locator('#approve-remarks').fill('Approved for institutional research registry.');
    await page.locator('form[action*="approve"] button[type="submit"]').click();
    await expectFlashSuccess(page, /approved successfully/i);

    await login(page, CREDENTIALS.faculty.email);
    await openNotificationBell(page);
    await expect(page.getByText(/approved/i).first()).toBeVisible();

    await login(page, CREDENTIALS.dean.email);
    await openNotificationBell(page);
    await expect(page.getByText(/approved/i).first()).toBeVisible();
  });

  test('return research — dean gets notification', async ({ page }) => {
    const title = uniqueTitle('OVPRI Return');
    await endorseToOvpri(page, title);

    await login(page, CREDENTIALS.ovpri.email);
    await openOvpriQueueResearch(page, title);
    await page.getByRole('button', { name: 'Return', exact: true }).click();
    await page.locator('#ovpri-return-remarks').fill('Please have the dean verify funding documentation.');
    await page.locator('form[action*="return"] button[type="submit"]').click();
    await expect(page.getByText(/returned/i).first()).toBeVisible({ timeout: 15_000 });

    await login(page, CREDENTIALS.dean.email);
    await openNotificationBell(page);
    await expect(page.getByText(/returned/i).first()).toBeVisible();
  });

  test('H-04: reject research — faculty gets notification', async ({ page }) => {
    const title = uniqueTitle('OVPRI Reject');
    await endorseToOvpri(page, title);

    await login(page, CREDENTIALS.ovpri.email);
    await openOvpriQueueResearch(page, title);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    await page.locator('#ovpri-reject-remarks').fill('Research output classification requires correction.');
    await page.locator('form[action*="reject"] button[type="submit"]').click();
    await expect(page.getByText(/rejected/i).first()).toBeVisible({ timeout: 15_000 });

    await login(page, CREDENTIALS.faculty.email);
    await openNotificationBell(page);
    await expect(page.getByText(/rejected/i).first()).toBeVisible();
  });

  test('M-07: CDAIC sees same approved/returned/rejected records as OVPRI', async ({ page }) => {
    await page.goto(`${BASE_URL}/ovpri/queue`);
    const ovpriPending = await page.locator('#tab-pending .kmsar-tab-badge').innerText();
    const ovpriApproved = await page.locator('#tab-approved .kmsar-tab-badge').innerText();
    const ovpriReturned = await page.locator('#tab-returned .kmsar-tab-badge').innerText();

    await login(page, CREDENTIALS.cdaic.email);
    await page.goto(`${BASE_URL}/ovpri/queue`);

    await expect(page.locator('#tab-pending .kmsar-tab-badge')).toHaveText(ovpriPending);
    await expect(page.locator('#tab-approved .kmsar-tab-badge')).toHaveText(ovpriApproved);
    await expect(page.locator('#tab-returned .kmsar-tab-badge')).toHaveText(ovpriReturned);
  });
});
