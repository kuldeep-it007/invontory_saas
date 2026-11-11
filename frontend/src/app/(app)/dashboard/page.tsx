"use client";

import { useAuth } from "@/components/providers/auth-provider";
import { FULFILLED_STATUSES } from "@/components/orders/order-history";
import { useEffectiveAuth, useImpersonation } from "@/components/providers/impersonation-provider";
import {
  Donut,
  GradientStat,
  ListRow,
  MiniStat,
  Panel,
  VerticalBars,
  formatCompact,
  formatCurrency,
  monogram,
} from "@/components/dashboard/widgets";
import { QuotaBar } from "@/components/ui/charts";
import { Badge, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useDashboard, useLowStock } from "@/lib/api/hooks";
import type { StockRow } from "@/lib/api/types";

/**
 * Company master dashboard (R33.1) — a scannable, visual landing page inspired
 * by modern admin templates. Every figure is driven by the real backend
 * snapshot ({@see DashboardService}) and the live low-stock report; there is no
 * fabricated data, and zero/empty states render cleanly for new tenants.
 *
 * Layout:
 *   1. Welcome header
 *   2. Hero KPI row — gradient cards (orders, sales, collected, outstanding)
 *   3. Mini stat tiles — inventory headlines
 *   4. Orders by status (bars) + Order mix (donut) + inventory overview
 *   5. Low-stock products list
 *   6. Plan usage vs limits
 */

interface Snapshot {
  orders?: Record<string, number>;
  sales?: { confirmed_orders?: number; confirmed_value?: number };
  payments?: { collected?: number };
  outstanding?: { total?: number };
  inventory?: { products?: number; units_on_hand?: number; low_stock?: number };
  /** "party" when scoped to the signed-in Distributor/Dealer, else "tenant". */
  scope?: "tenant" | "party";
  usage?: {
    plan?: { id: number; name: string };
    subscription_status?: string;
    quotas?: Record<string, { count: number; limit: number | null; unlimited: boolean }>;
  };
}

