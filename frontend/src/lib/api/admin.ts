import { apiBaseUrl } from "@/lib/branding/client";

import { ApiError, type ApiErrorBody, type Identity, type Paginated } from "./types";

/**
 * Platform-admin (superadmin) API client.
 *
 * The superadmin surface is CENTRAL: it runs without tenancy, against the bare
 * host, and is authorized by a Platform_Admin Sanctum token obtained from the
 * central login (`POST /api/platform/login`). This client is separate from the
 * tenant API client because:
 *   - It must NOT send the tenant resolution host (`X-Forwarded-Host`).
 *   - It uses the central token, stored under its own key.
 */

const ADMIN_TOKEN_KEY = "idp.admin.token";

let memoryToken: string | null = null;

export function getAdminToken(): string | null {
  if (memoryToken !== null) return memoryToken;
  if (typeof window === "undefined") return null;
  memoryToken = window.localStorage.getItem(ADMIN_TOKEN_KEY);
  return memoryToken;
}

export function setAdminToken(token: string | null): void {
  memoryToken = token;
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(ADMIN_TOKEN_KEY, token);
  else window.localStorage.removeItem(ADMIN_TOKEN_KEY);
}

async function adminRequest<T>(
  path: string,
  options: { method?: "GET" | "POST" | "PATCH" | "DELETE"; body?: unknown; auth?: boolean; form?: FormData } = {},
): Promise<T> {
  const { method = "GET", body, auth = true, form } = options;
  const hasBody = body !== undefined && body !== null;

  const headers: Record<string, string> = { Accept: "application/json" };
  // A FormData body sets its own multipart boundary header.
  if (hasBody && !form) headers["Content-Type"] = "application/json";
  if (auth) {
    const token = getAdminToken();
    if (token) headers["Authorization"] = `Bearer ${token}`;
  }
  // NOTE: intentionally NO X-Forwarded-Host — central routes resolve no tenant.

  // Same-origin when no API base is configured (served behind nginx).
  const base = apiBaseUrl();
  const url = base ? `${base}/api${path}` : `/api${path}`;
  const res = await fetch(url, {
    method,
    headers,
    body: form ?? (hasBody ? JSON.stringify(body) : undefined),
  });

  if (!res.ok) {
    let code = "http_error";
    let message = res.statusText || "Request failed";
    let details: Record<string, unknown> | null = null;
    try {
      const errBody = (await res.json()) as Partial<ApiErrorBody> & Record<string, unknown>;
      if (errBody?.error) {
        code = errBody.error.code ?? code;
        message = errBody.error.message ?? message;
        details = errBody.error.details ?? null;
      } else if (typeof errBody?.message === "string") {
        message = errBody.message;
      }
    } catch {
      /* non-JSON error body */
    }
    throw new ApiError(res.status, code, message, details);
  }

  if (res.status === 204) return undefined as T;
  const text = await res.text();
  return text ? (JSON.parse(text) as T) : (undefined as T);
}

/* ─── Response shapes ─────────────────────────────────────────────────────── */

export interface CentralLoginResponse {
  token: string;
  token_type: string;
  scope: string;
  user: Identity & { scope: string };
}

export const TENANT_DETAIL_FIELDS = [
  "company_name",
  "contact_name",
  "contact_email",
  "contact_phone",
  "billing_name",
  "billing_email",
  "billing_address",
  "tax_id",
  "notes",
] as const;
export type TenantDetails = Record<(typeof TENANT_DETAIL_FIELDS)[number], string | null>;

export interface UsageQuota {
  count: number;
  limit: number | null;
  unlimited: boolean;
  percent: number | null;
}
export type TenantUsage = Record<string, UsageQuota>;

export interface AdminTenant {
  id: string;
  subdomain: string;
  currency: string;
  backorders_enabled: boolean;
  dealer_stock_enabled: boolean;
  created_at?: string | null;
  domains: string[];
  details?: TenantDetails;
  usage?: TenantUsage | null;
  subscription: {
    id: number;
    plan_id: number;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at?: string | null;
    plan?: {
      id: number;
      name: string;
      price: string | number;
      billing_cycle: "monthly" | "yearly";
      limits: PlanLimit[];
    } | null;
  } | null;
}

