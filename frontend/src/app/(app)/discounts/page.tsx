"use client";

import { DiscountsSection } from "@/components/pricing/discounts-section";
import { useAuth } from "@/components/providers/auth-provider";
import { PageHeader } from "@/components/ui/primitives";

/**
 * Discount management (R12): customer and online discounts, optionally scoped
 * by category, collection and colour. Prices live on their own page.
 */
export default function DiscountsPage(): JSX.Element {
  const { can } = useAuth();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Discounts"
        description="Configure customer and online discounts, scoped by category, collection or colour."
      />
      <DiscountsSection canManage={can("catalog.manage")} />
    </div>
  );
}
