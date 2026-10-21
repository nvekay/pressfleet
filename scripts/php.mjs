// Runs PHP tooling for the plugin in Docker, so no local PHP or Composer is needed.
//   pnpm php composer install
//   pnpm php composer lint | analyse | test:unit
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const wp = resolve(import.meta.dirname, '..', 'wp');
const args = process.argv.slice(2);
if (!args.length) {
  console.error('Usage: pnpm php <command> [args]   e.g. pnpm php composer lint');
  process.exit(1);
}

const result = spawnSync(
  'docker',
  [
    'run',
    '--rm',
    '-v',
    // The whole wp/ folder, so the plugin's tooling can also check the theme
    `${wp}:/wp`,
    '-w',
    '/wp/plugins/pressfleet-connector',
    '-e',
    'COMPOSER_CACHE_DIR=/wp/plugins/pressfleet-connector/vendor/.cache',
    'composer:2',
    ...args,
  ],
  // Git Bash on Windows would rewrite /app into a Windows path
  { stdio: 'inherit', env: { ...process.env, MSYS_NO_PATHCONV: '1' } },
);
process.exit(result.status ?? 1);
