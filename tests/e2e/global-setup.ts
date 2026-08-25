import { FullConfig } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { credentials, refreshAuthStates } from './helpers/auth';
import { e2eCliEnv, runTinker } from './helpers/db';
import { releaseSuiteLock } from './helpers/db-lock';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(__dirname, '../..');

export default async function globalSetup(_config: FullConfig): Promise<void> {
  if (process.env.KMSAR_SKIP_GLOBAL_SETUP === '1') {
    console.log('Skipping global setup (KMSAR_SKIP_GLOBAL_SETUP=1)');
    return;
  }

  console.log('Running global database reset...');
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
    cwd: PROJECT_ROOT,
    stdio: 'inherit',
    timeout: 120_000,
    env: e2eCliEnv(),
  });
  execFileSync('php', ['artisan', 'cache:clear'], {
    cwd: PROJECT_ROOT,
    stdio: 'inherit',
    timeout: 60_000,
    env: e2eCliEnv(),
  });
  console.log('Database ready');

  const seededFaculty = runTinker(
    `echo \\App\\Models\\User::where('email','${credentials.faculty_ccs.email}')->exists() ? '1' : '0';`,
  );
  if (!seededFaculty.includes('1')) {
    throw new Error(`Seeded faculty account missing: ${credentials.faculty_ccs.email}`);
  }

  releaseSuiteLock();

  console.log('Saving auth storage states...');
  await refreshAuthStates();
  console.log('Auth states ready');
}
