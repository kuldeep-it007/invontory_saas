"use client";

import { useMemo, useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { ExportButtons, IconButton, SearchBox } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Badge, ListPageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCategories,
  useCreateCategory,
  useDeleteCatalogAttribute,
  useUpdateCatalogAttribute,
} from "@/lib/api/hooks";
import type { CatalogAttribute } from "@/lib/api/types";

/**
 * Categories — top of the catalog hierarchy (R11.2). A Category is the parent
 * of Collections. Managers (catalog.manage) can add, toggle active, and delete;
 * anyone with catalog.view sees the list. Styled as a DreamsPOS-style list page
 * with export affordances, in-table search, and icon action buttons.
 */
export default function CategoriesPage(): JSX.Element {
  const { can } = useAuth();
  const { confirm, toast } = useAlerts();
  const canManage = can("catalog.manage");

  const categories = useCategories();
  const del = useDeleteCatalogAttribute();
  const upd = useUpdateCatalogAttribute();
  const [actionError, setActionError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const rows = useMemo(() => {
    const all = categories.data?.data ?? [];
    const q = query.trim().toLowerCase();
    return q ? all.filter((c) => c.name.toLowerCase().includes(q) || (c.code ?? "").toLowerCase().includes(q)) : all;
  }, [categories.data, query]);

  async function onToggle(id: number, active: boolean): Promise<void> {
    setActionError(null);
    try {
      await upd.category.mutateAsync({ id, payload: { active: !active } });
    } catch (err) {
      setActionError(errorMessage(err));
    }
  }

  async function onDelete(id: number, name: string): Promise<void> {
    const ok = await confirm({
      title: "Delete category?",
      message: `"${name}" will be permanently removed. This cannot be undone.`,
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await del.category.mutateAsync(id);
      toast({ title: "Category deleted", message: `"${name}" was removed.`, tone: "success" });
    } catch (err) {
      setActionError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<CatalogAttribute>[] = [
    { header: "Code", cell: (c) => <span className="font-mono text-xs text-muted-foreground">{c.code ?? "—"}</span> },
    { header: "Name", cell: (c) => <span className="font-medium">{c.name}</span> },
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
            cell: (c: CatalogAttribute) => (
              <div className="flex justify-end gap-1">
                <IconButton label="Delete" tone="danger" disabled={del.category.isPending} onClick={() => void onDelete(c.id, c.name)}>
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            ),
          } as Column<CatalogAttribute>,
        ]
      : []),
  ];

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Categories"
        subtitle="Top level of the catalog hierarchy"
        actions={
          <>
            <ExportButtons />
            {canManage ? (
              <Button onClick={() => setOpen(true)}>
                <Icon.plus size={16} /> Add category
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

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(c) => c.id}
        isLoading={categories.isLoading}
        error={categories.error ? errorMessage(categories.error) : null}
        emptyMessage="No categories yet."
        toolbar={<SearchBox value={query} onChange={setQuery} />}
        footer={<span>{rows.length} categories</span>}
      />

      <CategoryModal open={open} onClose={() => setOpen(false)} />
    </div>
  );
}

function CategoryModal({ open, onClose }: { open: boolean; onClose: () => void }): JSX.Element {
  const create = useCreateCategory();
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setName("");
    setError(null);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync(name);
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
      title="Add category"
      footer={
        <ModalFormFooter
          formId="create-category-form"
          submitLabel="Add category"
          busy={create.isPending}
          disabled={!name}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-category-form" onSubmit={submit}>
        <Field label="Category name" required value={name} onChange={setName} />
        {error ? (
          <p className="mt-3 text-sm text-red-700" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}


