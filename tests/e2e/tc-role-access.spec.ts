import { test, expect, Page } from '@playwright/test';
import { login, logout, credentials } from './helpers/auth';
import { resetDatabase, runTinker } from './helpers/db';

const CO_AUTHOR_FACULTY_EMAIL = 'faculty.ccs2@auf.edu.ph';
const CO_AUTHOR_FACULTY_PASSWORD = 'password';

async function expectForbidden(page: Page, path: string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status()).toBe(403);
}

async function expectOk(page: Page, path: string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status()).toBe(200);
}

async function expectRedirectToLogin(page: Page, path: string): Promise<void> {
  await page.goto(path);
  await expect(page).toHaveURL(/\/login/);
}

function seedCoAuthorResearch(approvalStage: string, revisionCount = 0): number {
  const stamp = Date.now();
  const output = runTinker(
    `$primary = \\App\\Models\\User::where('email','faculty.ccs1@auf.edu.ph')->firstOrFail(); $co = \\App\\Models\\User::where('email','faculty.ccs2@auf.edu.ph')->firstOrFail(); $college = \\App\\Models\\College::where('code','CCS')->firstOrFail(); $r = \\App\\Models\\Research::create(['reference_number' => 'TEMP-CO-${stamp}', 'title' => 'COAUTHOR ACCESS ${stamp}', 'primary_author_id' => $primary->id, 'mother_college_id' => $college->id, 'research_classification' => 'internally_funded', 'expected_output' => ['publication'], 'start_date' => '2026-01-01', 'estimated_completion_date' => '2027-01-01', 'status' => 'proposal', 'approval_stage' => '${approvalStage}', 'revision_count' => ${revisionCount}, 'sdg_tags' => [4]]); \\App\\Models\\ResearchAuthor::create(['research_id' => $r->id, 'user_id' => $co->id, 'author_type' => 'internal', 'email' => $co->email, 'employee_number' => $co->employee_number, 'first_name' => $co->first_name, 'last_name' => $co->last_name, 'name' => $co->name, 'college_id' => $co->college_id, 'is_primary' => false, 'can_edit' => true]); echo $r->id;`,
  ).trim();

  const id = parseInt(output.match(/\d+/)?.[0] ?? '', 10);
  expect(id).toBeGreaterThan(0);

  return id;
}

function createCoAuthorRoleUser(stamp: number): string {
  const email = `e2e.coauthor.${stamp}@auf.edu.ph`;
  const employeeNumber = `AUF-C${String(stamp).slice(-6)}`;
  runTinker(
    `$college = \\App\\Models\\College::where('code','CCS')->firstOrFail(); $user = \\App\\Models\\User::updateOrCreate(['email' => '${email}'], ['employee_number' => '${employeeNumber}', 'first_name' => 'CO', 'last_name' => 'AUTHOR', 'name' => 'CO AUTHOR', 'password' => bcrypt('password'), 'college_id' => $college->id, 'is_active' => true, 'email_verified_at' => now()]); $user->syncRoles(['co_author']); echo $user->email;`,
  );

  return email;
}

