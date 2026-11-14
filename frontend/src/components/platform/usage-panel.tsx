"use client";

import { useCallback, useEffect, useState } from "react";
import { adminApi, type AuditEntry, type TenantUsage, type TenantUsageRow } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { Pagination } from "@/components/ui/form";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Badge, StateMessage } from "@/components/ui/primitives";

export function quotaLabel(key: string): string {
  return key.replace(/^max_/, "").replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? "—" : d.toLocaleString();
}

/** Usage-vs-limit bars for one tenant. 0/null limits are unlimited. */
export function UsageBars({ usage }: { usage: TenantUsage | null | undefined }): JSX.Element {
  if (!usage) return <StateMessage>Usage is unavailable for this tenant.</StateMessage>;
  const entries = Object.entries(usage);
  return (
    <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {entries.map(([key, q]) => {
        const pct = q.percent ?? 0;
        const tone = pct >= 100 ? "bg-danger" : pct >= 80 ? "bg-warning" : "bg-primary";
        return (
          <li key={key} className="rounded-lg border border-border p-3">
            <div className="flex items-center justify-between text-sm">
              <span className="font-medium text-foreground">{quotaLabel(key)}</span>
              <span className="text-muted-foreground">
                {q.count} / {q.unlimited ? "∞" : q.limit}
                {q.percent !== null ? ` (${q.percent}%)` : ""}
              </span>
            </div>
            <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
              <div
                className={`h-full rounded-full ${tone}`}
                style={{ width: `${q.unlimited ? 0 : pct}%` }}
                role="progressbar"
                aria-label={`${quotaLabel(key)} utilisation`}
                aria-valuenow={q.count}
                aria-valuemin={0}
                aria-valuemax={q.unlimited ? undefined : (q.limit ?? undefined)}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

/** Compact "what changed" summary of an audit entry. */
function changeSummary(e: AuditEntry): string {
  const keys = Object.keys(e.new_values ?? e.old_values ?? {});
  if (keys.length === 0) return "—";
  const fmt = (v: unknown) => (v === null || v === undefined || v === "" ? "∅" : typeof v === "object" ? JSON.stringify(v) : String(v));
  return keys
    .filter((k) => k !== "direction" && k !== "forced")
    .map((k) => {
      const from = e.old_values && k in e.old_values ? `${fmt(e.old_values[k])} → ` : "";
      return `${k}: ${from}${fmt(e.new_values?.[k])}`;
    })
    .join("; ");
}

export function AuditTable({
  rows,
  loading,
  error,
  showTenant = true,
}: {
  rows: AuditEntry[];
  loading: boolean;
  error: string | null;
  showTenant?: boolean;
}): JSX.Element {
  const columns: Column<AuditEntry>[] = [
    { header: "When", cell: (e) => <span className="whitespace-nowrap text-xs">{formatDate(e.created_at)}</span> },
    { header: "Event", cell: (e) => <Badge>{e.event.replace(/[._]/g, " ")}</Badge> },
    ...(showTenant ? [{ header: "Tenant", cell: (e: AuditEntry) => <span className="font-mono text-xs">{e.tenant_id ?? "—"}</span> }] : []),
    { header: "Actor", cell: (e) => e.actor_email ?? "—" },
    { header: "Change", cell: (e) => <span className="text-xs text-muted-foreground">{changeSummary(e)}</span> },
    { header: "Reason", cell: (e) => e.reason ?? "—" },
  ];
  return (
    <DataTable columns={columns} rows={rows} rowKey={(e) => e.id} isLoading={loading} error={error} emptyMessage="No activity recorded yet." />
  );
}

/** Tenant-wise resource consumption across the platform. */
export function UsagePanel(): JSX.Element {
  const [rows, setRows] = useState<TenantUsageRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.usage(page);
      setRows(res.data);
      setLastPage(res.last_page);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [page]);
  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4">
      <h2 className="text-sm font-semibold text-foreground">Tenant usage</h2>
      {loading ? (
        <StateMessage>Loading usage…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{error}</StateMessage>
      ) : rows.length === 0 ? (
        <StateMessage>No tenants yet.</StateMessage>
      ) : (
        rows.map((t) => (
          <section key={t.id} className="space-y-2 rounded-xl border border-border bg-card p-4 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="font-medium text-foreground">{t.subdomain}</h3>
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                {t.plan ?? "No plan"}
                {t.status ? <Badge>{t.status}</Badge> : null}
              </div>
            </div>
            <UsageBars usage={t.usage} />
          </section>
        ))
      )}
      <Pagination page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}

const EVENT_FILTERS = [
  { value: "", label: "All events" },
  { value: "subscription", label: "Subscriptions" },
  { value: "tenant", label: "Tenants" },
  { value: "plan", label: "Plans" },
  { value: "platform", label: "Platform settings & profile" },
];

/** Central, append-only audit trail of platform-level actions. */
export function AuditLogPanel(): JSX.Element {
  const [rows, setRows] = useState<AuditEntry[]>([]);
  const [event, setEvent] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.auditLogs({ event: event || undefined, page });
      setRows(res.data);
      setLastPage(res.last_page);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [event, page]);
  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-foreground">Audit log</h2>
        <select
          value={event}
          onChange={(e) => {
            setEvent(e.target.value);
            setPage(1);
          }}
          aria-label="Filter audit events"
          className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
        >
          {EVENT_FILTERS.map((f) => (
            <option key={f.value} value={f.value}>
              {f.label}
            </option>
          ))}
        </select>
      </div>
      <AuditTable rows={rows} loading={loading} error={error} />
      <Pagination page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
