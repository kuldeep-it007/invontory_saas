"use client";

import { useRouter } from "next/navigation";
import { useCallback } from "react";

/**
 * Returns a navigation callback that goes back to the previous page when there
 * is in-app history, and otherwise falls back to a known-safe route.
 *
 * Used by the full-page create/edit screens so the "Back"/"Cancel" controls and
 * the browser back button behave consistently: if the user arrived from the
 * list page, `history.back()` returns there; if they deep-linked straight to
 * the form (no history), we still land on a sensible page instead of leaving
 * the app.
 */
export function useBack(fallbackHref: string): () => void {
  const router = useRouter();

  return useCallback(() => {
    if (typeof window !== "undefined" && window.history.length > 1) {
      router.back();
    } else {
      router.push(fallbackHref);
    }
  }, [router, fallbackHref]);
}
