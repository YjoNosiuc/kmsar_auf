import { test, expect, APIRequestContext, Page } from '@playwright/test';
import { login, credentials } from './helpers/auth';
import { runTinker } from './helpers/db';
import { createAndSubmitResearch } from './helpers/research';

function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

async function csrfFrom(page: Page): Promise<string> {
  return (await page.locator('meta[name="csrf-token"]').getAttribute('content')) ?? '';
}

async function loginViaRequest(
  request: APIRequestContext,
  email: string,
  password: string,
): Promise<string> {
  const loginGet = await request.get('/login');
  const html = await loginGet.text();
  const token =
    html.match(/name="_token"\s+value="([^"]+)"/)?.[1] ??
    html.match(/name="_token".*?value="([^"]+)"/)?.[1] ??
    '';
  expect(token.length).toBeGreaterThan(0);

  const loginPost = await request.post('/login', {
    form: {
      _token: token,
      login: email,
      password,
    },
    maxRedirects: 5,
  });
  expect(loginPost.status()).toBeLessThan(500);

  const again = await request.get('/profile');
  const profileHtml = await again.text();
  const csrf =
    profileHtml.match(/name="csrf-token"\s+content="([^"]+)"/)?.[1] ??
    profileHtml.match(/csrf-token" content="([^"]+)"/)?.[1] ??
    token;
  return csrf;
}

function researchStage(id: string): string {
  const out = runTinker(`echo \\App\\Models\\Research::find(${id})?->approval_stage ?? 'missing';`);
  return out.trim().split(/\r?\n/).pop()?.trim() ?? 'missing';
}

function countResearchByTitle(title: string): number {
  const out = runTinker(
    `echo \\App\\Models\\Research::whereRaw('LOWER(title) = ?', [strtolower('${title.replace(/'/g, "\\'")}')])->count();`,
  );
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : -1;
}

function ccsCollegeId(): number {
  const out = runTinker(`echo \\App\\Models\\College::where('code','CCS')->value('id') ?? 0;`);
  const match = out.trim().match(/(\d+)\s*$/);
  return match ? parseInt(match[1], 10) : 0;
}

test.describe('API endpoint validation — UAT', () => {
  test.describe.configure({ timeout: 90_000 });

  // -------------------------------------------------------------------------
  test.describe('API endpoint security — unauthenticated requests', () => {
    test('API-001: POST /research without auth → 302 redirect to login (not 200 or 500)', async ({
      request,
    }) => {
      const loginHtml = await (await request.get('/login')).text();
      const token = loginHtml.match(/name="_token"\s+value="([^"]+)"/)?.[1] ?? '';
      const response = await request.post('/research', {
        form: { _token: token, title: 'Unauth create' },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).not.toBe(200);
      expect(response.status()).not.toBe(500);
      // Unauthenticated: redirect to login; missing CSRF alone would be 419
      expect([301, 302, 303, 307, 308, 419]).toContain(response.status());
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        expect(response.headers()['location'] ?? '').toMatch(/login/i);
      }
    });

    test('API-002: POST /logout without CSRF token → 419 CSRF error', async ({ request }) => {
      await loginViaRequest(request, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const response = await request.post('/logout', {
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).toBe(419);
    });

    test('API-003: DELETE /research/1 without auth → redirect to login', async ({ request }) => {
      const loginHtml = await (await request.get('/login')).text();
      const token = loginHtml.match(/name="_token"\s+value="([^"]+)"/)?.[1] ?? '';
      const response = await request.delete('/research/1', {
        headers: { 'X-CSRF-TOKEN': token },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).not.toBe(500);
      expect([301, 302, 303, 307, 308, 419]).toContain(response.status());
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        expect(response.headers()['location'] ?? '').toMatch(/login/i);
      }
    });

    test('API-004: POST /approval/1/endorse without auth → redirect to login', async ({
      request,
    }) => {
      const loginHtml = await (await request.get('/login')).text();
      const token = loginHtml.match(/name="_token"\s+value="([^"]+)"/)?.[1] ?? '';
      const response = await request.post('/approval/1/endorse', {
        form: {
          _token: token,
          remarks: 'Unauth endorse attempt for security test.',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect([301, 302, 303, 307, 308, 419]).toContain(response.status());
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        expect(response.headers()['location'] ?? '').toMatch(/login/i);
      }
    });

    test('API-005: GET /admin/users without auth → redirect to login', async ({ request }) => {
      const response = await request.get('/admin/users', { maxRedirects: 0 });
      expect([301, 302, 303, 307, 308]).toContain(response.status());
      expect(response.headers()['location'] ?? '').toMatch(/login/i);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('API endpoint security — wrong role', () => {
    test('API-006: Faculty POST to /approval/1/endorse → 403', async ({ page }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const csrf = await csrfFrom(page);
      const response = await page.request.post('/approval/1/endorse', {
        form: { _token: csrf, remarks: 'Faculty should not endorse research records.' },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).toBe(403);
    });

    test('API-007: Dean POST to /ovpri/approve/1 → 403', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const csrf = await csrfFrom(page);
      const response = await page.request.post('/ovpri/approve/1', {
        form: { _token: csrf, remarks: 'Dean should not approve at OVPRI level.' },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).toBe(403);
    });

    test('API-008: Faculty DELETE /documents/999 (not their document) → 403 or 404', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const csrf = await csrfFrom(page);
      const response = await page.request.delete('/documents/999', {
        headers: { 'X-CSRF-TOKEN': csrf },
        form: { _token: csrf, _method: 'DELETE' },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      // Laravel method spoofing via POST is common; also try explicit DELETE status
      let status = response.status();
      if (status === 405) {
        const spoof = await page.request.post('/documents/999', {
          form: { _token: csrf, _method: 'DELETE' },
          maxRedirects: 0,
          failOnStatusCode: false,
        });
        status = spoof.status();
      }
      expect([403, 404]).toContain(status);
    });

    test('API-009: Dean accessing /admin/users → 403', async ({ page }) => {
      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const response = await page.goto('/admin/users');
      expect(response?.status()).toBe(403);
    });

    test('API-010: OVPRI POST to /research → 403', async ({ page }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const csrf = await csrfFrom(page);
      const collegeId = ccsCollegeId();
      const response = await page.request.post('/research', {
        form: {
          _token: csrf,
          registration_type: 'new',
          title: uniqueTitle('API010 OVPRI Create'),
          mother_college_id: String(collegeId),
          research_classification: 'internally_funded',
          'expected_output[]': 'publication',
          sdg_tags: JSON.stringify([4]),
          start_date: '2026-01-01',
          estimated_completion_date: '2027-01-01',
          status: 'proposal',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      expect(response.status()).toBe(403);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('API response validation', () => {
    test('API-011: POST /notifications/{id}/read returns success response', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('API011 Read'));
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      await page.getByRole('button', { name: 'Notifications' }).click();

      const link = page.locator('a[onclick*="markRead"]').first();
      await expect(link).toBeVisible({ timeout: 15_000 });
      const onclick = await link.getAttribute('onclick');
      const notifId = onclick?.match(/markRead\('([^']+)'/)?.[1];
      expect(notifId).toBeTruthy();

      const csrf = await csrfFrom(page);
      const response = await page.request.post(`/notifications/${notifId}/read`, {
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
      });
      expect(response.ok()).toBeTruthy();
      const body = await response.json();
      expect(body.success).toBe(true);
    });

    test('API-012: POST /notifications/read-all returns success response', async ({ page }) => {
      await createAndSubmitResearch(page, uniqueTitle('API012 Read All'));
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      await page.goto('/research');
      const csrf = await csrfFrom(page);
      const response = await page.request.post('/notifications/read-all', {
        form: { _token: csrf },
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      // Controller returns back() → typically 302
      expect([200, 302, 303]).toContain(response.status());
      expect(response.status()).not.toBe(500);
      expect(response.status()).not.toBe(419);
    });

    test('API-013: GET /research returns only faculty\'s own research (not others)', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const response = await page.goto('/research');
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('heading', { name: /My research/i })).toBeVisible();
      // Seeded CBA-only reference should not appear on CCS faculty list
      await expect(page.getByText('AUF-2024-CBA-0001')).toHaveCount(0);
      await expect(page.getByText(/faculty\.cba1/i)).toHaveCount(0);
    });

    test('API-014: GET /ovpri/research returns all colleges research for OVPRI', async ({
      page,
    }) => {
      await login(page, credentials.ovpri.email, credentials.ovpri.password);
      const response = await page.goto('/ovpri/research');
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('heading', { name: /All research/i })).toBeVisible();
      await expect(page.locator('table tbody tr').first()).toBeVisible();
      // University-wide page should include multi-college filters / content
      const collegeFilter = page.locator('select[name="college_id"], #college_id');
      if (await collegeFilter.count()) {
        await expect(collegeFilter.first()).toBeVisible();
      }
      const body = await page.locator('main').innerText();
      expect(body.length).toBeGreaterThan(0);
    });

    test('API-015: POST /research with valid data creates research and returns redirect', async ({
      page,
    }) => {
      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const csrf = await csrfFrom(page);
      const collegeId = ccsCollegeId();
      const title = uniqueTitle('API015 Create Valid');

      const response = await page.request.post('/research', {
        form: {
          _token: csrf,
          registration_type: 'new',
          title,
          mother_college_id: String(collegeId),
          research_classification: 'internally_funded',
          'expected_output[]': 'publication',
          sdg_tags: JSON.stringify([4]),
          start_date: '2026-01-01',
          estimated_completion_date: '2027-01-01',
          status: 'proposal',
        },
        maxRedirects: 0,
        failOnStatusCode: false,
      });

      expect([302, 303]).toContain(response.status());
      expect(response.status()).not.toBe(500);
      expect(countResearchByTitle(title)).toBeGreaterThan(0);
    });
  });

  // -------------------------------------------------------------------------
  test.describe('Rate limiting and edge cases', () => {
    test('API-016: Rapid repeated login attempts with wrong password → still shows error (no server crash)', async ({
      request,
    }) => {
      for (let i = 0; i < 5; i++) {
        const loginGet = await request.get('/login');
        const html = await loginGet.text();
        const token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1] ?? '';
        const response = await request.post('/login', {
          form: {
            _token: token,
            login: credentials.faculty_ccs.email,
            password: `wrong-password-${i}`,
          },
          maxRedirects: 0,
          failOnStatusCode: false,
        });
        expect(response.status()).not.toBe(500);
        expect([200, 302, 422, 429]).toContain(response.status());
      }

      // Still can reach login page after rapid failures
      const finalGet = await request.get('/login');
      expect(finalGet.status()).toBe(200);
    });

    test('API-017: Submitting research twice rapidly → only one research created (duplicate prevention)', async ({
      page,
    }) => {
      const title = uniqueTitle('API017 Dup Submit');
      const researchId = await createAndSubmitResearch(page, title);
      expect(researchId).toBeTruthy();
      expect(countResearchByTitle(title)).toBe(1);

      await login(page, credentials.faculty_ccs.email, credentials.faculty_ccs.password);
      const csrf = await csrfFrom(page);

      // Research already submitted — concurrent re-submit should not create a second record
      const [a, b] = await Promise.all([
        page.request.post(`/research/${researchId}/submit`, {
          form: { _token: csrf },
          maxRedirects: 0,
          failOnStatusCode: false,
        }),
        page.request.post(`/research/${researchId}/submit`, {
          form: { _token: csrf },
          maxRedirects: 0,
          failOnStatusCode: false,
        }),
      ]);

      expect(a.status()).not.toBe(500);
      expect(b.status()).not.toBe(500);
      expect(countResearchByTitle(title)).toBe(1);
      expect(researchStage(researchId!)).toBe('dean_review');
    });

    test('API-018: Concurrent endorse and return on same research → only one action succeeds', async ({
      page,
      browser,
    }) => {
      const title = uniqueTitle('API018 Race');
      const researchId = await createAndSubmitResearch(page, title);
      expect(researchId).toBeTruthy();

      await login(page, credentials.dean_ccs.email, credentials.dean_ccs.password);
      const csrf = await csrfFrom(page);
      const storage = await page.context().storageState();

      // Use two isolated API contexts sharing the dean session to avoid clobbering one request
      const ctxA = await browser.newContext({ storageState: storage, baseURL: 'http://kmsar_auf.test' });
      const ctxB = await browser.newContext({ storageState: storage, baseURL: 'http://kmsar_auf.test' });

      try {
        const [endorseRes, returnRes] = await Promise.all([
          ctxA.request.post(`/approval/${researchId}/endorse`, {
            form: {
              _token: csrf,
              remarks: 'Concurrent endorse path for race-condition E2E coverage.',
            },
            maxRedirects: 0,
            failOnStatusCode: false,
          }),
          ctxB.request.post(`/approval/${researchId}/return`, {
            form: {
              _token: csrf,
              remarks: 'Concurrent return path for race-condition E2E coverage.',
            },
            maxRedirects: 0,
            failOnStatusCode: false,
          }),
        ]);

        expect(endorseRes.status()).not.toBe(500);
        expect(returnRes.status()).not.toBe(500);

        const stage = researchStage(researchId!);
        expect(['ovpri_review', 'draft']).toContain(stage);
      } finally {
        await ctxA.close();
        await ctxB.close();
      }
    });
  });
});