export default function DashboardPage(): JSX.Element {
  const { identity } = useAuth();
  const { acting } = useImpersonation();
  const { can, actorKind } = useEffectiveAuth();
  const { data, isLoading, error } = useDashboard(
    identity?.id,
    acting ? { buyer_type: acting.buyer_type, buyer_id: acting.buyer_id } : null,
  );
  const lowStock = useLowStock(identity?.id);
  const snap = (data?.data ?? {}) as Snapshot;

  if (isLoading) {
    return <DashboardSkeleton />;
  }
  if (error) {
    return (
      <div className="space-y-6">
        <WelcomeHeader name={identity?.name} />
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      </div>
    );
  }

  const ownData = snap.scope === "party";

  // Card destinations follow the sidebar: each role only gets links to pages it
  // can open. Orders: the Company sells, distributors/dealers buy. Stock: the
  // Company and distributors have stock pages; dealers hold none.
  const ordersHref =
    actorKind === "company" ? "/orders/sales" : "/orders/purchase";
  const stockHref =
    actorKind === "company"
      ? "/stock/stocks"
      : actorKind === "distributor"
        ? "/stock/distributors"
        : "/catalog";
  // Low stock: the Company has a dedicated report; a distributor filters its own stock.
  const lowStockHref =
    actorKind === "company" ? "/stock/low" : actorKind === "distributor" ? "/stock/distributors?low=1" : stockHref;
  // Each card deep-links with the status/value it represents; the listing reads
  // it from the URL and applies the matching filter.
  const reportsLink = (report: string) => (can("reports.view") ? `/reports?report=${report}` : undefined);
  const ordersLink = can("orders.view") ? ordersHref : undefined;
  const confirmedLink = can("orders.view") ? `${ordersHref}?status=${FULFILLED_STATUSES}` : undefined;
  const stockLink = can("inventory.view") || actorKind === "dealer" ? stockHref : undefined;
  const lowStockLink = can("inventory.view") ? lowStockHref : undefined;
  const orders = snap.orders ?? {};
  const sales = snap.sales ?? {};
  const inventory = snap.inventory ?? {};

  const orderBars = Object.entries(orders)
    .filter(([k]) => k !== "total" && k !== "pending")
    .map(([label, value]) => ({ label, value: Number(value) }));

  const orderDonut = orderBars.filter((d) => d.value > 0);

  const lowStockRows: StockRow[] = lowStock.data?.data ?? [];

  return (
    <div className="space-y-6">
      <WelcomeHeader name={identity?.name} ownData={ownData} />

      {/* ── Hero KPIs ──────────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <GradientStat
          tone="amber"
          icon={<IconCart />}
          href={ordersLink}
          label="Total orders"
          value={String(orders.total ?? 0)}
          caption={`${orders.pending ?? 0} pending`}
        />
        <GradientStat
          tone="navy"
          icon={<IconReceipt />}
          href={confirmedLink}
          label="Confirmed sales value"
          value={formatCurrency(Number(sales.confirmed_value ?? 0))}
          caption={`${sales.confirmed_orders ?? 0} confirmed orders`}
        />
        <GradientStat
          tone="teal"
          icon={<IconWallet />}
          href={reportsLink("payments")}
          label="Payments collected"
          value={formatCurrency(Number(snap.payments?.collected ?? 0))}
        />
        <GradientStat
          tone="slate"
          icon={<IconScale />}
          href={reportsLink("outstanding-ageing")}
          label="Outstanding balance"
          value={formatCurrency(Number(snap.outstanding?.total ?? 0))}
        />
      </div>

      {/* ── Mini stats ─────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <MiniStat
          href={stockLink}
          label={ownData ? "Products in your stock" : "Products in catalog"}
          value={String(inventory.products ?? 0)}
          accent="bg-indigo-50 text-indigo-600"
          icon={<IconBox />}
        />
        <MiniStat
          href={stockLink}
          label="Units on hand"
          value={formatCompact(Number(inventory.units_on_hand ?? 0))}
          accent="bg-teal-50 text-teal-600"
          icon={<IconLayers />}
        />
        <MiniStat
          href={lowStockLink}
          label="Low-stock items"
          value={String(inventory.low_stock ?? 0)}
          accent="bg-amber-50 text-amber-600"
          icon={<IconAlert />}
        />
        <MiniStat
          href={confirmedLink}
          label="Confirmed orders"
          value={String(sales.confirmed_orders ?? 0)}
          accent="bg-rose-50 text-rose-600"
          icon={<IconCheck />}
        />
      </div>

      {/* ── Orders chart + order mix ───────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Panel
          title={ownData ? "Your orders by status" : "Orders by status"}
          subtitle="Across every lifecycle state"
          className="lg:col-span-2"
        >
          <VerticalBars data={orderBars} />
        </Panel>
        <Panel title="Order mix" subtitle="Share by status">
          <Donut data={orderDonut} centerLabel="orders" />
        </Panel>
      </div>

      {/* ── Low stock + plan usage ─────────────────────────────────────── */}
      <div className={ownData ? "grid grid-cols-1 gap-4" : "grid grid-cols-1 gap-4 lg:grid-cols-2"}>
        <Panel
          title="Low-stock products"
          subtitle="At or below their configured threshold"
          action={<Badge>{String(lowStockRows.length)}</Badge>}
        >
          {lowStock.isLoading ? (
            <StateMessage>Loading…</StateMessage>
          ) : lowStock.error ? (
            <StateMessage tone="error">{errorMessage(lowStock.error)}</StateMessage>
          ) : lowStockRows.length === 0 ? (
            <StateMessage>Everything is above its threshold. Nice.</StateMessage>
          ) : (
            <div className="-mx-2 divide-y divide-border/60">
              {lowStockRows.slice(0, 6).map((s) => {
                const qty = Number(s.quantity);
                const threshold = s.low_stock_threshold != null ? Number(s.low_stock_threshold) : null;
                return (
                  <ListRow
                    key={s.id}
                    avatar={monogram(s.product?.name ?? "P")}
                    title={s.product?.name ?? `Product #${s.product_id}`}
                    subtitle={s.product?.sku ? `SKU ${s.product.sku}` : `Stock point #${s.stock_point_id}`}
                    trailing={
                      <div className="flex flex-col items-end">
                        <span className="text-sm font-semibold tabular-nums text-foreground">{qty}</span>
                        <span className="text-xs font-medium text-danger">
                          {threshold != null ? `≤ ${threshold}` : "low"}
                        </span>
                      </div>
                    }
                  />
                );
              })}
            </div>
          )}
        </Panel>

        {ownData ? null : (
        <Panel
          title={`Plan usage${snap.usage?.plan ? ` — ${snap.usage.plan.name}` : ""}`}
          subtitle="Consumption against your subscription limits"
          action={
            snap.usage?.subscription_status ? <Badge>{snap.usage.subscription_status}</Badge> : undefined
          }
        >
          {snap.usage?.quotas && Object.keys(snap.usage.quotas).length ? (
            <div className="space-y-4">
              {Object.entries(snap.usage.quotas).map(([key, q]) => (
                <QuotaBar key={key} label={key} count={q.count} limit={q.limit} unlimited={q.unlimited} />
              ))}
            </div>
          ) : (
            <StateMessage>No plan limits configured.</StateMessage>
          )}
        </Panel>
        )}
      </div>
    </div>
  );
}

