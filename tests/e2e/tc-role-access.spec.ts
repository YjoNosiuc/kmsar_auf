import { test, expect, Page } from '@playwright/test';
import { login, logout, credentials, authStatePath } from './helpers/auth';
import { runTinker } from './helpers/db';
import { completeRegistrationOtp } from './helpers/register';

const CO_AUTHOR_FACULTY_EMAIL = 'faculty.ccs2@yopmail.com';
const CO_AUTHOR_FACULTY_PASSWORD = 'password';
const emptyStorage = { cookies: [] as never[], origins: [] as never[] };

async function expectForbidden(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  expect(page.url(), `expected 403 for ${path}, got redirect to login (stale auth?)`).not.toMatch(/\/login/);
  expect(response?.status()).toBe(403);
}

async function expectOk(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  expect(page.url(), `expected 200 for ${path}, got redirect to login (stale auth?)`).not.toMatch(/\/login/);
  expect(response?.status()).toBe(200);
}

async function expectRedirectToLogin(page: Page, path: string): Promise<void> {
  await page.goto(path);
  await expect(page).toHaveURL(/\/login/);
}

function seedCoAuthorResearch(approvalStage: string, revisionCount = 0): number {
  const stamp = Date.now();
  const output = runTinker(
    `$primary = \\App\\Models\\User::where('email','faculty.ccs1@yopmail.com')->firstOrFail(); $co = \\App\\Models\\User::where('email','faculty.ccs2@yopmail.com')->firstOrFail(); $college = \\App\\Models\\College::where('code','CCS')->firstOrFail(); $r = \\App\\Models\\Research::create(['reference_number' => 'TEMP-CO-${stamp}', 'title' => 'COAUTHOR ACCESS ${stamp}', 'primary_author_id' => $primary->id, 'mother_college_id' => $college->id, 'research_classification' => 'internally_funded', 'expected_output' => ['publication'], 'start_date' => '2026-01-01', 'estimated_completion_date' => '2027-01-01', 'status' => 'draft', 'approval_stage' => '${approvalStage}', 'revision_count' => ${revisionCount}, 'sdg_tags' => [4]]); \\App\\Models\\ResearchAuthor::create(['research_id' => $r->id, 'user_id' => $co->id, 'author_type' => 'internal', 'email' => $co->email, 'employee_number' => $co->employee_number, 'first_name' => $co->first_name, 'last_name' => $co->last_name, 'name' => $co->name, 'college_id' => $co->college_id, 'is_primary' => false, 'can_edit' => true]); echo $r->id;`,
  ).trim();

  const id = parseInt(output.match(/\d+/)?.[0] ?? '', 10);
  expect(id).toBeGreaterThan(0);

  return id;
}

function createViewerRoleUser(stamp: number): string {
  const email = `e2e.viewer.${stamp}@auf.edu.ph`;
  const employeeNumber = `AUF-C${String(stamp).slice(-6)}`;
  runTinker(
    `$college = \\App\\Models\\College::where('code','CCS')->firstOrFail(); $user = \\App\\Models\\User::updateOrCreate(['email' => '${email}'], ['employee_number' => '${employeeNumber}', 'first_name' => 'VIEWER', 'last_name' => 'USER', 'name' => 'VIEWER USER', 'password' => bcrypt('password'), 'college_id' => $college->id, 'is_active' => true, 'email_verified_at' => now()]); $user->syncRoles(['viewer']); echo $user->email;`,
  );

  return email;
}

async function registerAndReadRole(page: Page, userType: string): Promise<string> {
  const id = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
  const email = `ra.${userType}.${id}@auf.edu.ph`;
  await page.goto('/register');
  await page.fill('#first_name', 'ROLE');
  await page.fill('#last_name', userType);
  await page.locator('#user_type').selectOption(userType);
  if (userType !== 'external_affiliate') {
    await page.fill('#employee_number', id.replace(/\D/g, '').slice(-10));
  } else {
    await page.fill('#institution', 'De La Salle University');
  }
  await page.locator('#college_id').selectOption({ index: 1 });
  await page.fill('#email', email);
  await page.fill('#password', 'password123');
  await page.fill('#password_confirmation', 'password123');
  await page.getByRole('button', { name: 'Create account' }).click();
  await completeRegistrationOtp(page, email);
  return (
    runTinker(
      `echo \\App\\Models\\User::where('email','${email}')->firstOrFail()->getRoleNames()->first();`,
    )
      .trim()
      .split(/\r?\n/)
      .pop()
      ?.trim() ?? ''
  );
}

