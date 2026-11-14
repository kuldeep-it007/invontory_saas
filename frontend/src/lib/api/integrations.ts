"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "./client";
import type { Resource } from "./types";

/**
 * A company's own SMTP server and object storage. Mirrors
 * `/api/tenant/integrations` (Company_Admin only). Passwords and secret keys are
 * write-only: the API only says whether one is saved.
 */

export type IntegrationType = "smtp" | "storage";

interface IntegrationBase {
  /** False when the company's plan does not include this feature. */
  available: boolean;
  configured: boolean;
  enabled: boolean;
  last_test: { ok: boolean; at: string } | null;
}

export interface SmtpIntegration extends IntegrationBase {
  host: string | null;
  port: number;
  encryption: "tls" | "ssl" | "none";
  username: string | null;
  has_password: boolean;
  from_address: string | null;
  from_name: string | null;
}

export interface CopyProgress {
  status: "queued" | "running" | "completed" | "completed_with_errors" | "failed";
  copied?: number;
  skipped?: number;
  failed?: number;
  error?: string;
}

export interface StorageIntegration extends IntegrationBase {
  provider: string;
  endpoint: string | null;
  region: string | null;
  bucket: string | null;
  path_style: boolean;
  access_key_hint: string | null;
  has_secret_key: boolean;
  copy: CopyProgress | null;
}

export interface IntegrationsOverview {
  smtp: SmtpIntegration;
  storage: StorageIntegration;
}

export interface TestResult {
  ok: boolean;
  message: string;
  tested_at: string;
}

export const integrationsApi = {
  overview: () => api.get<Resource<IntegrationsOverview>>("/tenant/integrations"),
  save: (type: IntegrationType, body: Record<string, unknown>) =>
    api.put<Resource<SmtpIntegration | StorageIntegration>>(`/tenant/integrations/${type}`, body),
  test: (type: IntegrationType, body: Record<string, unknown>) =>
    api.post<Resource<TestResult>>(`/tenant/integrations/${type}/test`, body),
  remove: (type: IntegrationType) => api.del<void>(`/tenant/integrations/${type}`),
};

const KEY = ["tenant-integrations"];

export const useIntegrations = () =>
  useQuery({
    queryKey: KEY,
    queryFn: () => integrationsApi.overview(),
    // While the one-off copy into a new bucket runs, keep the progress fresh.
    refetchInterval: (query) => {
      const status = query.state.data?.data.storage.copy?.status;
      return status === "queued" || status === "running" ? 5_000 : false;
    },
  });

export function useSaveIntegration(type: IntegrationType) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: Record<string, unknown>) => integrationsApi.save(type, body),
    onSuccess: () => void qc.invalidateQueries({ queryKey: KEY }),
  });
}

export const useTestIntegration = (type: IntegrationType) =>
  useMutation({ mutationFn: (body: Record<string, unknown>) => integrationsApi.test(type, body) });

export function useRemoveIntegration(type: IntegrationType) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => integrationsApi.remove(type),
    onSuccess: () => void qc.invalidateQueries({ queryKey: KEY }),
  });
}