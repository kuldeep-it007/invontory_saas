"use client";

import { linePath, niceMax, valueAt, type ChartPoint, type Level } from "@/lib/monitoring";

/**
 * Dependency-free chart primitives (SVG/CSS) wired to the white-label theme
 * tokens. Kept intentionally small — enough to turn the dashboard's structured
 * metrics into readable visuals without pulling in a charting library.
 */

export interface Datum {
  label: string;
  value: number;
}

/** Horizontal bar chart — good for status breakdowns with readable labels. */
export function BarChart({ data, unit }: { data: Datum[]; unit?: string }): JSX.Element {
  const max = Math.max(1, ...data.map((d) => d.value));

  return (
    <div className="space-y-2.5">
      {data.map((d) => {
        const pct = Math.round((d.value / max) * 100);
        return (
          <div key={d.label} className="grid grid-cols-[7rem_1fr_3rem] items-center gap-2 text-sm">
            <span className="truncate capitalize text-muted-foreground" title={d.label}>
              {d.label.replace(/_/g, " ")}
            </span>
            <span className="h-3 overflow-hidden rounded-full bg-muted" aria-hidden>
              <span
                className="block h-full rounded-full bg-primary transition-all"
                style={{ width: `${pct}%` }}
              />
            </span>
            <span className="text-right font-medium tabular-nums text-foreground">
              {d.value}
              {unit ? ` ${unit}` : ""}
            </span>
          </div>
        );
      })}
    </div>
  );
}

