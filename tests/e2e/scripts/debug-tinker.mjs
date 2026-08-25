import { execFileSync } from 'child_process';

const root = 'C:/laragon/www/kmsar_auf';
const php = `echo config('database.connections.mysql.database');`;

try {
  const out = execFileSync('php', ['artisan', 'tinker', `--execute=${php}`], {
    cwd: root,
    stdio: 'pipe',
  }).toString();
  console.log('RAW:', JSON.stringify(out));
  console.log('PARSED:', out.trim().split(/\r?\n/).pop()?.trim());
} catch (e) {
  console.error('ERR', e.stderr?.toString() ?? e.message);
}
