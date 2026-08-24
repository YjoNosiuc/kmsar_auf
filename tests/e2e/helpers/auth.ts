import { chromium, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const facultyCamp = { email: 'faculty.camp1@yopmail.com', password: 'password' };
const deanCamp = { email: 'dean.camp@yopmail.com', password: 'password' };

export const credentials = {
  faculty_ccs: { email: 'faculty.ccs1@yopmail.com', password: 'password' },
  faculty_ccs2: { email: 'faculty.ccs2@yopmail.com', password: 'password' },
  faculty_camp: facultyCamp,
  faculty_camp2: { email: 'faculty.camp2@yopmail.com', password: 'password' },
  faculty_cba: facultyCamp,
  dean_ccs: { email: 'dean.ccs@yopmail.com', password: 'password' },
  dean_camp: deanCamp,
  dean_cba: deanCamp,
  ovpri: { email: 'ovpri@yopmail.com', password: 'password' },
  cdaic: { email: 'cdaic@yopmail.com', password: 'password' },
  admin: { email: 'admin@yopmail.com', password: 'password' },
};

export const AUTH_DIR = path.resolve(__dirname, '../.auth');

export type AuthRole = 'faculty' | 'dean' | 'ovpri' | 'cdaic' | 'admin';

export function authStatePath(role: AuthRole | string): string {
  return path.join(AUTH_DIR, `${role}.json`);
}

export function shortEmployeeNumber(prefix: string, stamp: number): string {
  const prefixDigits = prefix.replace(/\D/g, '').slice(0, 2);
  const suffix = String(stamp).replace(/\D/g, '').slice(-8);

  return `${prefixDigits}${suffix}`.slice(0, 10);
}

const emailToRole: Record<string, AuthRole> = {
  [credentials.faculty_ccs.email]: 'faculty',
  [credentials.dean_ccs.email]: 'dean',
  [credentials.ovpri.email]: 'ovpri',
  [credentials.cdaic.email]: 'cdaic',
  [credentials.admin.email]: 'admin',
};

const keepAliveTimers = new WeakMap<Page, ReturnType<typeof setInterval>>();

/** Keep the idle-timeout modal from firing during long Playwright waits. */
export function startKeepAlive(page: Page): void {
  stopKeepAlive(page);
  const timer = setInterval(async () => {
    try {
      await page.mouse.move(100 + Math.random() * 10, 100 + Math.random() * 10);
    } catch {
      /* page may be closed */
    }
  }, 45_000);
  keepAliveTimers.set(page, timer);
}

export function stopKeepAlive(page: Page): void {
  const timer = keepAliveTimers.get(page);
  if (timer) {
    clearInterval(timer);
    keepAliveTimers.delete(page);
  }
}

async function applyStoredCookies(page: Page, statePath: string): Promise<boolean> {
  try {
    const raw = fs.readFileSync(statePath, 'utf8');
    const state = JSON.parse(raw) as { cookies?: Array<Record<string, unknown>> };
    if (!state.cookies?.length) {
      return false;
    }
    await page.context().clearCookies();
    await page.context().addCookies(state.cookies as Parameters<typeof page.context.addCookies>[0]);
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
    return !page.url().includes('/login');
  } catch {
    return false;
  }
}

export async function login(
  page: Page,
  email: string,
  password: string,
  options?: { forceFormLogin?: boolean },
): Promise<void> {
  stopKeepAlive(page);

  const role = emailToRole[email];
  const statePath = role ? authStatePath(role) : null;

  if (!options?.forceFormLogin && statePath && fs.existsSync(statePath)) {
    const ok = await applyStoredCookies(page, statePath);
    if (ok) {
      startKeepAlive(page);
      return;
    }
  }

  await page.goto('/login');

  const loginInput = page.locator('input[name="login"]');
  const onLoginForm = await loginInput.isVisible({ timeout: 3_000 }).catch(() => false);

  if (!onLoginForm) {
    const signOut = page.getByRole('button', { name: /sign out/i });
    if (await signOut.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await signOut.click();
      await page.waitForURL(
        (url) => url.pathname.endsWith('/login') || url.pathname === '/',
        { timeout: 15_000 },
      );
    }
    await page.goto('/login');
  }

  await page.fill('input[name="login"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 60_000 });
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
  startKeepAlive(page);
}

export async function logout(page: Page): Promise<void> {
  stopKeepAlive(page);
  await page.click('button[type="submit"]:has-text("Sign Out")');
  await page.waitForURL((url) => url.pathname.endsWith('/login') || url.pathname === '/', {
    timeout: 15_000,
  });
  if (!page.url().includes('/login')) {
    await page.goto('/login');
  }
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
}

const roleHomePath: Record<AuthRole, string> = {
  faculty: '/research',
  dean: '/dean/dashboard',
  ovpri: '/ovpri/dashboard',
  cdaic: '/ovpri/dashboard',
  admin: '/admin/dashboard',
};

async function sessionIsValid(page: Page, homePath: string): Promise<boolean> {
  await page.goto(homePath, { waitUntil: 'domcontentloaded' });
  return !page.url().includes('/login');
}

/** Re-login each seeded role and overwrite tests/e2e/.auth/*.json (call after migrate:fresh). */
export async function refreshAuthStates(baseURL = 'http://kmsar_auf.test'): Promise<void> {
  fs.mkdirSync(AUTH_DIR, { recursive: true });

  const roles = [
    { name: 'faculty' as const, email: credentials.faculty_ccs.email, password: credentials.faculty_ccs.password },
    { name: 'dean' as const, email: credentials.dean_ccs.email, password: credentials.dean_ccs.password },
    { name: 'ovpri' as const, email: credentials.ovpri.email, password: credentials.ovpri.password },
    { name: 'cdaic' as const, email: credentials.cdaic.email, password: credentials.cdaic.password },
    { name: 'admin' as const, email: credentials.admin.email, password: credentials.admin.password },
  ];

  const browser = await chromium.launch({ headless: true });
  try {
    for (const role of roles) {
      const statePath = authStatePath(role.name);
      const homePath = roleHomePath[role.name];
      let saved = false;

      for (let attempt = 1; attempt <= 2 && !saved; attempt++) {
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();
        await login(page, role.email, role.password, { forceFormLogin: true });
        if (!(await sessionIsValid(page, homePath))) {
          stopKeepAlive(page);
          await context.close();
          continue;
        }
        await context.storageState({ path: statePath });
        stopKeepAlive(page);
        await context.close();

        const verify = await browser.newContext({ baseURL, storageState: statePath });
        const verifyPage = await verify.newPage();
        saved = await sessionIsValid(verifyPage, homePath);
        await verify.close();
      }

      if (!saved) {
        throw new Error(`Failed to persist a valid ${role.name} auth state for ${role.email}`);
      }
    }
  } finally {
    await browser.close();
  }
}
