"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Select } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Badge, Input, Label } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCategories,
  useCollections,
  useColours,
  useDiscountMutations,
  useDiscounts,
} from "@/lib/api/hooks";
import type { Discount } from "@/lib/api/types";

/* ─── Discounts ─────────────────────────────────────────────────────────────── */

export function DiscountsSection({ canManage }: { canManage: boolean }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const { data, isLoading, error } = useDiscounts();
  const { update, remove } = useDiscountMutations();
  const [actionError, setActionError] = useState<string | null>(null);
  const [addOpen, setAddOpen] = useState(false);

  async function toggle(d: Discount) {
    setActionError(null);
    try {
      await update.mutateAsync({ id: d.id, payload: { is_active: !d.is_active } });
    } catch (e) {
      setActionError(errorMessage(e));
    }
  }
  async function del(d: Discount) {
    const ok = await confirm({
      title: "Delete discount?",
      message: "This discount will be permanently removed.",
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await remove.mutateAsync(d.id);
      toast({ title: "Discount deleted", tone: "success" });
    } catch (e) {
      setActionError(errorMessage(e));
      toast({ title: "Delete failed", message: errorMessage(e), tone: "danger" });
    }
  }

  const columns: Column<Discount>[] = [
    { header: "Type", cell: (d) => <Badge>{d.type}</Badge> },
    { header: "Customer type", cell: (d) => d.customer_type ?? "any" },
    { header: "%", cell: (d) => `${Number(d.percentage)}%` },
    {
      header: "Scope",
      cell: (d) =>
        [d.category_id && `cat #${d.category_id}`, d.collection_id && `coll #${d.collection_id}`, d.colour_id && `col #${d.colour_id}`]
          .filter(Boolean)
          .join(", ") || "all",
    },
    { header: "Active", cell: (d) => <Badge>{d.is_active ? "active" : "inactive"}</Badge> },
    ...(canManage
      ? [
          {
            header: "",
            className: "text-right",
            cell: (d: Discount) => (
              <div className="flex justify-end gap-2">
                <Button variant="secondary" disabled={update.isPending} onClick={() => void toggle(d)}>
                  {d.is_active ? "Disable" : "Enable"}
                </Button>
                <IconButton
                  label="Delete discount"
                  tone="danger"
                  disabled={remove.isPending}
                  onClick={() => void del(d)}
                >
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            ),
          } as Column<Discount>,
        ]
      : []),
  ];

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Discounts</h2>
        {canManage ? (
          <Button variant="secondary" onClick={() => setAddOpen(true)}>
            Add discount
          </Button>
        ) : null}
      </div>
      {actionError ? (
        <p className="text-sm text-red-700" role="alert">
          {actionError}
        </p>
      ) : null}
      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(d) => d.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No discounts configured."
      />
      <DiscountModal open={addOpen} onClose={() => setAddOpen(false)} />
    </section>
  );
}

function DiscountModal({ open, onClose }: { open: boolean; onClose: () => void }): JSX.Element {
  const { create } = useDiscountMutations();
  const categories = useCategories();
  const collections = useCollections();
  const colours = useColours();

  const empty = {
    type: "customer",
    customer_type: "",
    percentage: "",
    category_id: "",
    collection_id: "",
    colour_id: "",
  };
  const [form, setForm] = useState(empty);
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setForm(empty);
    setError(null);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync({
        type: form.type,
        customer_type: form.customer_type || null,
        percentage: Number(form.percentage),
        category_id: form.category_id ? Number(form.category_id) : null,
        collection_id: form.collection_id ? Number(form.collection_id) : null,
        colour_id: form.colour_id ? Number(form.colour_id) : null,
      });
      reset();
      onClose();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <Modal
      open={open}
      onClose={() => {
        reset();
        onClose();
      }}
      title="Add discount"
      footer={
        <ModalFormFooter
          formId="create-discount-form"
          submitLabel="Add discount"
          busy={create.isPending}
          disabled={!form.percentage}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-discount-form" onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          label="Type"
          value={form.type}
          onChange={(v) => setForm({ ...form, type: v })}
          options={[
            { value: "customer", label: "Customer" },
            { value: "online", label: "Online" },
          ]}
        />
        <Select
          label="Customer type (optional)"
          value={form.customer_type}
          onChange={(v) => setForm({ ...form, customer_type: v })}
          options={[
            { value: "distributor", label: "Distributor" },
            { value: "dealer", label: "Dealer" },
          ]}
        />
        <div className="space-y-1.5">
          <Label htmlFor="pct">Percentage</Label>
          <Input id="pct" type="number" value={form.percentage} onChange={(e) => setForm({ ...form, percentage: e.target.value })} />
        </div>
        <Select
          label="Category scope"
          value={form.category_id}
          onChange={(v) => setForm({ ...form, category_id: v, collection_id: "", colour_id: "" })}
          options={(categories.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
        />
        <Select
          label="Collection scope"
          value={form.collection_id}
          onChange={(v) => setForm({ ...form, collection_id: v, colour_id: "" })}
          options={(collections.data?.data ?? [])
            .filter((c) => !form.category_id || String(c.category_id) === form.category_id)
            .map((c) => ({ value: String(c.id), label: c.name }))}
        />
        <Select
          label="Colour scope"
          value={form.colour_id}
          onChange={(v) => setForm({ ...form, colour_id: v })}
          options={(colours.data?.data ?? [])
            .filter((c) => !form.collection_id || String(c.collection_id) === form.collection_id)
            .map((c) => ({ value: String(c.id), label: c.name }))}
        />
        {error ? (
          <p className="text-sm text-red-700 sm:col-span-2" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
