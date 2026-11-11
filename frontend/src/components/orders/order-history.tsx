"use client";

import { Suspense, useState } from "react";

import { useImpersonation } from "@/components/providers/impersonation-provider";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useOrders } from "@/lib/api/hooks";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { Order, OrderLineRow } from "@/lib/api/types";

import { OrderDetailModal } from "./order-detail-modal";
import { OrderStatusSelect } from "./order-status-select";

/**
 * Shared order-history table, reused by the Sales Orders and Purchase Orders
 * pages so each is a dedicated, single-concern page wired to the same
 * `GET /api/orders` scoping (R20, R21, R33).
 *
 * When `canManageLifecycle` is set, a "Change status" control is rendered as
 * the LAST (right-most) column ({@see OrderStatusSelect}) — but ONLY for orders
 * whose stock the current actor owns. The status of an order may only be
 * changed by its SELLER (the party that owns the stock it draws from): a
 * Company actor drives Company-stock orders; a Distributor drives only its own
 * stock's orders; a Dealer (buyer only) drives none. The backend enforces the
 * same rule, so this is a UI mirror of the authorization, not the gate itself.
 */

interface OrderHistoryProps {
  query: Record<string, string | number>;
  emptyMessage: string;
  showBuyer?: boolean;
  showSeller?: boolean;
  canManageLifecycle?: boolean;
}

/** Wrapped in Suspense because the filters live in the URL (`useSearchParams`). */
export function OrderHistory(props: OrderHistoryProps): JSX.Element {
  return (
    <Suspense fallback={null}>
      <OrderHistoryInner {...props} />
    </Suspense>
  );
}

