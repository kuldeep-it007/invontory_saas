"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Pagination, Select } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCategories,
  useCollections,
  useColours,
  useCreateProduct,
  useDeleteProduct,
  useProducts,
  useUpdateProduct,
} from "@/lib/api/hooks";
import type { Product } from "@/lib/api/types";

/**
 * Product catalog management (R11). Lists products (unique SKU per tenant —
 * R11.1) with their maintained price; managers create/edit via a modal with the
 * dependent Category/Collection/Colour selects (R11.2). The `max_products`
 * quota (R5.1) is enforced server-side.
 */
export default function ProductsPage(): JSX.Element {
  const { can } = useAuth();
  const { confirm, toast } = useAlerts();
  const canManage = can("catalog.manage");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Product | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const { data, isLoading, error } = useProducts(page);
  const del = useDeleteProduct();
  const [actionError, setActionError] = useState<string | null>(null);

  function openCreate() {
    setEditing(null);
    setModalOpen(true);
  }
  function openEdit(p: Product) {
    setEditing(p);
    setModalOpen(true);
  }

  async function remove(p: Product): Promise<void> {
    const ok = await confirm({
      title: "Delete product?",
      message: `"${p.name}" (${p.sku}) will be permanently removed. This cannot be undone.`,
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await del.mutateAsync(p.id);
      toast({ title: "Product deleted", message: `"${p.name}" was removed.`, tone: "success" });
    } catch (err) {
      setActionError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<Product>[] = [
    { header: "SKU", cell: (p) => <span className="font-mono text-xs">{p.sku}</span> },
    { header: "Name", cell: (p) => <span className="font-medium">{p.name}</span> },
    {
      header: "Price",
      className: "text-right tabular-nums",
      cell: (p) => (p.price != null && p.price !== "" ? Number(p.price).toFixed(2) : "—"),
    },
    { header: "Tax", cell: (p) => p.tax_type ?? "—" },
    { header: "UoM", cell: (p) => p.uom ?? "—" },
    ...(canManage
      ? [
          {
            header: "",
            className: "text-right",
            cell: (p: Product) => (
              <div className="flex justify-end gap-1">
                <IconButton label={`Edit ${p.name}`} onClick={() => openEdit(p)}>
                  <Icon.pencil size={16} />
                </IconButton>
                <IconButton
                  label={`Delete ${p.name}`}
                  tone="danger"
                  disabled={del.isPending}
                  onClick={() => void remove(p)}
                >
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            ),
          } as Column<Product>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Products"
        description="Your tenant catalog. SKUs are unique per tenant; set a base price per product."
        action={canManage ? <Button onClick={openCreate}>Add product</Button> : undefined}
      />

      {actionError ? (
        <p className="text-sm text-danger" role="alert">
          {actionError}
        </p>
      ) : null}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(p) => p.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No products yet."
      />

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}

      <ProductModal open={modalOpen} product={editing} onClose={() => setModalOpen(false)} />
    </div>
  );
}

/**
 * Create OR edit a product. When `product` is provided the form is pre-filled
 * and submits a PATCH; otherwise it creates. Price is maintained alongside the
 * other product details (R11.3).
 */
function ProductModal({
  open,
  product,
  onClose,
}: {
  open: boolean;
  product: Product | null;
  onClose: () => void;
}): JSX.Element {
  const create = useCreateProduct();
  const update = useUpdateProduct();
  const { toast } = useAlerts();
  const categories = useCategories();
  const collections = useCollections();
  const colours = useColours();

  const isEdit = product !== null;

  const initial = {
    sku: product?.sku ?? "",
    name: product?.name ?? "",
    price: product?.price != null ? String(product.price) : "",
    tax_type: product?.tax_type ?? "",
    uom: product?.uom ?? "",
    category_id: product?.category_id ? String(product.category_id) : "",
    collection_id: product?.collection_id ? String(product.collection_id) : "",
    colour_id: product?.colour_id ? String(product.colour_id) : "",
  };
  const [form, setForm] = useState(initial);
  const [error, setError] = useState<string | null>(null);
  // Re-seed the form whenever the modal target changes.
  const [seededFor, setSeededFor] = useState<number | "new" | null>(null);
  const targetKey = product?.id ?? "new";
  if (open && seededFor !== targetKey) {
    setForm(initial);
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
    const payload = {
      sku: form.sku,
      name: form.name,
      price: form.price === "" ? null : Number(form.price),
      tax_type: form.tax_type || null,
      uom: form.uom || null,
      category_id: form.category_id ? Number(form.category_id) : null,
      collection_id: form.collection_id ? Number(form.collection_id) : null,
      colour_id: form.colour_id ? Number(form.colour_id) : null,
    };
    try {
      if (isEdit && product) {
        await update.mutateAsync({ id: product.id, payload });
        toast({ title: "Product updated", message: `"${form.name}" saved.`, tone: "success" });
      } else {
        await create.mutateAsync(payload);
        toast({ title: "Product created", message: `"${form.name}" added.`, tone: "success" });
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
      size="lg"
      onClose={close}
      title={isEdit ? "Edit product" : "Add product"}
      footer={
        <ModalFormFooter
          formId="product-form"
          submitLabel={isEdit ? "Save changes" : "Create product"}
          busy={busy}
          disabled={!form.sku || !form.name}
          onCancel={close}
        />
      }
    >
      <form id="product-form" onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="SKU" required value={form.sku} onChange={(v) => setForm({ ...form, sku: v })} />
        <Field label="Name" required value={form.name} onChange={(v) => setForm({ ...form, name: v })} />
        <Field label="Price" type="number" value={form.price} onChange={(v) => setForm({ ...form, price: v })} />
        <Field label="Tax type" value={form.tax_type} onChange={(v) => setForm({ ...form, tax_type: v })} />
        <Field label="Unit of measure" value={form.uom} onChange={(v) => setForm({ ...form, uom: v })} />

        {/* Dependent selects: category -> collection -> colour. Picking a
            parent filters (and resets) its children, enforcing the hierarchy. */}
        <Select
          label="Category"
          value={form.category_id}
          onChange={(v) => setForm({ ...form, category_id: v, collection_id: "", colour_id: "" })}
          options={(categories.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
        />
        <Select
          label="Collection"
          value={form.collection_id}
          onChange={(v) => setForm({ ...form, collection_id: v, colour_id: "" })}
          options={(collections.data?.data ?? [])
            .filter((c) => !form.category_id || String(c.category_id) === form.category_id)
            .map((c) => ({ value: String(c.id), label: c.name }))}
        />
        <Select
          label="Colour"
          value={form.colour_id}
          onChange={(v) => setForm({ ...form, colour_id: v })}
          options={(colours.data?.data ?? [])
            .filter((c) => !form.collection_id || String(c.collection_id) === form.collection_id)
            .map((c) => ({ value: String(c.id), label: c.name }))}
        />

        {error ? (
          <p className="text-sm text-danger sm:col-span-2" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
