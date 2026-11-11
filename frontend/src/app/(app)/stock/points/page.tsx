"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useCreateStockPoint, useStockPoints, useUpdateStockPoint } from "@/lib/api/hooks";
import type { StockPoint } from "@/lib/api/types";

/**
 * Stock Point Management (R13.4, R14) — a dedicated page listing the Company's
 * physical stock points and letting a manager (inventory.adjust) add new ones
 * and EDIT existing ones. Stock is assigned to a point from Stocks Management.
 */
export default function StockPointsPage(): JSX.Element {
  const { can } = useAuth();
  const canManage = can("inventory.adjust");
  const points = useStockPoints();
  const [editing, setEditing] = useState<StockPoint | null>(null);
  const [open, setOpen] = useState(false);

  function openCreate() {
    setEditing(null);
    setOpen(true);
  }
  function openEdit(p: StockPoint) {
    setEditing(p);
    setOpen(true);
  }

  const columns: Column<StockPoint>[] = [
    { header: "Name", cell: (p) => <span className="font-medium">{p.name}</span> },
    { header: "Sort order", cell: (p) => String(p.sort_order), className: "text-right" },
    ...(canManage
      ? [
          {
            header: "",
            className: "text-right",
            cell: (p: StockPoint) => (
              <IconButton label={`Edit ${p.name}`} onClick={() => openEdit(p)}>
                <Icon.pencil size={16} />
              </IconButton>
            ),
          } as Column<StockPoint>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Stock Point Management"
        description="The physical locations where the Company holds stock."
        action={
          canManage ? (
            <Button onClick={openCreate}>
              <Icon.plus size={16} /> Add stock point
            </Button>
          ) : undefined
        }
      />

      <DataTable
        columns={columns}
        rows={points.data?.data ?? []}
        rowKey={(p) => p.id}
        isLoading={points.isLoading}
        error={points.error ? errorMessage(points.error) : null}
        emptyMessage="No stock points yet. Add one to start assigning stock."
        footer={points.data ? <span>{points.data.data.length} stock points</span> : undefined}
      />

      <StockPointModal open={open} point={editing} onClose={() => setOpen(false)} />
    </div>
  );
}

/** Create OR edit a stock point. Pre-fills and PATCHes when `point` is given. */
function StockPointModal({
  open,
  point,
  onClose,
}: {
  open: boolean;
  point: StockPoint | null;
  onClose: () => void;
}): JSX.Element {
  const create = useCreateStockPoint();
  const update = useUpdateStockPoint();
  const { toast } = useAlerts();
  const isEdit = point !== null;

  const [name, setName] = useState("");
  const [sortOrder, setSortOrder] = useState("0");
  const [error, setError] = useState<string | null>(null);
  const [seededFor, setSeededFor] = useState<number | "new" | null>(null);
  const targetKey = point?.id ?? "new";
  if (open && seededFor !== targetKey) {
    setName(point?.name ?? "");
    setSortOrder(point ? String(point.sort_order) : "0");
    setError(null);
    setSeededFor(targetKey);
  }

  function close() {
    setSeededFor(null);
    onClose();
  }

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    try {
      if (isEdit && point) {
        await update.mutateAsync({ id: point.id, payload: { name, sort_order: Number(sortOrder) || 0 } });
        toast({ title: "Stock point updated", message: `"${name}" saved.`, tone: "success" });
      } else {
        await create.mutateAsync({ name, sort_order: Number(sortOrder) || 0 });
        toast({ title: "Stock point created", message: `"${name}" added.`, tone: "success" });
      }
      close();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const busy = create.isPending || update.isPending;

  return (
    <Modal
      open={open}
      size="sm"
      onClose={close}
      title={isEdit ? "Edit stock point" : "Add stock point"}
      description="A physical location where stock is held."
      footer={
        <ModalFormFooter
          formId="stock-point-form"
          submitLabel={isEdit ? "Save changes" : "Add stock point"}
          busy={busy}
          disabled={!name}
          onCancel={close}
        />
      }
    >
      <form id="stock-point-form" onSubmit={submit} className="space-y-4">
        <Field label="Name" required value={name} onChange={setName} />
        <Field label="Sort order" type="number" value={sortOrder} onChange={setSortOrder} />
        {error ? (
          <p className="text-sm text-danger" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
