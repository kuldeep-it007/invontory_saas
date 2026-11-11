"use client";

import { PricesSection } from "@/components/pricing/prices-section";
import { useAuth } from "@/components/providers/auth-provider";
import { PageHeader } from "@/components/ui/primitives";

/**
 * Pricing management (R12), modeled on Rosetta: product- and collection-level
 * prices with optional markup (MUP). Discounts live on their own page.
 */
export default function PricingPage(): JSX.Element {
  const { can } = useAuth();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Pricing"
        description="Set product and collection prices, with optional markup (MUP)."
      />
      <PricesSection canManage={can("catalog.manage")} />
    </div>
  );
}
