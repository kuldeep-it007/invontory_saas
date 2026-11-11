"use client";

import { useState } from "react";

import { useAuth } from "@/components/providers/auth-provider";
import { EditStockModal } from "@/components/stock/edit-stock-modal";
import { StockActions, useMovementOptions } from "@/components/stock/stock-actions";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useAllStock, useStockPoints } from "@/lib/api/hooks";
import type { StockRow } from "@/lib/api/types";

/**
 * Stocks Management (R13, R14, R18) — the Company's per-(product, stock-point)
 * on-hand view, plus inward/adjust/transfer actions (managers only). Every
 * product/stock-point input is a dropdown (no raw IDs).
 */
export default function StocksPage(): JSX.Element {
  const { can } = useAuth();
  const canManage = can("inventory.adjust");

  const allStock = useAllStock();
  const points = useStockPoints();
  const { productOptions, pointOptions } = useMovementOptions();

  const [editing, setEditing] = useState<StockRow | null>(null);

  const pointName = (id: number) => points.data?.data.find((p) => p.id === id)?.name ?? `#${id}`;

  const columns: Column<StockRow>[] = [
    { header: "Product", cell: (s) => s.product?.name ?? `#${s.product_id}` },
    { header: "SKU", cell: (s) => <span className="font-mono text-xs">{s.product?.sku ?? "—"}</span> },
    { header: "Stock point", cell: (s) => s.stock_point?.name ?? pointName(s.stock_point_id) },
    { header: "On hand", cell: (s) => Number(s.quantity).toFixed(3), className: "text-right tabular-nums" },
    {
      header: "Threshold",
      cell: (s) => (s.low_stock_threshold != null ? Number(s.low_stock_threshold).toFixed(0) : "—"),
      className: "text-right",
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
                aria-label={`Edit stock for ${s.product?.name ?? `product ${s.product_id}`}`}
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
        title="Stocks Management"
        description="On-hand stock by product and stock point. Assign, adjust, and transfer stock."
        action={
          canManage ? <StockActions productOptions={productOptions} pointOptions={pointOptions} /> : undefined
        }
      />

      <DataTable
        columns={columns}
        rows={allStock.data?.data ?? []}
        rowKey={(s) => s.id}
        isLoading={allStock.isLoading}
        error={allStock.error ? errorMessage(allStock.error) : null}
        emptyMessage="No stock recorded yet. Use “Stock inward” to assign stock to a point."
        footer={allStock.data ? <span>{allStock.data.data.length} rows</span> : undefined}
      />

      <EditStockModal
        stock={editing}
        pointName={editing ? (editing.stock_point?.name ?? pointName(editing.stock_point_id)) : ""}
        onClose={() => setEditing(null)}
      />
    </div>
  );
}
