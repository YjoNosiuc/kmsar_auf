import { execFileSync, execSync } from 'child_process';
import { refreshAuthStates } from './auth';

const PROJECT_ROOT = 'C:/laragon/www/kmsar_auf';

/** Match Laragon web app (.env local) — not phpunit.xml / testing env vars in the shell. */
export function e2eCliEnv(): NodeJS.ProcessEnv {
  return {
    ...process.env,
    APP_ENV: 'local',
    DB_DATABASE: 'kmsar_auf',
  };
}

export function resetDatabase(retries = 3): void {
  for (let i = 0; i < retries; i++) {
    try {
      execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
        cwd: PROJECT_ROOT,
        stdio: 'pipe',
        timeout: 120_000,
        env: e2eCliEnv(),
      });
      execFileSync('php', ['artisan', 'cache:clear'], {
        cwd: PROJECT_ROOT,
        stdio: 'pipe',
        timeout: 60_000,
        env: e2eCliEnv(),
      });
      return;
    } catch (error) {
      if (i === retries - 1) {
        throw error;
      }
      console.warn(`resetDatabase attempt ${i + 1} failed, retrying...`);
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
  const [binary, ...args] = ['php', 'artisan', ...command.split(' ')];
  return execFileSync(binary, args, {
    cwd: PROJECT_ROOT,
    stdio: 'pipe',
    env: e2eCliEnv(),
  }).toString();
}

/** Run one-liner PHP via artisan tinker (use single quotes inside PHP strings). */
export function runTinker(php: string) {
  return execFileSync('php', ['artisan', 'tinker', `--execute=${php}`], {
    cwd: PROJECT_ROOT,
    stdio: 'pipe',
    env: e2eCliEnv(),
  }).toString();
}
