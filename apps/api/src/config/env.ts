import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { z } from 'zod';

// One .env at the repository root serves every app in development; production sets real variables
const rootEnv = fileURLToPath(new URL('../../../../.env', import.meta.url));
if (existsSync(rootEnv)) process.loadEnvFile(rootEnv);

const schema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  PORT: z.coerce.number().default(4000),
  DATABASE_URL: z.url(),
  REDIS_URL: z.url(),
  // Shared secret between the web app's server and this API; the browser never sees it
  API_INTERNAL_KEY: z.string().min(16),
  // Encrypts site credentials (Application Passwords, signing secrets) at rest: 32 bytes, base64
  SECRETS_KEY: z
    .string()
    .refine((v) => Buffer.from(v, 'base64').length === 32, 'SECRETS_KEY must be 32 bytes in base64'),
  WEB_URL: z.url().default('http://localhost:3000'),
  // Requests to one site at a time; a small shared host should not get the whole campaign at once
  PUBLISH_CONCURRENCY_PER_SITE: z.coerce.number().int().min(1).max(10).default(2),
  PUBLISH_ATTEMPTS: z.coerce.number().int().min(1).max(20).default(8),
  // First retry delay; doubles each attempt up to the cap (tests use a few ms)
  RETRY_DELAY_MS: z.coerce.number().int().positive().default(5000),
  RETRY_MAX_DELAY_MS: z.coerce.number().int().positive().default(120_000),
  WP_TIMEOUT_MS: z.coerce.number().int().positive().default(30_000),
});

export type Env = z.infer<typeof schema>;

let cached: Env | undefined;

/** Validated configuration; fails fast at startup with every missing variable listed */
export function env(): Env {
  if (!cached) {
    const parsed = schema.safeParse(process.env);
    if (!parsed.success) throw new Error(`Invalid environment:\n${z.prettifyError(parsed.error)}`);
    cached = parsed.data;
  }
  return cached;
}
