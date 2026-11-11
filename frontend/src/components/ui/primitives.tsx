import type { HTMLAttributes, InputHTMLAttributes, LabelHTMLAttributes, ReactNode } from "react";

/** Card container. */
export function Card({ className = "", ...props }: HTMLAttributes<HTMLDivElement>): JSX.Element {
  return (
    <div
      className={`rounded-xl border border-border bg-card p-5 shadow-card ${className}`}
      {...props}
    />
  );
}

/** Section heading with optional action slot on the right. */
export function PageHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}): JSX.Element {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">{title}</h1>
        {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {action}
    </div>
  );
}

/**
 * List-page header with a bold title, muted subtitle, and an optional cluster
 * of action controls on the right (e.g. export icons + a primary "Add" button).
 * Matches the DreamsPOS list-page chrome.
 */
export function ListPageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string;
  subtitle?: string;
  actions?: ReactNode;
}): JSX.Element {
  return (
    <div className="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 className="text-xl font-bold text-foreground">{title}</h1>
        {subtitle ? <p className="mt-0.5 text-sm text-muted-foreground">{subtitle}</p> : null}
      </div>
      {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function Label({ className = "", ...props }: LabelHTMLAttributes<HTMLLabelElement>): JSX.Element {
  return (
    <label className={`block text-sm font-medium text-foreground ${className}`} {...props} />
  );
}

export function Input({ className = "", ...props }: InputHTMLAttributes<HTMLInputElement>): JSX.Element {
  return (
    <input
      className={`w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground shadow-sm transition-colors placeholder:text-muted-foreground hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30 ${className}`}
      {...props}
    />
  );
}

/** Status badge coloured by common lifecycle/status words. */
export function Badge({ children }: { children: string }): JSX.Element {
  const value = children.toLowerCase();
  // NOTE: test "inactive" / "disabled" BEFORE "active" — "inactive" contains the
  // substring "active", so the negative state must win to avoid a green badge.
  const isInactive =
    value.includes("inactive") || value.includes("disabled") || value.includes("suspended");

  const tone = isInactive
    ? "bg-muted text-muted-foreground ring-1 ring-inset ring-border"
    : value.includes("cancel") || value.includes("reject") || value.includes("past_due")
      ? "bg-danger/10 text-danger ring-1 ring-inset ring-danger/20"
      : value.includes("pending") || value.includes("draft") || value.includes("placed")
        ? "bg-warning/10 text-warning ring-1 ring-inset ring-warning/20"
        : value.includes("confirm") ||
            value.includes("active") ||
            value.includes("deliver") ||
            value.includes("approve") ||
            value.includes("closed")
          ? "bg-success/10 text-success ring-1 ring-inset ring-success/20"
          : "bg-muted text-muted-foreground ring-1 ring-inset ring-border";

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${tone}`}
    >
      <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
      {children}
    </span>
  );
}

/** Inline error / empty / loading states for data regions. */
export function StateMessage({
  tone = "muted",
  children,
}: {
  tone?: "muted" | "error";
  children: ReactNode;
}): JSX.Element {
  const color = tone === "error" ? "text-red-700" : "text-muted-foreground";
  return <p className={`py-6 text-center text-sm ${color}`}>{children}</p>;
}
