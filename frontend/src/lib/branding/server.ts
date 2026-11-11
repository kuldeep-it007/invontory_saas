import { headers } from "next/headers";

import { fetchBranding } from "./client";
import type { Branding } from "./types";

/**
 * Server-side branding resolution for the root layout (R3.2).
 *
 * Reads the incoming request host from Next's `headers()` so the backend
 * resolves the correct tenant, then forwards it to `fetchBranding`. Preferring
 * `x-forwarded-host` covers deployments behind a proxy/load balancer; `host`
 * is the direct fallback. In dev the `client` falls back to
 * `NEXT_PUBLIC_TENANT_HOST` when no tenant host is present.
 */
export async function resolveBrandingForRequest(): Promise<Branding> {
  const h = headers();
  const host = h.get("x-forwarded-host") ?? h.get("host");
  return fetchBranding(host);
}
