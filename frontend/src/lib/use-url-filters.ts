"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback } from "react";

/**
 * Keeps a listing's filters in the URL query string.
 *
 * `values` is read from the URL on every render (so a page refresh, a shared
 * link, or a dashboard card deep-link all land on the same filtered view), and
 * `setFilters` writes changes back with `router.replace` (no history spam, no
 * scroll jump). An empty value removes its key. Keys not listed in `keys` are
 * left untouched.
 *
 * Callers must render under a `<Suspense>` boundary (Next requires it for
 * `useSearchParams`).
 */
export function useUrlFilters<K extends string>(keys: readonly K[]): {
  values: Record<K, string>;
  setFilters: (patch: Partial<Record<K, string>>) => void;
} {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const values = {} as Record<K, string>;
  for (const key of keys) {
    values[key] = params.get(key) ?? "";
  }

  const setFilters = useCallback(
    (patch: Partial<Record<K, string>>) => {
      const next = new URLSearchParams(params.toString());
      for (const [key, value] of Object.entries(patch) as [K, string | undefined][]) {
        if (value) next.set(key, value);
        else next.delete(key);
      }
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [params, pathname, router],
  );

  return { values, setFilters };
}
