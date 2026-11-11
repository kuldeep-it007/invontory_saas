"use client";

import { useEffect, useMemo, useState } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useImpersonation, useEffectiveAuth } from "@/components/providers/impersonation-provider";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { Card, ListPageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCart,
  useCartMutations,
  useCartSourcing,
  useCategories,
  useCollections,
  useColours,
  useDistributors,
  useProducts,
} from "@/lib/api/hooks";
import type { CartBuyer, CartItem, Product, StockAllocation } from "@/lib/api/types";

/**
 * Order Products — a database-backed shopping cart (R20.3, R20.4).
 *
 * Two panels, mirroring the reference design:
 *   • LEFT  — the buyer's open cart: each line shows RRP, discount %, discounted
 *             price, net/tax/total amounts; a running cart total; and Checkout.
 *   • RIGHT — a catalog search (Category → Collection → Colour + price filters)
 *             whose results can be added to the cart.
 *
 * All pricing/discount/tax maths is computed server-side by CartService, so the
 * figures here are authoritative and consistent with the resulting order.
 */
export default function OrderProductsPage(): JSX.Element {
  const { can } = useEffectiveAuth();
  const { acting } = useImpersonation();

  // Buyer selection — a cart belongs to a Distributor (or Dealer) buyer. When
  // impersonating a party, the cart is locked to that acting-as buyer.
  const distributors = useDistributors(1);
  const [buyerId, setBuyerId] = useState<string>("");

  const buyer: CartBuyer | null = acting
    ? { buyer_type: acting.buyer_type, buyer_id: acting.buyer_id }
    : buyerId
      ? { buyer_type: "distributor", buyer_id: Number(buyerId) }
      : null;

  const buyerOptions = (distributors.data?.data ?? []).map((d) => ({
    value: String(d.id),
    label: d.name,
  }));

  if (!can("orders.create")) {
    return (
      <div className="space-y-5">
        <ListPageHeader title="Order Products" subtitle="Build a cart and place an order" />
        <StateMessage tone="error">You do not have permission to place orders.</StateMessage>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Order Products"
        subtitle={
          acting
            ? `Ordering on behalf of ${acting.label} (${acting.kind})`
            : "Search the catalog, build a cart, and check out"
        }
        actions={
          acting ? undefined : (
            <div className="w-64">
              <Select label="Buyer" value={buyerId} onChange={setBuyerId} options={buyerOptions} />
            </div>
          )
        }
      />

      {!buyer ? (
        <Card>
          <StateMessage>
            Select a buyer to start a cart, or use “Act as” from a distributor or dealer.
          </StateMessage>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-[22rem_1fr]">
          <CartPanel buyer={buyer} />
          <SearchPanel buyer={buyer} />
        </div>
      )}
    </div>
  );
}

/* ─── Left: shopping cart ───────────────────────────────────────────────────── */

function CartPanel({ buyer }: { buyer: CartBuyer }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const { data, isLoading, error } = useCart(buyer);
  const { remove, update, clear, submit } = useCartMutations(buyer);
  const [actionError, setActionError] = useState<string | null>(null);
  const [placed, setPlaced] = useState<number | null>(null);

  const cart = data?.data;
  const items = cart?.items ?? [];
  const totals = cart?.totals;

  async function onClear(): Promise<void> {
    const ok = await confirm({
      title: "Clear cart?",
      message: "All items will be removed from the cart.",
      tone: "warning",
      confirmLabel: "Clear cart",
    });
    if (!ok) return;
    await clear.mutateAsync();
    toast({ title: "Cart cleared", tone: "info" });
  }

  async function onRemove(item: CartItem): Promise<void> {
    const name = item.product?.name ?? `Product #${item.product_id}`;
    const ok = await confirm({
      title: "Remove item?",
      message: `"${name}" will be removed from the cart.`,
      tone: "danger",
      confirmLabel: "Remove",
    });
    if (!ok) return;
    try {
      await remove.mutateAsync(item.id);
      toast({ title: "Item removed", message: `"${name}" was removed from the cart.`, tone: "success" });
    } catch (err) {
      toast({ title: "Remove failed", message: errorMessage(err), tone: "danger" });
    }
  }

  async function onCheckout(): Promise<void> {
    setActionError(null);
    setPlaced(null);
    try {
      const res = await submit.mutateAsync(undefined);
      setPlaced(res.data.id);
      toast({
        title: "Order placed",
        message: `Order #${res.data.id} was created successfully.`,
        tone: "success",
      });
    } catch (err) {
      setActionError(errorMessage(err));
      toast({ title: "Checkout failed", message: errorMessage(err), tone: "danger" });
    }
  }

  return (
    <Card className="flex h-fit flex-col gap-4 lg:sticky lg:top-20">
      <div className="flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
          <Icon.cart size={16} /> Shopping Cart
          <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground">
            {items.length}
          </span>
        </h2>
        {items.length > 0 ? (
          <button
            onClick={() => void onClear()}
            className="text-xs text-muted-foreground hover:text-danger"
          >
            Clear
          </button>
        ) : null}
      </div>

      {isLoading ? (
        <StateMessage>Loading cart…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : items.length === 0 ? (
        <StateMessage>Your cart is empty. Add products from the search panel.</StateMessage>
      ) : (
        <ul className="space-y-3">
          {items.map((item) => (
            <CartLine
              key={item.id}
              item={item}
              buyer={buyer}
              currency={totals?.currency ?? ""}
              onRemove={() => void onRemove(item)}
              onQuantity={async (q) => {
                try {
                  await update.mutateAsync({ itemId: item.id, quantity: q });
                } catch (err) {
                  toast({ title: "Cannot update quantity", message: errorMessage(err), tone: "danger" });
                }
              }}
              onAllocate={async (allocations) => {
                try {
                  await update.mutateAsync({ itemId: item.id, quantity: Number(item.quantity), allocations });
                } catch (err) {
                  toast({ title: "Cannot update allocation", message: errorMessage(err), tone: "danger" });
                }
              }}
            />
          ))}
        </ul>
      )}

      {actionError ? (
        <p className="text-sm text-danger" role="alert">
          {actionError}
        </p>
      ) : null}
      {placed ? (
        <p className="rounded-lg bg-success/10 px-3 py-2 text-sm text-success" role="status">
          Order #{placed} placed.
        </p>
      ) : null}

      {totals ? (
        <div className="border-t border-border pt-3">
          <dl className="space-y-1 text-sm">
            <Row label="Net" value={money(totals.net_amount, totals.currency)} />
            <Row label="Tax" value={money(totals.tax_amount, totals.currency)} />
            <div className="flex items-center justify-between pt-1 text-base font-semibold text-foreground">
              <dt>Total</dt>
              <dd className="tabular-nums">{money(totals.total_amount, totals.currency)}</dd>
            </div>
          </dl>
          <Button
            className="mt-3 w-full"
            disabled={items.length === 0 || submit.isPending}
            onClick={() => void onCheckout()}
          >
            {submit.isPending ? "Placing…" : "Checkout"}
          </Button>
        </div>
      ) : null}
    </Card>
  );
}

function CartLine({
  item,
  buyer,
  currency,
  onRemove,
  onQuantity,
  onAllocate,
}: {
  item: CartItem;
  buyer: CartBuyer;
  currency: string;
  onRemove: () => void;
  onQuantity: (q: number) => void;
  onAllocate: (allocations: StockAllocation[]) => void;
}): JSX.Element {
  const [showPoints, setShowPoints] = useState(false);
  const title = item.product
    ? [item.product.category, item.product.collection, item.product.colour]
        .filter(Boolean)
        .join(" » ") || item.product.name
    : `Product #${item.product_id}`;

  const allocationCount = item.allocations?.length ?? 0;

  return (
    <li className="rounded-lg border border-border bg-background p-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-medium text-foreground">{title}</p>
          <p className="text-xs text-muted-foreground">{item.product?.sku ?? ""}</p>
        </div>
        <button
          onClick={onRemove}
          aria-label="Remove line"
          className="rounded-md p-1 text-muted-foreground hover:bg-danger/10 hover:text-danger"
        >
          <Icon.trash size={14} />
        </button>
      </div>

      <div className="mt-2 flex items-center gap-2">
        <span className="text-xs text-muted-foreground">Qty</span>
        <QtyStepper value={Number(item.quantity)} onChange={onQuantity} />
      </div>

      <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
        <Mini label="RRP" value={money(item.rrp, currency)} />
        <Mini label="Discount" value={`${trimPct(item.discount_pct)}%`} />
        <Mini label="Disc. Price" value={money(item.unit_price, currency)} />
        <Mini label="Tax" value={`${trimPct(item.tax_rate)}%`} />
        <Mini label="Net" value={money(item.net_amount, currency)} />
        <Mini label="Tax Amt" value={money(item.tax_amount, currency)} />
      </dl>
      <p className="mt-1 text-right text-sm font-semibold text-foreground">
        {money(item.total_amount, currency)}
      </p>

      <button
        onClick={() => setShowPoints((s) => !s)}
        className="mt-2 flex w-full items-center justify-between rounded-md border border-dashed border-border px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted/60"
      >
        <span className="flex items-center gap-1">
          <Icon.warehouse size={13} />
          {allocationCount > 0 ? `Sourced from ${allocationCount} stock point(s)` : "Source from stock points"}
        </span>
        <span aria-hidden>{showPoints ? "▾" : "▸"}</span>
      </button>

      {showPoints ? (
        <AllocationEditor
          item={item}
          buyer={buyer}
          onSave={(allocations) => {
            onAllocate(allocations);
            setShowPoints(false);
          }}
        />
      ) : null}
    </li>
  );
}

/**
 * Per-line stock-point allocation editor. Lists the seller's stock points with
 * available quantity and lets the buyer split the line quantity across them.
 * The split must sum to the line quantity to save.
 */
function AllocationEditor({
  item,
  buyer,
  onSave,
}: {
  item: CartItem;
  buyer: CartBuyer;
  onSave: (allocations: StockAllocation[]) => void;
}): JSX.Element {
  const { data, isLoading, error } = useCartSourcing(buyer, item.product_id);
  const lineQty = Number(item.quantity);

  // Seed the editor from existing allocations, else empty per point.
  const initial = useMemo(() => {
    const map: Record<number, string> = {};
    (item.allocations ?? []).forEach((a) => {
      map[a.stock_point_id] = String(a.quantity);
    });
    return map;
  }, [item.allocations]);

  const [alloc, setAlloc] = useState<Record<number, string>>(initial);

  const points = data?.data.points ?? [];
  const allocated = Object.values(alloc).reduce((s, v) => s + (Number(v) || 0), 0);
  const remaining = Math.round((lineQty - allocated) * 1000) / 1000;
  const balanced = Math.abs(remaining) < 0.0005 && allocated > 0;

  function save(): void {
    const allocations: StockAllocation[] = points
      .map((p) => ({ stock_point_id: p.stock_point_id, quantity: Number(alloc[p.stock_point_id] || 0) }))
      .filter((a) => a.quantity > 0);
    onSave(allocations);
  }

  return (
    <div className="mt-2 space-y-2 rounded-md bg-muted/40 p-2">
      {isLoading ? (
        <p className="text-xs text-muted-foreground">Loading stock points…</p>
      ) : error ? (
        <p className="text-xs text-danger">{errorMessage(error)}</p>
      ) : points.length === 0 ? (
        <p className="text-xs text-muted-foreground">The seller has no stock points for this product.</p>
      ) : (
        <>
          {points.map((p) => (
            <div key={p.stock_point_id} className="flex items-center gap-2">
              <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-medium text-foreground">{p.name}</p>
                <p className="text-[11px] text-muted-foreground">
                  {Number(p.available).toFixed(0)} available
                </p>
              </div>
              <input
                type="number"
                min={0}
                step="0.001"
                max={Number(p.available)}
                value={alloc[p.stock_point_id] ?? ""}
                onChange={(e) =>
                  setAlloc((prev) => ({ ...prev, [p.stock_point_id]: e.target.value }))
                }
                placeholder="0"
                className="w-20 rounded-md border border-border bg-card px-2 py-1 text-right text-xs focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
              />
            </div>
          ))}
          <div className="flex items-center justify-between border-t border-border/60 pt-1.5 text-[11px]">
            <span className={remaining === 0 ? "text-success" : "text-muted-foreground"}>
              Allocated {allocated} / {lineQty}
              {remaining !== 0 ? ` (${remaining > 0 ? remaining + " left" : Math.abs(remaining) + " over"})` : ""}
            </span>
            <Button size="sm" disabled={!balanced} onClick={save}>
              Save split
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

/* ─── Right: catalog search ─────────────────────────────────────────────────── */

function SearchPanel({ buyer }: { buyer: CartBuyer }): JSX.Element {
  const categories = useCategories();
  const collections = useCollections();
  const colours = useColours();
  const products = useProducts(1);
  const { add } = useCartMutations(buyer);

  const [categoryId, setCategoryId] = useState("");
  const [collectionId, setCollectionId] = useState("");
  const [colourId, setColourId] = useState("");
  const [addError, setAddError] = useState<string | null>(null);
  const [qty, setQty] = useState<Record<number, string>>({});
  const [expanded, setExpanded] = useState<number | null>(null);

  // Dependent dropdowns: collections filtered by category, colours by collection.
  const collectionOptions = useMemo(() => {
    const all = collections.data?.data ?? [];
    const filtered = categoryId ? all.filter((c) => String(c.category_id) === categoryId) : all;
    return filtered.map((c) => ({ value: String(c.id), label: c.name }));
  }, [collections.data, categoryId]);

  const colourOptions = useMemo(() => {
    const all = colours.data?.data ?? [];
    const filtered = collectionId
      ? all.filter((c) => String(c.collection_id) === collectionId)
      : all;
    return filtered.map((c) => ({ value: String(c.id), label: c.name }));
  }, [colours.data, collectionId]);

  const rows = useMemo(() => {
    let all = products.data?.data ?? [];
    if (collectionId) all = all.filter((p) => String(p.collection_id) === collectionId);
    if (colourId) all = all.filter((p) => String(p.colour_id) === colourId);
    if (categoryId && !collectionId) {
      // Filter by category via the collections that belong to it.
      const collIds = new Set(
        (collections.data?.data ?? [])
          .filter((c) => String(c.category_id) === categoryId)
          .map((c) => c.id),
      );
      all = all.filter((p) => p.collection_id !== null && collIds.has(p.collection_id));
    }
    return all;
  }, [products.data, collections.data, categoryId, collectionId, colourId]);

  async function onAdd(product: Product): Promise<void> {
    setAddError(null);
    const q = Number(qty[product.id] ?? "1");
    try {
      await add.mutateAsync({ productId: product.id, quantity: q > 0 ? q : 1 });
    } catch (err) {
      setAddError(errorMessage(err));
    }
  }

  // Add a product sourced from a single chosen stock point (the SELECT flow).
  async function onSelectPoint(product: Product, stockPointId: number): Promise<void> {
    setAddError(null);
    const q = Number(qty[product.id] ?? "1");
    const quantity = q > 0 ? q : 1;
    try {
      const res = await add.mutateAsync({
        productId: product.id,
        quantity,
        allocations: [{ stock_point_id: stockPointId, quantity }],
      });
      const line = res.data.items.find((i) => i.product_id === product.id);
      if (!line?.allocations?.length) {
        // The product was already in the cart without a split, so the choice
        // cannot cover the whole line quantity.
        setAddError(
          "This product is already in the cart without a stock point. Use “Source from stock points” on its cart line to choose one.",
        );
      }
      setExpanded(null);
    } catch (err) {
      setAddError(errorMessage(err));
    }
  }

  return (
    <Card className="space-y-4">
      <h2 className="text-sm font-semibold text-foreground">Catalog</h2>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Select
          label="Category"
          value={categoryId}
          onChange={(v) => {
            setCategoryId(v);
            setCollectionId("");
            setColourId("");
          }}
          options={(categories.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
        />
        <Select
          label="Collection"
          value={collectionId}
          onChange={(v) => {
            setCollectionId(v);
            setColourId("");
          }}
          options={collectionOptions}
        />
        <Select label="Colour" value={colourId} onChange={setColourId} options={colourOptions} />
      </div>

      {addError ? (
        <p className="text-sm text-danger" role="alert">
          {addError}
        </p>
      ) : null}

      {products.isLoading ? (
        <StateMessage>Loading catalog…</StateMessage>
      ) : products.error ? (
        <StateMessage tone="error">{errorMessage(products.error)}</StateMessage>
      ) : rows.length === 0 ? (
        <StateMessage>No products match the selected filters.</StateMessage>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/60">
              <tr>
                <th className="px-3 py-2 text-xs font-semibold uppercase text-muted-foreground">SKU</th>
                <th className="px-3 py-2 text-xs font-semibold uppercase text-muted-foreground">Name</th>
                <th className="px-3 py-2 text-xs font-semibold uppercase text-muted-foreground">Tax</th>
                <th className="w-24 px-3 py-2 text-xs font-semibold uppercase text-muted-foreground">Qty</th>
                <th className="px-3 py-2" />
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <ProductRow
                  key={p.id}
                  product={p}
                  buyer={buyer}
                  qty={qty[p.id] ?? "1"}
                  onQty={(v) => setQty((prev) => ({ ...prev, [p.id]: v }))}
                  expanded={expanded === p.id}
                  onToggle={() => setExpanded((cur) => (cur === p.id ? null : p.id))}
                  adding={add.isPending}
                  onAdd={() => void onAdd(p)}
                  onSelectPoint={(sp) => void onSelectPoint(p, sp)}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

/**
 * A single catalog product row with an expandable "Available Stock" panel. The
 * buyer can either add from the seller's primary point (the plain "Add"), or
 * expand the row to pick a specific stock point to source from via "Select".
 */
function ProductRow({
  product,
  buyer,
  qty,
  onQty,
  expanded,
  onToggle,
  adding,
  onAdd,
  onSelectPoint,
}: {
  product: Product;
  buyer: CartBuyer;
  qty: string;
  onQty: (v: string) => void;
  expanded: boolean;
  onToggle: () => void;
  adding: boolean;
  onAdd: () => void;
  onSelectPoint: (stockPointId: number) => void;
}): JSX.Element {
  return (
    <>
      <tr className="border-t border-border/70 hover:bg-accent/50">
        <td className="px-3 py-2 font-mono text-xs">{product.sku}</td>
        <td className="px-3 py-2 font-medium text-foreground">{product.name}</td>
        <td className="px-3 py-2 text-muted-foreground">{product.tax_type ?? "—"}</td>
        <td className="px-3 py-2">
          <input
            type="number"
            min={1}
            step="0.001"
            value={qty}
            onChange={(e) => onQty(e.target.value)}
            className="w-20 rounded-md border border-border bg-card px-2 py-1 text-sm focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
          />
        </td>
        <td className="px-3 py-2">
          <Button
            size="sm"
            variant="secondary"
            onClick={onToggle}
            aria-expanded={expanded}
            aria-label="Show available stock by stock point"
          >
            <Icon.warehouse size={14} />
            {expanded ? "Hide stock" : "Stock"}
          </Button>
        </td>
        <td className="px-3 py-2 text-right">
          <Button size="sm" disabled={adding} onClick={onAdd}>
            <Icon.plus size={14} /> Add
          </Button>
        </td>
      </tr>
      {expanded ? (
        <tr className="border-t border-border/40 bg-muted/30">
          <td colSpan={6} className="px-3 py-3">
            <AvailableStockTable
              buyer={buyer}
              productId={product.id}
              disabled={adding}
              onSelect={onSelectPoint}
            />
          </td>
        </tr>
      ) : null}
    </>
  );
}

/**
 * The "Available Stock" table shown under a product: one row per seller stock
 * point with its on-hand (Stock) and reserved (Blocked) quantities and a
 * SELECT button that adds the product sourced from that point.
 */
function AvailableStockTable({
  buyer,
  productId,
  disabled,
  onSelect,
}: {
  buyer: CartBuyer;
  productId: number;
  disabled: boolean;
  onSelect: (stockPointId: number) => void;
}): JSX.Element {
  const { data, isLoading, error } = useCartSourcing(buyer, productId);
  const points = data?.data.points ?? [];

  return (
    <div className="space-y-2">
      <p className="text-center text-sm font-semibold italic text-foreground">Available Stock</p>
      {isLoading ? (
        <p className="text-xs text-muted-foreground">Loading stock points…</p>
      ) : error ? (
        <p className="text-xs text-danger">{errorMessage(error)}</p>
      ) : points.length === 0 ? (
        <p className="text-xs text-muted-foreground">The seller has no stock points for this product.</p>
      ) : (
        <div className="overflow-x-auto rounded-md border border-border bg-card">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/60">
              <tr>
                <th className="px-3 py-2 text-xs font-semibold text-foreground">Stock point</th>
                <th className="px-3 py-2 text-right text-xs font-semibold text-foreground">Stock</th>
                <th className="px-3 py-2 text-right text-xs font-semibold text-foreground">Blocked</th>
                <th className="w-24 px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {points.map((p) => (
                <tr key={p.stock_point_id} className="border-t border-border/60">
                  <td className="px-3 py-2 text-foreground">{p.name}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-foreground">
                    {trimQty(p.available)}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                    {trimQty(p.reserved)}
                  </td>
                  <td className="px-3 py-2 text-right">
                    <Button
                      size="sm"
                      disabled={disabled || Number(p.available) <= 0}
                      onClick={() => onSelect(p.stock_point_id)}
                    >
                      Select
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

/* ─── Small helpers ─────────────────────────────────────────────────────────── */

function Row({ label, value }: { label: string; value: string }): JSX.Element {
  return (
    <div className="flex items-center justify-between text-muted-foreground">
      <dt>{label}</dt>
      <dd className="tabular-nums text-foreground">{value}</dd>
    </div>
  );
}

function Mini({ label, value }: { label: string; value: string }): JSX.Element {
  return (
    <div className="flex justify-between gap-2">
      <span>{label}</span>
      <span className="tabular-nums text-foreground">{value}</span>
    </div>
  );
}

/**
 * Controlled quantity stepper for a cart line. Stays in sync with the server
 * `value` (so the display never desyncs from the stored quantity), lets the
 * user type freely, and commits a changed value via `onChange` on blur, Enter,
 * or the −/+ buttons. The committed quantity triggers a server recompute of the
 * line's price breakdown.
 */
function QtyStepper({
  value,
  onChange,
}: {
  value: number;
  onChange: (q: number) => void;
}): JSX.Element {
  const [text, setText] = useState<string>(String(value));

  // Re-sync when the authoritative server value changes (after a mutation).
  useEffect(() => {
    setText(String(value));
  }, [value]);

  function commit(next: number): void {
    if (Number.isFinite(next) && next > 0 && next !== value) {
      onChange(next);
    } else {
      // Revert the field to the authoritative value when input is invalid.
      setText(String(value));
    }
  }

  const step = (delta: number) => () => {
    const next = Math.max(1, Math.round((value + delta) * 1000) / 1000);
    setText(String(next));
    if (next !== value) onChange(next);
  };

  return (
    <div className="inline-flex items-center rounded-md border border-border bg-card">
      <button
        type="button"
        onClick={step(-1)}
        disabled={value <= 1}
        aria-label="Decrease quantity"
        className="px-2 py-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
      >
        −
      </button>
      <input
        type="number"
        min={1}
        step="0.001"
        value={text}
        onChange={(e) => setText(e.target.value)}
        onBlur={() => commit(Number(text))}
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            commit(Number(text));
          }
        }}
        aria-label="Quantity"
        className="w-14 border-x border-border bg-transparent px-2 py-1 text-center text-sm focus-visible:outline-none"
      />
      <button
        type="button"
        onClick={step(1)}
        aria-label="Increase quantity"
        className="px-2 py-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      >
        +
      </button>
    </div>
  );
}

function money(amount: string | number, currency: string): string {
  const n = Number(amount);
  if (!currency) return n.toFixed(2);
  try {
    return new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(n);
  } catch {
    return `${currency} ${n.toFixed(2)}`;
  }
}

/** Trim trailing zeros from a percentage string (e.g. "28.7500" → "28.75"). */
function trimPct(pct: string | number): string {
  const n = Number(pct);
  return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(2)));
}

/** Trim trailing zeros from a DECIMAL(15,3) quantity (e.g. "1803.590" → "1803.59"). */
function trimQty(qty: string | number): string {
  const n = Number(qty);
  return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)));
}