function OrderHistoryInner({
  query,
  emptyMessage,
  showBuyer = true,
  showSeller = true,
  canManageLifecycle = false,
}: OrderHistoryProps): JSX.Element {
  // Filters are read from (and written to) the URL, so a dashboard card link,
  // a page refresh and the chips below always agree: ?status=...&order_type=...
  const { values, setFilters } = useUrlFilters(["status", "order_type"] as const);
  const status = normalizeStatus(values.status);
  const orderType = ORDER_TYPES.some((t) => t.value === values.order_type) ? values.order_type : "";
  const { data, isLoading, error } = useOrders({
    ...query,
    ...(status ? { status } : {}),
    ...(orderType ? { order_type: orderType } : {}),
  });
  const { acting } = useImpersonation();
  const [viewing, setViewing] = useState<number | null>(null);
  const activeFilter = STATUS_FILTERS.find((f) => f.value === status);

  /**
   * Whether the current actor owns the stock behind this order (is its seller),
   * and so may change its status. Company actor (no acting party) → Company
   * orders; a distributor acting context → only that distributor's orders; a
   * dealer acting context → none.
   */
  const ownsOrderStock = (o: Order): boolean => {
    const sellerKind = (o.seller_type?.split("\\").pop() ?? "").toLowerCase();
    if (!acting) {
      // Acting as the Company (company-side user, no impersonation).
      return sellerKind === "company";
    }
    if (acting.kind === "distributor") {
      return sellerKind === "distributor" && Number(o.seller_id) === Number(acting.buyer_id);
    }
    // Dealers are buyers only — they never own seller stock.
    return false;
  };

  const columns: Column<Order>[] = [
    {
      header: "Order #",
      cell: (o) => (
        <button
          type="button"
          onClick={() => setViewing(o.id)}
          aria-label={`View order ${o.order_number ?? o.id}`}
          className="rounded font-mono font-medium text-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          {o.order_number ?? `#${o.id}`}
        </button>
      ),
    },
    { header: "Products", cell: (o) => <ProductNames lines={o.lines} /> },
    ...(showBuyer
      ? [
          {
            header: "Buyer",
            cell: (o: Order) => o.buyer?.name ?? o.buyer?.subdomain ?? partyLabel(o.buyer_type, o.buyer_id),
          } as Column<Order>,
        ]
      : []),
    ...(showSeller
      ? [
          {
            header: "Seller",
            cell: (o: Order) => o.seller?.name ?? o.seller?.subdomain ?? partyLabel(o.seller_type, o.seller_id),
          } as Column<Order>,
        ]
      : []),
    { header: "Type", cell: (o) => <span className="capitalize">{o.order_type ?? "standard"}</span> },
    { header: "Lines", cell: (o) => String(o.lines?.length ?? 0), className: "text-right" },
    { header: "Total", cell: (o) => Number(o.total).toFixed(2), className: "text-right tabular-nums" },
    { header: "Status", cell: (o) => <Badge>{o.status}</Badge> },
    {
      header: "Placed",
      cell: (o) => (o.created_at ? new Date(o.created_at).toLocaleDateString() : "—"),
    },
    // "Change status" lives on the RIGHT, and only for orders this actor owns.
    ...(canManageLifecycle
      ? [
          {
            header: "Change status",
            className: "text-right",
            cell: (o: Order) =>
              ownsOrderStock(o) ? (
                <OrderStatusSelect order={o} />
              ) : (
                <span className="text-xs text-muted-foreground">—</span>
              ),
          } as Column<Order>,
        ]
      : []),
  ];

  return (
    <>
      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(o) => o.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage={
          status || orderType
            ? `No ${[activeFilter?.label.toLowerCase(), orderType].filter(Boolean).join(" ")} orders match these filters.`
            : emptyMessage
        }
        toolbar={
          <div className="flex flex-wrap items-center gap-3">
          <div role="group" aria-label="Filter orders by status" className="flex flex-wrap gap-2">
            {STATUS_FILTERS.map((f) => {
              const active = f.value === status;
              return (
                <button
                  key={f.value || "all"}
                  type="button"
                  aria-pressed={active}
                  onClick={() => setFilters({ status: f.value })}
                  className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                    active
                      ? "border-primary bg-primary text-primary-foreground"
                      : "border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground"
                  }`}
                >
                  {f.label}
                </button>
              );
            })}
          </div>
            <select
              aria-label="Filter by order type"
              value={orderType}
              onChange={(e) => setFilters({ order_type: e.target.value })}
              className="rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
            >
              <option value="">All types</option>
              {ORDER_TYPES.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
        }
        footer={data ? <span>{data.total} orders</span> : undefined}
      />
      <OrderDetailModal orderId={viewing} onClose={() => setViewing(null)} />
    </>
  );
}

/**
 * Status filters. "Pending" is an order that has been placed but not yet
 * confirmed (backend status `placed`). An empty value means all orders.
 */
const STATUS_ORDER = ["draft", "placed", "confirmed", "dispatched", "delivered", "closed", "cancelled"];

/** Statuses counted as a realised sale (matches the dashboard's confirmed figures). */
export const FULFILLED_STATUSES = "confirmed,dispatched,delivered,closed";

const STATUS_FILTERS: { value: string; label: string }[] = [
  { value: "", label: "All" },
  { value: "placed", label: "Pending" },
  { value: "confirmed", label: "Confirmed" },
  { value: FULFILLED_STATUSES, label: "Confirmed & fulfilled" },
  { value: "dispatched", label: "Dispatched" },
  { value: "delivered", label: "Delivered" },
  { value: "closed", label: "Closed" },
  { value: "cancelled", label: "Cancelled" },
];

const ORDER_TYPES = [
  { value: "standard", label: "Standard" },
  { value: "blanket", label: "Blanket" },
  { value: "advance", label: "Advance" },
  { value: "depot", label: "Depot" },
];

/**
 * Clean a `status` URL value: keep only known statuses, de-duplicate, and put
 * them in canonical order so e.g. "delivered,confirmed" matches its chip.
 */
function normalizeStatus(raw: string): string {
  const wanted = new Set(raw.split(",").map((s) => s.trim().toLowerCase()));
  return STATUS_ORDER.filter((s) => wanted.has(s)).join(",");
}

/** Product names on an order: the first two, then "+N more" (full list on hover). */
function ProductNames({ lines }: { lines?: OrderLineRow[] }): JSX.Element {
  const names = (lines ?? []).map((l) => l.product?.name ?? `Product #${l.product_id}`);
  if (names.length === 0) return <span className="text-muted-foreground">—</span>;
  const shown = names.slice(0, 2).join(", ");
  const extra = names.length - 2;
  return (
    <span title={names.join(", ")} className="block max-w-[12rem] truncate">
      {shown}
      {extra > 0 ? <span className="text-muted-foreground"> +{extra} more</span> : null}
    </span>
  );
}

/** Human label for a polymorphic party when the relation isn't embedded. */
function partyLabel(type: string | null, id: number | null): string {
  if (!type || id === null) return "—";
  const kind = type.split("\\").pop() ?? type;
  return `${kind} #${id}`;
}
