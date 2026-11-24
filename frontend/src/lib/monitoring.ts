/** Pure helpers for the monitoring dashboard (formatting + chart geometry). */

export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || Number.isNaN(bytes)) return "n/a";
  const units = ["B", "KB", "MB", "GB", "TB"];
  let value = bytes;
  let i = 0;
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024;
    i += 1;
  }
  return `${value >= 100 || i === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[i]}`;
}

export function formatRate(bytesPerSecond: number | null | undefined): string {
  return bytesPerSecond === null || bytesPerSecond === undefined ? "n/a" : `${formatBytes(bytesPerSecond)}/s`;
}

export function formatDuration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return "n/a";
  const d = Math.floor(seconds / 86400);
  const h = Math.floor((seconds % 86400) / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (d > 0) return `${d}d ${h}h`;
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${seconds % 60}s`;
  return `${seconds}s`;
}

export type Level = "ok" | "warning" | "critical";

/** Level of a value against [warning, critical]; `lowerIsWorse` flips the comparison (SSL days left). */
export function levelFor(value: number | null | undefined, warning: number, critical: number, lowerIsWorse = false): Level {
  if (value === null || value === undefined) return "ok";
  if (lowerIsWorse) return value <= critical ? "critical" : value <= warning ? "warning" : "ok";
  return value >= critical ? "critical" : value >= warning ? "warning" : "ok";
}

export interface ChartPoint {
  ts: number;
}

function valueAt(p: ChartPoint, key: string): unknown {
  return (p as unknown as Record<string, unknown>)[key];
}

/**
 * SVG path for one series. Gaps (null values) break the line instead of
 * dropping to zero, so downtime of the agent or a probe is visible.
 */
export function linePath(points: ChartPoint[], key: string, width: number, height: number, max: number, tMin: number, tMax: number): string {
  const span = Math.max(tMax - tMin, 1);
  let d = "";
  let pen = false;
  for (const p of points) {
    const v = valueAt(p, key);
    if (typeof v !== "number") {
      pen = false;
      continue;
    }
    const x = ((p.ts - tMin) / span) * width;
    const y = height - (Math.min(v, max) / Math.max(max, 1e-9)) * height;
    d += `${pen ? "L" : "M"}${x.toFixed(1)},${y.toFixed(1)} `;
    pen = true;
  }
  return d.trim();
}

/** A round upper bound for the y axis. */
export { valueAt };

export function niceMax(values: number[], floor = 1): number {
  const peak = Math.max(floor, ...values);
  const pow = 10 ** Math.floor(Math.log10(peak));
  for (const step of [1, 2, 2.5, 5, 10]) {
    if (peak <= step * pow) return step * pow;
  }
  return 10 * pow;
}
