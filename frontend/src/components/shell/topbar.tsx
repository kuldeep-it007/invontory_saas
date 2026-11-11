"use client";

import Link from "next/link";

import { useAuth } from "@/components/providers/auth-provider";
import { useNotifications } from "@/lib/api/hooks";
import { Icon } from "@/components/ui/icons";

/**
 * Top application bar (DreamsPOS-style). Shows a search field with a ⌘K hint,
 * a notifications bell with an unread badge (live from the notifications
 * endpoint), a settings shortcut, and the signed-in user's avatar monogram.
 * Purely presentational chrome — the search is a visual affordance for now.
 */
export function Topbar(): JSX.Element {
  const { identity } = useAuth();
  const { data } = useNotifications();
  const unread = data?.meta?.unread_count ?? 0;

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-border bg-card/80 px-4 backdrop-blur sm:px-6">
      <label className="relative hidden flex-1 items-center sm:flex" aria-label="Search">
        <span className="pointer-events-none absolute left-3 text-muted-foreground">
          <Icon.search size={16} />
        </span>
        <input
          type="search"
          placeholder="Search…"
          className="w-full max-w-md rounded-lg border border-border bg-muted/40 py-2 pl-9 pr-16 text-sm text-foreground placeholder:text-muted-foreground transition-colors hover:bg-muted/60 focus-visible:border-ring focus-visible:bg-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
        />
        <kbd className="pointer-events-none absolute right-3 hidden rounded border border-border bg-background px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground md:inline-block">
          ⌘K
        </kbd>
      </label>

      <div className="flex flex-1 items-center justify-end gap-1 sm:flex-none">
        <Link
          href="/notifications"
          className="relative rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          aria-label={`Notifications${unread ? `, ${unread} unread` : ""}`}
        >
          <Icon.bell size={18} />
          {unread > 0 ? (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
              {unread > 9 ? "9+" : unread}
            </span>
          ) : null}
        </Link>

        <Link
          href="/settings"
          className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          aria-label="Settings"
        >
          <Icon.settings size={18} />
        </Link>

        <span
          className="ml-1 flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
          title={identity?.name ?? "User"}
        >
          {monogram(identity?.name ?? "U")}
        </span>
      </div>
    </header>
  );
}

function monogram(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