export interface PlanLimit {
  id?: number;
  key: string;
  value: number | null;
}
export interface PlanFeature {
  id?: number;
  key: string;
  enabled: boolean;
}
export interface AdminPlan {
  id: number;
  name: string;
  price: string | number;
  billing_cycle: "monthly" | "yearly";
  is_active: boolean;
  limits: PlanLimit[];
  features: PlanFeature[];
  tenants_count?: number;
}

export interface AdminSubscription {
  id: number;
  tenant_id: string;
  plan_id: number;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  plan?: { id: number; name: string; price: string; billing_cycle: string };
}

export interface AuditEntry {
  id: number;
  actor_email: string | null;
  event: string;
  tenant_id: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  reason: string | null;
  ip_address: string | null;
  created_at: string;
}
export interface AdminProfile {
  id: number;
  name: string;
  email: string;
  phone: string | null;
}
export interface ProfileUpdate {
  name?: string;
  email?: string;
  phone?: string | null;
  current_password?: string;
  password?: string;
  password_confirmation?: string;
}
export interface PlatformSettings {
  platform_name: string;
  tagline: string | null;
  legal_name: string | null;
  support_email: string | null;
  support_phone: string | null;
  website: string | null;
  address: string | null;
  default_currency: string;
  timezone: string;
  primary_color: string | null;
  logo: string | null;
  favicon: string | null;
}
export interface TenantUsageRow {
  id: string;
  subdomain: string;
  plan: string | null;
  status: string | null;
  usage: TenantUsage | null;
}

export interface ProvisionTenantInput {
  subdomain: string;
  domain?: string;
  plan_id: number;
  currency?: string;
  backorders_enabled?: boolean;
  dealer_stock_enabled?: boolean;
  company_name: string;
  company_gstin?: string;
  admin_name: string;
  admin_email: string;
  admin_password: string;
}

export interface ProvisionTenantResponse {
  tenant: {
    id: string;
    subdomain: string;
    currency: string;
    backorders_enabled: boolean;
    dealer_stock_enabled: boolean;
    domains: string[];
  };
  subscription: { id: number; plan_id: number; status: string };
  company_id: number;
  admin_id: number;
}

/* ─── Endpoints ───────────────────────────────────────────────────────────── */

/** A tenant-owned custom domain as seen by the platform admin. */
export interface AdminDomain {
  id: number;
  tenant_id: string;
  domain: string;
  status: "pending" | "active" | "failed" | "disabled";
  routing_ok: boolean;
  ssl_status: "none" | "pending" | "issued" | "failed";
  ssl_expires_at: string | null;
  last_checked_at: string | null;
  last_error: string | null;
}

