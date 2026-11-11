"use client";

import { useCallback, useEffect, useState } from "react";

import { adminApi, type AdminTenant } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Badge } from "@/components/ui/primitives";

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

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.tenants();
      setTenants(res.data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

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
    { header: "Currency", cell: (t) => t.currency },
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
        <IconButton label={`Delete tenant ${t.subdomain}`} tone="danger" onClick={() => void remove(t)}>
          <Icon.trash size={16} />
        </IconButton>
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
        emptyMessage='No tenants yet. Use "Add tenant" to provision one.'
      />
    </div>
  );
}
