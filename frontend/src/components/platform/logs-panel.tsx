"use client";

import { useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Input, Label } from "@/components/ui/primitives";
import { adminApi, type LogArchiveFile, type LogFilters, type LogSearchResult } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";

const selectClass = "w-full rounded-md border border-border bg-background px-3 py-2 text-sm";

function isoDate(daysAgo: number): string {
  const d = new Date(Date.now() - daysAgo * 86_400_000);
  return d.toISOString().slice(0, 10);
}

function formatSize(bytes: number): string {
  return bytes < 1024 ? `${bytes} B` : bytes < 1_048_576 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1_048_576).toFixed(1)} MB`;
}

/**
 * Platform_Admin log centre: open Telescope (live view, last few days) and
 * browse, search, download or export the archived logs kept in the master
 * bucket for at least 365 days. Every search, download and export is audited.
 */
export function LogsPanel(): JSX.Element {
  const [filters, setFilters] = useState<LogFilters>({ from: isoDate(7), to: isoDate(1) });
  const [files, setFiles] = useState<LogArchiveFile[] | null>(null);
  const [result, setResult] = useState<LogSearchResult | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const set = (key: keyof LogFilters) => (e: { target: { value: string } }) => setFilters({ ...filters, [key]: e.target.value });

  async function run(label: string, action: () => Promise<void>): Promise<void> {
    setBusy(label);
    setError(null);
    try {
      await action();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(null);
    }
  }

  const openTelescope = () =>
    run("telescope", async () => {
      const res = await adminApi.telescopeLink();
      window.open(res.data.url, "_blank", "noopener");
    });

  const list = (e?: FormEvent) => {
    e?.preventDefault();
    return run("list", async () => {
      setResult(null);
      setFiles((await adminApi.logArchives(filters)).data);
    });
  };

  const search = () => run("search", async () => setResult((await adminApi.searchLogs(filters)).data));

  const columns: Column<LogArchiveFile>[] = [
    { header: "Date (IST)", cell: (f) => f.log_date },
    { header: "Scope", cell: (f) => (f.scope === "platform" ? "Platform" : `${f.tenant_slug ?? ""} (${f.tenant_id})`) },
    { header: "Type", cell: (f) => f.kind },
    { header: "Lines", cell: (f) => f.lines },
    { header: "Size", cell: (f) => formatSize(f.size) },
    {
      header: "",
      cell: (f) => (
        <button
          className="rounded-md border border-border px-2 py-1 text-xs hover:bg-muted"
          onClick={() => void run("download", () => adminApi.downloadLogFile(f))}
        >
          Download
        </button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">Live monitoring (Telescope)</h2>
          <p className="text-sm text-muted-foreground">
            Requests, exceptions, queries, jobs, mail and logs from the last few days. Opens in a new tab with a one-time link.
          </p>
        </div>
        <Button onClick={() => void openTelescope()} disabled={busy !== null}>
          {busy === "telescope" ? "Opening…" : "Open Telescope"}
        </Button>
      </div>

      <form onSubmit={(e) => void list(e)} className="space-y-4 rounded-lg border border-border p-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">Log archive (master bucket, kept 365+ days)</h2>
          <p className="text-sm text-muted-foreground">Dates are IST. Search covers up to 31 days, export up to 93 days.</p>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <div className="space-y-1.5">
            <Label htmlFor="log-from">From</Label>
            <Input id="log-from" type="date" value={filters.from ?? ""} onChange={set("from")} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-to">To</Label>
            <Input id="log-to" type="date" value={filters.to ?? ""} onChange={set("to")} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-scope">Scope</Label>
            <select id="log-scope" className={selectClass} value={filters.scope ?? ""} onChange={set("scope")}>
              <option value="">All</option>
              <option value="tenant">Tenants</option>
              <option value="platform">Platform</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-tenant">Tenant ID</Label>
            <Input id="log-tenant" value={filters.tenant_id ?? ""} onChange={set("tenant_id")} placeholder="optional" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-kind">Type</Label>
            <select id="log-kind" className={selectClass} value={filters.kind ?? ""} onChange={set("kind")}>
              <option value="">All</option>
              <option value="app">Application</option>
              <option value="audit">Audit</option>
            </select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="log-q">Text contains</Label>
            <Input id="log-q" value={filters.q ?? ""} onChange={set("q")} placeholder="e.g. login_failed or an email" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-event">Event starts with</Label>
            <Input id="log-event" value={filters.event ?? ""} onChange={set("event")} placeholder="audit.login" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-user">User ID</Label>
            <Input id="log-user" value={filters.user_id ?? ""} onChange={set("user_id")} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="log-request">Request ID</Label>
            <Input id="log-request" value={filters.request_id ?? ""} onChange={set("request_id")} />
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="submit" variant="outline" disabled={busy !== null}>
            {busy === "list" ? "Loading…" : "List files"}
          </Button>
          <Button type="button" variant="outline" disabled={busy !== null} onClick={() => void search()}>
            {busy === "search" ? "Searching…" : "Search entries"}
          </Button>
          <Button type="button" disabled={busy !== null} onClick={() => void run("export", () => adminApi.exportLogs(filters))}>
            {busy === "export" ? "Preparing…" : "Export for audit (zip)"}
          </Button>
        </div>
      </form>

      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}

      {result ? (
        <div className="space-y-2">
          <p className="text-sm text-muted-foreground">
            {result.matches.length} matching entries in {result.files_scanned} files
            {result.truncated ? " (limit reached, narrow the filters)" : ""}.
          </p>
          <pre className="max-h-[32rem] overflow-auto rounded-md border border-border bg-muted p-3 text-xs">
            {result.matches.map((m) => JSON.stringify(m.entry)).join("\n") || "No matches."}
          </pre>
        </div>
      ) : null}

      {files && !result ? (
        <DataTable columns={columns} rows={files} rowKey={(f) => f.id} emptyMessage="No archived logs for these filters." />
      ) : null}
    </div>
  );
}