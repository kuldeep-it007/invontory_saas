"use client";

import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Icon } from "@/components/ui/icons";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useLowStock, useStockPoints } from "@/lib/api/hooks";
import type { StockRow } from "@/lib/api/types";

/**
 * Low Stock Management Report (R19) — a dedicated page listing every stock row
 * at or below its configured threshold, computed from actual inventory by
 * LowStockService (GET /api/stock/low). Supports a client-side CSV export of
 * the current breaching rows.
 */
export default function LowStockPage(): JSX.Element {
  const lowStock = useLowStock();
  const points = useStockPoints();

  const pointName = (id: number) => points.data?.data.find((p) => p.id === id)?.name ?? `#${id}`;
  const rows = lowStock.data?.data ?? [];

  const columns: Column<StockRow>[] = [
    { header: "Product", cell: (s) => s.product?.name ?? `#${s.product_id}` },
    { header: "SKU", cell: (s) => <span className="font-mono text-xs">{s.product?.sku ?? "—"}</span> },
    { header: "Stock point", cell: (s) => s.stock_point?.name ?? pointName(s.stock_point_id) },
    { header: "On hand", cell: (s) => Number(s.quantity).toFixed(0), className: "text-right tabular-nums" },
    {
      header: "Threshold",
      cell: (s) => (s.low_stock_threshold != null ? Number(s.low_stock_threshold).toFixed(0) : "—"),
      className: "text-right",
    },
  ];

  function exportCsv() {
    const header = ["Product", "SKU", "Stock point", "On hand", "Threshold"];
    const lines = rows.map((s) =>
      [
        csvCell(s.product?.name ?? `#${s.product_id}`),
        csvCell(s.product?.sku ?? ""),
        csvCell(s.stock_point?.name ?? pointName(s.stock_point_id)),
        Number(s.quantity).toFixed(3),
        s.low_stock_threshold != null ? Number(s.low_stock_threshold).toFixed(3) : "",
      ].join(","),
    );
    const csv = [header.join(","), ...lines].join("\r\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `low-stock-report-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Low Stock Management Report"
        description="Products at or below their configured low-stock threshold."
        action={
          <Button variant="secondary" onClick={exportCsv} disabled={rows.length === 0}>
            <Icon.fileExcel size={16} /> Export CSV
          </Button>
        }
      />

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(s) => s.id}
        isLoading={lowStock.isLoading}
        error={lowStock.error ? errorMessage(lowStock.error) : null}
        emptyMessage="No products are at or below their threshold."
        footer={lowStock.data ? <span>{rows.length} breaching rows</span> : undefined}
      />
    </div>
  );
}

/** Escape a CSV cell (wrap in quotes and double any embedded quotes). */
function csvCell(value: string): string {
  if (/[",\r\n]/.test(value)) return `"${value.replace(/"/g, '""')}"`;
  return value;
}
