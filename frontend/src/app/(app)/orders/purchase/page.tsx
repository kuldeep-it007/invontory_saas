"use client";

import { useImpersonation } from "@/components/providers/impersonation-provider";
import { OrderHistory } from "@/components/orders/order-history";
import { PageHeader } from "@/components/ui/primitives";

/**
 * Purchase Orders (R20, R21, R33) — orders the current actor PLACED, i.e. the
 * actor is the BUYER:
 *
 *   • Distributor → orders it placed with the Company against Company stock.
 *   • Dealer    → orders it placed with its Distributor against Distributor stock.
 *
 * The Company buys from no one, so it never reaches this page (nav + route
 * guard confine it to Distributor/Dealer). Purchase orders are read-only to the
 * buyer — the lifecycle is driven by the SELLER on their Sales Orders page — so
 * no "Change status" control is offered here.
 */
export default function PurchaseOrdersPage(): JSX.Element {
  const { acting } = useImpersonation();

  // Buyer-scoped to the acting party; without an acting party there is nothing
  // to show (a company-side user is redirected away by the route guard).
  const query: Record<string, string | number> = acting
    ? { scope: "buyer", party_type: acting.kind, party_id: acting.buyer_id }
    : { scope: "buyer", party_type: "distributor", party_id: -1 };

  const sellerLabel = acting?.kind === "dealer" ? "your distributor" : "the Company";

  return (
    <div className="space-y-6">
      <PageHeader
        title="Purchase Orders"
        description={`Orders you placed with ${sellerLabel}.`}
      />

      <OrderHistory
        query={query}
        emptyMessage={`You have not placed any orders with ${sellerLabel} yet.`}
        // The actor is the buyer on this page, so don't repeat the buyer column.
        showBuyer={false}
        // Buyers never change an order's status — that's the seller's action.
        canManageLifecycle={false}
      />
    </div>
  );
}
