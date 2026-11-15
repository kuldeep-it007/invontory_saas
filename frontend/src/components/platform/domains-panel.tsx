"use client";

import { useCallback, useEffect, useState } from "react";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/primitives";
import { adminApi, type AdminDomain } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";

const FILTERS = ["", "pending", "active", "failed", "disabled"];

/**
 * Cross-tenant view of custom domains with a kill switch. Disabling a domain
 * stops it serving immediately; enabling sends it back to DNS verification.
 */
export function DomainsPanel(): JSX.Element {
  const [domains, setDomains] = useState<AdminDomain[]>([]);
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setDomains((await adminApi.domains(status || undefined)).data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [status]);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggle(d: AdminDomain): Promise<void> {
    const verb = d.status === "disabled" ? "enable" : "disable";
    if (verb === "disable" && !window.confirm(`Disable ${d.domain}? It stops serving immediately.`)) return;
    try {
      await (verb === "enable" ? adminApi.enableDomain(d.id) : adminApi.disableDomain(d.id));
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function renew(d: AdminDomain): Promise<void> {
    const ok = window.confirm(
      `Renew the HTTPS certificate for ${d.domain} now?\n\nLet's Encrypt allows only 5 renewals of the same domain per week, so use this only when needed.`,
    );
    if (!ok) return;
    setError(null);
    try {
      await adminApi.renewDomainSsl(d.id);
      setNotice(`Renewal queued for ${d.domain}. It runs within a few minutes.`);
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  function httpsCell(d: AdminDomain): string {
    if (d.ssl_renewal_pending) return "renewal queued";
    if (d.ssl_status === "issued" && d.ssl_expires_at) {
      return `issued, expires ${new Date(d.ssl_expires_at).toLocaleDateString()}`;
    }
    return d.ssl_status;
  }

  const columns: Column<AdminDomain>[] = [
    { header: "Domain", cell: (d) => <span className="font-mono text-xs">{d.domain}</span> },
    { header: "Tenant", cell: (d) => <span className="font-mono text-xs">{d.tenant_id}</span> },
    { header: "Status", cell: (d) => <Badge>{d.status}</Badge> },
    { header: "Routing", cell: (d) => (d.routing_ok ? "OK" : "Not found") },
    { header: "HTTPS", cell: (d) => httpsCell(d) },
    { header: "Note", cell: (d) => d.last_error ?? "—" },
    {
      header: "Action",
      cell: (d) => (
        <div className="flex gap-2">
          {d.status === "active" ? (
            <button
              onClick={() => void renew(d)}
              disabled={d.ssl_renewal_pending || !d.routing_ok}
              title={
                !d.routing_ok
                  ? "The domain does not point at the server yet"
                  : d.ssl_renewal_pending
                    ? "A renewal is already queued"
                    : "Request a new HTTPS certificate now"
              }
              className="rounded-md border border-border px-2 py-1 text-xs hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            >
              Renew SSL
            </button>
          ) : null}
          <button
            onClick={() => void toggle(d)}
            className="rounded-md border border-border px-2 py-1 text-xs hover:bg-muted"
          >
            {d.status === "disabled" ? "Enable" : "Disable"}
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-foreground">Custom domains</h2>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-border bg-background px-2 py-1 text-sm"
          aria-label="Filter by status"
        >
          {FILTERS.map((f) => (
            <option key={f} value={f}>
              {f === "" ? "All statuses" : f}
            </option>
          ))}
        </select>
      </div>
      {notice ? (
        <p className="text-sm text-green-700" role="status">
          {notice}
        </p>
      ) : null}
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
      <DataTable
        columns={columns}
        rows={domains}
        rowKey={(d) => d.id}
        isLoading={loading}
        emptyMessage="No custom domains yet."
      />
    </div>
  );
}