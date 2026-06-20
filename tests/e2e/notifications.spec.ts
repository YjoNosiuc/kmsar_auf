import { test, expect } from '@playwright/test';
import { BASE_URL, CREDENTIALS, login, openNotificationBell } from './helpers/auth';
import { openDeanQueueResearch, registerResearchThroughWizard, uniqueTitle } from './helpers/research';

test.describe('Notifications', () => {
  test('H-04: faculty submission confirmation in bell', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    const title = uniqueTitle('Notif Submit');
    await registerResearchThroughWizard(page, title, { submit: true });

    await openNotificationBell(page);
    await expect(page.getByText(/submission confirmed|submitted/i).first()).toBeVisible();
  });

  test('H-04: faculty rejection notification with remarks', async ({ page }) => {
    const title = uniqueTitle('Notif Rejection');
    await login(page, CREDENTIALS.faculty.email);
    await registerResearchThroughWizard(page, title, { submit: true });

    await login(page, CREDENTIALS.dean.email);
    await openDeanQueueResearch(page, title);
    await page.getByRole('button', { name: 'Reject', exact: true }).click();
    const rejectionRemarks = 'Insufficient supporting documents for ethics review clearance.';
    await page.locator('#reject-remarks').fill(rejectionRemarks);
    await page.locator('form[action*="reject"] button[type="submit"]').click();

    await login(page, CREDENTIALS.faculty.email);
    await openNotificationBell(page);
    await expect(page.getByText(/rejected/i).first()).toBeVisible();
  });

  test('mark notification as read', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    const title = uniqueTitle('Notif Read One');
    await registerResearchThroughWizard(page, title, { submit: true });

    await openNotificationBell(page);
    const notificationLink = page
      .locator('a[onclick*="markRead"]')
      .filter({ hasText: /submission confirmed|submitted/i })
      .first();
    await expect(notificationLink).toBeVisible();
    const onclick = await notificationLink.getAttribute('onclick');
    const notifId = onclick?.match(/markRead\('([^']+)'/)?.[1];
    expect(notifId).toBeTruthy();

    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const response = await page.request.post(`${BASE_URL}/notifications/${notifId}/read`, {
      headers: { 'X-CSRF-TOKEN': csrf ?? '' },
    });
    expect(response.ok()).toBeTruthy();

    await page.reload();
    await openNotificationBell(page);
    await expect(page.locator(`a[onclick*="markRead('${notifId}')"]`)).toHaveCount(0);
  });

  test('mark all as read', async ({ page }) => {
    await login(page, CREDENTIALS.faculty.email);
    const title = uniqueTitle('Notif Read All');
    await registerResearchThroughWizard(page, title, { submit: true });

    await openNotificationBell(page);
    await page.locator('form[action*="read-all"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await openNotificationBell(page);
    await expect(page.getByText('No new notifications')).toBeVisible();
  });
});
