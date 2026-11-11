"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Select } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Badge } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useCollections, usePriceMutations, usePrices, useProducts } from "@/lib/api/hooks";
import type { Price } from "@/lib/api/types";

/* ─── Prices ──────────────────────────────────────────────────────────────── */

export function PricesSection({ canManage }: { canManage: boolean }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const { data, isLoading, error } = usePrices();
  const { remove } = usePriceMutations();
  // Resolve friendly names for a price's referenced product/collection so the
  // table shows "Name (#id)" instead of a bare id.
  const products = useProducts(1);
  const collections = useCollections();
  const [actionError, setActionError] = useState<string | null>(null);
  const [addOpen, setAddOpen] = useState(false);

  const refLabel = (p: Price): string => {
    if (p.level === "product") {
      const prod = (products.data?.data ?? []).find((x) => x.id === p.ref_id);
      return prod ? `${prod.name} (#${prod.id})` : `Product #${p.ref_id}`;
    }
    const coll = (collections.data?.data ?? []).find((x) => x.id === p.ref_id);
    return coll ? `${coll.name} (#${coll.id})` : `Collection #${p.ref_id}`;
  };

  async function del(p: Price) {
    const ok = await confirm({
      title: "Delete price?",
      message: "This price configuration will be permanently removed.",
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await remove.mutateAsync(p.id);
      toast({ title: "Price deleted", tone: "success" });
    } catch (e) {
      setActionError(errorMessage(e));
      toast({ title: "Delete failed", message: errorMessage(e), tone: "danger" });
    }
  }

  const columns: Column<Price>[] = [
    { header: "Level", cell: (p) => <Badge>{p.level}</Badge> },
    {
      header: "Product / Collection",
      cell: (p) => <span className="font-medium">{refLabel(p)}</span>,
    },
    { header: "Ref ID", cell: (p) => <span className="font-mono text-xs">#{p.ref_id}</span> },
    { header: "Amount", cell: (p) => Number(p.amount).toFixed(2), className: "text-right tabular-nums" },
    {
      header: "Markup / MUP",
      cell: (p) => (p.markup_type ? `${p.markup_type} +${Number(p.markup_value ?? 0)}%` : "—"),
    },
    ...(canManage
      ? [
          {
            header: "",
            className: "text-right",
            cell: (p: Price) => (
              <IconButton
                label="Delete price"
                tone="danger"
                disabled={remove.isPending}
                onClick={() => void del(p)}
              >
                <Icon.trash size={16} />
              </IconButton>
            ),
          } as Column<Price>,
        ]
      : []),
  ];

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Prices</h2>
        {canManage ? (
          <Button variant="secondary" onClick={() => setAddOpen(true)}>
            Add price
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
        rowKey={(p) => p.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No prices configured."
      />
      <PriceModal open={addOpen} onClose={() => setAddOpen(false)} />
    </section>
  );
}

function PriceModal({ open, onClose }: { open: boolean; onClose: () => void }): JSX.Element {
  const { create } = usePriceMutations();
  const collections = useCollections();
  const products = useProducts(1);

  const [level, setLevel] = useState<"product" | "collection">("collection");
  const [refId, setRefId] = useState("");
  const [amount, setAmount] = useState("");
  const [markupType, setMarkupType] = useState("");
  const [markupValue, setMarkupValue] = useState("");
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setLevel("collection");
    setRefId("");
    setAmount("");
    setMarkupType("");
    setMarkupValue("");
    setError(null);
  }

  const refOptions =
    level === "collection"
      ? (collections.data?.data ?? []).map((c) => ({
          value: String(c.id),
          label: c.category ? `${c.category.name} / ${c.name}` : c.name,
        }))
      : (products.data?.data ?? []).map((p) => ({ value: String(p.id), label: `${p.sku} — ${p.name}` }));

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync({
        level,
        ref_id: Number(refId),
        amount: Number(amount),
        markup_type: level === "collection" && markupType ? markupType : null,
        markup_value: level === "collection" && markupType ? Number(markupValue || 0) : null,
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
      title="Add price"
      footer={
        <ModalFormFooter
          formId="create-price-form"
          submitLabel="Add price"
          busy={create.isPending}
          disabled={!refId || !amount}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-price-form" onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          label="Level"
          value={level}
          onChange={(v) => {
            setLevel(v as "product" | "collection");
            setRefId("");
          }}
          options={[
            { value: "collection", label: "Collection" },
            { value: "product", label: "Product" },
          ]}
        />
        <Select label={level === "collection" ? "Collection" : "Product"} value={refId} onChange={setRefId} options={refOptions} />
        <Field label="Amount" value={amount} onChange={setAmount} />
        {level === "collection" ? (
          <>
            <Select
              label="Markup type (optional)"
              value={markupType}
              onChange={setMarkupType}
              options={[
                { value: "markup", label: "Markup" },
                { value: "mup", label: "MUP" },
              ]}
            />
            <Field label="Markup %" value={markupValue} onChange={setMarkupValue} />
          </>
        ) : null}
        {error ? (
          <p className="text-sm text-red-700 sm:col-span-2" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
