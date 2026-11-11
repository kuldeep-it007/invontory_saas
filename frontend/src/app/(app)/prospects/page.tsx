"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Badge, PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useProspectAction, useProspects } from "@/lib/api/hooks";
import type { Prospect } from "@/lib/api/types";

/**
 * Prospect approval queue (R9.2, R9.3). Lists sign-ups; a manager approves
 * (creates the Distributor/Dealer) or rejects each pending prospect. Actions
 * are disabled once a prospect leaves the pending state.
 */
export default function ProspectsPage(): JSX.Element {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useProspects(page);
  const action = useProspectAction();
  const [actionError, setActionError] = useState<string | null>(null);

  async function run(id: number, kind: "approve" | "reject"): Promise<void> {
    setActionError(null);
    try {
      await action.mutateAsync({ id, action: kind });
    } catch (err) {
      setActionError(errorMessage(err));
    }
  }

  const columns: Column<Prospect>[] = [
    { header: "Name", cell: (p) => <span className="font-medium">{p.name}</span> },
    { header: "Email", cell: (p) => p.contact_email ?? "—" },
    { header: "Type", cell: (p) => p.type ?? "—" },
    { header: "Status", cell: (p) => <Badge>{p.status}</Badge> },
    {
      header: "Actions",
      className: "text-right",
      cell: (p) =>
        p.status === "pending" ? (
          <div className="flex justify-end gap-2">
            <Button
              variant="primary"
              disabled={action.isPending}
              onClick={() => void run(p.id, "approve")}
            >
              Approve
            </Button>
            <Button
              variant="danger"
              disabled={action.isPending}
              onClick={() => void run(p.id, "reject")}
            >
              Reject
            </Button>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">—</span>
        ),
    },
  ];

  return (
    <div className="space-y-6">
      <PageHeader title="Prospects" description="Review and approve prospective buyer sign-ups." />

      {actionError ? (
        <p className="text-sm text-red-700" role="alert">
          {actionError}
        </p>
      ) : null}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(p) => p.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No prospects in the queue."
      />
    </div>
  );
}
