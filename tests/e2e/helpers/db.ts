import { execSync } from 'child_process';
import { refreshAuthStates } from './auth';

const PROJECT_ROOT = 'C:/laragon/www/kmsar_auf';

export function resetDatabase(retries = 3): void {
  for (let i = 0; i < retries; i++) {
    try {
      execSync('php artisan migrate:fresh --seed --force', {
        cwd: PROJECT_ROOT,
        stdio: 'pipe',
        timeout: 120_000,
      });
      // Warmup — give DB / app caches time to settle after fresh migrate
      execSync('php artisan cache:clear', {
        cwd: PROJECT_ROOT,
        stdio: 'pipe',
        timeout: 60_000,
      });
      return;
    } catch (error) {
      if (i === retries - 1) {
        throw error;
      }
      console.warn(`resetDatabase attempt ${i + 1} failed, retrying...`);
      // Windows ~3s pause before retry
      execSync('ping -n 4 127.0.0.1 > nul', { stdio: 'pipe', shell: true });
    }
  }
}

/** migrate:fresh --seed, then rewrite storageState files so cookies match new session rows. */
export async function resetDatabaseAndAuth(): Promise<void> {
  resetDatabase();
  await refreshAuthStates();
}

export function runArtisan(command: string) {
  return execSync(`php artisan ${command}`, {
    cwd: PROJECT_ROOT,
    stdio: 'pipe',
    shell: true,
  }).toString();
}

/** Run one-liner PHP via artisan tinker (use single quotes inside PHP strings). */
export function runTinker(php: string) {
  return execSync(`php artisan tinker --execute="${php}"`, {
    cwd: PROJECT_ROOT,
    stdio: 'pipe',
    shell: true,
  }).toString();
}
