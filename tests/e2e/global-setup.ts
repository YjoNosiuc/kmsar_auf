import { FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { refreshAuthStates } from './helpers/auth';
import { releaseSuiteLock } from './helpers/db-lock';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(__dirname, '../..');

export default async function globalSetup(_config: FullConfig): Promise<void> {
  if (process.env.KMSAR_SKIP_GLOBAL_SETUP === '1') {
    console.log('Skipping global setup (KMSAR_SKIP_GLOBAL_SETUP=1)');
    return;
  }

  console.log('Running global database reset...');
  execSync('php artisan migrate:fresh --seed --force', {
    cwd: PROJECT_ROOT,
    stdio: 'inherit',
    timeout: 120_000,
  });
  execSync('php artisan cache:clear', {
    cwd: PROJECT_ROOT,
    stdio: 'inherit',
    timeout: 60_000,
  });
  console.log('Database ready');

  releaseSuiteLock();

  console.log('Saving auth storage states...');
  await refreshAuthStates();
  console.log('Auth states ready');
}
