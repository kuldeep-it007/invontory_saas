"use client";

import { useImpersonation } from "@/components/providers/impersonation-provider";
import { Icon } from "@/components/ui/icons";

/**
 * A prominent banner shown while a party-impersonation session is active (R8.6),
 * mirroring the Rosetta "You are impersonating …" bar. It names the party being
 * acted as (and, for a dealer, its parent distributor) and offers a one-click
 * "Stop" to end the session. Renders nothing when not impersonating.
 */
export function ImpersonationBanner(): JSX.Element | null {
  const { acting, stop, locked } = useImpersonation();

  // A logged-in panel user is acting as its OWN party — that is not an
  // impersonation session, so no banner (and nothing to stop).
  if (!acting || locked) return null;

  const parent = acting.parent ? ` · ${acting.parent.name}` : "";

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 bg-warning px-4 py-2 text-sm font-medium text-warning-foreground sm:px-6">
      <span className="flex items-center gap-2">
        <Icon.users size={16} />
        You are acting as{" "}
        <span className="font-semibold">
          {acting.label} ({acting.kind}
          {parent})
        </span>
      </span>
      <button
        onClick={stop}
        className="inline-flex items-center gap-1 rounded-md bg-black/15 px-2.5 py-1 text-xs font-semibold transition-colors hover:bg-black/25"
      >
        <Icon.logout size={14} /> Stop impersonating
      </button>
    </div>
  );
}
