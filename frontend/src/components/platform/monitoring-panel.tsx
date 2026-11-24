"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Fragment, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { LineChart, Meter, StatusDot } from "@/components/ui/charts";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Card, Input, StateMessage } from "@/components/ui/primitives";
import {
  adminApi,
  type DeploymentRecord,
  type MonitorAlert,
  type MonitorRange,
  type MonitorSnapshot,
  type ThresholdRow,
} from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { formatBytes, formatDuration, formatRate, levelFor } from "@/lib/monitoring";

type View = "overview" | "application" | "infrastructure" | "deployments" | "alerts";

const VIEWS: { id: View; label: string }[] = [
  { id: "overview", label: "Server health" },
  { id: "application", label: "Application" },
  { id: "infrastructure", label: "Configuration" },
  { id: "deployments", label: "Deployments" },
  { id: "alerts", label: "Alerts & thresholds" },
];
const RANGES: MonitorRange[] = ["1h", "6h", "24h", "7d", "30d"];
const COLORS = ["#6366f1", "#10b981", "#f59e0b", "#ef4444", "#06b6d4"];

const selectClass = "rounded-md border border-border bg-background px-3 py-1.5 text-sm";

function useOverview() {
  return useQuery({ queryKey: ["monitoring", "overview"], queryFn: () => adminApi.monitoringOverview(), refetchInterval: 15_000 });
}

function useMetrics(range: MonitorRange) {
  return useQuery({ queryKey: ["monitoring", "metrics", range], queryFn: () => adminApi.monitoringMetrics(range), refetchInterval: 30_000 });
}

function useThresholds() {
  return useQuery({ queryKey: ["monitoring", "thresholds"], queryFn: () => adminApi.monitoringThresholds() });
}

function Section({ title, children, hint }: { title: string; children: React.ReactNode; hint?: string }): JSX.Element {
  return (
    <Card className="space-y-3">
      <div>
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      </div>
      {children}
    </Card>
  );
}

function SeverityBadge({ severity }: { severity: string }): JSX.Element {
  const cls = severity === "critical" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800";
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>{severity}</span>;
}

/**
 * Platform_Admin monitoring dashboard. Every number comes from the host
 * monitoring agent (real /proc, Docker, MySQL, Redis and nginx data). When the
 * agent is not reporting, the dashboard says so instead of showing stale values.
 */
export function MonitoringPanel(): JSX.Element {
  const [view, setView] = useState<View>("overview");
  const [range, setRange] = useState<MonitorRange>("24h");
  const overview = useOverview();
  const current = overview.data?.data.current;
  const snap = current?.data ?? null;
  const thresholds = useThresholds().data?.data;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <nav className="flex flex-wrap gap-1" aria-label="Monitoring views">
          {VIEWS.map((v) => (
            <button
              key={v.id}
              onClick={() => setView(v.id)}
              aria-current={view === v.id ? "page" : undefined}
              className={`rounded-lg px-3 py-1.5 text-sm font-medium ${view === v.id ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted"}`}
            >
              {v.label}
            </button>
          ))}
        </nav>
        {view === "overview" || view === "application" ? (
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            History
            <select className={selectClass} value={range} onChange={(e) => setRange(e.target.value as MonitorRange)} aria-label="History range">
              {RANGES.map((r) => (
                <option key={r} value={r}>
                  {r === "1h" ? "Last hour" : r === "6h" ? "Last 6 hours" : r === "24h" ? "Last 24 hours" : r === "7d" ? "Last 7 days" : "Last 30 days"}
                </option>
              ))}
            </select>
          </label>
        ) : null}
      </div>

      {overview.isError ? <StateMessage tone="error">{errorMessage(overview.error)}</StateMessage> : null}
      {current && !current.available ? (
        <StateMessage tone="error">
          The monitoring agent is not reporting. Check it on the server with: <code>sudo /opt/inventory/deploy.sh monitor-status</code>
        </StateMessage>
      ) : null}
      {current?.available && current.stale ? (
        <StateMessage tone="error">Metrics are stale ({formatDuration(current.age_s)} old). The agent may have stopped.</StateMessage>
      ) : null}

      {(overview.data?.data.alerts.active.length ?? 0) > 0 ? (
        <Card className="space-y-2 border-red-300">
          <h3 className="text-sm font-semibold text-foreground">Active alerts</h3>
          <ul className="space-y-1 text-sm">
            {overview.data?.data.alerts.active.map((a) => (
              <li key={a.key} className="flex items-center gap-2">
                <SeverityBadge severity={a.severity} />
                <span className="text-foreground">{a.message}</span>
              </li>
            ))}
          </ul>
        </Card>
      ) : null}

      {view === "overview" ? <ServerView snap={snap} range={range} thresholds={thresholds} /> : null}
      {view === "application" ? <ApplicationView snap={snap} range={range} thresholds={thresholds} /> : null}
      {view === "infrastructure" ? <InfrastructureView snap={snap} /> : null}
      {view === "deployments" ? <DeploymentsView /> : null}
      {view === "alerts" ? <AlertsView history={overview.data?.data.alerts.history ?? []} /> : null}
    </div>
  );
}