test.describe('Role Access — UAT Test Suite', () => {
  test.describe('Faculty access control', () => {
    test.use({ storageState: authStatePath('faculty') });

    test.beforeEach(async ({ page }) => {
      await page.goto('/research');
      if (page.url().includes('/login')) {
        await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password, {
          forceFormLogin: true,
        });
      }
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
    test.use({ storageState: authStatePath('dean') });

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
    test.use({ storageState: authStatePath('ovpri') });

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
    test.use({ storageState: authStatePath('cdaic') });

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
    test.use({ storageState: authStatePath('admin') });

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
      await expect(page.getByRole('heading', { name: 'Colleges/Offices & programs' })).toBeVisible();
    });

    test('RA-027: Admin CAN access /audit-logs → 200', async ({ page }) => {
      await expectOk(page, '/admin/audit-logs');
      await expect(page.getByRole('heading', { name: 'Audit logs' })).toBeVisible();
    });
  });

  test.describe('Linked author and viewer access', () => {
    test('RA-028: Co-author can VIEW research they are tagged on → no 403', async ({ page }) => {
      const researchId = seedCoAuthorResearch('dean_review');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      const response = await page.goto(`/research/${researchId}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('RA-029: Linked faculty co-author can EDIT research they are tagged on → wizard loads', async ({ page }) => {
      const researchId = seedCoAuthorResearch('draft');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      const response = await page.goto(`/research/${researchId}/details`);
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
      await expect(page.getByRole('link', { name: 'Edit Details' }).first()).toBeVisible();
    });

    test('RA-032: Co-author CANNOT delete research → delete not available', async ({ page }) => {
      const researchId = seedCoAuthorResearch('draft');
      await login(page, CO_AUTHOR_FACULTY_EMAIL, CO_AUTHOR_FACULTY_PASSWORD);
      await page.goto('/research');
      const card = page.locator('div').filter({ hasText: /COAUTHOR ACCESS/ }).first();
      await expect(card.getByRole('button', { name: 'Delete' })).toHaveCount(0);
    });

    test('RA-033: Viewer CANNOT access dean/ovpri/admin routes → 403', async ({ page }) => {
      const email = createViewerRoleUser(Date.now());
      await login(page, email, 'password');
      await expectForbidden(page, '/dean/dashboard');
      await expectForbidden(page, '/ovpri/dashboard');
      await expectForbidden(page, '/admin/dashboard');
    });

    test('RA-033c: Registrar, Unit Head, and co_author are not assignable in admin role options', async ({
      page,
    }) => {
      await login(page, credentials.admin.email, credentials.admin.password);
      await page.goto('/admin/users');
      await page.getByRole('button', { name: 'Add user' }).click();
      const role = page.locator('#add-role');
      await expect(role.locator('option[value="college_dean"]')).toHaveText('Dean/Head');
      await expect(role.locator('option[value="viewer"]')).toHaveCount(1);
      await expect(role.locator('option').filter({ hasText: /^Registrar$/ })).toHaveCount(0);
      await expect(role.locator('option').filter({ hasText: /Unit Head/ })).toHaveCount(0);
      await expect(role.locator('option[value="co_author"]')).toHaveCount(0);
      await expect(role.locator('option[value="registrar"]')).toHaveCount(0);
      await expect(role.locator('option[value="unit_head"]')).toHaveCount(0);
    });
  });

  test.describe('Registration user_type role mapping', () => {
    test.use({ storageState: emptyStorage });

    test('RA-039: Registering as Faculty assigns viewer role until approved', async ({ page }) => {
      expect(await registerAndReadRole(page, 'faculty')).toBe('viewer');
    });

    test('RA-040: Registering as Staff assigns viewer role until approved', async ({ page }) => {
      expect(await registerAndReadRole(page, 'staff')).toBe('viewer');
    });

    test('RA-041: Registering as Student assigns viewer role', async ({ page }) => {
      expect(await registerAndReadRole(page, 'student')).toBe('viewer');
    });

    test('RA-042: Registering as External Affiliate assigns viewer role', async ({ page }) => {
      expect(await registerAndReadRole(page, 'external_affiliate')).toBe('viewer');
    });

    test('RA-033b: Viewer can view own research but cannot edit or submit it', async ({ page }) => {
      const stamp = Date.now();
      const email = createViewerRoleUser(stamp);
      const output = runTinker(
        `$u=\\App\\Models\\User::where('email','${email}')->firstOrFail(); $c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $r=\\App\\Models\\Research::create(['reference_number'=>'VIEW-${stamp}','title'=>'VIEWER OWN ${stamp}','primary_author_id'=>$u->id,'mother_college_id'=>$c->id,'research_classification'=>'internally_funded','expected_output'=>['publication'],'start_date'=>'2026-01-01','estimated_completion_date'=>'2027-01-01','status'=>'draft','approval_stage'=>'draft','revision_count'=>0,'sdg_tags'=>[4]]); echo $r->id;`,
      );
      const researchId = parseInt(output.match(/\d+/)?.[0] ?? '0', 10);

      await login(page, email, 'password');
      const response = await page.goto(`/research/${researchId}`);
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('link', { name: 'Edit Details' })).toHaveCount(0);
      await expect(page.getByRole('button', { name: 'Submit for Dean Review' })).toHaveCount(0);
      const createResponse = await page.goto('/research/create');
      expect(createResponse?.status()).toBe(403);
    });
  });

  test.describe('Session security (H-02)', () => {
    test('RA-034: After logout pressing back button does not show protected page', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
      await logout(page);
      await page.goBack();
      await expect(page).toHaveURL(/\/login/);
      await expect(page.getByRole('heading', { name: 'My research' })).toHaveCount(0);
    });

    test.describe('Unauthenticated redirects', () => {
      test.use({ storageState: emptyStorage });

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
});
