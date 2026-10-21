import { existsSync } from 'node:fs';
import type { NextConfig } from 'next';

// One .env at the repository root serves every app in development; production sets real variables
if (existsSync('../../.env')) process.loadEnvFile('../../.env');

const nextConfig: NextConfig = {
  devIndicators: false,
  experimental: {
    // A CSV import of up to 2 MB, as the API accepts
    serverActions: { bodySizeLimit: '2.5mb' },
  },
};

export default nextConfig;