type Th = Record<string, ThresholdRow> | undefined;

function lvl(th: Th, key: string, value: number | null | undefined) {
  const t = th?.[key];
  return t ? levelFor(value, t.warning, t.critical, t.lower_is_worse) : "ok";
}

function refs(th: Th, key: string) {
  const t = th?.[key];
  return t
    ? [
        { value: t.warning, color: "#f59e0b", label: `warning ${t.warning}` },
        { value: t.critical, color: "#ef4444", label: `critical ${t.critical}` },
      ]
    : [];
}

function ServerView({ snap, range, thresholds }: { snap: MonitorSnapshot | null; range: MonitorRange; thresholds: Th }): JSX.Element {
  const metrics = useMetrics(range);
  const points = metrics.data?.data.points ?? [];
  if (!snap) return <StateMessage>Waiting for the first agent snapshot.</StateMessage>;
  const s = snap.sample;
  const m = snap.server.memory;
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2">
        <Section title="Live utilisation" hint={`Updated ${snap.updated}. Uptime ${formatDuration(snap.server.uptime_s)}.`}>
          <Meter label="CPU" value={s.cpu_pct} display={`${s.cpu_pct ?? 0}%`} level={lvl(thresholds, "cpu_pct", s.cpu_pct)} />
          <Meter label={`RAM (${formatBytes(m.mem_used)} of ${formatBytes(m.mem_total)}, ${formatBytes(m.mem_avail)} available)`} value={m.mem_pct} display={`${m.mem_pct}%`} level={lvl(thresholds, "mem_pct", m.mem_pct)} />
          <Meter label={`Swap (${formatBytes(m.swap_used)} of ${formatBytes(m.swap_total)})`} value={m.swap_pct} display={`${m.swap_pct}%`} level={lvl(thresholds, "swap_pct", m.swap_pct)} />
          {snap.server.disks.map((d) => (
            <Meter key={d.mount} label={`Disk ${d.mount} (${formatBytes(d.free)} free of ${formatBytes(d.total)})`} value={d.pct} display={`${d.pct}%`} level={lvl(thresholds, "disk_pct", d.pct)} />
          ))}
          <p className="text-xs text-muted-foreground">
            Load average {snap.server.load.map((l) => l.toFixed(2)).join(" / ")} on {snap.server.cpu_cores} core(s).
          </p>
        </Section>
        <Section title="Network" hint="Public interfaces only (loopback and Docker bridges excluded).">
          <div className="grid grid-cols-3 gap-3 text-sm">
            <div><p className="text-muted-foreground">Incoming</p><p className="text-lg font-semibold tabular-nums">{formatRate(snap.server.net.rx_bps)}</p></div>
            <div><p className="text-muted-foreground">Outgoing</p><p className="text-lg font-semibold tabular-nums">{formatRate(snap.server.net.tx_bps)}</p></div>
            <div><p className="text-muted-foreground">Latency</p><p className="text-lg font-semibold tabular-nums">{snap.server.net.latency_ms === null ? "n/a" : `${snap.server.net.latency_ms} ms`}</p></div>
          </div>
        </Section>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Section title="CPU (%)"><LineChart points={points} max={100} unit="%" series={[{ key: "cpu_pct", label: "CPU", color: COLORS[0] }]} refLines={refs(thresholds, "cpu_pct")} /></Section>
        <Section title="Load per core"><LineChart points={points} series={[{ key: "load_per_core", label: "1 min", color: COLORS[2] }]} refLines={refs(thresholds, "load_per_core")} /></Section>
        <Section title="Memory and swap (%)"><LineChart points={points} max={100} unit="%" series={[{ key: "mem_pct", label: "RAM", color: COLORS[1] }, { key: "swap_pct", label: "Swap", color: COLORS[3] }]} refLines={refs(thresholds, "mem_pct")} /></Section>
        <Section title="Disk used (%)"><LineChart points={points} max={100} unit="%" series={[{ key: "disk_pct", label: "Root disk", color: COLORS[4] }]} refLines={refs(thresholds, "disk_pct")} /></Section>
        <Section title="Network traffic"><LineChart points={points} format={(v) => formatBytes(v)} unit="/s" series={[{ key: "net_rx_bps", label: "In", color: COLORS[1] }, { key: "net_tx_bps", label: "Out", color: COLORS[0] }]} /></Section>
        <Section title="Network latency (ms)"><LineChart points={points} unit=" ms" series={[{ key: "net_latency_ms", label: "TCP connect", color: COLORS[2] }]} /></Section>
      </div>

      <Section title="Top processes" hint="By CPU, then memory.">
        <DataTable
          columns={[
            { header: "PID", cell: (p) => p.pid },
            { header: "Process", cell: (p) => p.name },
            { header: "CPU %", cell: (p) => p.cpu_pct },
            { header: "Memory", cell: (p) => formatBytes(p.rss) },
          ] as Column<MonitorSnapshot["server"]["processes"][number]>[]}
          rows={snap.server.processes}
          rowKey={(p) => p.pid}
        />
      </Section>
    </div>
  );
}

