import { render } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { BrandLogo } from "@/components/branding/brand-logo";
import { BrandingProvider } from "@/components/branding/branding-provider";
import { DEFAULT_BRANDING } from "@/lib/branding/defaults";
import type { Branding } from "@/lib/branding/types";

import { scanForA11y } from "./axe-helper";

/**
 * Automated accessibility scans for key branded UI surfaces (task 15.10,
 * R37.5 — WCAG 2.1 Level AA).
 *
 * These scans run axe-core against the rendered DOM of the components a tenant
 * sees first: the branded header/logo and the home dashboard shell. They catch
 * machine-detectable issues (missing alt text, invalid ARIA, heading/landmark
 * structure, control names). They do NOT, and cannot, prove full WCAG 2.1 AA
 * conformance — that requires manual assistive-technology testing and expert
 * review, which the CI pipeline flags as a separate required step.
 *
 * The real HomePage is an async Server Component that resolves branding via a
 * server-only data path, so here we render the equivalent branded markup and
 * the actual client components (`BrandLogo`, `BrandingProvider`) that ship to
 * the browser, which is where axe-detectable a11y problems live.
 */

function renderWithBranding(ui: React.ReactNode, branding: Branding = DEFAULT_BRANDING) {
  return render(<BrandingProvider branding={branding}>{ui}</BrandingProvider>);
}

/**
 * Mirrors the markup of `src/app/page.tsx` (the branded home dashboard shell)
 * so the scan exercises the real page structure: landmarks, heading, nav, and
 * actionable controls.
 */
function BrandedHomeShell({ brandName }: { brandName: string }): JSX.Element {
  return (
    <main className="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-8 px-4 py-10">
      <header className="flex flex-wrap items-center justify-between gap-4 border-b pb-6">
        <BrandLogo className="h-10 w-auto" />
        <nav aria-label="Primary" className="flex items-center gap-4 text-sm">
          <a href="/dashboard">Dashboard</a>
          <a href="/catalog">Catalog</a>
          <a href="/orders">Orders</a>
        </nav>
      </header>

      <section className="flex flex-col gap-4">
        <h1 className="text-2xl font-semibold">Welcome to {brandName}</h1>
        <p className="max-w-prose">
          This workspace is branded for the resolved tenant. The logo, name,
          colour theme, and favicon are loaded from the tenant&apos;s white-label
          settings on page load.
        </p>

        <div className="flex flex-wrap gap-3">
          <button type="button">Primary action</button>
          <button type="button">Secondary action</button>
        </div>
      </section>
    </main>
  );
}

describe("accessibility (axe) — key branded pages (R37.5, WCAG 2.1 AA subset)", () => {
  it("branded home dashboard shell has no detectable a11y violations", async () => {
    const { container } = renderWithBranding(
      <BrandedHomeShell brandName={DEFAULT_BRANDING.brandName} />,
    );

    const results = await scanForA11y(container);

    expect(results).toHaveNoViolations();
  });

  it("brand logo (text fallback) has no detectable a11y violations", async () => {
    const { container } = renderWithBranding(<BrandLogo />, DEFAULT_BRANDING);

    const results = await scanForA11y(container);

    expect(results).toHaveNoViolations();
  });

  it("brand logo (image variant) exposes an accessible name via alt text", async () => {
    const branding: Branding = {
      ...DEFAULT_BRANDING,
      brandName: "Acme Distribution",
      logo: "https://cdn.example.test/acme-logo.png",
    };

    const { container, getByRole } = renderWithBranding(<BrandLogo />, branding);

    // The logo <img> must carry the brand name as its accessible name.
    expect(getByRole("img", { name: "Acme Distribution" })).toBeInTheDocument();

    const results = await scanForA11y(container);

    expect(results).toHaveNoViolations();
  });
});
