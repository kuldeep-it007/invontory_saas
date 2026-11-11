import type { Metadata } from "next";
import type { ReactNode } from "react";

import "./globals.css";
import { BrandingProvider } from "@/components/branding/branding-provider";
import { AlertsProvider } from "@/components/providers/alerts-provider";
import { AuthProvider } from "@/components/providers/auth-provider";
import { QueryProvider } from "@/components/providers/query-provider";
import { themeToCssVars } from "@/lib/branding/apply";
import { resolveBrandingForRequest } from "@/lib/branding/server";

/**
 * Root layout — the white-label theme loader entry point (R3.2).
 *
 * This is a server component, so branding for the resolved tenant host is
 * fetched on the server during the initial render. The theme is applied two
 * ways for a flash-free first paint:
 *   1. Theme CSS variables are written inline on <html style=...> here, so the
 *      very first server-rendered HTML already carries the tenant colours.
 *   2. `favicon` and `<title>` are emitted via Next `generateMetadata` below.
 * After hydration, `BrandingProvider` re-applies branding to the live document
 * and exposes it to client components via `useBranding()`.
 *
 * If branding can't be resolved (backend down / host unresolved / null values)
 * `resolveBrandingForRequest` returns defaults, so the app still renders
 * (R37.4 — zero-config boot).
 */
export async function generateMetadata(): Promise<Metadata> {
  const branding = await resolveBrandingForRequest();
  return {
    title: branding.brandName,
    icons: branding.favicon ? { icon: branding.favicon } : undefined,
  };
}

export default async function RootLayout({
  children,
}: {
  children: ReactNode;
}): Promise<JSX.Element> {
  const branding = await resolveBrandingForRequest();
  // Inline theme vars so the first paint is already themed (no FOUC).
  const themeStyle = themeToCssVars(branding.theme) as React.CSSProperties;

  return (
    <html lang="en" style={themeStyle}>
      <body className="min-h-screen bg-background text-foreground antialiased">
        <QueryProvider>
          <BrandingProvider branding={branding}>
            <AuthProvider>
              <AlertsProvider>{children}</AlertsProvider>
            </AuthProvider>
          </BrandingProvider>
        </QueryProvider>
      </body>
    </html>
  );
}
