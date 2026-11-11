"use client";

import { useCallback, useEffect, useState } from "react";

import { adminApi, type AdminSubscription } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/primitives";

const STATUSES = ["active", "past_due", "cancelled", "trial"];

/**
 * Subscriptions master view for the superadmin (R4.6, R6). Lists every
 * tenant subscription with its plan, and lets the superadmin change the
 * lifecycle status inline.
 */
export function SubscriptionsPanel(): JSX.Element {
  const [subs, setSubs] = useState<AdminSubscription[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.subscriptions();
      setSubs(res.data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function changeStatus(s: AdminSubscription, status: string) {
    try {
      await adminApi.updateSubscription(s.id, { status });
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const columns: Column<AdminSubscription>[] = [
    { header: "ID", cell: (s) => `#${s.id}` },
    { header: "Tenant", cell: (s) => <span className="font-mono text-xs">{s.tenant_id}</span> },
    { header: "Plan", cell: (s) => s.plan?.name ?? `#${s.plan_id}` },
    { header: "Status", cell: (s) => <Badge>{s.status}</Badge> },
    {
      header: "Change status",
      cell: (s) => (
        <select
          value={s.status}
          onChange={(e) => void changeStatus(s, e.target.value)}
          className="rounded-md border border-border bg-background px-2 py-1 text-sm"
          aria-label={`Change status for subscription ${s.id}`}
        >
          {STATUSES.map((st) => (
            <option key={st} value={st}>
              {st}
            </option>
          ))}
        </select>
      ),
    },
  ];

  return (
    <div className="space-y-3">
      <h2 className="text-sm font-semibold text-foreground">Subscriptions</h2>
      <DataTable
        columns={columns}
        rows={subs}
        rowKey={(s) => s.id}
        isLoading={loading}
        error={error}
        emptyMessage="No subscriptions yet."
      />
    </div>
  );
}
