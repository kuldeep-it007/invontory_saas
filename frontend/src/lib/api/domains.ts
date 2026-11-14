"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "./client";
import type { Resource } from "./types";

/**
 * Custom domains: a company maps its own domain or subdomain to its workspace.
 * Mirrors `GET|POST|DELETE /api/tenant/domains` (Company_Admin only).
 */

export type DomainStatus = "pending" | "active" | "failed" | "disabled";
export type SslStatus = "none" | "pending" | "issued" | "failed";

/** One DNS record the customer must create at their registrar / DNS host. */
export interface DnsRecord {
  purpose: "ownership" | "routing";
  type: "TXT" | "CNAME" | "A";
  /** Fully-qualified name, e.g. `_inventory-verify.shop.example.com`. */
  name: string;
  /** Name relative to the zone, as most DNS panels expect it (`@` for the root). */
  host: string;
  value: string;
  /** The suggested routing option for this domain (CNAME for subdomains, A for a root). */
  recommended: boolean;
}

export interface CustomDomain {
  id: number;
  domain: string;
  type: "platform" | "custom";
  status: DomainStatus;
  routing_ok: boolean;
  ssl_status: SslStatus;
  ssl_expires_at: string | null;
  is_primary: boolean;
  verified_at: string | null;
  last_checked_at: string | null;
  /** When an unverified claim lapses. */
  expires_at: string | null;
  last_error: string | null;
  dns_records: DnsRecord[];
}

export const domainsApi = {
  list: () => api.get<Resource<CustomDomain[]>>("/tenant/domains"),
  add: (domain: string) => api.post<Resource<CustomDomain>>("/tenant/domains", { domain }),
  verify: (id: number) => api.post<Resource<CustomDomain>>(`/tenant/domains/${id}/verify`),
  setPrimary: (id: number, primary: boolean) =>
    api.post<Resource<CustomDomain>>(`/tenant/domains/${id}/primary`, { primary }),
  remove: (id: number) => api.del<void>(`/tenant/domains/${id}`),
};

const KEY = ["tenant-domains"];

/** True while something is still moving (DNS being verified or a certificate being issued). */
function isSettling(domains: CustomDomain[] | undefined): boolean {
  return (domains ?? []).some(
    (d) => d.type === "custom" && (d.status === "pending" || (d.status === "active" && d.ssl_status === "pending")),
  );
}

export const useDomains = () =>
  useQuery({
    queryKey: KEY,
    queryFn: () => domainsApi.list(),
    // Re-check on its own while DNS / HTTPS is still settling.
    refetchInterval: (query) => (isSettling(query.state.data?.data) ? 15_000 : false),
  });

function useDomainMutation<TVars, TResult>(fn: (vars: TVars) => Promise<TResult>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => void qc.invalidateQueries({ queryKey: KEY }),
  });
}

export const useAddDomain = () => useDomainMutation((domain: string) => domainsApi.add(domain));
export const useVerifyDomain = () => useDomainMutation((id: number) => domainsApi.verify(id));
export const useRemoveDomain = () => useDomainMutation((id: number) => domainsApi.remove(id));
export const useSetPrimaryDomain = () =>
  useDomainMutation((vars: { id: number; primary: boolean }) => domainsApi.setPrimary(vars.id, vars.primary));