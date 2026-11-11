"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";

import { Icon } from "./icons";
import { Input } from "./primitives";

/**
 * Shared list-page chrome (DreamsPOS-style): export icon buttons, an in-table
 * search box, and a compact icon action button for row actions. Kept in a
 * component module (not inside a page file) so Next.js page modules only export
 * their default component.
 */

export function ExportButtons(): JSX.Element {
  return (
    <div className="flex items-center gap-1">
      <IconButton label="Export PDF">
        <Icon.filePdf size={18} />
      </IconButton>
      <IconButton label="Export Excel">
        <Icon.fileExcel size={18} />
      </IconButton>
    </div>
  );
}

export function SearchBox({
  value,
  onChange,
  placeholder = "Search…",
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
}): JSX.Element {
  return (
    <label className="relative flex items-center" aria-label="Search">
      <span className="pointer-events-none absolute left-3 text-muted-foreground">
        <Icon.search size={16} />
      </span>
      <Input
        type="search"
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-56 pl-9"
      />
    </label>
  );
}

export function IconButton({
  label,
  children,
  tone = "default",
  ...props
}: {
  label: string;
  children: ReactNode;
  tone?: "default" | "danger";
} & ButtonHTMLAttributes<HTMLButtonElement>): JSX.Element {
  const toneCls =
    tone === "danger"
      ? "text-danger hover:bg-danger/10 hover:border-danger/30"
      : "text-muted-foreground hover:bg-muted hover:text-foreground";
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      className={`inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-card shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${toneCls}`}
      {...props}
    >
      {children}
    </button>
  );
}
