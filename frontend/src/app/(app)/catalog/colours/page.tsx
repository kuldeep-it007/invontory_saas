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
  useCollections,
  useColours,
  useCreateColour,
  useDeleteCatalogAttribute,
  useUpdateCatalogAttribute,
} from "@/lib/api/hooks";
import type { Colour } from "@/lib/api/types";

/**
 * Colours — the leaf of the catalog hierarchy (R11.2). A Colour belongs to a
 * Collection (which in turn belongs to a Category). The add form enforces the
 * hierarchy with a dependent parent-collection select. Styled as a
 * DreamsPOS-style list page.
 */
export default function ColoursPage(): JSX.Element {
  const { can } = useAuth();
  const canManage = can("catalog.manage");

  const { confirm, toast } = useAlerts();
  const collections = useCollections();
  const colours = useColours();
  const del = useDeleteCatalogAttribute();
  const upd = useUpdateCatalogAttribute();
  const [actionError, setActionError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const collectionOptions = (collections.data?.data ?? []).map((c) => ({
    value: String(c.id),
    label: c.category ? `${c.category.name} / ${c.name}` : c.name,
  }));

  const rows = useMemo(() => {
    const all = colours.data?.data ?? [];
    const q = query.trim().toLowerCase();
    return q
      ? all.filter(
          (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.code ?? "").toLowerCase().includes(q) ||
            (c.collection?.name ?? "").toLowerCase().includes(q),
        )
      : all;
  }, [colours.data, query]);

  async function onToggle(id: number, active: boolean): Promise<void> {
    setActionError(null);
    try {
      await upd.colour.mutateAsync({ id, payload: { active: !active } });
    } catch (err) {
      setActionError(errorMessage(err));
    }
  }

  async function onDelete(id: number, name: string): Promise<void> {
    const ok = await confirm({
      title: "Delete colour?",
      message: `"${name}" will be permanently removed. This cannot be undone.`,
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    setActionError(null);
    try {
      await del.colour.mutateAsync(id);
      toast({ title: "Colour deleted", message: `"${name}" was removed.`, tone: "success" });
    } catch (err) {
      setActionError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<Colour>[] = [
    { header: "Code", cell: (c) => <span className="font-mono text-xs text-muted-foreground">{c.code ?? "—"}</span> },
    { header: "Name", cell: (c) => <span className="font-medium">{c.name}</span> },
    { header: "Collection", cell: (c) => c.collection?.name ?? `#${c.collection_id}` },
    { header: "Category", cell: (c) => c.collection?.category?.name ?? "—" },
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
            cell: (c: Colour) => (
              <div className="flex justify-end gap-1">
                <IconButton label="Delete" tone="danger" disabled={del.colour.isPending} onClick={() => void onDelete(c.id, c.name)}>
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            ),
          } as Column<Colour>,
        ]
      : []),
  ];

  const canAdd = canManage && collectionOptions.length > 0;

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Colours"
        subtitle="Belong to a collection — the leaf of the catalog hierarchy"
        actions={
          <>
            <ExportButtons />
            {canAdd ? (
              <Button onClick={() => setOpen(true)}>
                <Icon.plus size={16} /> Add colour
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

      {canManage && !collectionOptions.length ? (
        <StateMessage>Add a collection first — colours belong to a collection.</StateMessage>
      ) : null}

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(c) => c.id}
        isLoading={colours.isLoading}
        error={colours.error ? errorMessage(colours.error) : null}
        emptyMessage="No colours yet."
        toolbar={<SearchBox value={query} onChange={setQuery} />}
        footer={<span>{rows.length} colours</span>}
      />

      <ColourModal open={open} onClose={() => setOpen(false)} collectionOptions={collectionOptions} />
    </div>
  );
}

function ColourModal({
  open,
  onClose,
  collectionOptions,
}: {
  open: boolean;
  onClose: () => void;
  collectionOptions: { value: string; label: string }[];
}): JSX.Element {
  const create = useCreateColour();
  const [collectionId, setCollectionId] = useState("");
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setCollectionId("");
    setName("");
    setError(null);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync({ collectionId: Number(collectionId), name });
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
      title="Add colour"
      footer={
        <ModalFormFooter
          formId="create-colour-form"
          submitLabel="Add colour"
          busy={create.isPending}
          disabled={!name || !collectionId}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-colour-form" onSubmit={submit} className="space-y-4">
        <Select label="Parent collection" value={collectionId} onChange={setCollectionId} options={collectionOptions} />
        <Field label="Colour name" required value={name} onChange={setName} />
        {error ? (
          <p className="text-sm text-red-700" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
