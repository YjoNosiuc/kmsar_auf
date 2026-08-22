import { test, expect, Page } from '@playwright/test';
import { login, logout, credentials } from './helpers/auth';
import { runTinker } from './helpers/db';
import { selectCurrentUserAsPrimary } from './helpers/research';

const SAMPLE_PDF = 'tests/e2e/fixtures/sample.pdf';

function stamp(): string {
  return `${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

async function registerAs(page: Page, userType: string): Promise<string> {
  const id = stamp();
  const email = `e2e.${userType}.${id}@auf.edu.ph`;
  await page.goto('/register');
  await page.fill('#first_name', 'E2E');
  await page.fill('#last_name', userType);
  await page.fill('#employee_number', `E2E-${id}`.slice(0, 20));
  await page.locator('#college_id').selectOption({ index: 1 });
  await page.locator('#user_type').selectOption(userType);
  await page.fill('#email', email);
  await page.fill('#password', 'password123');
  await page.fill('#password_confirmation', 'password123');
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page).toHaveURL(/\/research/);
  return email;
}

function roleFor(email: string): string {
  return runTinker(
    `echo \\App\\Models\\User::where('email','${email}')->firstOrFail()->getRoleNames()->first();`,
  ).trim().split(/\r?\n/).pop()?.trim() ?? '';
}

async function startAuthors(page: Page, title: string): Promise<string> {
  await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
  await page.goto('/research/create');
  await page.waitForURL(/\/research\/\d+\/details/);
  const researchId = page.url().match(/\/research\/(\d+)\//)?.[1] ?? '';
  await page.fill('textarea[name="title"]', title);
  await page.selectOption('select[name="research_classification"]', 'internally_funded');
  await page.check('input[name="expected_output[]"][value="publication"]');
  await page.fill('input[name="start_date"]', '2026-01-01');
  await page.fill('input[name="estimated_completion_date"]', '2027-01-01');
  await page.selectOption('select[name="status"]', 'proposal');
  await page.getByRole('button', { name: 'SDG 4', exact: true }).click();
  await page.getByRole('button', { name: 'Continue to authors' }).click();
  await expect(page).toHaveURL(/\/authors/);
  return researchId;
}

async function clearPrimary(page: Page): Promise<void> {
  const clear = page.getByRole('button', { name: 'Clear', exact: true });
  if (await clear.isVisible().catch(() => false)) {
    await clear.click();
  }
}

async function addCoAuthor(page: Page, email: string): Promise<void> {
  await page.locator('#coauthor-search').fill(email);
  const result = page.locator('.author-result').filter({ hasText: email });
  await expect(result).toBeVisible({ timeout: 15_000 });
  await result.click();
}

test.describe('Registration and author wizard — UAT', () => {
  test.describe.configure({ mode: 'serial', timeout: 120_000 });

  test('REG-001: Registration page has all User Type options', async ({ page }) => {
    await page.goto('/register');
    const userType = page.locator('#user_type');
    await expect(userType).toBeVisible();
    for (const label of ['Faculty', 'Staff', 'Student', 'External Affiliate']) {
      await expect(userType.locator('option').filter({ hasText: label })).toHaveCount(1);
    }
  });

  for (const [id, userType, expectedRole] of [
    ['REG-002', 'faculty', 'faculty'],
    ['REG-003', 'staff', 'faculty'],
    ['REG-004', 'student', 'viewer'],
    ['REG-005', 'external_affiliate', 'viewer'],
  ] as const) {
    test(`${id}: Registering as ${userType} assigns ${expectedRole} role`, async ({ page }) => {
      const email = await registerAs(page, userType);
      expect(roleFor(email)).toBe(expectedRole);
      await logout(page);
    });
  }

  test('REG-006: Viewer role user cannot register new research', async ({ page }) => {
    const email = await registerAs(page, 'student');
    const response = await page.goto('/research/create');
    expect(response?.status()).toBe(403);
    expect(roleFor(email)).toBe('viewer');
  });

  test('REG-007: Viewer can view research they are linked to as co-author', async ({ page }) => {
    const email = await registerAs(page, 'external_affiliate');
    const output = runTinker(
      `$u=\\App\\Models\\User::where('email','${email}')->firstOrFail(); $p=\\App\\Models\\User::where('email','faculty.ccs1@yopmail.com')->firstOrFail(); $c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $r=\\App\\Models\\Research::create(['reference_number'=>'REG-VIEW-${stamp()}','title'=>'REG VIEWER COAUTHOR','primary_author_id'=>$p->id,'mother_college_id'=>$c->id,'research_classification'=>'internally_funded','expected_output'=>['publication'],'start_date'=>'2026-01-01','estimated_completion_date'=>'2027-01-01','status'=>'proposal','approval_stage'=>'dean_review','revision_count'=>0,'sdg_tags'=>[4]]); \\App\\Models\\ResearchAuthor::create(['research_id'=>$r->id,'user_id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'is_primary'=>false,'can_edit'=>false]); echo $r->id;`,
    );
    const researchId = output.match(/\d+/)?.[0];
    const response = await page.goto(`/research/${researchId}`);
    expect(response?.status()).toBe(200);
  });

  test('REG-008: This is me selects the logged-in user as primary author', async ({ page }) => {
    await startAuthors(page, `REG008 ${stamp()}`);
    await clearPrimary(page);
    await page.getByRole('button', { name: 'This is me', exact: true }).click();
    await expect(page.locator('input[name="primary_author_user_id"]')).not.toHaveValue('');
    await expect(page.locator('.author-selected-card--primary')).toBeVisible();
    await expect(page.locator('.author-selected-card--primary .kmsar-badge')).toHaveText(/Primary Author/);
  });

  test('REG-009: Searching by name shows author results', async ({ page }) => {
    await startAuthors(page, `REG009 ${stamp()}`);
    await clearPrimary(page);
    await page.locator('#primary-author-search').fill('JUAN');
    await expect(page.locator('.author-result').first()).toBeVisible({ timeout: 15_000 });
  });

  test('REG-010: Searching by email shows author results', async ({ page }) => {
    await startAuthors(page, `REG010 ${stamp()}`);
    await clearPrimary(page);
    await page.locator('#primary-author-search').fill('faculty.ccs2@yopmail.com');
    await expect(page.locator('.author-result').filter({ hasText: 'faculty.ccs2@yopmail.com' })).toBeVisible();
  });

  test('REG-011: Selected primary is excluded from co-author search', async ({ page }) => {
    await startAuthors(page, `REG011 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.locator('#coauthor-search').fill(credentials.faculty_ccs.email);
    await expect(
      page.locator('#coauthor-search').locator('..').getByText(/No users found matching/i),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('REG-012: Same user cannot be primary and co-author', async ({ page }) => {
    await startAuthors(page, `REG012 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.locator('#coauthor-search').fill(credentials.faculty_ccs.email);
    await expect(page.locator('.author-result').filter({ hasText: credentials.faculty_ccs.email })).toHaveCount(0);
    await expect(page.locator('input[name^="coauthors"]')).toHaveCount(0);
  });

  test('REG-013: Multiple co-authors can be added', async ({ page }) => {
    await startAuthors(page, `REG013 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await addCoAuthor(page, 'faculty.ccs2@yopmail.com');
    await addCoAuthor(page, 'faculty.ccs3@yopmail.com');
    await expect(page.locator('input[name$="[user_id]"]')).toHaveCount(2);
  });

  test('REG-014: Removing a co-author updates the list', async ({ page }) => {
    await startAuthors(page, `REG014 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await addCoAuthor(page, 'faculty.ccs2@yopmail.com');
    await page.getByRole('button', { name: /Remove/i }).click();
    await expect(page.locator('input[name$="[user_id]"]')).toHaveCount(0);
    await expect(page.getByText('No co-authors selected.')).toBeVisible();
  });

  test('REG-015: Step 2 is locked with a lock icon before Step 1 completes', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research/create');
    await page.waitForURL(/\/details/);
    const step = page.locator('.kmsar-step.locked').filter({ hasText: 'Authors' });
    await expect(step).toBeVisible();
    await expect(step.locator('svg')).toBeVisible();
  });

  test('REG-016: Step 3 is locked before Step 2 completes', async ({ page }) => {
    await startAuthors(page, `REG016 ${stamp()}`);
    await expect(page.locator('.kmsar-step.locked').filter({ hasText: 'Documents' })).toBeVisible();
  });

  test('REG-017: Step 2 is accessible after Step 1 completes', async ({ page }) => {
    await startAuthors(page, `REG017 ${stamp()}`);
    await expect(page.locator('#kmsar-reg-stepper-nav a').filter({ hasText: 'Authors' })).toBeVisible();
  });

  test('REG-018: Step 3 is accessible after primary author is saved', async ({ page }) => {
    await startAuthors(page, `REG018 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await expect(page).toHaveURL(/\/documents/);
    await expect(page.locator('#kmsar-reg-stepper-nav a').filter({ hasText: 'Documents' })).toBeVisible();
  });

  test('REG-019: Direct Step 2 URL redirects to Step 1 when incomplete', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    await page.goto('/research/create');
    await page.waitForURL(/\/research\/\d+\/details/);
    const id = page.url().match(/\/research\/(\d+)\//)?.[1];
    await page.goto(`/research/${id}/authors`);
    await expect(page).toHaveURL(new RegExp(`/research/${id}/details`));
  });

  test('REG-020: Direct Step 3 URL redirects to Step 2 when authors incomplete', async ({ page }) => {
    const id = await startAuthors(page, `REG020 ${stamp()}`);
    await clearPrimary(page);
    await page.goto(`/research/${id}/documents`);
    await expect(page).toHaveURL(new RegExp(`/research/${id}/authors`));
  });

  test('REG-021: Documents has Submit for Dean Review and no Finish Registration', async ({ page }) => {
    await startAuthors(page, `REG021 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await expect(page.getByRole('button', { name: 'Submit for Dean Review' })).toBeVisible();
    await expect(page.getByText('Finish Registration')).toHaveCount(0);
  });

  test('REG-022: Submit is disabled when no documents are uploaded', async ({ page }) => {
    await startAuthors(page, `REG022 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await expect(page.getByRole('button', { name: 'Submit for Dean Review' })).toBeDisabled();
  });

  test('REG-023: Submit is enabled after a document is uploaded', async ({ page }) => {
    await startAuthors(page, `REG023 ${stamp()}`);
    await selectCurrentUserAsPrimary(page);
    await page.getByRole('button', { name: 'Continue to documents' }).click();
    await page.locator('#kmsar-document-file-input').setInputFiles(SAMPLE_PDF);
    await page.getByRole('button', { name: 'Save Document' }).click();
    await expect(page.getByRole('button', { name: 'Submit for Dean Review' })).toBeEnabled();
  });

  test('REG-024: Revise rejected research redirects to show with info message', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const id = runTinker(
      `$u=\\App\\Models\\User::where('email','faculty.ccs1@yopmail.com')->firstOrFail(); $c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $r=\\App\\Models\\Research::create(['reference_number'=>'REG-REV-${stamp()}','title'=>'REG REVISE','primary_author_id'=>$u->id,'mother_college_id'=>$c->id,'research_classification'=>'internally_funded','expected_output'=>['publication'],'start_date'=>'2026-01-01','estimated_completion_date'=>'2027-01-01','status'=>'proposal','approval_stage'=>'rejected','revision_count'=>1,'sdg_tags'=>[4]]); echo $r->id;`,
    ).match(/\d+/)?.[0];
    await page.goto(`/research/${id}`);
    await page.getByRole('button', { name: 'Revise' }).click();
    await expect(page).toHaveURL(new RegExp(`/research/${id}$`));
    await expect(page.getByText(/returned to draft/i)).toBeVisible();
  });

  test('REG-025: After Revise Edit Details links to wizard Step 1', async ({ page }) => {
    await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
    const id = runTinker(
      `$u=\\App\\Models\\User::where('email','faculty.ccs1@yopmail.com')->firstOrFail(); $c=\\App\\Models\\College::where('code','CCS')->firstOrFail(); $r=\\App\\Models\\Research::create(['reference_number'=>'REG-EDIT-${stamp()}','title'=>'REG EDIT DETAILS','primary_author_id'=>$u->id,'mother_college_id'=>$c->id,'research_classification'=>'internally_funded','expected_output'=>['publication'],'start_date'=>'2026-01-01','estimated_completion_date'=>'2027-01-01','status'=>'proposal','approval_stage'=>'rejected','revision_count'=>1,'sdg_tags'=>[4]]); echo $r->id;`,
    ).match(/\d+/)?.[0];
    await page.goto(`/research/${id}`);
    await page.getByRole('button', { name: 'Revise' }).click();
    await expect(page.getByRole('link', { name: 'Edit Details' }).first()).toHaveAttribute(
      'href',
      new RegExp(`/research/${id}/details$`),
    );
  });
});