function ApplicationView({ snap, range, thresholds }: { snap: MonitorSnapshot | null; range: MonitorRange; thresholds: Th }): JSX.Element {
  const metrics = useMetrics(range);
  const errors = useQuery({ queryKey: ["monitoring", "errors"], queryFn: () => adminApi.monitoringErrors(), refetchInterval: 60_000 });
  const points = metrics.data?.data.points ?? [];
  if (!snap) return <StateMessage>Waiting for the first agent snapshot.</StateMessage>;
  const app = snap.application;
  const db = app.database as Record<string, number | string | boolean>;
  return (
    <div className="space-y-4">
      <Section title="Service availability">
        <div className="flex flex-wrap gap-x-6 gap-y-2">
          {Object.entries(app.services).map(([name, st]) => (
            <StatusDot key={name} up={st.up} label={name} hint={st.status} />
          ))}
        </div>
      </Section>
      <div className="grid gap-4 md:grid-cols-2">
        <Section title="API response time (ms)" hint={`Health probe now: ${app.api.ms ?? "n/a"} ms (HTTP ${app.api.code ?? "none"})`}>
          <LineChart points={points} unit=" ms" series={[{ key: "api_ms", label: "Health probe", color: COLORS[0] }, { key: "req_p95_ms", label: "Request p95", color: COLORS[3] }, { key: "req_p50_ms", label: "Request p50", color: COLORS[1] }]} refLines={refs(thresholds, "api_ms")} />
        </Section>
        <Section title="Request volume" hint={app.http.nginx_log_available ? "From the nginx access log, per bucket." : "nginx monitor log not found. Re-run deploy.sh update to enable it."}>
          <LineChart points={points} format={(v) => `${Math.round(v)}`} series={[{ key: "req", label: "Requests", color: COLORS[0] }]} />
        </Section>
        <Section title="HTTP errors"><LineChart points={points} format={(v) => `${Math.round(v)}`} series={[{ key: "s4xx", label: "4xx", color: COLORS[2] }, { key: "s5xx", label: "5xx", color: COLORS[3] }]} /></Section>
        <Section title="5xx error rate (%)"><LineChart points={points} unit="%" series={[{ key: "err5xx_pct", label: "5xx rate", color: COLORS[3] }]} refLines={refs(thresholds, "error_rate_5xx_pct")} /></Section>
        <Section title="Database connections" hint={db.up ? `MySQL ${String(db.version)}. ${db.threads_connected} of ${db.max_connections} connections (${db.threads_running} active, ${db.idle} idle).` : "Database is not reachable."}>
          <LineChart points={points} format={(v) => `${Math.round(v)}`} series={[{ key: "db_conn", label: "Connected", color: COLORS[0] }, { key: "db_running", label: "Running", color: COLORS[3] }]} />
        </Section>
        <Section title="Query performance" hint={db.up ? `Slow queries total ${db.slow_queries} (log ${String(db.slow_query_log)}, threshold ${String(db.long_query_time)} s).` : undefined}>
          <LineChart points={points} series={[{ key: "db_qps", label: "Queries/s", color: COLORS[1] }, { key: "db_slow", label: "Slow queries", color: COLORS[3] }]} />
        </Section>
      </div>
      <Section title="Queue and workers" hint="Redis queue depth. The worker and scheduler containers are listed under service availability.">
        <p className="text-sm text-foreground">
          Pending jobs: <strong>{app.queue.depth ?? "n/a"}</strong>
          {app.queue.delayed !== undefined ? ` · delayed ${app.queue.delayed}` : ""}
          {app.queue.memory ? ` · Redis memory ${app.queue.memory}` : ""}
        </p>
        <LineChart points={points} format={(v) => `${Math.round(v)}`} series={[{ key: "queue_depth", label: "Queue depth", color: COLORS[2] }]} refLines={refs(thresholds, "queue_depth")} />
      </Section>
      <Section title="Recent application errors" hint="From the Laravel log. Credentials are masked.">
        {errors.isError ? <StateMessage tone="error">{errorMessage(errors.error)}</StateMessage> : null}
        {(errors.data?.data.length ?? 0) === 0 ? <p className="text-sm text-muted-foreground">No recent errors.</p> : (
          <ul className="max-h-72 space-y-1 overflow-auto font-mono text-xs">
            {errors.data?.data.map((e, i) => (
              <li key={`${e.time}-${i}`}><span className="text-muted-foreground">{e.time}</span> <SeverityBadge severity={e.level === "ERROR" ? "warning" : "critical"} /> {e.message}</li>
            ))}
          </ul>
        )}
      </Section>
    </div>
  );
}