export const adminApi = {
  /** Central (Platform_Admin) login — issues a token for /api/admin/*. */
  login: (login: string, password: string) =>
    adminRequest<CentralLoginResponse>("/platform/login", {
      method: "POST",
      body: { login, password, device_name: "platform-web" },
      auth: false,
    }),

  me: () => adminRequest<Identity & { scope: string }>("/platform/me"),
  logout: () => adminRequest<{ message: string }>("/platform/logout", { method: "POST" }),

  /** Sanity probe used to validate an existing token. */
  ping: () => adminRequest<{ status: string; scope: string }>("/admin/ping"),

  /* Tenants */
  tenants: () => adminRequest<Paginated<AdminTenant>>("/admin/tenants"),
  tenant: (id: string) => adminRequest<{ data: AdminTenant }>(`/admin/tenants/${id}`),
  provisionTenant: (input: ProvisionTenantInput) =>
    adminRequest<ProvisionTenantResponse>("/admin/tenants", { method: "POST", body: input }),
  updateTenant: (id: string, body: Partial<AdminTenant>) =>
    adminRequest<{ data: AdminTenant }>(`/admin/tenants/${id}`, { method: "PATCH", body }),
  deleteTenant: (id: string) =>
    adminRequest<{ message: string }>(`/admin/tenants/${id}`, { method: "DELETE" }),

  tenantsSearch: (params: { q?: string; status?: string; page?: number }) => {
    const qs = new URLSearchParams();
    if (params.q) qs.set("q", params.q);
    if (params.status) qs.set("status", params.status);
    if (params.page) qs.set("page", String(params.page));
    return adminRequest<Paginated<AdminTenant>>(`/admin/tenants?${qs.toString()}`);
  },
  updateTenantDetails: (id: string, body: Partial<TenantDetails> & { currency?: string }) =>
    adminRequest<{ data: AdminTenant }>(`/admin/tenants/${id}`, { method: "PATCH", body }),
  changeTenantPlan: (id: string, body: { plan_id: number; force?: boolean; reason?: string }) =>
    adminRequest<{ data: AdminTenant }>(`/admin/tenants/${id}/plan`, { method: "POST", body }),
  tenantHistory: (id: string, page = 1) =>
    adminRequest<Paginated<AuditEntry>>(`/admin/tenants/${id}/history?page=${page}`),
  usage: (page = 1) => adminRequest<Paginated<TenantUsageRow>>(`/admin/usage?page=${page}`),
  auditLogs: (params: { event?: string; page?: number }) => {
    const qs = new URLSearchParams();
    if (params.event) qs.set("event", params.event);
    qs.set("page", String(params.page ?? 1));
    return adminRequest<Paginated<AuditEntry>>(`/admin/audit-logs?${qs.toString()}`);
  },

  /* Profile + platform settings */
  profile: () => adminRequest<{ data: AdminProfile }>("/admin/profile"),
  updateProfile: (body: ProfileUpdate) =>
    adminRequest<{ data: AdminProfile }>("/admin/profile", { method: "PATCH", body }),
  settings: () => adminRequest<{ data: PlatformSettings }>("/admin/settings"),
  updateSettings: (body: Partial<PlatformSettings>) =>
    adminRequest<{ data: PlatformSettings }>("/admin/settings", { method: "PATCH", body }),
  uploadAsset: (asset: "logo" | "favicon", file: File) => {
    const form = new FormData();
    form.append("file", file);
    return adminRequest<{ data: PlatformSettings }>(`/admin/settings/${asset}`, {
      method: "POST",
      body: true,
      form,
    });
  },
  removeAsset: (asset: "logo" | "favicon") =>
    adminRequest<{ data: PlatformSettings }>(`/admin/settings/${asset}`, { method: "DELETE" }),
  /** Public (no auth): platform name/logo/favicon for the console chrome. */
  branding: () =>
    adminRequest<{ data: Pick<PlatformSettings, "platform_name" | "logo" | "favicon" | "primary_color"> }>(
      "/platform/branding",
      { auth: false },
    ),

  /* Plans */
  plans: () => adminRequest<{ data: AdminPlan[] }>("/admin/plans"),
  createPlan: (body: Partial<AdminPlan>) =>
    adminRequest<{ data: AdminPlan }>("/admin/plans", { method: "POST", body }),
  updatePlan: (id: number, body: Partial<AdminPlan>) =>
    adminRequest<{ data: AdminPlan }>(`/admin/plans/${id}`, { method: "PATCH", body }),
  deletePlan: (id: number) =>
    adminRequest<{ message: string }>(`/admin/plans/${id}`, { method: "DELETE" }),

  /* Subscriptions */
  subscriptions: () => adminRequest<Paginated<AdminSubscription>>("/admin/subscriptions"),
  createSubscription: (body: Partial<AdminSubscription>) =>
    adminRequest<{ data: AdminSubscription }>("/admin/subscriptions", { method: "POST", body }),
  updateSubscription: (id: number, body: Partial<AdminSubscription>) =>
    adminRequest<{ data: AdminSubscription }>(`/admin/subscriptions/${id}`, {
      method: "PATCH",
      body,
    }),
  /* Custom domains (cross-tenant oversight) */
  domains: (status?: string) =>
    adminRequest<{ data: AdminDomain[] }>(`/admin/domains${status ? `?status=${encodeURIComponent(status)}` : ""}`),
  disableDomain: (id: number) =>
    adminRequest<{ data: AdminDomain }>(`/admin/domains/${id}/disable`, { method: "POST" }),
  enableDomain: (id: number) =>
    adminRequest<{ data: AdminDomain }>(`/admin/domains/${id}/enable`, { method: "POST" }),
};
