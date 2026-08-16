import { spawnSync } from 'child_process';
import { fileURLToPath } from 'url';
import path from 'path';

/**
 * Runs parallel then sequential projects. Sequential always runs even if parallel
 * has failures (unlike Playwright project `dependencies`).
 * Global setup (DB reset + auth) runs only on the first invocation.
 */
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const extraArgs = process.argv.slice(2);

function run(args, env = {}) {
  return spawnSync('npx', ['playwright', 'test', ...args, ...extraArgs], {
    cwd: root,
    stdio: 'inherit',
    shell: true,
    env: { ...process.env, ...env },
  });
}

const parallel = run(['--project=parallel']);
const sequential = run(['--project=sequential', '--workers=1', '--no-deps'], {
  KMSAR_SKIP_GLOBAL_SETUP: '1',
});

const code = parallel.status || sequential.status || 0;
process.exit(code === null ? 1 : code);
