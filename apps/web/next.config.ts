import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import type { NextConfig } from 'next';

// One .env at the repository root serves every app in development; production sets real variables
if (existsSync('../../.env')) process.loadEnvFile('../../.env');

const nextConfig: NextConfig = {
  devIndicators: false,
  // A self-contained server for the Docker image; tracing starts at the repository root for the workspace packages
  output: 'standalone',
  outputFileTracingRoot: fileURLToPath(new URL('../..', import.meta.url)),
  experimental: {
    // A CSV import of up to 2 MB, as the API accepts
    serverActions: { bodySizeLimit: '2.5mb' },
  },
};

export default nextConfig;
