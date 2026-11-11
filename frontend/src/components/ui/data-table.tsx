import type { ReactNode } from "react";

import { StateMessage } from "./primitives";

/**
 * Generic, accessible data table (DreamsPOS-style). Columns declare a header
 * and a cell renderer. Handles loading / error / empty states so pages stay
 * declarative. Optional `toolbar` (search/filters above the rows) and `footer`
 * (pagination/row-count below) slots give list pages a consistent card shell.
 */
export interface Column<T> {
  header: string;
  cell: (row: T) => ReactNode;
  className?: string;
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  isLoading,
  error,
  emptyMessage = "Nothing to show yet.",
  toolbar,
  footer,
}: {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  isLoading?: boolean;
  error?: string | null;
  emptyMessage?: string;
  toolbar?: ReactNode;
  footer?: ReactNode;
}): JSX.Element {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
      {toolbar ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
          {toolbar}
        </div>
      ) : null}

      {/* Columns shrink and text wraps so the table fits the available width; the
          scroll container is only a safety net for very narrow screens. */}
      <div className="overflow-x-auto">
        <table className="w-full table-auto border-collapse text-left text-sm">
          <thead className="bg-muted/60">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.header}
                  scope="col"
                  className={`px-3 py-3 align-bottom text-xs font-semibold uppercase tracking-wide text-muted-foreground [overflow-wrap:anywhere] ${col.className ?? ""}`}
                >
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr>
                <td colSpan={columns.length}>
                  <StateMessage>Loading…</StateMessage>
                </td>
              </tr>
            ) : error ? (
              <tr>
                <td colSpan={columns.length}>
                  <StateMessage tone="error">{error}</StateMessage>
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length}>
                  <StateMessage>{emptyMessage}</StateMessage>
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr
                  key={rowKey(row)}
                  className="border-t border-border/70 transition-colors hover:bg-accent/60"
                >
                  {columns.map((col) => (
                    <td key={col.header} className={`px-3 py-3 align-top text-foreground [overflow-wrap:anywhere] ${col.className ?? ""}`}>
                      {col.cell(row)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {footer ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm text-muted-foreground">
          {footer}
        </div>
      ) : null}
    </div>
  );
}
