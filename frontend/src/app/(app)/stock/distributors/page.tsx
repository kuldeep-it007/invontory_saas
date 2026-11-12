"use client";

import { Suspense, useState } from "react";

import { useAuth } from "@/components/providers/auth-provider";
import { useImpersonation } from "@/components/providers/impersonation-provider";
import { AddDistributorStockModal } from "@/components/stock/add-distributor-stock-modal";
import { EditStockModal } from "@/components/stock/edit-stock-modal";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Icon } from "@/components/ui/icons";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useDistributorOptions, useDistributorStock, useProductOptions } from "@/lib/api/hooks";
import type { StockRow } from "@/lib/api/types";
import { useUrlFilters } from "@/lib/use-url-filters";

/**
 * Distributor Stock Management (R13.5).
 *
 * Distributor stock is owned by each distributor (its own stock points and
 * ledger) and is kept separate from Company stock. Stock normally lands here as
 * the distributor's orders with the Company are confirmed.
 *
 *   • A Distributor sees ONLY its own stock (read-only).
 *   • A Company user with `inventory.adjust` sees every distributor's stock,
 *     can filter by distributor / product / low stock, add stock to a
 *     distributor, and edit a row's quantity (ledgered + audited).
 *
 * Filters live in the URL (`?distributor_id=&product_id=&low=1`) so dashboard
 * links, refreshes and the filter controls stay in sync.
 */
export default function DistributorStockPage(): JSX.Element {
  return (
    <Suspense fallback={null}>
      <DistributorStockView />
    </Suspense>
  );
}

