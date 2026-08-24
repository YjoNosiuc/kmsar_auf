import { test, expect, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { runTinker } from './helpers/db';

/**
 * Current-system UAT for every live role.
 * Uses seeded demo data (UserSeeder + ResearchSeeder + ApprovalSeeder).
 * Password for all seeded accounts: password
 */

const OVPRI_APPROVED = [
  'Blockchain-Based Academic',
  'IoT-Enabled Smart Campus',
  'Ergonomic Interventions',
  'Antimicrobial Stewardship',
  'Point-of-Care Testing',
];

const NOT_OVPRI_APPROVED = [
  'Tagalog Sentiment',
  'Crop Disease Detection',
  'Augmented Reality Application',
  'Federated Learning Framework',
  'Home-Based Physical Therapy',
  'Telerehabilitation Outcomes',
  'Laboratory Quality Indicators',
];

async function expectForbidden(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  expect(page.url(), `expected 403 for ${path}`).not.toMatch(/\/login/);
  expect(response?.status()).toBe(403);
}

async function expectNavHas(page: Page, label: string): Promise<void> {
  await expect(page.locator('a.kmsar-nav-item', { hasText: label })).toBeVisible();
}

async function expectNavMissing(page: Page, label: string): Promise<void> {
  await expect(page.locator('a.kmsar-nav-item', { hasText: label })).toHaveCount(0);
}

async function openReports(page: Page): Promise<void> {
  await page.goto('/reports');
  await expect(page.getByRole('heading', { name: 'Reports & Analytics' })).toBeVisible();
  await expect(page.getByLabel(/OVPRI approved from/i)).toBeVisible();
  await expect(page.getByLabel(/OVPRI approved to/i)).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Registered' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'OVPRI approved' })).toBeVisible();
}

async function expectApprovedRowsVisible(page: Page, titles: string[]): Promise<void> {
  const body = page.locator('table.kmsar-table tbody');
  for (const title of titles) {
    await expect(body.getByText(title, { exact: false })).toBeVisible();
  }
}

async function expectTitlesAbsent(page: Page, titles: string[]): Promise<void> {
  const body = page.locator('table.kmsar-table tbody');
  for (const title of titles) {
    await expect(body.getByText(title, { exact: false })).toHaveCount(0);
  }
}

function researchIdByRef(reference: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Research::where('reference_number','${reference}')->value('id') ?? 0;`,
  );
  const id = parseInt(out.trim().split(/\r?\n/).pop() ?? '', 10);
  expect(id).toBeGreaterThan(0);
  return id;
}

test.describe('Current system UAT — all roles', () => {
  test.describe('Guest and authentication', () => {
    test('AUTH-01 / AUTH-06: Login form loads; protected pages redirect', async ({ page }) => {
      await page.goto('/login');
      await expect(page.locator('input[name="login"]')).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();
      await expect(page.locator('button[type="submit"]')).toBeVisible();

      for (const path of ['/research', '/reports', '/admin/dashboard', '/ovpri/dashboard', '/dean/dashboard']) {
        await page.goto(path);
        await expect(page).toHaveURL(/\/login/);
      }
    });

    test('AUTH-03: Wrong password stays on login', async ({ page }) => {
      await page.goto('/login');
      await page.fill('input[name="login"]', credentials.faculty_ccs.email);
      await page.fill('input[name="password"]', 'wrong-password');
      await page.click('button[type="submit"]');
      await expect(page).toHaveURL(/\/login/);
    });

    test('AUTH-08 / AUTH-12: Register and forgot-password pages load', async ({ page }) => {
      await page.goto('/register');
      await expect(page.locator('#email, input[name="email"]').first()).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();

      await page.goto('/forgot-password');
      await expect(page.locator('input[name="email"], input[name="login"]').first()).toBeVisible();
    });
  });

  test.describe('Faculty', () => {
    test('UAT-FAC-001: Login lands on My Research with own records, including drafts', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await expect(page).toHaveURL(/\/research\/?$/);
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
      await expectNavHas(page, 'My Research');
      await expectNavHas(page, 'Register New');
      await expectNavMissing(page, 'Reports');
      await expectNavMissing(page, 'Approval Queue');

      await expect(page.getByText('Natural Language Processing for Tagalog Sentiment Analysis')).toBeVisible();
      await expect(page.getByText('AI-Based Crop Disease Detection Using Convolutional Neural Networks')).toBeVisible();
      await expect(page.getByText('Blockchain-Based Academic Credential Verification System')).toBeVisible();
      await expect(page.getByText('Telerehabilitation Outcomes Among Outpatients in Pampanga')).toHaveCount(0);
    });

    test('UAT-FAC-002: Search and stage filters apply across all of the faculty records', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research?search=Blockchain');
      await expect(page.getByText('Blockchain-Based Academic Credential Verification System')).toBeVisible();
      await expect(page.getByText('Natural Language Processing for Tagalog Sentiment Analysis')).toHaveCount(0);

      await page.goto('/research?approval_stage=draft');
      await expect(page.getByText('Natural Language Processing for Tagalog Sentiment Analysis')).toBeVisible();
      await expect(page.getByText('Blockchain-Based Academic Credential Verification System')).toHaveCount(0);
    });

    test('UAT-FAC-003: Faculty cannot open Reports or other role dashboards', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await expectForbidden(page, '/reports');
      await expectForbidden(page, '/dean/dashboard');
      await expectForbidden(page, '/ovpri/dashboard');
      await expectForbidden(page, '/admin/dashboard');
    });

    test('FAC-09 / SHR-01: Faculty can view an owned record and open profile', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const id = researchIdByRef('AUF-2025-CCS-0003');
      const response = await page.goto(`/research/${id}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByText(/Blockchain/i).first()).toBeVisible();

      await page.goto('/profile');
      await expect(page.getByRole('heading', { name: /My Profile/i })).toBeVisible();
      await expect(page.locator('#profile_first_name, input[name="first_name"]').first()).toBeVisible();
    });

    test('FAC-11: Register New opens the details wizard', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research/create');
      await expect(page).toHaveURL(/\/research\/\d+\/details/);
      await expect(page.locator('textarea[name="title"], input[name="title"]').first()).toBeVisible();
    });
  });

  test.describe('College Dean', () => {
    test('UAT-DEAN-001: Dashboard and queue are college-scoped', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await expect(page).toHaveURL(/\/dean\/dashboard/);
      await expectNavHas(page, 'Dashboard');
      await expectNavHas(page, 'Approval Queue');
      await expectNavHas(page, 'Reports');
      await expectNavMissing(page, 'User Management');

      await page.goto('/approval/queue');
      await expect(page.getByText(/CCS|Computer Studies/i).first()).toBeVisible();
    });

    test('UAT-DEAN-002: Reports show only this college’s OVPRI-approved research', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await openReports(page);

      await expectApprovedRowsVisible(page, [
        'Blockchain-Based Academic',
        'IoT-Enabled Smart Campus',
      ]);
      await expectTitlesAbsent(page, [
        ...NOT_OVPRI_APPROVED,
        'Ergonomic Interventions',
        'Antimicrobial Stewardship',
        'Point-of-Care Testing',
      ]);
    });

    test('UAT-DEAN-003: Dean cannot open faculty, OVPRI, or admin pages', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await expectForbidden(page, '/research');
      await expectForbidden(page, '/ovpri/dashboard');
      await expectForbidden(page, '/admin/dashboard');
    });

    test('DEAN-08: Dean can open a CCS dean-review record', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const id = researchIdByRef('AUF-2025-CCS-0002');
      const response = await page.goto(`/approval/${id}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByText(/Crop Disease/i).first()).toBeVisible();
    });

    test('DEAN-17: Dean reports date range uses OVPRI approval month', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      await page.goto('/reports?date_from=2025-03-01&date_to=2025-03-31');
      await expect(page.getByText('Blockchain-Based Academic', { exact: false })).toBeVisible();
      await expect(page.locator('table.kmsar-table tbody').getByText('IoT-Enabled Smart Campus', { exact: false })).toHaveCount(0);
    });

    test('DEAN-21: CAMP dean reports only CAMP OVPRI-approved rows', async ({ page }) => {
      await login(page, credentials.dean_camp.email, credentials.dean_camp.password);
      await openReports(page);
      await expectApprovedRowsVisible(page, [
        'Ergonomic Interventions',
        'Antimicrobial Stewardship',
        'Point-of-Care Testing',
      ]);
      await expectTitlesAbsent(page, [
        'Blockchain-Based Academic',
        'IoT-Enabled Smart Campus',
        ...NOT_OVPRI_APPROVED,
      ]);
    });
  });

  test.describe('OVPRI and CDAIC', () => {
    test('UAT-OVPRI-001: Dashboard, queue, and All Research are available', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await expect(page).toHaveURL(/\/ovpri\/dashboard/);
      await expectNavHas(page, 'Dashboard');
      await expectNavHas(page, 'Approval Queue');
      await expectNavHas(page, 'Reports');
      await expectNavHas(page, 'All Research');
      await expectNavMissing(page, 'User Management');
    });

    test('UAT-OVPRI-002: Reports are university-wide and only OVPRI-approved', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await openReports(page);
      await expectApprovedRowsVisible(page, OVPRI_APPROVED);
      await expectTitlesAbsent(page, NOT_OVPRI_APPROVED);
    });

    test('UAT-CDAIC-001: CDAIC sees the same OVPRI-approved reports as OVPRI', async ({ page }) => {
      await login(page, credentials.cdaic.email, credentials.cdaic.password);
      await expect(page).toHaveURL(/\/ovpri\/dashboard/);
      await openReports(page);
      await expectApprovedRowsVisible(page, OVPRI_APPROVED);
      await expectTitlesAbsent(page, NOT_OVPRI_APPROVED);
    });

    test('UAT-OVPRI-003: OVPRI cannot open admin or dean dashboards', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await expectForbidden(page, '/admin/dashboard');
      await expectForbidden(page, '/dean/dashboard');
    });

    test('OVP-05 / OVP-06: OVPRI queue and review open seeded ovpri_review record', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/queue');
      await expect(page.getByText(/Federated Learning|Telerehabilitation/i).first()).toBeVisible();

      const id = researchIdByRef('AUF-2025-CCS-0005');
      const response = await page.goto(`/ovpri/review/${id}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByText(/Federated Learning/i).first()).toBeVisible();
    });

    test('OVP-07: All Research page loads with filters', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/ovpri/research');
      await expect(page.locator('table, .kmsar-table').first()).toBeVisible();
    });

    test('OVP-15: University reports date range uses OVPRI approval', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      await page.goto('/reports?date_from=2025-03-01&date_to=2025-03-31');
      const body = page.locator('table.kmsar-table tbody');
      await expect(body.getByText('Blockchain-Based Academic', { exact: false })).toBeVisible();
      await expect(body.getByText('Point-of-Care Testing', { exact: false })).toBeVisible();
      await expect(body.getByText('IoT-Enabled Smart Campus', { exact: false })).toHaveCount(0);
      await expect(body.getByText('Antimicrobial Stewardship', { exact: false })).toHaveCount(0);
    });
  });

  test.describe('Super Admin', () => {
    test('UAT-ADM-001: Admin sidebar shows management links and hides Import Data', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await expect(page).toHaveURL(/\/admin\/dashboard/);
      await expectNavHas(page, 'Dashboard');
      await expectNavHas(page, 'User Management');
      await expectNavHas(page, 'Colleges/Offices');
      await expectNavHas(page, 'Reports');
      await expectNavHas(page, 'Audit Logs');
      await expectNavMissing(page, 'Import Data');
    });

    test('UAT-ADM-002: Admin reports match OVPRI — all years, OVPRI approval only', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await openReports(page);
      await expectApprovedRowsVisible(page, OVPRI_APPROVED);
      await expectTitlesAbsent(page, NOT_OVPRI_APPROVED);
    });

    test('UAT-ADM-003: User management and colleges load', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/users');
      await expect(page.getByRole('heading', { name: /User management/i })).toBeVisible();
      await expect(page.getByText('ovpri@yopmail.com')).toBeVisible();
      await expect(page.getByText('faculty.ccs1@yopmail.com')).toBeVisible();

      await page.goto('/admin/colleges');
      await expect(page.getByRole('heading', { name: 'Colleges/Offices & programs' })).toBeVisible();
      await expect(page.getByText('CCS').first()).toBeVisible();
      await expect(page.getByText('CAMP').first()).toBeVisible();
    });

    test('ADM-12 / ADM-14: Audit logs load; reports date filter matches OVPRI', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/audit-logs');
      await expect(page.getByRole('heading', { name: /Audit logs/i })).toBeVisible();

      await page.goto('/reports?date_from=2025-03-01&date_to=2025-03-31');
      const body = page.locator('table.kmsar-table tbody');
      await expect(body.getByText('Blockchain-Based Academic', { exact: false })).toBeVisible();
      await expect(body.getByText('IoT-Enabled Smart Campus', { exact: false })).toHaveCount(0);
    });

    test('AUTH-07: Admin sign out returns to login', async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.getByRole('button', { name: /sign out/i }).click();
      await expect(page).toHaveURL(/\/login/);
    });
  });

  test.describe('Viewer', () => {
    test('UAT-VIEW-001: Viewer can open My Research and is blocked from Reports', async ({ page }) => {
      const email = `e2e.uat.viewer.${Date.now()}@auf.edu.ph`;
      runTinker(
        `$c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $u=\\App\\Models\\User::updateOrCreate(['email'=>'${email}'],['employee_number'=>'V${String(Date.now()).slice(-8)}','first_name'=>'VIEWER','last_name'=>'UAT','name'=>'VIEWER UAT','password'=>bcrypt('password'),'college_id'=>$c->id,'is_active'=>true,'email_verified_at'=>now()]); $u->syncRoles(['viewer']); echo $u->email;`,
      );

      await login(page, email, 'password');
      await page.goto('/research');
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
      await expectNavMissing(page, 'Register New');
      await expectNavMissing(page, 'Reports');
      await expectForbidden(page, '/reports');
    });
  });
});
