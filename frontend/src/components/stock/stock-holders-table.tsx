"use client";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/primitives";
import type { StockHolder } from "@/lib/api/types";

/**
 * A product's stock across the network: one block per holder (the Company,
 * then each distributor) with a row per stock point showing available and
 * blocked (reserved) quantities.
 *
 * With `onSelect`, holders the buyer may order from get a Select button per
 * point (the chosen point's holder becomes the line's seller). Holders the
 * buyer cannot order from are shown read-only.
 */
export function StockHoldersTable({
  holders,
  disabled = false,
  onSelect,
}: {
  holders: StockHolder[];
  disabled?: boolean;
  onSelect?: (holder: StockHolder, stockPointId: number) => void;
}): JSX.Element {
  if (holders.length === 0) {
    return <p className="text-xs text-muted-foreground">No stock is held for this product yet.</p>;
  }

  const selectable = onSelect !== undefined;

  return (
    <div className="space-y-3">
      {holders.map((holder) => (
        <div
          key={`${holder.holder_type}-${holder.holder_id}`}
          className="overflow-x-auto rounded-md border border-border bg-card"
        >
          <div className="flex items-center justify-between gap-2 border-b border-border bg-muted/60 px-3 py-1.5">
            <span className="text-xs font-semibold text-foreground">{holder.holder_name}</span>
            <span className="flex items-center gap-1">
              <Badge>{holder.holder_type === "company" ? "company" : "distributor"}</Badge>
              {selectable && holder.orderable === false ? <Badge>view only</Badge> : null}
            </span>
          </div>
          {holder.points.length === 0 ? (
            <p className="px-3 py-2 text-xs text-muted-foreground">No stock points.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr>
                  <th className="px-3 py-1.5 text-xs font-semibold text-muted-foreground">Stock point</th>
                  <th className="px-3 py-1.5 text-right text-xs font-semibold text-muted-foreground">Stock</th>
                  <th className="px-3 py-1.5 text-right text-xs font-semibold text-muted-foreground">Blocked</th>
                  {selectable ? <th className="w-24 px-3 py-1.5" /> : null}
                </tr>
              </thead>
              <tbody>
                {holder.points.map((point) => (
                  <tr key={point.stock_point_id} className="border-t border-border/60">
                    <td className="px-3 py-1.5 text-foreground">{point.name}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums text-foreground">{trimQty(point.available)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums text-muted-foreground">{trimQty(point.reserved)}</td>
                    {selectable ? (
                      <td className="px-3 py-1.5 text-right">
                        {holder.orderable ? (
                          <Button
                            size="sm"
                            disabled={disabled || Number(point.available) <= 0}
                            onClick={() => onSelect(holder, point.stock_point_id)}
                          >
                            Select
                          </Button>
                        ) : null}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      ))}
    </div>
  );
}

/** Trim trailing zeros from a DECIMAL(15,3) quantity (e.g. "1803.590" → "1803.59"). */
function trimQty(qty: string | number): string {
  const n = Number(qty);
  return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)));
}
