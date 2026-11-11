import { DEFAULT_BRANDING } from "./defaults";
import { resolveBranding } from "./resolve";
import type { Branding, TenantSettingsResponse } from "./types";

/**
 * Backend client for white-label branding (R3.2).
 *
 * The backend resolves the tenant from the *request host* (subdomain or custom
 * domain) via `stancl/tenancy`. The frontend therefore has to make sure the
 * host the user hit reaches the backend as the resolution host.
 *
 * ── Host forwarding: dev vs prod ───────────────────────────────────────────
 *
 * PROD: the backend lives behind the same hostnames as the frontend (e.g.
 *   `acme.app.example.com` / a tenant custom domain `shop.acme.test`). The
 *   browser's own Host header already carries the tenant host, so a same-origin
 *   relative request (`/api/tenant/settings`) resolves the right tenant with no
 *   extra work. For *server-side* fetches we read the incoming request host
 *   from Next's `headers()` and forward it to the API base URL as an explicit
 *   `Host` (and `X-Forwarded-Host`) header so the API still resolves by the
 *   user's host even though the server-to-API hop targets an internal URL.
 *
 * DEV: the frontend runs on `localhost:3000` and the API on
 *   `localhost:8000`, so there is no tenant subdomain in the host. We let the
 *   developer pin a tenant host with `NEXT_PUBLIC_TENANT_HOST` (e.g.
 *   `acme.localhost`), which we send as the forwarded host so the backend's
 *   `InitializeTenancyByDomainOrSubdomain` resolves a real tenant locally.
 *
 * All failures are swallowed and fall back to defaults so a missing/unreachable
 * backend never blocks rendering (R37.4).
 */

/** Base URL of the Laravel API. Configure via `NEXT_PUBLIC_API_BASE_URL`. */
export function apiBaseUrl(): string {
  return (
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    process.env.API_BASE_URL ??
    "http://localhost:8000"
  ).replace(/\/+$/, "");
}

/** Dev-only override: pin the tenant resolution host when running locally. */
function devTenantHost(): string | null {
  return process.env.NEXT_PUBLIC_TENANT_HOST ?? null;
}

const SETTINGS_PATH = "/api/tenant/settings";

/**
 * Build the headers forwarding the resolution host to the backend. On the
 * server we pass the caller's host (or the dev override); on the client we rely
 * on the browser Host header and only add the dev override when present.
 */
function hostHeaders(host: string | null): HeadersInit {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (host) {
    // `X-Forwarded-Host` is honoured by stancl/tenancy host resolution behind
    // a proxy; `Host` covers direct resolution. Sending both keeps dev and
    // prod paths identical from the backend's perspective.
    headers["X-Forwarded-Host"] = host;
    headers["Host"] = host;
  }
  return headers;
}

/**
 * Fetch raw tenant settings and resolve them to a `Branding`. Returns default
 * branding on any error, non-2xx response, or malformed body (R37.4).
 *
 * @param host Explicit resolution host to forward (server-side). Omit on the
 *   client to use the browser's own Host via a same-origin relative request.
 */
export async function fetchBranding(host?: string | null): Promise<Branding> {
  const resolvedHost = host ?? devTenantHost();

  // On the client with no explicit host, go same-origin+relative so the
  // browser's Host header (the tenant host) drives resolution. Otherwise hit
  // the configured API base and forward the host explicitly.
  const useRelative = typeof window !== "undefined" && !resolvedHost;
  const url = useRelative ? SETTINGS_PATH : `${apiBaseUrl()}${SETTINGS_PATH}`;

  try {
    const res = await fetch(url, {
      headers: hostHeaders(resolvedHost ?? null),
      // Branding is per-tenant and may change; revalidate often but allow a
      // short cache so first paint is fast. Server components honour this.
      next: { revalidate: 60 },
      cache: "no-store",
    });

    if (!res.ok) return DEFAULT_BRANDING;

    const body = (await res.json()) as Partial<TenantSettingsResponse>;
    if (!body || typeof body !== "object" || !("data" in body) || !body.data) {
      return DEFAULT_BRANDING;
    }

    return resolveBranding(body.data);
  } catch {
    // Network error, DNS failure, timeout, invalid JSON — boot with defaults.
    return DEFAULT_BRANDING;
  }
}
