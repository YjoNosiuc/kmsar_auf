import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { AUTH_DIR } from './auth';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const LOCK_PATH = path.join(AUTH_DIR, 'suite.lock');

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Serialize mutating E2E suites across Playwright workers that share one MySQL DB.
 * Parallel-safe specs (security/api/a11y/role-access/performance) do not take this lock.
 */
export async function acquireSuiteLock(owner: string, timeoutMs = 45 * 60_000): Promise<void> {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  const started = Date.now();

  while (Date.now() - started < timeoutMs) {
    try {
      fs.writeFileSync(LOCK_PATH, `${owner}@${process.pid}\n`, { flag: 'wx' });
      return;
    } catch {
      await sleep(750);
    }
  }

  throw new Error(`Timed out waiting for suite lock (${owner})`);
}

export function releaseSuiteLock(): void {
  try {
    fs.unlinkSync(LOCK_PATH);
  } catch {
    /* ignore */
  }
}
