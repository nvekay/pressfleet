import 'server-only';
import { z } from 'zod';

const schema = z.object({
  API_URL: z.url().default('http://localhost:4000'),
  API_INTERNAL_KEY: z.string().min(16),
});

let cached: z.infer<typeof schema> | undefined;

/** Parsed on first use, so builds work without runtime secrets */
export function env() {
  cached ??= schema.parse(process.env);
  return cached;
}
