"use client";

import { useMemo, useState } from "react";

import { OrderHistory } from "@/components/orders/order-history";
import { useEffectiveAuth, useImpersonation } from "@/components/providers/impersonation-provider";
import { PageHeader } from "@/components/ui/primitives";

/**
 * Orders landing — the viewer-scoped placed-order history (R20, R21, R33).
 *
 * Sales Orders and Purchase Orders each have their own dedicated page in the
 * sidebar. This page remains the landing + the home for impersonated buyer
 * sessions, which don't fit a single company/distributor split:
 *
 *   • Company (no impersonation): every order in the tenant.
 *   • Distributor (impersonated): TWO listings — "My orders" (placed against
 *     the Company) and "Dealer orders" (its dealers' orders against its stock).
 *   • Dealer (impersonated): "My orders".
 */
export default function OrdersPage(): JSX.Element {
  const { can } = useEffectiveAuth();
  const { acting } = useImpersonation();

  const isDistributor = acting?.kind === "distributor";
  const isDealer = acting?.kind === "dealer";

  const tabs = useMemo(() => {
    if (isDistributor && acting) {
      return [
        {
          id: "mine",
          label: "My orders",
          query: { scope: "buyer", party_type: "distributor", party_id: acting.buyer_id },
          empty: "You have not placed any orders with the company yet.",
          showBuyer: false,
        },
        {
          id: "dealers",
          label: "Dealer orders",
          query: { scope: "seller", party_type: "distributor", party_id: acting.buyer_id },
          empty: "No dealer has placed an order against your stock yet.",
          showBuyer: true,
        },
      ];
    }
    if (isDealer && acting) {
      return [
        {
          id: "mine",
          label: "My orders",
          query: { scope: "buyer", party_type: "dealer", party_id: acting.buyer_id },
          empty: "You have not placed any orders yet.",
          showBuyer: false,
        },
      ];
    }
    return [{ id: "all", label: "All orders", query: {}, empty: "No orders placed yet.", showBuyer: true }];
  }, [isDistributor, isDealer, acting]);

  const [activeTab, setActiveTab] = useState(0);
  const tab = tabs[Math.min(activeTab, tabs.length - 1)];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Orders"
        description={
          acting ? `Order history for ${acting.label}` : "Placed-order history across the tenant."
        }
      />

      {tabs.length > 1 ? (
        <nav className="flex gap-2 border-b border-border" aria-label="Order listings">
          {tabs.map((t, i) => (
            <button
              key={t.id}
              onClick={() => setActiveTab(i)}
              aria-current={i === activeTab ? "page" : undefined}
              className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                i === activeTab
                  ? "border-primary text-foreground"
                  : "border-transparent text-muted-foreground hover:text-foreground"
              }`}
            >
              {t.label}
            </button>
          ))}
        </nav>
      ) : null}

      <OrderHistory
        query={tab.query}
        emptyMessage={tab.empty}
        showBuyer={tab.showBuyer}
        canManageLifecycle={can("orders.confirm")}
      />
    </div>
  );
}