test.describe('Role Access — UAT Test Suite', () => {
  test.beforeAll(async () => {
    resetDatabase();
  });

  test.describe('Faculty access control', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    });

    test('RA-001: Faculty cannot access /dean/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/dean/dashboard');
    });

    test('RA-002: Faculty cannot access /dean/queue → 403', async ({ page }) => {
      await expectForbidden(page, '/approval/queue');
    });

    test('RA-003: Faculty cannot access /ovpri/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/ovpri/dashboard');
    });

    test('RA-004: Faculty cannot access /ovpri/queue → 403', async ({ page }) => {
      await expectForbidden(page, '/ovpri/queue');
    });

    test('RA-005: Faculty cannot access /ovpri/research → 403', async ({ page }) => {
      await expectForbidden(page, '/ovpri/research');
    });

    test('RA-006: Faculty cannot access /admin/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/dashboard');
    });

    test('RA-007: Faculty cannot access /admin/users → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/users');
    });

    test('RA-008: Faculty cannot access /admin/colleges → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/colleges');
    });

    test('RA-009: Faculty cannot access /audit-logs → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/audit-logs');
    });
  });

  test.describe('Dean access control', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
    });

    test('RA-010: Dean cannot access /research → 403', async ({ page }) => {
      await expectForbidden(page, '/research');
    });

    test('RA-011: Dean cannot access /research/create → 403', async ({ page }) => {
      await expectForbidden(page, '/research/create');
    });

    test('RA-012: Dean cannot access /ovpri/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/ovpri/dashboard');
    });

    test('RA-013: Dean cannot access /ovpri/queue → 403', async ({ page }) => {
      await expectForbidden(page, '/ovpri/queue');
    });

    test('RA-014: Dean cannot access /admin/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/dashboard');
    });

    test('RA-015: Dean cannot access /admin/users → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/users');
    });
  });

  test.describe('OVPRI access control', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
    });

    test('RA-016: OVPRI cannot access /research → 403', async ({ page }) => {
      await expectForbidden(page, '/research');
    });

    test('RA-017: OVPRI cannot access /dean/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/dean/dashboard');
    });

    test('RA-018: OVPRI cannot access /approval/queue → 403', async ({ page }) => {
      await expectForbidden(page, '/approval/queue');
    });

    test('RA-019: OVPRI cannot access /admin/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/dashboard');
    });

    test('RA-020: OVPRI cannot access /admin/users → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/users');
    });
  });

  test.describe('CDAIC access control (same as OVPRI)', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, credentials.cdaic.email, credentials.cdaic.password);
    });

    test('RA-021: CDAIC cannot access /research → 403', async ({ page }) => {
      await expectForbidden(page, '/research');
    });

    test('RA-022: CDAIC cannot access /dean/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/dean/dashboard');
    });

    test('RA-023: CDAIC cannot access /admin/dashboard → 403', async ({ page }) => {
      await expectForbidden(page, '/admin/dashboard');
    });
  });

  test.describe('Admin access control (should have access to everything)', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
    });

    test('RA-024: Admin CAN access /admin/dashboard → 200', async ({ page }) => {
      await expectOk(page, '/admin/dashboard');
      await expect(page.getByRole('heading', { name: 'Admin Dashboard' })).toBeVisible();
    });

    test('RA-025: Admin CAN access /admin/users → 200', async ({ page }) => {
      await expectOk(page, '/admin/users');
      await expect(page.getByRole('heading', { name: 'User management' })).toBeVisible();
    });

    test('RA-026: Admin CAN access /admin/colleges → 200', async ({ page }) => {
      await expectOk(page, '/admin/colleges');
      await expect(page.getByRole('heading', { name: 'Colleges & programs' })).toBeVisible();
    });

    test('RA-027: Admin CAN access /audit-logs → 200', async ({ page }) => {
      await expectOk(page, '/admin/audit-logs');
      await expect(page.getByRole('heading', { name: 'Audit logs' })).toBeVisible();
    });
  });

  test.describe('Co-author access (M-02 fixes)', () => {
    test('RA-028: Co-author can VIEW research they are tagged on → no 403', async ({ page }) => {
      const researchId = seedCoAuthorResearch('dean_review');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      const response = await page.goto(`/research/${researchId}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('RA-029: Co-author can EDIT research they are tagged on → edit form loads', async ({ page }) => {
      const researchId = seedCoAuthorResearch('draft');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      const response = await page.goto(`/research/${researchId}/edit`);
      expect(response?.status()).toBe(200);
      await expect(page.locator('textarea[name="title"], #field_title').first()).toBeVisible();
    });

    test('RA-030: Co-author can REVISE rejected research they are tagged on → Revise button visible', async ({
      page,
    }) => {
      const researchId = seedCoAuthorResearch('rejected');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      await page.goto(`/research/${researchId}`);
      await expect(page.getByRole('button', { name: 'Revise', exact: true })).toBeVisible();
    });

    test('RA-031: Co-author can SUBMIT returned research they are tagged on → Submit button visible', async ({
      page,
    }) => {
      const researchId = seedCoAuthorResearch('draft', 1);
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      await page.goto(`/research/${researchId}`);
      await expect(page.getByRole('button', { name: 'Revise & Resubmit', exact: true })).toBeVisible();
    });

    test('RA-032: Co-author CANNOT delete research → delete not available', async ({ page }) => {
      const researchId = seedCoAuthorResearch('draft');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      await page.goto('/research');
      const card = page.locator('div').filter({ hasText: /COAUTHOR ACCESS/ }).first();
      await expect(card.getByRole('button', { name: 'Delete' })).toHaveCount(0);
    });

    test('RA-033: Co-author CANNOT access dean/ovpri/admin routes → 403', async ({ page }) => {
      const email = createCoAuthorRoleUser(Date.now());
      await login(page, email, 'password');
      await expectForbidden(page, '/dean/dashboard');
      await expectForbidden(page, '/ovpri/dashboard');
      await expectForbidden(page, '/admin/dashboard');
    });
  });

  test.describe('Session security (H-02)', () => {
    test('RA-034: After logout pressing back button does not show protected page', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await expect(page.getByRole('heading', { name: 'My research' })).toBeVisible();
      await logout(page);
      await page.goBack();
      await expect(page).toHaveURL(/\/login/);
      await expect(page.getByRole('heading', { name: 'My research' })).toHaveCount(0);
    });

    test('RA-035: Unauthenticated user accessing /research redirects to login', async ({ page }) => {
      await expectRedirectToLogin(page, '/research');
    });

    test('RA-036: Unauthenticated user accessing /dean/dashboard redirects to login', async ({ page }) => {
      await expectRedirectToLogin(page, '/dean/dashboard');
    });

    test('RA-037: Unauthenticated user accessing /ovpri/dashboard redirects to login', async ({ page }) => {
      await expectRedirectToLogin(page, '/ovpri/dashboard');
    });

    test('RA-038: Unauthenticated user accessing /admin/dashboard redirects to login', async ({ page }) => {
      await expectRedirectToLogin(page, '/admin/dashboard');
    });
  });
});
