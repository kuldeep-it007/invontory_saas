# Inventory & Distribution Platform — Frontend

Next.js (App Router) + TypeScript + Tailwind (shadcn/ui conventions) + React Query
frontend. This scaffold's headline feature is the **white-label theme loader**
(spec task 3.5, Requirements R3.2 and R37.4).

## Getting started

```bash
npm install
cp .env.example .env.local   # adjust if needed
npm run dev                  # http://localhost:3000
npm run typecheck            # tsc --noEmit
npm run build
```

## White-label theme loader

On page load the app fetches the resolved tenant's branding from the backend
`GET /api/tenant/settings` and applies it to the page:

- **Brand name** → `<title>` and the header (`BrandLogo`).
- **Logo** → rendered in the header; falls back to the brand name text.
- **Colour theme** → written as CSS variables (`--primary`, `--background`, …)
  that Tailwind reads, so components restyle without a rebuild.
- **Favicon** → emitted via Next metadata and re-applied client-side.

### Where it runs

| Layer | File | Responsibility |
|-------|------|----------------|
| Server fetch | `src/lib/branding/server.ts` | Reads request host, calls the API |
| API client | `src/lib/branding/client.ts` | `fetch` + host forwarding + fallback |
| Resolve/parse | `src/lib/branding/resolve.ts` | Raw settings → `Branding`, null-safe |
| Apply to DOM | `src/lib/branding/apply.ts` | Theme CSS vars, favicon, title |
| Root layout | `src/app/layout.tsx` | Server-renders theme vars inline (no FOUC) |
| Client provider | `src/components/branding/branding-provider.tsx` | Context + re-apply after hydration |

The root layout is a **server component**: it resolves branding during the
initial render and writes the theme variables inline on `<html>`, so the first
paint is already themed (no flash of unbranded content).

## Tenant resolution — host forwarding (dev vs prod)

The backend resolves the tenant from the **request host** (subdomain or custom
domain) using `stancl/tenancy`. The frontend ensures that host reaches the API:

- **Production.** The frontend and API share the tenant hostnames (e.g.
  `acme.app.example.com`, or a custom domain `shop.acme.test`). For server-side
  fetches the layout reads the incoming `x-forwarded-host`/`host` header and
  forwards it to the API as `Host` + `X-Forwarded-Host`. Client-side fetches are
  same-origin and relative, so the browser's own Host header drives resolution.

- **Development.** The frontend runs on `localhost:3000` and the API on
  `localhost:8000`, with no tenant subdomain. Set `NEXT_PUBLIC_TENANT_HOST`
  (e.g. `acme.localhost`) to pin the tenant host the frontend forwards, so the
  backend resolves a real tenant locally.

## Resilience (zero-config boot — R37.4)

If branding can't be resolved — backend unreachable, host unresolved, non-2xx
response, malformed JSON, or every setting `null` — the loader falls back to
`DEFAULT_BRANDING` and the default CSS variables in `globals.css`. The app
always renders a complete, responsive, branded-looking UI.

## Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `NEXT_PUBLIC_API_BASE_URL` | `http://localhost:8000` | Laravel API base URL (server-side fetches) |
| `NEXT_PUBLIC_TENANT_HOST` | _(unset)_ | Dev-only tenant host override for local resolution |
