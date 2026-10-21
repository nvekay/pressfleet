# Dashboard (Next.js standalone server). Build from the repository root:
#   docker build -f apps/web/Dockerfile .
FROM node:24-alpine AS build
RUN corepack enable
WORKDIR /repo

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml tsconfig.base.json ./
COPY packages/shared/package.json packages/shared/
COPY apps/web/package.json apps/web/
RUN pnpm install --frozen-lockfile --filter @pressfleet/web...

COPY packages/shared packages/shared
COPY apps/web apps/web
# Inlined into the browser bundle at build time
ARG NEXT_PUBLIC_GITHUB_URL=
ENV NEXT_PUBLIC_GITHUB_URL=$NEXT_PUBLIC_GITHUB_URL NEXT_TELEMETRY_DISABLED=1
RUN pnpm --filter @pressfleet/shared build && pnpm --filter @pressfleet/web build

FROM node:24-alpine
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 HOSTNAME=0.0.0.0 PORT=3000
WORKDIR /app
# The standalone output keeps the monorepo layout; static files and public/ are not traced, so they are copied
COPY --from=build --chown=node /repo/apps/web/.next/standalone ./
COPY --from=build --chown=node /repo/apps/web/.next/static ./apps/web/.next/static
COPY --from=build --chown=node /repo/apps/web/public ./apps/web/public
USER node
EXPOSE 3000
CMD ["node", "apps/web/server.js"]
