import { execSync } from 'child_process';

const PROJECT_ROOT = 'C:/laragon/www/kmsar_auf';

export function resetDatabase() {
  execSync('php artisan migrate:fresh --seed --force', {
    cwd: PROJECT_ROOT,
    stdio: 'pipe',
  });
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