function DistributorStockView(): JSX.Element {
  const { identity, can } = useAuth();
  const { acting } = useImpersonation();
  // A distributor login, or a company user "acting as" a distributor, sees ONE
  // distributor's stock read-only; otherwise the Company manages them all.
  const actingDistributorId = acting?.kind === "distributor" ? String(acting.buyer_id) : "";
  const ownDistributorId = identity?.distributor_id ?? null;
  const isDistributorUser = ownDistributorId != null || actingDistributorId !== "";
  // A distributor manages (add / edit) its OWN stock; the Company manages any
  // distributor's. The backend enforces both, this just shows the controls.
  const canManage = ownDistributorId != null || can("inventory.adjust");
  const fixedDistributorId =
    ownDistributorId ?? (actingDistributorId !== "" ? Number(actingDistributorId) : undefined);

  const { values, setFilters } = useUrlFilters(["distributor_id", "product_id", "low"] as const);
  const urlDistributorId = /^\d+$/.test(values.distributor_id) ? values.distributor_id : "";
  const distributorId = actingDistributorId || urlDistributorId;
  const productId = /^\d+$/.test(values.product_id) ? values.product_id : "";
  const lowOnly = values.low === "1" || values.low === "true";

  const query: Record<string, string | number> = {
    ...(distributorId && (actingDistributorId || identity?.distributor_id == null)
      ? { distributor_id: distributorId }
      : {}),
    ...(productId ? { product_id: productId } : {}),
    ...(lowOnly ? { low: 1 } : {}),
  };
  const stock = useDistributorStock(query, identity?.id);
  const distributors = useDistributorOptions();
  const products = useProductOptions();
  const rows = stock.data?.data ?? [];

  const [adding, setAdding] = useState(false);
  const [editing, setEditing] = useState<StockRow | null>(null);

  const ownerName = (s: StockRow) => s.owner?.name ?? s.stockable?.name ?? `Distributor #${s.stockable_id}`;
  const isLow = (s: StockRow) =>
    s.low_stock_threshold != null && Number(s.quantity) <= Number(s.low_stock_threshold);
  const totalUnits = rows.reduce((sum, s) => sum + Number(s.quantity), 0);
  const hasFilters = Boolean(urlDistributorId || productId || lowOnly);

  const columns: Column<StockRow>[] = [
    ...(isDistributorUser
      ? []
      : [
          {
            header: "Distributor",
            cell: (s: StockRow) => <span className="font-medium">{ownerName(s)}</span>,
          } as Column<StockRow>,
        ]),
    { header: "Product", cell: (s) => <span className="font-medium">{s.product?.name ?? `#${s.product_id}`}</span> },
    { header: "SKU", cell: (s) => <span className="font-mono text-xs">{s.product?.sku ?? "—"}</span> },
    { header: "Stock point", cell: (s) => s.stock_point?.name ?? `#${s.stock_point_id}` },
    { header: "On hand", cell: (s) => Number(s.quantity).toFixed(3), className: "text-right tabular-nums" },
    {
      header: "Threshold",
      cell: (s) => (s.low_stock_threshold != null ? Number(s.low_stock_threshold).toFixed(0) : "—"),
      className: "text-right",
    },
    {
      header: "Status",
      cell: (s) =>
        isLow(s) ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2.5 py-0.5 text-xs font-medium text-warning ring-1 ring-inset ring-warning/20">
            <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            Low
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-xs font-medium text-success ring-1 ring-inset ring-success/20">
            <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            OK
          </span>
        ),
    },
    ...(canManage
      ? [
          {
            header: "Actions",
            className: "text-right",
            cell: (s: StockRow) => (
              <Button
                size="sm"
                variant="secondary"
                aria-label={`Edit ${s.product?.name ?? `product ${s.product_id}`} stock for ${ownerName(s)}`}
                onClick={() => setEditing(s)}
              >
                Edit
              </Button>
            ),
          } as Column<StockRow>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title={isDistributorUser ? "My Stock" : "Distributor Stock Management"}
        description={
          isDistributorUser
            ? "Your stock on hand. It grows as your orders with the Company are confirmed, and you can add or edit quantities yourself."
            : "Stock held by each distributor, kept separate from Company stock."
        }
        action={
          canManage ? (
            <Button onClick={() => setAdding(true)}>
              <Icon.plus size={16} /> Add stock
            </Button>
          ) : undefined
        }
      />

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(s) => s.id}
        isLoading={stock.isLoading}
        error={stock.error ? errorMessage(stock.error) : null}
        emptyMessage={
          hasFilters
            ? "No distributor stock matches these filters."
            : isDistributorUser
              ? "You hold no stock yet. Use “Add stock”, or it appears here once your order with the Company is confirmed."
              : "No distributor holds stock yet. Use “Add stock” or confirm a distributor order."
        }
        toolbar={
          <div className="flex flex-wrap items-center gap-3">
            {isDistributorUser ? null : (
              <select
                aria-label="Filter by distributor"
                value={distributorId}
                onChange={(e) => setFilters({ distributor_id: e.target.value })}
                className={selectClass}
              >
                <option value="">All distributors</option>
                {(distributors.data?.data ?? []).map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
            )}
            <select
              aria-label="Filter by product"
              value={productId}
              onChange={(e) => setFilters({ product_id: e.target.value })}
              className={selectClass}
            >
              <option value="">All products</option>
              {(products.data?.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.sku})
                </option>
              ))}
            </select>
            <button
              type="button"
              aria-pressed={lowOnly}
              onClick={() => setFilters({ low: lowOnly ? "" : "1" })}
              className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                lowOnly
                  ? "border-primary bg-primary text-primary-foreground"
                  : "border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground"
              }`}
            >
              Low stock only
            </button>
            {hasFilters ? (
              <button
                type="button"
                onClick={() => setFilters({ distributor_id: "", product_id: "", low: "" })}
                className="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
              >
                Clear filters
              </button>
            ) : null}
          </div>
        }
        footer={
          stock.data ? (
            <span>
              {rows.length} rows · {totalUnits.toFixed(3)} units
            </span>
          ) : undefined
        }
      />

      <AddDistributorStockModal
        open={adding}
        onClose={() => setAdding(false)}
        defaultDistributorId={distributorId}
        fixedDistributorId={fixedDistributorId}
      />
      <EditStockModal
        stock={editing}
        pointName={editing ? (editing.stock_point?.name ?? `#${editing.stock_point_id}`) : ""}
        ownerName={editing ? ownerName(editing) : undefined}
        onClose={() => setEditing(null)}
      />
    </div>
  );
}

const selectClass =
  "rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30";
