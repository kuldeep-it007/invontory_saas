"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
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
  useDealerOptions,
  useDiscountMutations,
  useDiscounts,
  useDistributorOptions,
  useProductOptions,
} from "@/lib/api/hooks";
import type { Discount } from "@/lib/api/types";

/* ─── Discounts ─────────────────────────────────────────────────────────────── */

/**
 * Priority level of a discount, mirroring the server's resolution order
 * (Dealer > Distributor > Product > General). Only the highest-priority
 * applicable discount is applied to a price; discounts never stack.
 */
export function discountLevel(d: Pick<Discount, "buyer_type" | "product_id" | "category_id" | "collection_id" | "colour_id">): string {
  if (d.buyer_type === "dealer") return "Dealer";
  if (d.buyer_type === "distributor") return "Distributor";
  if (d.product_id || d.category_id || d.collection_id || d.colour_id) return "Product";
  return "General";
}

/** Human-readable discount value: "10%" or "50.00 off". */
export function discountValue(d: Pick<Discount, "value_type" | "percentage" | "amount">): string {
  return d.value_type === "fixed" ? `${Number(d.amount ?? 0).toFixed(2)} off` : `${Number(d.percentage)}%`;
}

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
    { header: "Priority", cell: (d) => <Badge>{discountLevel(d)}</Badge> },
    { header: "Type", cell: (d) => d.type },
    { header: "Value", className: "tabular-nums", cell: (d) => discountValue(d) },
    {
      header: "Buyer",
      cell: (d) => (d.buyer_type ? `${d.buyer_type} #${d.buyer_id}` : (d.customer_type ?? "any")),
    },
    {
      header: "Scope",
      cell: (d) =>
        [
          d.product_id && `product #${d.product_id}`,
          d.category_id && `cat #${d.category_id}`,
          d.collection_id && `coll #${d.collection_id}`,
          d.colour_id && `col #${d.colour_id}`,
        ]
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
        <div>
          <h2 className="text-sm font-semibold text-foreground">Discounts</h2>
          <p className="text-xs text-muted-foreground">
            Only one discount applies per price, by priority: Dealer, then Distributor, then Product, then General.
          </p>
        </div>
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
  const categories = useCategories("mine");
  const collections = useCollections("mine");
  const colours = useColours("mine");

  const empty = {
    type: "customer",
    customer_type: "",
    value_type: "percent",
    percentage: "",
    amount: "",
    buyer_type: "",
    distributor_id: "",
    dealer_id: "",
    product_id: "",
    category_id: "",
    collection_id: "",
    colour_id: "",
  };
  const [form, setForm] = useState(empty);
  const [error, setError] = useState<string | null>(null);

  // A distributor user discounts for its own dealers or for other distributors;
  // the server enforces the same rule.
  const { identity } = useAuth();
  const ownDistributorId = identity && !identity.dealer_id ? (identity.distributor_id ?? null) : null;
  const dealerParentId =
    form.buyer_type !== "dealer" ? null : (ownDistributorId ?? (form.distributor_id ? Number(form.distributor_id) : null));
  const showDistributorPicker =
    form.buyer_type === "distributor" || (form.buyer_type === "dealer" && ownDistributorId === null);

  const distributors = useDistributorOptions();
  const dealers = useDealerOptions(dealerParentId);
  const products = useProductOptions("mine");

  const isFixed = form.value_type === "fixed";
  const buyerId = form.buyer_type === "dealer" ? form.dealer_id : form.buyer_type === "distributor" ? form.distributor_id : "";
  const valueMissing = isFixed ? !form.amount : !form.percentage;
  const buyerMissing = form.buyer_type !== "" && !buyerId;

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
        value_type: form.value_type,
        percentage: isFixed ? null : Number(form.percentage),
        amount: isFixed ? Number(form.amount) : null,
        buyer_type: form.buyer_type || null,
        buyer_id: buyerId ? Number(buyerId) : null,
        product_id: form.product_id ? Number(form.product_id) : null,
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
      size="lg"
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
          disabled={valueMissing || buyerMissing}
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
        <Select
          label="Discount as"
          value={form.value_type}
          onChange={(v) => setForm({ ...form, value_type: v || "percent", percentage: "", amount: "" })}
          options={[
            { value: "percent", label: "Percentage (%)" },
            { value: "fixed", label: "Fixed amount off per unit" },
          ]}
        />
        {isFixed ? (
          <div className="space-y-1.5">
            <Label htmlFor="discount-amount">Amount *</Label>
            <Input
              id="discount-amount"
              type="number"
              min="0"
              step="0.01"
              value={form.amount}
              onChange={(e) => setForm({ ...form, amount: e.target.value })}
            />
          </div>
        ) : (
          <div className="space-y-1.5">
            <Label htmlFor="discount-pct">Percentage *</Label>
            <Input
              id="discount-pct"
              type="number"
              min="0"
              max="100"
              step="0.01"
              value={form.percentage}
              onChange={(e) => setForm({ ...form, percentage: e.target.value })}
            />
          </div>
        )}

        {/* Buyer scope decides the priority level (Dealer > Distributor). */}
        <Select
          label="Applies to buyer (optional)"
          value={form.buyer_type}
          onChange={(v) => setForm({ ...form, buyer_type: v, distributor_id: "", dealer_id: "" })}
          options={[
            { value: "distributor", label: ownDistributorId ? "Another distributor" : "A specific distributor" },
            { value: "dealer", label: ownDistributorId ? "One of my dealers" : "A specific dealer" },
          ]}
        />
        {showDistributorPicker ? (
          <Select
            label="Distributor"
            value={form.distributor_id}
            onChange={(v) => setForm({ ...form, distributor_id: v, dealer_id: "" })}
            options={(distributors.data?.data ?? [])
              .filter((d) => d.id !== ownDistributorId)
              .map((d) => ({ value: String(d.id), label: d.name }))}
          />
        ) : null}
        {form.buyer_type === "dealer" ? (
          <Select
            label="Dealer"
            value={form.dealer_id}
            onChange={(v) => setForm({ ...form, dealer_id: v })}
            options={(dealers.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))}
          />
        ) : null}

        <Select
          label="Product (optional)"
          value={form.product_id}
          onChange={(v) => setForm({ ...form, product_id: v })}
          options={(products.data?.data ?? []).map((p) => ({ value: String(p.id), label: `${p.sku} — ${p.name}` }))}
        />
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