function InfrastructureView({ snap }: { snap: MonitorSnapshot | null }): JSX.Element {
  if (!snap) return <StateMessage>Waiting for the first agent snapshot.</StateMessage>;
  const i = snap.infrastructure;
  const rows: [string, string | undefined][] = [
    ["Operating system", i.os], ["Kernel", i.kernel ? `${i.kernel} (${i.arch})` : undefined], ["Hostname", i.hostname],
    ["CPU", i.cpu_model ? `${i.cpu_cores} cores, ${i.cpu_model}` : String(i.cpu_cores ?? "")],
    ["Memory", formatBytes(snap.server.memory.mem_total)], ["Docker", i.docker], ["Compose", i.compose],
    ["PHP", i.php], ["Laravel", i.laravel], ["Node.js (frontend)", i.node], ["Python (agent)", i.python], ["Web server", i.nginx],
  ];
  const db = snap.application.database;
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2">
        <Section title="Server and runtimes">
          <dl className="grid grid-cols-[10rem_1fr] gap-y-1.5 text-sm">
            {rows.map(([k, v]) => (<Fragment key={k}><dt className="text-muted-foreground">{k}</dt><dd className="break-words text-foreground">{v || "n/a"}</dd></Fragment>))}
          </dl>
        </Section>
        <div className="space-y-4">
          <Section title="Web server and SSL">
            <StatusDot up={i.nginx_config_ok === true} label={i.nginx_config_ok ? "nginx configuration test passes" : "nginx configuration test failed"} />
            {i.ssl?.available ? (
              <p className="text-sm text-foreground">
                {i.ssl.domain}: expires {i.ssl.expires} (<strong>{i.ssl.days_left} days left</strong>). Issuer: {i.ssl.issuer}
              </p>
            ) : <p className="text-sm text-muted-foreground">No certificate found for the platform domain.</p>}
          </Section>
          <Section title="Database">
            {db.up ? (
              <dl className="grid grid-cols-[10rem_1fr] gap-y-1.5 text-sm">
                <dt className="text-muted-foreground">Version</dt><dd>{String(db.version)}</dd>
                <dt className="text-muted-foreground">Max connections</dt><dd>{String(db.max_connections)}</dd>
                <dt className="text-muted-foreground">InnoDB buffer pool</dt><dd>{String(db.innodb_buffer_pool_mb)} MB</dd>
                <dt className="text-muted-foreground">Uptime</dt><dd>{formatDuration(Number(db.uptime))}</dd>
              </dl>
            ) : <p className="text-sm text-muted-foreground">Database is not reachable.</p>}
          </Section>
        </div>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <Section title="Listening ports" hint="Public ports are reachable from outside the server.">
          <DataTable
            columns={[{ header: "Port", cell: (p) => p.port }, { header: "Scope", cell: (p) => p.scope }] as Column<{ port: number; scope: string }>[]}
            rows={i.ports ?? []}
            rowKey={(p) => p.port}
          />
        </Section>
        <Section title="Environment validation" hint="Checks only. Values are never shown.">
          <ul className="space-y-1">
            {(i.env_checks ?? []).map((c) => (<li key={c.check}><StatusDot up={c.ok} label={c.check} /></li>))}
          </ul>
        </Section>
      </div>
    </div>
  );
}

