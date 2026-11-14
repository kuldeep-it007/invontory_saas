"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { applyBrandingToDocument } from "@/lib/branding/apply";
import { DEFAULT_BRANDING } from "@/lib/branding/defaults";
import type { Branding } from "@/lib/branding/types";

/**
 * BrandingProvider makes the resolved `Branding` available to any client
 * component via `useBranding()`, keeps the live document in sync, and lets
 * client code replace the active branding after hydration via `useSetBranding()`.
 *
 * Branding is resolved on the server (root layout) and passed in as the initial
 * `branding`, so first paint is already themed (R3.2, no flash of unbranded
 * content). The provider then holds branding in state seeded from that value.
 * On the client we re-apply it after hydration (favicon/title/vars) and whenever
 * it changes — e.g. after a Company_Admin saves white-label settings in the
 * portal, `useUpdateSettings` pushes the freshly resolved branding here so the
 * live theme, logo, brand name and favicon update without a full reload (R3.5).
 */
const BrandingContext = createContext<Branding>(DEFAULT_BRANDING);
const SetBrandingContext = createContext<(branding: Branding) => void>(() => {});

export function BrandingProvider({
  branding,
  children,
}: {
  branding: Branding;
  children: ReactNode;
}): JSX.Element {
  // Seed state from the server-resolved branding, but allow later client-side
  // updates (settings save) to drive the live theme. Relying on the prop alone
  // would freeze branding at the one-time server/host resolution.
  const [current, setCurrent] = useState<Branding>(branding);

  // If the server-resolved prop itself changes (navigation re-render with a new
  // host-resolved value), adopt it.
  useEffect(() => {
    setCurrent(branding);
  }, [branding]);

  // Re-apply to the document whenever the active branding changes.
  useEffect(() => {
    applyBrandingToDocument(current);
  }, [current]);

  // A company that picked a primary address is always served from it: visits to its
  // other addresses (platform subdomain, secondary domains) move there, keeping the path.
  useEffect(() => {
    const canonical = current.canonicalHost;
    if (canonical && window.location.hostname !== canonical && /^[a-z0-9.-]+$/i.test(canonical)) {
      const { pathname, search, hash } = window.location;
      window.location.replace(`https://${canonical}${pathname}${search}${hash}`);
    }
  }, [current.canonicalHost]);

  const value = useMemo(() => current, [current]);

  return (
    <BrandingContext.Provider value={value}>
      <SetBrandingContext.Provider value={setCurrent}>{children}</SetBrandingContext.Provider>
    </BrandingContext.Provider>
  );
}

/** Access the active tenant's resolved branding from any client component. */
export function useBranding(): Branding {
  return useContext(BrandingContext);
}

/**
 * Replace the active branding from a client component. Used after a white-label
 * settings save so the portal reflects the new branding immediately (R3.5).
 */
export function useSetBranding(): (branding: Branding) => void {
  return useContext(SetBrandingContext);
}