/* ─── Header ──────────────────────────────────────────────────────────────── */

function WelcomeHeader({ name, ownData = false }: { name?: string; ownData?: boolean }): JSX.Element {
  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  return (
    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">
          Welcome, {name?.split(" ")[0] ?? "Admin"}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {ownData
            ? "Here is a summary of your own orders, payments and balance."
            : "Here is what is happening across your workspace today."}
        </p>
      </div>
      <div className="flex items-center gap-2 rounded-xl border border-border bg-background px-4 py-2 text-sm text-muted-foreground shadow-sm">
        <IconCalendar />
        <span>{today}</span>
      </div>
    </div>
  );
}

/* ─── Loading skeleton ──────────────────────────────────────────────────────── */

function DashboardSkeleton(): JSX.Element {
  return (
    <div className="space-y-6">
      <div className="h-24 animate-pulse rounded-2xl bg-muted" />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="h-36 animate-pulse rounded-2xl bg-muted" />
        ))}
      </div>
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="h-20 animate-pulse rounded-2xl bg-muted" />
        ))}
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="h-64 animate-pulse rounded-2xl bg-muted lg:col-span-2" />
        <div className="h-64 animate-pulse rounded-2xl bg-muted" />
      </div>
    </div>
  );
}

/* ─── Inline icons (dependency-free) ────────────────────────────────────────── */

function svgProps() {
  return {
    width: 20,
    height: 20,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 2,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
  };
}

function IconCart(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </svg>
  );
}
function IconReceipt(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z" />
      <path d="M8 7h8M8 11h8M8 15h5" />
    </svg>
  );
}
function IconWallet(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
      <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
      <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
    </svg>
  );
}
function IconScale(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M12 3v18M7 7l-4 7h8ZM17 7l4 7h-8ZM4 21h16" />
    </svg>
  );
}
function IconBox(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
      <path d="m3.3 7 8.7 5 8.7-5M12 22V12" />
    </svg>
  );
}
function IconLayers(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="m12 2 10 5-10 5L2 7l10-5ZM2 12l10 5 10-5M2 17l10 5 10-5" />
    </svg>
  );
}
function IconAlert(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
      <path d="M12 9v4M12 17h.01" />
    </svg>
  );
}
function IconCheck(): JSX.Element {
  return (
    <svg {...svgProps()}>
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
      <path d="m9 11 3 3L22 4" />
    </svg>
  );
}
function IconCalendar(): JSX.Element {
  return (
    <svg {...svgProps()} width={16} height={16}>
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </svg>
  );
}