const STATUS_STYLE: Record<string, string> = {
  success: "bg-emerald-100 text-emerald-800", running: "bg-sky-100 text-sky-800", blocked: "bg-amber-100 text-amber-800",
  failed: "bg-red-100 text-red-800", rolled_back: "bg-orange-100 text-orange-800",
};

function DeploymentsView(): JSX.Element {
  const [selected, setSelected] = useState<string | null>(null);
  const list = useQuery({ queryKey: ["monitoring", "deployments"], queryFn: () => adminApi.monitoringDeployments(), refetchInterval: 20_000 });
  const rows = list.data?.data.deployments ?? [];
  const latest = rows[0];
  const columns: Column<DeploymentRecord>[] = [
    { header: "Started", cell: (d) => d.started_at.replace("T", " ").replace("Z", " UTC") },
    { header: "Commit", cell: (d) => <code>{d.commit.slice(0, 8)}</code> },
    { header: "Status", cell: (d) => <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[d.status] ?? ""}`}>{d.status.replace("_", " ")}</span> },
    { header: "Tests", cell: (d) => (d.gate ? `${d.gate.passed} passed / ${d.gate.failed} failed / ${d.gate.skipped} skipped` : "n/a") },
    { header: "Duration", cell: (d) => formatDuration(d.duration_s) },
    { header: "Trigger", cell: (d) => d.trigger },
    { header: "", cell: (d) => <Button size="sm" variant="ghost" onClick={() => setSelected(d.id)}>Details</Button> },
  ];
  return (
    <div className="space-y-4">
      {latest ? (
        <Section title="Latest deployment">
          <p className="text-sm text-foreground">
            <span className={`mr-2 rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLE[latest.status] ?? ""}`}>{latest.status.replace("_", " ")}</span>
            commit <code>{latest.commit.slice(0, 8)}</code> {latest.subject ? `"${latest.subject}"` : ""} on {latest.branch}. Stage: {latest.stage}.
          </p>
          {latest.reason ? <p className="text-sm text-red-700">Reason: {latest.reason}</p> : null}
        </Section>
      ) : null}
      <DataTable columns={columns} rows={rows} rowKey={(d) => d.id} isLoading={list.isLoading} error={list.isError ? errorMessage(list.error) : null} emptyMessage="No deployments recorded yet. History starts with the next deploy.sh update." />
      {(list.data?.data.rollbacks.length ?? 0) > 0 ? (
        <Section title="Rollback history">
          <ul className="space-y-1 text-sm">
            {list.data?.data.rollbacks.map((r) => (<li key={r.id}>{r.rollback.at ?? r.started_at}: back to <code>{(r.rollback.to_commit ?? "").slice(0, 8)}</code>. {r.reason}</li>))}
          </ul>
        </Section>
      ) : null}
      {selected ? <DeploymentDetail id={selected} onClose={() => setSelected(null)} /> : null}
    </div>
  );
}

function DeploymentDetail({ id, onClose }: { id: string; onClose: () => void }): JSX.Element {
  const q = useQuery({ queryKey: ["monitoring", "deployment", id], queryFn: () => adminApi.monitoringDeployment(id) });
  const d = q.data?.data;
  return (
    <Card className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-foreground">Deployment {id}</h3>
        <Button size="sm" variant="ghost" onClick={onClose}>Close</Button>
      </div>
      {q.isError ? <StateMessage tone="error">{errorMessage(q.error)}</StateMessage> : null}
      {d?.gate ? (
        <div className="space-y-2">
          <p className="text-sm text-foreground">
            Gate <strong>{d.gate.verdict === "pass" ? "passed" : "failed"}</strong>: {d.gate.totals.passed} passed, {d.gate.totals.failed} failed, {d.gate.totals.skipped} skipped ({d.gate.duration_s}s).
          </p>
          <DataTable
            columns={[
              { header: "Check", cell: (c) => c.name },
              { header: "Required", cell: (c) => (c.mandatory ? "yes" : "no") },
              { header: "Result", cell: (c) => c.status },
              { header: "Detail", cell: (c) => <span className="text-xs">{c.detail}{c.failures.length ? ` — ${c.failures.slice(0, 3).join("; ")}` : ""}</span> },
            ]}
            rows={d.gate.checks}
            rowKey={(c) => c.name}
          />
          <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
            {Object.entries(d.gate.categories).map(([k, v]) => (<span key={k}>{k.replace(/_/g, " ")}: {v.passed}/{v.total}</span>))}
          </div>
        </div>
      ) : <p className="text-sm text-muted-foreground">No gate report for this deployment.</p>}
      <LogBlock title="Deployment log" lines={d?.deploy_log ?? []} />
      <LogBlock title="Gate log" lines={d?.gate_log ?? []} />
    </Card>
  );
}

function LogBlock({ title, lines }: { title: string; lines: string[] }): JSX.Element | null {
  if (lines.length === 0) return null;
  return (
    <details>
      <summary className="cursor-pointer text-sm font-medium text-foreground">{title} ({lines.length} lines, credentials masked)</summary>
      <pre className="mt-2 max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs">{lines.join("\n")}</pre>
    </details>
  );
}

function AlertsView({ history }: { history: MonitorAlert[] }): JSX.Element {
  const qc = useQueryClient();
  const q = useThresholds();
  const [draft, setDraft] = useState<Record<string, { warning: string; critical: string }>>({});
  const [message, setMessage] = useState<{ tone: "ok" | "error"; text: string } | null>(null);
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (q.data) setDraft(Object.fromEntries(Object.entries(q.data.data).map(([k, v]) => [k, { warning: String(v.warning), critical: String(v.critical) }])));
  }, [q.data]);

  async function save(): Promise<void> {
    setSaving(true);
    setMessage(null);
    try {
      const body = Object.fromEntries(Object.entries(draft).map(([k, v]) => [k, { warning: Number(v.warning), critical: Number(v.critical) }]));
      await adminApi.saveMonitoringThresholds(body);
      await qc.invalidateQueries({ queryKey: ["monitoring", "thresholds"] });
      setMessage({ tone: "ok", text: "Thresholds saved. The agent applies them within 15 seconds." });
    } catch (err) {
      setMessage({ tone: "error", text: errorMessage(err) });
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-4">
      <Section title="Warning and critical thresholds" hint="Alerts fire when a metric reaches the value. SSL days left alerts when it falls to the value.">
        <div className="grid gap-x-6 gap-y-3 md:grid-cols-2">
          {Object.entries(q.data?.data ?? {}).map(([key, row]) => (
            <div key={key} className="grid grid-cols-[1fr_6rem_6rem] items-center gap-2 text-sm">
              <span className="text-foreground">{row.label}</span>
              <Input aria-label={`${row.label} warning`} type="number" step="any" value={draft[key]?.warning ?? ""} onChange={(e) => setDraft({ ...draft, [key]: { ...draft[key], warning: e.target.value } })} />
              <Input aria-label={`${row.label} critical`} type="number" step="any" value={draft[key]?.critical ?? ""} onChange={(e) => setDraft({ ...draft, [key]: { ...draft[key], critical: e.target.value } })} />
            </div>
          ))}
        </div>
        <div className="flex items-center gap-3">
          <Button onClick={() => void save()} disabled={saving}>{saving ? "Saving…" : "Save thresholds"}</Button>
          {message ? <span className={`text-sm ${message.tone === "ok" ? "text-emerald-700" : "text-red-700"}`}>{message.text}</span> : null}
        </div>
      </Section>
      <Section title="Alert history">
        <DataTable
          columns={[
            { header: "Time", cell: (a) => a.ts ?? "" },
            { header: "Event", cell: (a) => a.event ?? "" },
            { header: "Severity", cell: (a) => <SeverityBadge severity={a.severity} /> },
            { header: "Message", cell: (a) => a.message },
          ] as Column<MonitorAlert>[]}
          rows={history}
          rowKey={(a) => `${a.ts}-${a.key}-${a.event}`}
          emptyMessage="No alerts have fired."
        />
      </Section>
    </div>
  );
}
