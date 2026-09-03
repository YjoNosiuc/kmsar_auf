import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { resetDatabaseAndAuth, runTinker, processQueuedJobs, waitForUnreadNotifications } from './helpers/db';
import { acquireSuiteLock, releaseSuiteLock } from './helpers/db-lock';
import {
  createAndSubmitResearch,
  endorseResearch,
  approveResearch,
  returnResearchOvpri,
  returnResearchDean,
  setupEndorsedResearch,
  setupRegisteredResearch,
  setupFinalDeanReviewResearch,
  setupFinalOvpriReviewResearch,
  submitCompletionViaModal,
} from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

function queuedMailJobsContaining(className: string, email?: string): number {
  const escapedClass = className.replace(/\\/g, '\\\\');
  const emailClause = email ? `->where('payload','like','%${email}%')` : '';
  const out = runTinker(
    `echo \\Illuminate\\Support\\Facades\\DB::table('jobs')->where('payload','like','%${escapedClass}%')${emailClause}->count();`,
  );
  return parseInt(out.match(/(\d+)\s*$/)?.[1] ?? '0', 10);
}

async function openNotificationBell(page: Page): Promise<void> {
  processQueuedJobs();
  await page.getByRole('button', { name: 'Notifications' }).click();
  await expect(page.locator('.kmsar-navbar-notif-panel')).toBeVisible({ timeout: 10_000 });
}

async function getUnreadBellCount(page: Page): Promise<number> {
  processQueuedJobs();
  const dot = page.locator('.kmsar-navbar-notif-dot');
  if (!(await dot.count()) || !(await dot.isVisible().catch(() => false))) {
    return 0;
  }
  const text = (await dot.innerText()).trim();
  if (text === '9+') {
    return 9;
  }
  return parseInt(text, 10) || 0;
}

async function csrfToken(page: Page): Promise<string> {
  return (await page.locator('meta[name="csrf-token"]').getAttribute('content')) ?? '';
}

