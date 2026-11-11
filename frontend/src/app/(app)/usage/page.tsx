"use client";

import { Card, PageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useUsage } from "@/lib/api/hooks";
import type { UsageEntry } from "@/lib/api/types";

/**
 * Usage-vs-limits widget (R5.5). Normalizes the usage snapshot (which the
 * backend may return as a keyed object or an array) into a list of resources
 * with a progress bar. Treats 0/NULL limits as unlimited (R4.4).
 */
export default function UsagePage(): JSX.Element {
  const { data, isLoading, error } = useUsage();

  const entries = normalize(data?.data);

  return (
    <div className="space-y-6">
      <PageHeader title="Usage" description="Current resource counts against your plan limits." />

      {isLoading ? (
        <StateMessage>Loading usage…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : entries.length === 0 ? (
        <StateMessage>No usage data available.</StateMessage>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {entries.map((entry) => (
            <UsageCard key={entry.key} entry={entry} />
          ))}
        </div>
      )}
    </div>
  );
}

function normalize(snapshot: unknown): UsageEntry[] {
  if (!snapshot) return [];
  if (Array.isArray(snapshot)) return snapshot as UsageEntry[];
  if (typeof snapshot === "object") {
    return Object.entries(snapshot as Record<string, unknown>).map(([key, raw]) => {
      const v = (raw ?? {}) as Partial<UsageEntry> & { count?: number; limit?: number | null };
      const limit = v.limit ?? null;
      return {
        key: v.key ?? key,
        count: Number(v.count ?? 0),
        limit,
        unlimited: v.unlimited ?? (limit === null || limit === 0),
      };
    });
  }
  return [];
}

function label(key: string): string {
  return key.replace(/^max_/, "").replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function UsageCard({ entry }: { entry: UsageEntry }): JSX.Element {
  const pct =
    entry.unlimited || !entry.limit ? 0 : Math.min(100, Math.round((entry.count / entry.limit) * 100));
  const near = pct >= 90;

  return (
    <Card>
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-foreground">{label(entry.key)}</p>
        <p className="text-sm text-muted-foreground">
          {entry.count}
          {entry.unlimited ? " / ∞" : ` / ${entry.limit}`}
        </p>
      </div>
      <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={`h-full rounded-full ${near ? "bg-red-500" : "bg-primary"}`}
          style={{ width: entry.unlimited ? "0%" : `${pct}%` }}
          role="progressbar"
          aria-valuenow={entry.count}
          aria-valuemax={entry.unlimited ? undefined : entry.limit ?? undefined}
        />
      </div>
    </Card>
  );
}
