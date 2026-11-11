"use client";

import { useState } from "react";

import { DataTable, type Column } from "@/components/ui/data-table";
import { Pagination } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Card, Input, Label, PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useCatalog } from "@/lib/api/hooks";
import type { Product } from "@/lib/api/types";

/**
 * Buyer-scoped catalog browse (R11.4–R11.6). Optionally scope the catalog to a
 * specific Distributor/Dealer via buyer_type + buyer_id; absent or unconfigured
 * the full tenant catalog is returned. Paginated against the active tenant DB.
 */
export default function CatalogPage(): JSX.Element {
  const [page, setPage] = useState(1);
  const [buyerType, setBuyerType] = useState("");
  const [buyerId, setBuyerId] = useState("");

  const query: Record<string, string | number> = { page };
  if (buyerType) query.buyer_type = buyerType;
  if (buyerId) query.buyer_id = buyerId;

  const { data, isLoading, error } = useCatalog(query);

  const columns: Column<Product>[] = [
    { header: "SKU", cell: (p) => <span className="font-mono text-xs">{p.sku}</span> },
    { header: "Name", cell: (p) => <span className="font-medium">{p.name}</span> },
    { header: "Tax", cell: (p) => p.tax_type ?? "—" },
    { header: "UoM", cell: (p) => p.uom ?? "—" },
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Catalog"
        description="Browse the catalog as a buyer sees it, with optional attribute scoping."
      />

      <Card>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <Label htmlFor="buyer-type">Buyer type</Label>
            <select
              id="buyer-type"
              value={buyerType}
              onChange={(e) => {
                setBuyerType(e.target.value);
                setPage(1);
              }}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              <option value="">All products</option>
              <option value="distributor">Distributor</option>
              <option value="dealer">Dealer</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="buyer-id">Buyer ID</Label>
            <Input
              id="buyer-id"
              type="number"
              value={buyerId}
              onChange={(e) => {
                setBuyerId(e.target.value);
                setPage(1);
              }}
              disabled={!buyerType}
              placeholder="e.g. 12"
            />
          </div>
        </div>
      </Card>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(p) => p.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No products visible for this scope."
      />

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}
    </div>
  );
}
