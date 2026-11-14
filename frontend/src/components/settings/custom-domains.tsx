"use client";

import { useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, Input, Label, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useAddDomain,
  useDomains,
  useRemoveDomain,
  useSetPrimaryDomain,
  useVerifyDomain,
  type CustomDomain,
  type DnsRecord,
} from "@/lib/api/domains";

const STATUS_LABEL: Record<CustomDomain["status"], string> = {
  pending: "Waiting for DNS",
  active: "Active",
  failed: "Failed",
  disabled: "Disabled",
};

function httpsLabel(d: CustomDomain): string {
  if (d.type === "platform") return "HTTPS on";
  if (d.status !== "active") return "HTTPS after DNS is verified";
  switch (d.ssl_status) {
    case "issued":
      return d.ssl_expires_at ? `HTTPS on, renews before ${new Date(d.ssl_expires_at).toLocaleDateString()}` : "HTTPS on";
    case "failed":
      return "HTTPS could not be issued yet. It will be retried automatically.";
    default:
      return d.routing_ok ? "Issuing HTTPS certificate…" : "HTTPS starts once the routing record is found";
  }
}

function CopyButton({ value, label }: { value: string; label: string }): JSX.Element {
  const [copied, setCopied] = useState(false);
  return (
    <Button
      type="button"
      size="sm"
      variant="ghost"
      aria-label={`Copy ${label}`}
      onClick={() => {
        void navigator.clipboard?.writeText(value).then(() => {
          setCopied(true);
          setTimeout(() => setCopied(false), 1500);
        });
      }}
    >
      {copied ? "Copied" : "Copy"}
    </Button>
  );
}

function RecordsTable({ records, routingOk }: { records: DnsRecord[]; routingOk: boolean }): JSX.Element {
  const ownership = records.filter((r) => r.purpose === "ownership");
  const routing = records.filter((r) => r.purpose === "routing");
  const recommendedRouting = routing.filter((r) => r.recommended);
  const rows = [...ownership, ...(routingOk ? [] : recommendedRouting)];
  const alternatives = routing.filter((r) => !r.recommended);

  return (
    <div className="space-y-2">
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full text-left text-xs">
          <thead className="bg-muted text-muted-foreground">
            <tr>
              <th className="px-3 py-2 font-medium">Type</th>
              <th className="px-3 py-2 font-medium">Name / Host</th>
              <th className="px-3 py-2 font-medium">Value</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={`${r.type}-${r.value}`} className="border-t border-border align-top">
                <td className="px-3 py-2 font-mono">{r.type}</td>
                <td className="px-3 py-2">
                  <span className="font-mono">{r.host}</span>
                  <CopyButton value={r.host} label={`${r.type} name`} />
                </td>
                <td className="px-3 py-2">
                  <span className="break-all font-mono">{r.value}</span>
                  <CopyButton value={r.value} label={`${r.type} value`} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {!routingOk && alternatives.length > 0 ? (
        <p className="text-xs text-muted-foreground">
          Alternative for the routing record:{" "}
          {alternatives.map((r) => `${r.type} ${r.host} → ${r.value}`).join("; ")}. A root domain cannot use a CNAME;
          use the A record (or ALIAS/ANAME if your provider offers one).
        </p>
      ) : null}
    </div>
  );
}

function DnsHelp(): JSX.Element {
  return (
    <details className="rounded-md border border-border p-3 text-sm">
      <summary className="cursor-pointer font-medium text-foreground">How to add these records at your domain provider</summary>
      <ol className="mt-3 list-decimal space-y-1.5 pl-5 text-muted-foreground">
        <li>Sign in where your domain is managed (Hostinger hPanel, GoDaddy, Namecheap, Cloudflare, …) and open <em>DNS</em> / <em>DNS Zone</em> for the domain.</li>
        <li>Delete any existing <strong>A</strong>, <strong>AAAA</strong> or <strong>CNAME</strong> record for the same name, such as a parking page.</li>
        <li>Add the <strong>TXT</strong> record exactly as shown. Most panels add your domain automatically, so enter only the <em>Name / Host</em> column (for example <code className="font-mono">_inventory-verify</code>), not the full name.</li>
        <li>Add the routing record: a <strong>CNAME</strong> for a subdomain such as <code className="font-mono">shop</code> or <code className="font-mono">www</code>, or an <strong>A</strong> record for a root domain (use <code className="font-mono">@</code> as the name).</li>
        <li>Save. Changes take from a few minutes up to a few hours to spread. This page re-checks automatically, or press <em>Check DNS now</em>.</li>
        <li>In Cloudflare, set the routing record to <strong>DNS only</strong> (grey cloud) until HTTPS is issued.</li>
      </ol>
    </details>
  );
}

