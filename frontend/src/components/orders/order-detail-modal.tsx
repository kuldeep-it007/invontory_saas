"use client";

import { Modal } from "@/components/ui/modal";
import { Badge, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useOrder } from "@/lib/api/hooks";
import type { OrderLineRow } from "@/lib/api/types";

/**
 * Order view: the order summary plus every ordered product with its details
 * (SKU, category › collection › colour, UoM, tax type), the price/quantity the
 * order was placed at, and the stock points each line is sourced from.
 */
export function OrderDetailModal({
  orderId,
  onClose,
}: {
  orderId: number | null;
  onClose: () => void;
}): JSX.Element | null {
  const { data, isLoading, error } = useOrder(orderId);
  const order = data?.data;

  return (
    <Modal
      open={orderId !== null}
      onClose={onClose}
      size="lg"
      title={order ? `Order ${order.order_number ?? `#${order.id}`}` : "Order"}
      description={order?.created_at ? `Placed ${new Date(order.created_at).toLocaleString()}` : undefined}
    >
      {isLoading ? (
        <StateMessage>Loading order…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : order ? (
        <div className="space-y-4">
          <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <Field label="Buyer" value={order.buyer?.name ?? order.buyer?.subdomain ?? "—"} />
            <Field label="Seller" value={order.seller?.name ?? order.seller?.subdomain ?? "—"} />
            <Field label="Type" value={<span className="capitalize">{order.order_type ?? "standard"}</span>} />
            <Field label="Status" value={<Badge>{order.status}</Badge>} />
          </dl>

          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted/60">
                <tr>
                  <Th>Product</Th>
                  <Th>Details</Th>
                  <Th className="text-right">Qty</Th>
                  <Th className="text-right">Unit price</Th>
                  <Th className="text-right">Amount</Th>
                </tr>
              </thead>
              <tbody>
                {(order.lines ?? []).map((line) => (
                  <LineRow key={line.id} line={line} />
                ))}
                {(order.lines ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-4 text-center text-muted-foreground">
                      This order has no lines.
                    </td>
                  </tr>
                ) : null}
              </tbody>
              <tfoot>
                <tr className="border-t border-border">
                  <td colSpan={4} className="px-3 py-2 text-right font-semibold">
                    Total
                  </td>
                  <td className="px-3 py-2 text-right font-semibold tabular-nums">
                    {Number(order.total).toFixed(2)}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      ) : null}
    </Modal>
  );
}

function LineRow({ line }: { line: OrderLineRow }): JSX.Element {
  const p = line.product;
  const hierarchy = [p?.collection?.category?.name, p?.collection?.name, p?.colour?.name]
    .filter(Boolean)
    .join(" › ");
  const qty = Number(line.quantity);
  const unit = Number(line.unit_price);
  const sources = line.allocations ?? [];

  return (
    <tr className="border-t border-border/70 align-top">
      <td className="px-3 py-2">
        <p className="font-medium text-foreground">{p?.name ?? `Product #${line.product_id}`}</p>
        {p?.sku ? <p className="font-mono text-xs text-muted-foreground">{p.sku}</p> : null}
      </td>
      <td className="px-3 py-2 text-xs text-muted-foreground">
        {hierarchy ? <p>{hierarchy}</p> : null}
        <p>
          {[p?.uom ? `UoM ${p.uom}` : null, p?.tax_type ? `Tax ${p.tax_type}` : null]
            .filter(Boolean)
            .join(" · ") || "—"}
        </p>
        {sources.length > 0 ? (
          <p className="mt-1">
            Sourced from:{" "}
            {sources
              .map((a) => `${a.stock_point?.name ?? `Stock point #${a.stock_point_id}`} (${Number(a.quantity)})`)
              .join(", ")}
          </p>
        ) : null}
      </td>
      <td className="px-3 py-2 text-right tabular-nums">{qty}</td>
      <td className="px-3 py-2 text-right tabular-nums">{unit.toFixed(2)}</td>
      <td className="px-3 py-2 text-right tabular-nums">{(qty * unit).toFixed(2)}</td>
    </tr>
  );
}

function Th({ children, className = "" }: { children: string; className?: string }): JSX.Element {
  return (
    <th className={`px-3 py-2 text-xs font-semibold uppercase text-muted-foreground ${className}`}>
      {children}
    </th>
  );
}

function Field({ label, value }: { label: string; value: React.ReactNode }): JSX.Element {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 font-medium text-foreground">{value}</dd>
    </div>
  );
}
