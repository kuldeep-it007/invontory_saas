"use client";

import { useBranding } from "./branding-provider";

/**
 * Renders the tenant logo when configured, otherwise the brand name as text.
 * Small and composable per the design principles — it reads branding from
 * context and owns no fetching of its own.
 */
export function BrandLogo({ className }: { className?: string }): JSX.Element {
  const { logo, brandName } = useBranding();

  if (logo) {
    // Tenant-controlled URL; plain <img> avoids next/image remote host config.
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={logo} alt={brandName} className={className ?? "h-8 w-auto"} />;
  }

  return <span className={className ?? "text-lg font-semibold text-foreground"}>{brandName}</span>;
}
