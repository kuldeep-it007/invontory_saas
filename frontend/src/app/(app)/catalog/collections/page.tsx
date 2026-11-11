"use client";

import { useMemo, useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Select } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { ExportButtons, IconButton, SearchBox } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Badge, ListPageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCategories,
  useCollections,
  useCreateCollection,
  useDeleteCatalogAttribute,
  useUpdateCatalogAttribute,
} from "@/lib/api/hooks";
import type { Collection } from "@/lib/api/types";

/**
 * Collections — the middle of the catalog hierarchy (R11.2). A Collection
 * belongs to a Category and is the parent of Colours. The add form enforces
 * the hierarchy with a dependent parent-category select. Styled as a
 * DreamsPOS-style list page.
 */
export default function CollectionsPage(): JSX.Element {
  const { can } = useAuth();
  const canManage = can("catalog.manage");

  const { confirm, toast } = useAlerts();
  const categories = useCategories();
  const collections = useCollections();
  const del = useDeleteCatalogAttribute();
  const upd = useUpdateCatalogAttribute();
  const [actionError, setActionError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const categoryOptions = (categories.data?.data ?? []).map((c) => ({
    value: String(c.id),
    label: c.name,
  }));

  const rows = useMemo(() => {
    const all = collections.data?.data ?? [];
    const q = query.trim().toLowerCase();
    return q
      ? all.filter(
          (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.code ?? "").toLowerCase().includes(q) ||
            (c.category?.name ?? "").toLowerCase().includes(q),
        )
      : all;
  }, [collections.data, query]);

  async function onToggle(id: number, active: boolean): Promise<void> {
    setActionError(null);
    try {
      await upd.collection.mutateAsync({ id, payload: { active: !active } });
    } catch (err) {
      setActionError(errorMessage(err));
    }
  }

  async function onDelete(id: number, name: string): Promise<void> {
    const ok = await confirm({
      title: "Delete collection?",
      message: `"${name}" will be permanently removed. This cannot be undone.`,
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await del.collection.mutateAsync(id);
      toast({ title: "Collection deleted", message: `"${name}" was removed.`, tone: "success" });
    } catch (err) {
      setActionError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<Collection>[] = [
    { header: "Code", cell: (c) => <span className="font-mono text-xs text-muted-foreground">{c.code ?? "—"}</span> },
    { header: "Name", cell: (c) => <span className="font-medium">{c.name}</span> },
    { header: "Category", cell: (c) => c.category?.name ?? `#${c.category_id}` },
    {
      header: "Status",
      cell: (c) =>
        canManage ? (
          <button onClick={() => void onToggle(c.id, c.active ?? true)} className="cursor-pointer" aria-label="Toggle status">
            <Badge>{c.active ?? true ? "active" : "inactive"}</Badge>
          </button>
        ) : (
          <Badge>{c.active ?? true ? "active" : "inactive"}</Badge>
        ),
    },
    ...(canManage
      ? [
          {
            header: "Actions",
            className: "text-right",
            cell: (c: Collection) => (
              <div className="flex justify-end gap-1">
                <IconButton label="Delete" tone="danger" disabled={del.collection.isPending} onClick={() => void onDelete(c.id, c.name)}>
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            ),
          } as Column<Collection>,
        ]
      : []),
  ];

  const canAdd = canManage && categoryOptions.length > 0;

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Collections"
        subtitle="Belong to a category and group colours"
        actions={
          <>
            <ExportButtons />
            {canAdd ? (
              <Button onClick={() => setOpen(true)}>
                <Icon.plus size={16} /> Add collection
              </Button>
            ) : null}
          </>
        }
      />

      {actionError ? (
        <p className="text-sm text-red-700" role="alert">
          {actionError}
        </p>
      ) : null}

      {canManage && !categoryOptions.length ? (
        <StateMessage>Add a category first — collections belong to a category.</StateMessage>
      ) : null}

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(c) => c.id}
        isLoading={collections.isLoading}
        error={collections.error ? errorMessage(collections.error) : null}
        emptyMessage="No collections yet."
        toolbar={<SearchBox value={query} onChange={setQuery} />}
        footer={<span>{rows.length} collections</span>}
      />

      <CollectionModal open={open} onClose={() => setOpen(false)} categoryOptions={categoryOptions} />
    </div>
  );
}

function CollectionModal({
  open,
  onClose,
  categoryOptions,
}: {
  open: boolean;
  onClose: () => void;
  categoryOptions: { value: string; label: string }[];
}): JSX.Element {
  const create = useCreateCollection();
  const [categoryId, setCategoryId] = useState("");
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setCategoryId("");
    setName("");
    setError(null);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync({ categoryId: Number(categoryId), name });
      reset();
      onClose();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <Modal
      open={open}
      size="sm"
      onClose={() => {
        reset();
        onClose();
      }}
      title="Add collection"
      footer={
        <ModalFormFooter
          formId="create-collection-form"
          submitLabel="Add collection"
          busy={create.isPending}
          disabled={!name || !categoryId}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-collection-form" onSubmit={submit} className="space-y-4">
        <Select label="Parent category" value={categoryId} onChange={setCategoryId} options={categoryOptions} />
        <Field label="Collection name" required value={name} onChange={setName} />
        {error ? (
          <p className="text-sm text-red-700" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
