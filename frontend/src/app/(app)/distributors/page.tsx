"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { useImpersonation } from "@/components/providers/impersonation-provider";
import { Button, buttonClasses } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Pagination } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Icon } from "@/components/ui/icons";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useDistributors } from "@/lib/api/hooks";
import type { Distributor } from "@/lib/api/types";

/**
 * Distributor list + creation (R10.1). Managers (distributors.manage) get an
 * "Add distributor" button that opens the full create page; everyone with
 * distributors.view sees the paginated table. Each row links to that
 * distributor's dealers (R10.2) and offers an edit shortcut.
 */
export default function DistributorsPage(): JSX.Element {
  const { can } = useAuth();
  const router = useRouter();
  const { start } = useImpersonation();
  const { toast } = useAlerts();
  const [page, setPage] = useState(1);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [impError, setImpError] = useState<string | null>(null);
  const { data, isLoading, error } = useDistributors(page);

  // Company roles may impersonate (act as) any distributor to order on its behalf.
  const canImpersonate = can("orders.create");

  async function impersonate(d: Distributor): Promise<void> {
    setImpError(null);
    setBusyId(d.id);
    try {
      await start("distributor", d.id);
      toast({ title: "Impersonation started", message: `Now acting as ${d.name}.`, tone: "info" });
      router.push("/orders/cart");
    } catch (err) {
      setImpError(errorMessage(err));
      toast({ title: "Could not impersonate", message: errorMessage(err), tone: "danger" });
    } finally {
      setBusyId(null);
    }
  }

  const columns: Column<Distributor>[] = [
    {
      header: "Name",
      cell: (d) => (
        <Link href={`/distributors/${d.id}`} className="font-medium text-primary hover:underline">
          {d.name}
        </Link>
      ),
    },
    { header: "Email", cell: (d) => d.contact_email ?? "—" },
    { header: "GSTIN", cell: (d) => d.gstin ?? "—" },
    { header: "Dealers", cell: (d) => d.dealers_count ?? 0, className: "text-right" },
    ...(can("distributors.manage") || canImpersonate
      ? [
          {
            header: "Actions",
            className: "text-right",
            cell: (d: Distributor) => (
              <div className="flex justify-end gap-1">
                {canImpersonate ? (
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={busyId === d.id}
                    onClick={() => void impersonate(d)}
                    title={`Act as ${d.name}`}
                  >
                    <Icon.users size={14} />
                    {busyId === d.id ? "…" : "Act as"}
                  </Button>
                ) : null}
                {can("distributors.manage") ? (
                  <Link
                    href={`/distributors/${d.id}/edit`}
                    aria-label={`Edit ${d.name}`}
                    title="Edit"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-sm transition-colors hover:bg-muted hover:text-foreground"
                  >
                    <Icon.pencil size={16} />
                  </Link>
                ) : null}
              </div>
            ),
          } as Column<Distributor>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Distributors"
        description="Organizations that buy from the company and sell to dealers."
        action={
          can("distributors.manage") ? (
            <Link href="/distributors/new" className={buttonClasses("primary")}>
              <Icon.plus size={16} /> Add distributor
            </Link>
          ) : undefined
        }
      />

      {impError ? (
        <p className="text-sm text-danger" role="alert">
          {impError}
        </p>
      ) : null}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(d) => d.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No distributors yet."
      />

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}
    </div>
  );
}
