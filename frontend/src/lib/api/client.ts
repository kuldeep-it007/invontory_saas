import { apiBaseUrl } from "@/lib/branding/client";

import { getToken } from "./token";
import { ApiError, type ApiErrorBody } from "./types";

/**
 * Thin fetch wrapper for the Laravel JSON REST API.
 *
 * Responsibilities:
 *   - Prefix the configured API base URL (NEXT_PUBLIC_API_BASE_URL).
 *   - Attach the Sanctum bearer token when present.
 *   - Forward the dev tenant host so stancl/tenancy resolves the right tenant
 *     database locally (prod relies on the browser Host).
 *   - Normalize the `{ error: { code, message, details } }` envelope into a
 *     typed `ApiError`.
 *
 * This runs in the browser (client components / React Query). Server-side data
 * fetching for branding stays in `lib/branding`.
 */

const DEV_TENANT_HOST = process.env.NEXT_PUBLIC_TENANT_HOST ?? null;

export interface RequestOptions {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  /** JSON body — serialized automatically. A FormData body is sent as-is (file upload). */
  body?: unknown;
  /** Query string params; null/undefined entries are dropped. */
  query?: Record<string, string | number | boolean | null | undefined>;
  signal?: AbortSignal;
}

function buildUrl(path: string, query?: RequestOptions["query"]): string {
  const base = apiBaseUrl();
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;

  // Build the query string once.
  const search = new URLSearchParams();
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== null && value !== undefined && value !== "") {
        search.set(key, String(value));
      }
    }
  }
  const qs = search.toString();
  const suffix = qs ? `?${qs}` : "";

  // Same-origin mode: when no API base is configured (empty), use a RELATIVE
  // URL so the browser's own Host (the tenant subdomain, via nginx) drives
  // tenant resolution. Otherwise prefix the absolute configured base.
  if (!base) {
    return `/api${normalizedPath}${suffix}`;
  }
  return `${base}/api${normalizedPath}${suffix}`;
}

function buildHeaders(hasBody: boolean): HeadersInit {
  const headers: Record<string, string> = { Accept: "application/json" };

  if (hasBody) headers["Content-Type"] = "application/json";

  const token = getToken();
  if (token) headers["Authorization"] = `Bearer ${token}`;

  // Dev-only: forward the tenant resolution host (prod uses the browser Host).
  if (DEV_TENANT_HOST) {
    headers["X-Forwarded-Host"] = DEV_TENANT_HOST;
  }

  return headers;
}

async function parseError(res: Response): Promise<ApiError> {
  let code = "http_error";
  let message = res.statusText || "Request failed";
  let details: Record<string, unknown> | null = null;

  try {
    const body = (await res.json()) as Partial<ApiErrorBody> & Record<string, unknown>;
    if (body && typeof body === "object" && "error" in body && body.error) {
      code = body.error.code ?? code;
      message = body.error.message ?? message;
      details = body.error.details ?? null;
    } else if (body && typeof body.message === "string") {
      // Laravel validation / default errors sometimes use { message, errors }.
      message = body.message;
      details = (body.errors as Record<string, unknown>) ?? null;
    }
  } catch {
    // Non-JSON error body — keep the status-derived defaults.
  }

  return new ApiError(res.status, code, message, details);
}

/** Perform an API request and return the parsed JSON body as `T`. */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = "GET", body, query, signal } = options;
  const hasBody = body !== undefined && body !== null;
  // Multipart uploads: let the browser set the Content-Type (with its boundary).
  const isForm = typeof FormData !== "undefined" && body instanceof FormData;

  const res = await fetch(buildUrl(path, query), {
    method,
    headers: buildHeaders(hasBody && !isForm),
    body: !hasBody ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    signal,
  });

  if (!res.ok) {
    throw await parseError(res);
  }

  // 204 No Content and empty bodies resolve to undefined cast as T.
  if (res.status === 204) return undefined as T;

  const text = await res.text();
  if (!text) return undefined as T;

  return JSON.parse(text) as T;
}

export const api = {
  get: <T>(path: string, query?: RequestOptions["query"]) =>
    apiRequest<T>(path, { method: "GET", query }),
  post: <T>(path: string, body?: unknown, query?: RequestOptions["query"]) =>
    apiRequest<T>(path, { method: "POST", body, query }),
  patch: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: "PATCH", body }),
  put: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: "PUT", body }),
  del: <T>(path: string, query?: RequestOptions["query"]) =>
    apiRequest<T>(path, { method: "DELETE", query }),
};