/** Donut chart — compact share-of-total visual for a small set of segments. */
export function DonutChart({ data }: { data: Datum[] }): JSX.Element {
  const total = data.reduce((sum, d) => sum + d.value, 0);
  const palette = [
    "hsl(var(--primary))",
    "hsl(var(--accent-foreground))",
    "#f59e0b",
    "#10b981",
    "#ef4444",
    "#6366f1",
    "#06b6d4",
    "#a855f7",
  ];

  // Build stroke-dasharray segments around a circle (circumference 100).
  const radius = 15.915; // gives circumference ~100 for easy percentages
  let offset = 25; // start at top

  const segments = data
    .filter((d) => d.value > 0)
    .map((d, i) => {
      const pct = total > 0 ? (d.value / total) * 100 : 0;
      const seg = {
        color: palette[i % palette.length],
        dash: `${pct} ${100 - pct}`,
        offset,
        label: d.label,
        value: d.value,
        pct,
      };
      offset -= pct;
      return seg;
    });

  if (total === 0) {
    return <p className="py-6 text-center text-sm text-muted-foreground">No data to chart yet.</p>;
  }

  return (
    <div className="flex items-center gap-5">
      <svg viewBox="0 0 36 36" className="h-28 w-28 shrink-0" role="img" aria-label="Share chart">
        <circle cx="18" cy="18" r={radius} fill="none" stroke="hsl(var(--muted))" strokeWidth="4" />
        {segments.map((s) => (
          <circle
            key={s.label}
            cx="18"
            cy="18"
            r={radius}
            fill="none"
            stroke={s.color}
            strokeWidth="4"
            strokeDasharray={s.dash}
            strokeDashoffset={s.offset}
            transform="rotate(-90 18 18)"
          />
        ))}
        <text x="18" y="18" textAnchor="middle" dominantBaseline="central" className="fill-foreground text-[6px] font-semibold">
          {total}
        </text>
      </svg>
      <ul className="space-y-1 text-sm">
        {segments.map((s) => (
          <li key={s.label} className="flex items-center gap-2">
            <span className="inline-block h-3 w-3 rounded-sm" style={{ backgroundColor: s.color }} />
            <span className="capitalize text-muted-foreground">{s.label.replace(/_/g, " ")}</span>
            <span className="font-medium text-foreground">{s.value}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** A single big stat with a caption. */
export function Stat({ label, value }: { label: string; value: string | number }): JSX.Element {
  return (
    <div>
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums text-foreground">{value}</p>
    </div>
  );
}

/** Quota usage bar (count vs limit, unlimited-aware). */
export function QuotaBar({
  label,
  count,
  limit,
  unlimited,
}: {
  label: string;
  count: number;
  limit: number | null;
  unlimited: boolean;
}): JSX.Element {
  const pct = unlimited || !limit ? 0 : Math.min(100, Math.round((count / limit) * 100));
  const near = pct >= 90;
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-sm">
        <span className="capitalize text-foreground">{label.replace(/^max_/, "").replace(/_/g, " ")}</span>
        <span className="tabular-nums text-muted-foreground">
          {count}
          {unlimited ? " / ∞" : ` / ${limit}`}
        </span>
      </div>
      <span className="block h-2 overflow-hidden rounded-full bg-muted" aria-hidden>
        <span
          className={`block h-full rounded-full ${near ? "bg-red-500" : "bg-primary"}`}
          style={{ width: unlimited ? "0%" : `${pct}%` }}
        />
      </span>
    </div>
  );
}

/* ─── Time-series chart + meter (monitoring dashboard) ─────────────────────── */

export interface LineSeries {
  key: string;
  label: string;
  color: string;
}

const W = 600;
const H = 140;

/** Multi-series SVG line chart over time. Missing samples break the line. `refLines` draws warning/critical levels. */
export function LineChart({
  points,
  series,
  unit = "",
  max,
  refLines = [],
  format = (v: number) => `${Math.round(v * 10) / 10}`,
}: {
  points: ChartPoint[];
  series: LineSeries[];
  unit?: string;
  max?: number;
  refLines?: { value: number; color: string; label: string }[];
  format?: (v: number) => string;
}): JSX.Element {
  if (points.length < 2) {
    return <p className="py-8 text-center text-sm text-muted-foreground">Collecting data. The chart appears after a few samples.</p>;
  }
  const values = points.flatMap((p) => series.map((s) => valueAt(p, s.key)).filter((v): v is number => typeof v === "number"));
  const yMax = max ?? niceMax(values);
  const tMin = points[0].ts;
  const tMax = points[points.length - 1].ts;
  const span = tMax - tMin;
  const fmtTime = (ts: number) => {
    const d = new Date(ts * 1000);
    return span > 2 * 86400
      ? d.toLocaleDateString(undefined, { month: "short", day: "numeric" })
      : d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
  };
  const last = (key: string) => {
    for (let i = points.length - 1; i >= 0; i -= 1) {
      const v = valueAt(points[i], key);
      if (typeof v === "number") return v;
    }
    return null;
  };
  return (
    <div>
      <svg viewBox={`0 0 ${W} ${H + 18}`} className="w-full" role="img" aria-label={series.map((s) => s.label).join(", ")} preserveAspectRatio="none">
        {[0, 0.5, 1].map((f) => (
          <g key={f}>
            <line x1="0" x2={W} y1={H - f * H} y2={H - f * H} stroke="hsl(var(--border))" strokeWidth="1" vectorEffect="non-scaling-stroke" />
            <text x="2" y={H - f * H - 3} className="fill-muted-foreground" fontSize="9">
              {format(yMax * f)}
              {unit}
            </text>
          </g>
        ))}
        {refLines
          .filter((r) => r.value <= yMax)
          .map((r) => (
            <line key={r.label} x1="0" x2={W} y1={H - (r.value / yMax) * H} y2={H - (r.value / yMax) * H} stroke={r.color} strokeDasharray="4 3" strokeWidth="1" vectorEffect="non-scaling-stroke">
              <title>{r.label}</title>
            </line>
          ))}
        {series.map((s) => (
          <path key={s.key} d={linePath(points, s.key, W, H, yMax, tMin, tMax)} fill="none" stroke={s.color} strokeWidth="1.6" vectorEffect="non-scaling-stroke" />
        ))}
        <text x="2" y={H + 13} className="fill-muted-foreground" fontSize="9">{fmtTime(tMin)}</text>
        <text x={W - 2} y={H + 13} textAnchor="end" className="fill-muted-foreground" fontSize="9">{fmtTime(tMax)}</text>
      </svg>
      <ul className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
        {series.map((s) => {
          const v = last(s.key);
          return (
            <li key={s.key} className="flex items-center gap-1.5 text-muted-foreground">
              <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: s.color }} />
              {s.label}
              <span className="font-medium tabular-nums text-foreground">{v === null ? "n/a" : `${format(v)}${unit}`}</span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

const LEVEL_BAR: Record<Level, string> = { ok: "bg-emerald-500", warning: "bg-amber-500", critical: "bg-red-500" };

/** Horizontal meter that turns amber/red at the configured thresholds. */
export function Meter({ label, value, display, level = "ok" }: { label: string; value: number | null; display: string; level?: Level }): JSX.Element {
  const pct = value === null ? 0 : Math.max(0, Math.min(100, value));
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">{label}</span>
        <span className="font-medium tabular-nums text-foreground">{display}</span>
      </div>
      <span className="block h-2 overflow-hidden rounded-full bg-muted" role="meter" aria-label={label} aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
        <span className={`block h-full rounded-full transition-all ${LEVEL_BAR[level]}`} style={{ width: `${pct}%` }} />
      </span>
    </div>
  );
}

/** Coloured dot + label for service availability. */
export function StatusDot({ up, label, hint }: { up: boolean; label: string; hint?: string }): JSX.Element {
  return (
    <span className="inline-flex items-center gap-2 text-sm" title={hint}>
      <span className={`inline-block h-2.5 w-2.5 rounded-full ${up ? "bg-emerald-500" : "bg-red-500"}`} aria-hidden />
      <span className="text-foreground">{label}</span>
      <span className="sr-only">{up ? "up" : "down"}</span>
    </span>
  );
}