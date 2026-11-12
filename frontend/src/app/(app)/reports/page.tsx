"use client";

import { Suspense, useState } from "react";

import { Button } from "@/components/ui/button";
import { Pagination } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Card, Input, Label, PageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useEffectiveAuth } from "@/components/providers/impersonation-provider";
import { useReport } from "@/lib/api/hooks";
import { useUrlFilters } from "@/lib/use-url-filters";

/**
 * Standard report runner (R28, R29). Picks a report type and optional date
 * range (defaults to the previous calendar month server-side when omitted —
 * R29.2), then renders the paginated result. Columns are derived dynamically
 * from the first row so the UI works for every report shape.
 */
const REPORT_TYPES = [
  "stock-on-hand",
  "stock-ledger",
  "sales",
  "purchases",
  "outstanding-ageing",
  "payments",
  "order-status",
  "low-stock",
  "plan-usage",
];

export default function ReportsPage(): JSX.Element {
  return (
    <Suspense fallback={null}>
      <ReportsView />
    </Suspense>
  );
}

function ReportsView(): JSX.Element {
  // The report and date range live in the URL (?report=&from=&to=) so dashboard
  // links, refreshes and the controls stay in sync.
  const { values, setFilters } = useUrlFilters(["report", "from", "to"] as const);
  // Plan usage is tenant-level data; distributors and dealers cannot run it.
  const { actorKind } = useEffectiveAuth();
  const reportTypes = actorKind === "company" ? REPORT_TYPES : REPORT_TYPES.filter((t) => t !== "plan-usage");
  const type = reportTypes.includes(values.report) ? values.report : reportTypes[0];
  const from = /^\d{4}-\d{2}-\d{2}$/.test(values.from) ? values.from : "";
  const to = /^\d{4}-\d{2}-\d{2}$/.test(values.to) ? values.to : "";
  const [page, setPage] = useState(1);

  const query: Record<string, string | number> = { page };
  if (from) query.from = from;
  if (to) query.to = to;

  const { data, isLoading, error, refetch, isFetching } = useReport(type, query);

  const rows = data?.data ?? [];
  const columns = rows.length > 0 ? Object.keys(rows[0]) : [];

  return (
    <div className="space-y-6">
      <PageHeader title="Reports" description="Run a standard report over your tenant data." />

      <Card>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div className="space-y-1.5">
            <Label htmlFor="report-type">Report</Label>
            <select
              id="report-type"
              value={type}
              onChange={(e) => {
                setFilters({ report: e.target.value });
                setPage(1);
              }}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              {reportTypes.map((t) => (
                <option key={t} value={t}>
                  {humanizeHeader(t.replace(/-/g, "_"))}
                </option>
              ))}
            </select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="from">From</Label>
            <Input id="from" type="date" value={from} onChange={(e) => setFilters({ from: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="to">To</Label>
            <Input id="to" type="date" value={to} onChange={(e) => setFilters({ to: e.target.value })} />
          </div>
          <div className="flex items-end">
            <Button onClick={() => void refetch()} disabled={isFetching}>
              {isFetching ? "Running…" : "Run report"}
            </Button>
          </div>
        </div>
      </Card>

      {isLoading ? (
        <StateMessage>Loading report…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : rows.length === 0 ? (
        <StateMessage>No rows for the selected filters.</StateMessage>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border bg-card">
          <table className="w-full table-auto border-collapse text-left text-sm">
            <thead className="bg-muted/60">
              <tr>
                {columns.map((col) => (
                  <th key={col} scope="col" className="px-3 py-3 align-bottom font-medium text-muted-foreground [overflow-wrap:anywhere]">
                    {humanizeHeader(col)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, idx) => (
                <tr key={idx} className="border-t border-border">
                  {columns.map((col) => (
                    <td key={col} className="px-3 py-3 align-top text-foreground [overflow-wrap:anywhere]">
                      {formatCell(col, (row as Record<string, unknown>)[col])}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}
    </div>
  );
}

/** Words that should stay upper-case in a header (e.g. "product_id" -> "Product ID"). */
const ACRONYMS = new Set(["id", "sku", "gstin", "uom", "gst", "mrp", "rrp"]);

/**
 * Turn a column key such as `bucket_90_plus` or `purchasesValue` into a clean
 * header: no underscores, words spaced and capitalised ("Bucket 90 Plus").
 */
function humanizeHeader(key: string): string {
  return key
    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
    .replace(/[_\s]+/g, " ")
    .trim()
    .split(" ")
    .map((w) => (ACRONYMS.has(w.toLowerCase()) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()))
    .join(" ");
}

function formatCell(column: string, value: unknown): string {
  if (value === null || value === undefined) return "—";
  // Party types arrive as a short name; strip any namespace defensively
  // ("App\Models\Distributor" -> "Distributor").
  if (/_type$/.test(column) && typeof value === "string") {
    return value.split("\\").pop() ?? value;
  }
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}
