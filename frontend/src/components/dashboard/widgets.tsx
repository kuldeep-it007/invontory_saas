"use client";

import Link from "next/link";
import type { ReactNode } from "react";

/** Wraps a card in a link (with a visible keyboard focus ring) when `href` is set. */
function CardLink({
  href,
  label,
  className,
  children,
}: {
  href?: string;
  label: string;
  className: string;
  children: ReactNode;
}): JSX.Element {
  if (!href) {
    return <div className={className}>{children}</div>;
  }
  return (
    <Link
      href={href}
      aria-label={`${label} — view details`}
      className={`${className} block transition-transform hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`}
    >
      {children}
    </Link>
  );
}

/**
 * Presentational building blocks for the redesigned company dashboard (R33.1).
 *
 * All visuals are dependency-free (SVG/CSS + Tailwind) and theme-aware. They are
 * intentionally "dumb": every number is passed in from the real backend
 * snapshot by the page, so nothing here fabricates data. Empty/zero states are
 * handled gracefully so a freshly-provisioned tenant still renders cleanly.
 */

/* ─── Formatting helpers ──────────────────────────────────────────────────── */

export function formatCurrency(value: number, currency = "INR"): string {
  try {
    return new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(value);
  } catch {
    return `${currency} ${Math.round(value).toLocaleString()}`;
  }
}

export function formatCompact(value: number): string {
  return new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(value);
}

/* ─── Gradient KPI cards (hero row) ───────────────────────────────────────── */

export type GradientTone = "amber" | "navy" | "teal" | "slate";

const GRADIENTS: Record<GradientTone, string> = {
  amber: "from-orange-400 to-amber-500",
  navy: "from-slate-800 to-slate-900",
  teal: "from-teal-500 to-emerald-600",
  slate: "from-gray-700 to-gray-900",
};

export function GradientStat({
  label,
  value,
  caption,
  icon,
  tone,
  trend,
  href,
}: {
  label: string;
  value: string;
  caption?: string;
  icon: ReactNode;
  tone: GradientTone;
  trend?: { value: string; up: boolean };
  href?: string;
}): JSX.Element {
  return (
    <CardLink
      href={href}
      label={label}
      className={`relative overflow-hidden rounded-2xl bg-gradient-to-br ${GRADIENTS[tone]} p-5 text-white shadow-lg`}
    >
      <div className="flex items-start justify-between">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
          {icon}
        </div>
        {trend ? (
          <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
              trend.up ? "bg-white/25" : "bg-black/20"
            }`}
          >
            <span aria-hidden>{trend.up ? "▲" : "▼"}</span>
            {trend.value}
          </span>
        ) : null}
      </div>
      <p className="mt-4 text-2xl font-bold tabular-nums tracking-tight">{value}</p>
      <p className="mt-0.5 text-sm font-medium text-white/90">{label}</p>
      {caption ? <p className="mt-2 text-xs text-white/70">{caption}</p> : null}
      {/* decorative orb */}
      <span
        aria-hidden
        className="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10"
      />
    </CardLink>
  );
}

/* ─── Small KPI tiles (second row) ────────────────────────────────────────── */

export function MiniStat({
  label,
  value,
  icon,
  accent,
  href,
}: {
  label: string;
  value: string;
  icon: ReactNode;
  accent: string; // tailwind text/bg accent, e.g. "text-teal-600 bg-teal-50"
  href?: string;
}): JSX.Element {
  return (
    <CardLink
      href={href}
      label={label}
      className="flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-card transition-shadow hover:shadow-md"
    >
      <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${accent}`}>
        {icon}
      </div>
      <div className="min-w-0">
        <p className="truncate text-lg font-bold tabular-nums text-foreground">{value}</p>
        <p className="truncate text-xs text-muted-foreground">{label}</p>
      </div>
    </CardLink>
  );
}

/* ─── Section card with header + optional action ──────────────────────────── */

export function Panel({
  title,
  subtitle,
  action,
  children,
  className = "",
}: {
  title: string;
  subtitle?: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}): JSX.Element {
  return (
    <section className={`rounded-xl border border-border bg-card shadow-card ${className}`}>
      <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">{title}</h2>
          {subtitle ? <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p> : null}
        </div>
        {action}
      </header>
      <div className="p-5">{children}</div>
    </section>
  );
}

/* ─── Vertical bar chart (grouped-friendly, single series) ────────────────── */