function DomainRow({ d, canEdit }: { d: CustomDomain; canEdit: boolean }): JSX.Element {
  const verify = useVerifyDomain();
  const remove = useRemoveDomain();
  const primary = useSetPrimaryDomain();
  const [error, setError] = useState<string | null>(null);

  async function run(action: () => Promise<unknown>): Promise<void> {
    setError(null);
    try {
      await action();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const isCustom = d.type === "custom";
  const needsRecords = isCustom && (d.status === "pending" || d.status === "failed" || !d.routing_ok);
  const canBePrimary = d.status === "active" && (!isCustom || d.ssl_status === "issued");

  return (
    <li className="space-y-3 rounded-lg border border-border p-4">
      <div className="flex flex-wrap items-center gap-2">
        <span className="font-mono text-sm font-medium text-foreground">{d.domain}</span>
        <Badge>{STATUS_LABEL[d.status]}</Badge>
        {d.is_primary ? <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Primary</span> : null}
        {!isCustom ? <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Platform address</span> : null}
      </div>
      <p className="text-xs text-muted-foreground">{httpsLabel(d)}</p>
      {d.last_error && isCustom ? <p className="text-xs text-amber-700">{d.last_error}</p> : null}
      {d.status === "pending" && d.expires_at ? (
        <p className="text-xs text-muted-foreground">Verify before {new Date(d.expires_at).toLocaleString()}.</p>
      ) : null}
      {needsRecords && d.status !== "disabled" ? <RecordsTable records={d.dns_records} routingOk={d.routing_ok} /> : null}
      {d.status === "disabled" ? (
        <p className="text-xs text-muted-foreground">This domain was disabled by the platform. Contact support.</p>
      ) : null}
      {canEdit ? (
        <div className="flex flex-wrap gap-2">
          {isCustom && d.status !== "disabled" ? (
            <Button size="sm" variant="outline" disabled={verify.isPending} onClick={() => void run(() => verify.mutateAsync(d.id))}>
              {verify.isPending ? "Checking…" : "Check DNS now"}
            </Button>
          ) : null}
          {canBePrimary ? (
            <Button
              size="sm"
              variant="outline"
              disabled={primary.isPending}
              onClick={() => void run(() => primary.mutateAsync({ id: d.id, primary: !d.is_primary }))}
            >
              {d.is_primary ? "Remove primary" : "Make primary"}
            </Button>
          ) : null}
          {isCustom ? (
            <Button
              size="sm"
              variant="danger"
              disabled={remove.isPending}
              onClick={() => {
                if (window.confirm(`Remove ${d.domain}? It will stop opening this workspace.`)) {
                  void run(() => remove.mutateAsync(d.id));
                }
              }}
            >
              Remove
            </Button>
          ) : null}
        </div>
      ) : null}
      {error ? (
        <p className="text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
    </li>
  );
}

/**
 * Settings section where a Company_Admin maps its own domain or subdomain to
 * the workspace: add, publish DNS records, verify, then use it with HTTPS.
 * With a primary address set, visits to the other addresses are redirected to it.
 */
export function CustomDomains({ canEdit }: { canEdit: boolean }): JSX.Element {
  const { data, isLoading, error } = useDomains();
  const add = useAddDomain();
  const [value, setValue] = useState("");
  const [addError, setAddError] = useState<string | null>(null);

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setAddError(null);
    try {
      await add.mutateAsync(value.trim());
      setValue("");
    } catch (err) {
      setAddError(errorMessage(err));
    }
  }

  return (
    <Card>
      <div className="space-y-4">
        <div>
          <h2 className="text-base font-semibold text-foreground">Custom domains</h2>
          <p className="text-sm text-muted-foreground">
            Open your workspace on your own domain, such as <span className="font-mono">orders.yourcompany.com</span>.
            Your current address keeps working.
          </p>
        </div>

        {canEdit ? (
          <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div className="flex-1 space-y-1.5">
              <Label htmlFor="new-domain">Domain</Label>
              <Input
                id="new-domain"
                value={value}
                placeholder="shop.example.com"
                autoComplete="off"
                onChange={(e) => setValue(e.target.value)}
              />
            </div>
            <Button type="submit" disabled={add.isPending || value.trim() === ""}>
              {add.isPending ? "Adding…" : "Add domain"}
            </Button>
          </form>
        ) : null}
        {addError ? (
          <p className="text-sm text-red-700" role="alert">
            {addError}
          </p>
        ) : null}

        {isLoading ? (
          <StateMessage>Loading domains…</StateMessage>
        ) : error ? (
          <StateMessage tone="error">{errorMessage(error)}</StateMessage>
        ) : (
          <ul className="space-y-3">
            {(data?.data ?? []).map((d) => (
              <DomainRow key={d.id} d={d} canEdit={canEdit} />
            ))}
          </ul>
        )}

        <DnsHelp />
      </div>
    </Card>
  );
}