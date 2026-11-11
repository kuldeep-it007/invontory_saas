# ─── Frontend image: Next.js (App Router) standalone ────────────────────────
#
# Multi-stage build:
#   1) deps  — install npm dependencies (cached unless package*.json change)
#   2) build — compile the Next.js app to a self-contained standalone server
#   3) run   — minimal Node image running `node server.js`
#
# NEXT_PUBLIC_* values are inlined at BUILD time, so the API base URL is passed
# as a build arg (defaulting to the compose-internal API URL).

# ---------- stage 1: dependencies ----------
FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci

# ---------- stage 2: build ----------
FROM node:20-alpine AS build
WORKDIR /app

# Public API base URL is baked into the client bundle at build time.
ARG NEXT_PUBLIC_API_BASE_URL=http://localhost:8000
ARG NEXT_PUBLIC_TENANT_HOST
ENV NEXT_PUBLIC_API_BASE_URL=${NEXT_PUBLIC_API_BASE_URL}
ENV NEXT_PUBLIC_TENANT_HOST=${NEXT_PUBLIC_TENANT_HOST}
ENV NEXT_TELEMETRY_DISABLED=1

COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

# ---------- stage 3: runtime ----------
FROM node:20-alpine AS run
WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# The standalone output contains a minimal server + only the needed modules.
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public

EXPOSE 3000
CMD ["node", "server.js"]