export function VerticalBars({
  data,
  color = "hsl(var(--primary))",
}: {
  data: { label: string; value: number }[];
  color?: string;
}): JSX.Element {
  const max = Math.max(1, ...data.map((d) => d.value));
  if (!data.length) {
    return <p className="py-10 text-center text-sm text-muted-foreground">No data to chart yet.</p>;
  }
  return (
    <div className="flex h-48 items-end justify-between gap-2">
      {data.map((d) => {
        const pct = Math.round((d.value / max) * 100);
        return (
          <div key={d.label} className="flex flex-1 flex-col items-center gap-2">
            <div className="flex w-full flex-1 items-end">
              <div
                className="group relative w-full rounded-t-md transition-all hover:opacity-80"
                style={{ height: `${Math.max(pct, 2)}%`, backgroundColor: color }}
                title={`${d.label}: ${d.value}`}
              >
                <span className="pointer-events-none absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold tabular-nums text-foreground opacity-0 transition-opacity group-hover:opacity-100">
                  {d.value}
                </span>
              </div>
            </div>
            <span className="w-full truncate text-center text-[10px] capitalize text-muted-foreground" title={d.label}>
              {d.label.replace(/_/g, " ")}
            </span>
          </div>
        );
      })}
    </div>
  );
}

/* ─── Donut with centred total + legend ───────────────────────────────────── */

const DONUT_PALETTE = [
  "hsl(var(--primary))",
  "#0ea5e9",
  "#10b981",
  "#f59e0b",
  "#8b5cf6",
  "#ec4899",
  "#14b8a6",
  "#f43f5e",
];

export function Donut({
  data,
  centerLabel,
}: {
  data: { label: string; value: number }[];
  centerLabel?: string;
}): JSX.Element {
  const segments = data.filter((d) => d.value > 0);
  const total = segments.reduce((s, d) => s + d.value, 0);
  const radius = 15.915;
  let offset = 25;

  if (total === 0) {
    return <p className="py-10 text-center text-sm text-muted-foreground">No data to chart yet.</p>;
  }

  const built = segments.map((d, i) => {
    const pct = (d.value / total) * 100;
    const seg = {
      ...d,
      color: DONUT_PALETTE[i % DONUT_PALETTE.length],
      dash: `${pct} ${100 - pct}`,
      offset,
      pct,
    };
    offset -= pct;
    return seg;
  });

  return (
    <div className="flex flex-wrap items-center gap-6">
      <svg viewBox="0 0 36 36" className="h-32 w-32 shrink-0" role="img" aria-label="Share chart">
        <circle cx="18" cy="18" r={radius} fill="none" stroke="hsl(var(--muted))" strokeWidth="3.5" />
        {built.map((s) => (
          <circle
            key={s.label}
            cx="18"
            cy="18"
            r={radius}
            fill="none"
            stroke={s.color}
            strokeWidth="3.5"
            strokeLinecap="round"
            strokeDasharray={s.dash}
            strokeDashoffset={s.offset}
            transform="rotate(-90 18 18)"
          />
        ))}
        <text x="18" y="16.5" textAnchor="middle" className="fill-foreground text-[5px] font-bold">
          {total}
        </text>
        {centerLabel ? (
          <text x="18" y="21" textAnchor="middle" className="fill-muted-foreground text-[2.6px] uppercase">
            {centerLabel}
          </text>
        ) : null}
      </svg>
      <ul className="flex-1 space-y-2 text-sm">
        {built.map((s) => (
          <li key={s.label} className="flex items-center gap-2">
            <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: s.color }} />
            <span className="capitalize text-muted-foreground">{s.label.replace(/_/g, " ")}</span>
            <span className="ml-auto font-semibold tabular-nums text-foreground">{s.value}</span>
            <span className="w-10 text-right text-xs text-muted-foreground">{Math.round(s.pct)}%</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/* ─── List row (products / low-stock / recent items) ──────────────────────── */

export function ListRow({
  title,
  subtitle,
  trailing,
  avatar,
}: {
  title: string;
  subtitle?: string;
  trailing?: ReactNode;
  avatar?: ReactNode;
}): JSX.Element {
  return (
    <div className="flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors hover:bg-accent/60">
      {avatar ? (
        <div className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted text-xs font-semibold text-muted-foreground">
          {avatar}
        </div>
      ) : null}
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-foreground">{title}</p>
        {subtitle ? <p className="truncate text-xs text-muted-foreground">{subtitle}</p> : null}
      </div>
      {trailing ? <div className="shrink-0 text-right">{trailing}</div> : null}
    </div>
  );
}

/** Two-letter monogram from a name, for list avatars. */
export function monogram(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
