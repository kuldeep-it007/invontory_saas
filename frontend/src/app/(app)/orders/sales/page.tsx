"use client";

import { OrderHistory } from "@/components/orders/order-history";
import { useEffectiveAuth, useImpersonation } from "@/components/providers/impersonation-provider";
import { PageHeader } from "@/components/ui/primitives";

/**
 * Sales Orders (R20, R21, R33) — orders placed AGAINST the current actor's
 * stock, i.e. the actor is the SELLER:
 *
 *   • Company   → orders its Distributors placed against Company stock.
 *   • Distributor → orders its Dealers placed against the Distributor's stock.
 *
 * A Dealer holds no stock and sells to no one, so Dealers never reach this page
 * (the nav + route guard confine it to Company/Distributor). The seller (held
 * by orders.confirm, confined to its own orders server-side) drives the
 * lifecycle inline via the right-most "Change status" control.
 */
export default function SalesOrdersPage(): JSX.Element {
  const { can, actorKind } = useEffectiveAuth();
  const { acting } = useImpersonation();

  const isDistributor = actorKind === "distributor" && acting;

  // Seller-scoped: a distributor sees orders against ITS stock; the Company
  // sees orders against Company stock.
  const query: Record<string, string | number> = isDistributor
    ? { scope: "seller", party_type: "distributor", party_id: acting.buyer_id }
    : { scope: "seller", party_type: "company" };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Sales Orders"
        description={
          isDistributor
            ? "Orders your dealers placed against your stock."
            : "Orders your distributors placed against Company stock."
        }
      />

      <OrderHistory
        query={query}
        emptyMessage={
          isDistributor
            ? "No dealer has placed an order against your stock yet."
            : "No distributor has placed an order against Company stock yet."
        }
        // The actor is the seller on this page, so don't repeat the seller column.
        showSeller={false}
        canManageLifecycle={can("orders.confirm")}
      />
    </div>
  );
}
