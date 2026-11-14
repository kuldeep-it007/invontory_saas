"use client";

import { useCallback, useEffect, useState } from "react";

import { adminApi, type AdminTenant } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Pagination } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Input } from "@/components/ui/primitives";
import { Badge } from "@/components/ui/primitives";
import { TenantDetailModal } from "./tenant-detail-modal";

/**
 * Tenants master list for the superadmin (R2, R1). Lists every tenant with its
 * domains + current subscription, and supports editing business toggles and
 * deleting a tenant (which drops its database — confirmed first).
 */
export function TenantsPanel({ refreshKey = 0 }: { refreshKey?: number }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const [tenants, setTenants] = useState<AdminTenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [viewId, setViewId] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.tenantsSearch({ q: q.trim(), status, page });
      setTenants(res.data);
      setLastPage(res.last_page);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [q, status, page]);

  useEffect(() => {
    void load();
  }, [load, refreshKey]);

  async function toggle(t: AdminTenant, field: "backorders_enabled" | "dealer_stock_enabled") {
    try {
      await adminApi.updateTenant(t.id, { [field]: !t[field] } as Partial<AdminTenant>);
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function remove(t: AdminTenant) {
    const ok = await confirm({
      title: `Delete tenant "${t.subdomain}"?`,
      message:
        "This permanently DROPS the tenant's entire database — all its data is lost and cannot be recovered.",
      tone: "danger",
      confirmLabel: "Delete tenant",
    });
    if (!ok) return;
    try {
      await adminApi.deleteTenant(t.id);
      await load();
      toast({ title: "Tenant deleted", message: `"${t.subdomain}" was removed.`, tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<AdminTenant>[] = [
    { header: "Subdomain", cell: (t) => <span className="font-medium">{t.subdomain}</span> },
    { header: "Domains", cell: (t) => t.domains.join(", ") || "—" },
    { header: "Plan", cell: (t) => t.subscription?.plan?.name ?? "—" },
    {
      header: "Subscription",
      cell: (t) => (t.subscription ? <Badge>{t.subscription.status}</Badge> : "—"),
    },
    {
      header: "Backorders",
      cell: (t) => (
        <Button variant="ghost" onClick={() => void toggle(t, "backorders_enabled")}>
          {t.backorders_enabled ? "On" : "Off"}
        </Button>
      ),
    },
    {
      header: "Dealer stock",
      cell: (t) => (
        <Button variant="ghost" onClick={() => void toggle(t, "dealer_stock_enabled")}>
          {t.dealer_stock_enabled ? "On" : "Off"}
        </Button>
      ),
    },
    {
      header: "",
      className: "text-right",
      cell: (t) => (
        <div className="flex items-center justify-end gap-1">
        <Button variant="outline" size="sm" onClick={() => setViewId(t.id)} aria-label={`View tenant ${t.subdomain}`}>
          View
        </Button>
        <IconButton label={`Delete tenant ${t.subdomain}`} tone="danger" onClick={() => void remove(t)}>
          <Icon.trash size={16} />
        </IconButton>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-3">
      <DataTable
        columns={columns}
        rows={tenants}
        rowKey={(t) => t.id}
        isLoading={loading}
        error={error}
        emptyMessage={q || status ? "No tenants match your filters." : 'No tenants yet. Use "Add tenant" to provision one.'}
        toolbar={
          <>
            <Input
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setPage(1);
              }}
              placeholder="Search by subdomain"
              aria-label="Search tenants"
              className="max-w-xs"
            />
            <select
              value={status}
              onChange={(e) => {
                setStatus(e.target.value);
                setPage(1);
              }}
              aria-label="Filter by subscription status"
              className="rounded-md border border-border bg-background px-2 py-2 text-sm"
            >
              <option value="">All statuses</option>
              {["active", "trial", "past_due", "cancelled"].map((s) => (
                <option key={s} value={s}>
                  {s.replace("_", " ")}
                </option>
              ))}
            </select>
          </>
        }
        footer={<Pagination page={page} lastPage={lastPage} onChange={setPage} />}
      />
      <TenantDetailModal tenantId={viewId} onClose={() => setViewId(null)} onChanged={() => void load()} />
    </div>
  );
}