test.describe('Notifications — UAT', () => {
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async () => {
    test.setTimeout(180_000);
    await acquireSuiteLock('notifications');
    await resetDatabaseAndAuth();
  });

  test.afterAll(() => {
    releaseSuiteLock();
  });

  // -------------------------------------------------------------------------
  test.describe('Notification bell — faculty', () => {
    test('NOTIF-001: Bell shows unread count badge when notifications exist', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('NOTIF001 Badge'));
      await waitForUnreadNotifications(credentials.faculty_ccs.email);
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      const count = await getUnreadBellCount(page);
      expect(count).toBeGreaterThan(0);
      await expect(page.locator('.kmsar-navbar-notif-dot')).toBeVisible();
    });

    test('NOTIF-002: Bell count decreases by 1 after marking one notification as read', async ({
      page,
    }) => {
      await createAndSubmitResearch(page, uniqueTitle('NOTIF002 Mark One'));
      await waitForUnreadNotifications(credentials.faculty_ccs.email);
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      const before = await getUnreadBellCount(page);
      expect(before).toBeGreaterThan(0);

      await openNotificationBell(page);
      const link = page.locator('a[onclick*="markRead"]').first();
      const onclick = await link.getAttribute('onclick');
      const notifId = onclick?.match(/markRead\('([^']+)'/)?.[1];
      expect(notifId).toBeTruthy();

      const csrf = await csrfToken(page);
      const res = await page.request.post(`/notifications/${notifId}/read`, {
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
      });
      expect(res.ok()).toBeTruthy();
      await page.reload();

      const after = await getUnreadBellCount(page);
      expect(after).toBe(before - 1);
    });

    test('NOTIF-003: Bell count resets to 0 after marking all as read', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('NOTIF003 Mark All'));
      await waitForUnreadNotifications(credentials.faculty_ccs.email);
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      expect(await getUnreadBellCount(page)).toBeGreaterThan(0);

      await openNotificationBell(page);
      await page.locator('form[action*="read-all"] button[type="submit"]').click();
      await page.waitForLoadState('networkidle');
      expect(await getUnreadBellCount(page)).toBe(0);
    });

    test('NOTIF-004: Clicking notification bell opens notification list', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await openNotificationBell(page);
      await expect(page.getByText(/View all notifications|No new notifications|Mark all as read/i).first()).toBeVisible();
    });

    test('NOTIF-005: Notification list shows correct title and timestamp', async ({ page }) => {
      const title = uniqueTitle('NOTIF005 Timestamp');
      await createAndSubmitResearch(page, title);
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await openNotificationBell(page);
      await expect(page.locator('.kmsar-navbar-notif-panel').getByText(/submitted for dean review|has been submitted/i).first()).toBeVisible();
      await expect(page.locator('.kmsar-navbar-notif-panel').getByText(/ago|just now|minute|second|hour/i).first()).toBeVisible();
    });

    test('NOTIF-006: Faculty receives notification after submitting research', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('NOTIF006 Submit'));
      await waitForUnreadNotifications(credentials.faculty_ccs.email);
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/submitted for dean review/i).first()).toBeVisible();
    });

    test('NOTIF-007: Faculty receives notification when dean returns research (with remarks)', async ({
      page,
    }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('NOTIF007 Return'));
      expect(researchId).toBeTruthy();
      await returnResearchDean(
        page,
        researchId!,
        'Please revise methodology section and resubmit documents.',
      );

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/returned.*revision|returned by/i).first()).toBeVisible();
    });

    test('NOTIF-008: Faculty receives notification when dean returns research (duplicate path with remarks)', async ({
      page,
    }) => {
      const remarks = 'Does not meet minimum documentation requirements for college review.';
      const researchId = await createAndSubmitResearch(page, uniqueTitle('NOTIF008 Return'));
      expect(researchId).toBeTruthy();
      await returnResearchDean(page, researchId!, remarks);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/returned.*revision|returned by/i).first()).toBeVisible();
      await expect(page.getByText(remarks).first()).toBeVisible();
    });

    test('NOTIF-009: Faculty receives notification when OVPRI approves research', async ({
      page,
    }) => {
      const researchId = await setupEndorsedResearch(page, uniqueTitle('NOTIF009 Approve'));
      await approveResearch(page, researchId);
      await waitForUnreadNotifications(credentials.faculty_ccs.email);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/registered by OVPRI|has been registered by OVPRI/i).first()).toBeVisible();
    });

    test('NOTIF-010: Faculty receives notification when OVPRI returns research (with remarks)', async ({
      page,
    }) => {
      const remarks = 'OVPRI return remarks for faculty notification E2E test case.';
      const researchId = await setupEndorsedResearch(page, uniqueTitle('NOTIF010 Ovpri Return'));
      await returnResearchOvpri(page, researchId, remarks);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/returned.*revision|returned by/i).first()).toBeVisible();
      await expect(page.getByText(remarks).first()).toBeVisible();
    });

    test('NOTIF-011: Faculty DOES receive notification when OVPRI returns research to faculty', async ({
      page,
    }) => {
      const title = uniqueTitle('NOTIF011 Faculty Return');
      const researchId = await setupEndorsedResearch(page, title);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await openNotificationBell(page);
      await page.locator('form[action*="read-all"] button[type="submit"]').click().catch(() => undefined);
      await page.waitForLoadState('networkidle');
      const before = await getUnreadBellCount(page);

      await returnResearchOvpri(
        page,
        researchId,
        'OVPRI return with faculty notification for E2E validation test.',
      );

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      const after = await getUnreadBellCount(page);
      expect(after).toBeGreaterThan(before);

      await openNotificationBell(page);
      await expect(page.getByText(/returned.*revision|returned by/i).first()).toBeVisible();
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Notification bell — dean', () => {
    test('NOTIF-012: Dean receives notification when faculty submits research', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('NOTIF012 Dean Submit'));
      await waitForUnreadNotifications(credentials.dean_ccs.email);
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      await openNotificationBell(page);
      await expect(page.getByText(/has been submitted for your review/i).first()).toBeVisible();
    });

    test('NOTIF-013: Dean receives notification when OVPRI approves research', async ({ page }) => {
      const researchId = await setupEndorsedResearch(page, uniqueTitle('NOTIF013 Dean Approve'));
      await approveResearch(page, researchId);
      await waitForUnreadNotifications(credentials.dean_ccs.email);

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/registered by OVPRI|has been registered by OVPRI/i).first()).toBeVisible();
    });

    test('NOTIF-014: Dean receives in-app notification when OVPRI returns research to faculty', async ({
      page,
    }) => {
      const remarks = 'Please have the faculty revise the abstract before resubmission.';
      const researchId = await setupEndorsedResearch(page, uniqueTitle('NOTIF014 Dean Return'));

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      const before = await getUnreadBellCount(page);

      await returnResearchOvpri(page, researchId, remarks);

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      const after = await getUnreadBellCount(page);
      expect(after).toBeGreaterThan(before);

      await openNotificationBell(page);
      await expect(page.getByText(/returned by OVPRI/i).first()).toBeVisible();
      await expect(page.getByText(remarks).first()).toBeVisible();
    });

    test('NOTIF-015: Dean in-app notification on OVPRI return includes cycle context', async ({ page }) => {
      const researchId = await setupEndorsedResearch(page, uniqueTitle('NOTIF015 Dean Return'));
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');

      await returnResearchOvpri(
        page,
        researchId,
        'Returned at university level due to incomplete documentation.',
      );

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      await openNotificationBell(page);
      await expect(page.getByText(/returned by OVPRI/i).first()).toBeVisible();
      await expect(page.getByText(/initial review/i).first()).toBeVisible();
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Notification bell — OVPRI', () => {
    test('NOTIF-016: OVPRI receives notification when dean endorses research', async ({ page }) => {
      const researchId = await createAndSubmitResearch(page, uniqueTitle('NOTIF016 Endorse'));
      expect(researchId).toBeTruthy();
      await endorseResearch(page, researchId!);
      await waitForUnreadNotifications(credentials.ovpri.email);

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await openNotificationBell(page);
      await expect(
        page.getByText(/endorsed by the college dean|awaits OVPRI/i).first(),
      ).toBeVisible();
    });

    test('NOTIF-017: Notification links to correct research page when clicked', async ({ page }) => {
      const title = uniqueTitle('NOTIF017 Link');
      const researchId = await createAndSubmitResearch(page, title);
      expect(researchId).toBeTruthy();
      await endorseResearch(page, researchId!);

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await openNotificationBell(page);
      const link = page
        .locator('.kmsar-navbar-notif-panel a[onclick*="markRead"]')
        .filter({ hasText: /endorsed by the college dean|awaits OVPRI/i })
        .first();
      await Promise.all([
        page.waitForURL(new RegExp(`/ovpri/review/${researchId}`), { timeout: 15_000 }),
        link.click(),
      ]);
      await expect(page.getByRole('heading', { name: new RegExp(title, 'i') })).toBeVisible({
        timeout: 15_000,
      });
    });

    test('NOTIF-018: Old notifications persist after new ones arrive', async ({ page }) => {
      const firstId = await createAndSubmitResearch(page, uniqueTitle('NOTIF018 First'));
      await endorseResearch(page, firstId!);

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await openNotificationBell(page);
      await expect(page.getByText(/endorsed by the college dean|awaits OVPRI/i).first()).toBeVisible();

      const secondId = await createAndSubmitResearch(page, uniqueTitle('NOTIF018 Second'));
      await endorseResearch(page, secondId!);

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/notifications');
      await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible();
      const endorsed = page.getByText(/endorsed by the college dean|awaits OVPRI/i);
      expect(await endorsed.count()).toBeGreaterThanOrEqual(2);
    });

    test('NOTIF-019: Notifications page shows full list with pagination if many exist', async ({
      page,
    }) => {
      runTinker(
        "$u=\\App\\Models\\User::where('email','ovpri@yopmail.com')->first(); for($i=0;$i<25;$i++){ $u->notifications()->create(['id'=>(string)\\Illuminate\\Support\\Str::uuid(),'type'=>'App\\\\Notifications\\\\ResearchEndorsedToOvpri','data'=>['message'=>'Bulk notification '.$i,'title'=>'Bulk','action_url'=>'/ovpri/queue','reference_number'=>'BULK-'.$i,'type'=>'endorsed_to_ovpri']]); } echo 'OK';",
      );

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/notifications');
      await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible();
      await expect(page.getByText(/Bulk notification/i).first()).toBeVisible();
      const pager = page.locator('.pagination, nav[role="navigation"] a, .kmsar-pagination').first();
      await expect(pager).toBeVisible({ timeout: 10_000 });
    });
  });

  test.describe('Email notification smoke tests', () => {
    test('NOTIF-020: Research submission queues an email to faculty', async ({ page }) => {
      const before = queuedMailJobsContaining('ResearchSubmittedFacultyMail', credentials.faculty_ccs.email);
      await createAndSubmitResearch(page, uniqueTitle('NOTIF020 Faculty Mail'));
      processQueuedJobs();
      const after = queuedMailJobsContaining('ResearchSubmittedFacultyMail', credentials.faculty_ccs.email);
      expect(after).toBeGreaterThan(before);
    });

    test('NOTIF-021: Research submission queues an email to the dean', async ({ page }) => {
      const before = queuedMailJobsContaining('ResearchSubmittedDeanMail', credentials.dean_ccs.email);
      await createAndSubmitResearch(page, uniqueTitle('NOTIF021 Dean Mail'));
      processQueuedJobs();
      const after = queuedMailJobsContaining('ResearchSubmittedDeanMail', credentials.dean_ccs.email);
      expect(after).toBeGreaterThan(before);
    });

    test('NOTIF-022: Password-reset request creates OTP and dispatches email flow', async ({ page }) => {
      await page.goto('/forgot-password');
      await page.getByLabel('Email address').fill(credentials.faculty_ccs.email);
      await page.getByRole('button', { name: 'Send verification code' }).click();
      await expect(page).toHaveURL(/\/verify-otp\?email=/, { timeout: 30_000 });
      const out = runTinker(
        `echo \\App\\Models\\PasswordResetOtp::where('email','${credentials.faculty_ccs.email}')->where('expires_at','>',now())->count();`,
      );
      expect(parseInt(out.match(/(\d+)\s*$/)?.[1] ?? '0', 10)).toBe(1);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Notification bell — final cycle', () => {
    test('NOTIF-023: Dean receives notification when faculty submits completion', async ({ page }) => {
      await setupFinalDeanReviewResearch(page, uniqueTitle('NOTIF023 Completion'));
      await waitForUnreadNotifications(credentials.dean_ccs.email);

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/dean/dashboard');
      await openNotificationBell(page);
      await expect(page.getByText(/completion has been submitted for final dean review/i).first()).toBeVisible();
    });

    test('NOTIF-024: OVPRI receives notification when dean endorses final review', async ({ page }) => {
      const researchId = await setupFinalDeanReviewResearch(page, uniqueTitle('NOTIF024 Final Endorse'));
      await endorseResearch(page, researchId);
      await waitForUnreadNotifications(credentials.ovpri.email);

      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/dashboard');
      await openNotificationBell(page);
      await expect(page.getByText(/final OVPRI|awaits final OVPRI/i).first()).toBeVisible();
    });

    test('NOTIF-025: Faculty receives accepted notification when OVPRI approves final review', async ({
      page,
    }) => {
      const researchId = await setupFinalOvpriReviewResearch(page, uniqueTitle('NOTIF025 Final Approve'));
      await approveResearch(page, researchId);
      await waitForUnreadNotifications(credentials.faculty_ccs.email);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/accepted by OVPRI/i).first()).toBeVisible();
    });

    test('NOTIF-026: Dean receives accepted notification when OVPRI approves final review', async ({
      page,
    }) => {
      const researchId = await setupFinalOvpriReviewResearch(page, uniqueTitle('NOTIF026 Dean Final Approve'));
      await approveResearch(page, researchId);
      await waitForUnreadNotifications(credentials.dean_ccs.email);

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/accepted by OVPRI/i).first()).toBeVisible();
    });

    test('NOTIF-027: Faculty receives final-review return from dean with remarks', async ({ page }) => {
      const remarks = 'Please revise outcome classifications before final resubmission.';
      const researchId = await setupFinalDeanReviewResearch(page, uniqueTitle('NOTIF027 Final Dean Return'));
      await returnResearchDean(page, researchId, remarks);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/final review/i).first()).toBeVisible();
      await expect(page.getByText(remarks).first()).toBeVisible();
    });

    test('NOTIF-028: Dean receives in-app notification when OVPRI returns final review', async ({ page }) => {
      const remarks = 'Final outcomes need stronger evidence documentation.';
      const researchId = await setupFinalOvpriReviewResearch(page, uniqueTitle('NOTIF028 Final Ovpri Return'));
      await returnResearchOvpri(page, researchId, remarks);

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await openNotificationBell(page);
      await expect(page.getByText(/returned by OVPRI/i).first()).toBeVisible();
      await expect(page.getByText(/final review/i).first()).toBeVisible();
      await expect(page.getByText(remarks).first()).toBeVisible();
    });
  });
});
