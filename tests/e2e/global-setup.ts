import { execSync } from 'child_process';

const PROJECT_ROOT = 'C:/laragon/www/kmsar_auf';

export default async function globalSetup(): Promise<void> {
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
}
