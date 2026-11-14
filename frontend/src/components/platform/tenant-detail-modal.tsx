"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import {
  adminApi,
  TENANT_DETAIL_FIELDS,
  type AdminPlan,
  type AdminTenant,
  type AuditEntry,
  type TenantDetails,
} from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { Field, Pagination, Select } from "@/components/ui/form";
import { Modal } from "@/components/ui/modal";
import { Badge, Label, StateMessage } from "@/components/ui/primitives";
import { AuditTable, formatDate, UsageBars } from "./usage-panel";

type Section = "overview" | "details" | "plan" | "history";

const SECTIONS: { id: Section; label: string }[] = [
  { id: "overview", label: "Overview" },
  { id: "details", label: "Details" },
  { id: "plan", label: "Change plan" },
  { id: "history", label: "History" },
];

const DETAIL_LABELS: Record<(typeof TENANT_DETAIL_FIELDS)[number], string> = {
  company_name: "Company name",
  contact_name: "Contact name",
  contact_email: "Contact email",
  contact_phone: "Contact phone",
  billing_name: "Billing name",
  billing_email: "Billing email",
  billing_address: "Billing address",
  tax_id: "Tax ID",
  notes: "Internal notes",
};

/**
 * One tenant's full picture for the Platform_Admin: subscription and billing,
 * live usage against plan limits, editable details, plan change (with the
 * usage-based downgrade guard) and its change history.
 */
export function TenantDetailModal({
  tenantId,
  onClose,
  onChanged,
}: {
  tenantId: string | null;
  onClose: () => void;
  onChanged: () => void;
}): JSX.Element | null {
  const [tenant, setTenant] = useState<AdminTenant | null>(null);
  const [section, setSection] = useState<Section>("overview");
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!tenantId) return;
    setError(null);
    try {
      setTenant((await adminApi.tenant(tenantId)).data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }, [tenantId]);

  useEffect(() => {
    setTenant(null);
    setSection("overview");
    void load();
  }, [load]);

  function changed(next: AdminTenant) {
    setTenant(next);
    onChanged();
  }

  return (
    <Modal open={tenantId !== null} onClose={onClose} title={tenant ? `Tenant: ${tenant.subdomain}` : "Tenant"} size="lg">
      <nav className="mb-4 flex flex-wrap gap-1 border-b border-border" aria-label="Tenant sections">
        {SECTIONS.map((s) => (
          <button
            key={s.id}
            type="button"
            onClick={() => setSection(s.id)}
            aria-current={section === s.id ? "page" : undefined}
            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
              section === s.id ? "border-primary text-foreground" : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            {s.label}
          </button>
        ))}
      </nav>
      {error ? (
        <StateMessage tone="error">{error}</StateMessage>
      ) : !tenant ? (
        <StateMessage>Loading…</StateMessage>
      ) : section === "overview" ? (
        <Overview tenant={tenant} />
      ) : section === "details" ? (
        <DetailsForm tenant={tenant} onSaved={changed} />
      ) : section === "plan" ? (
        <PlanChange tenant={tenant} onChanged={changed} />
      ) : (
        <History tenantId={tenant.id} />
      )}
    </Modal>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }): JSX.Element {
  return (
    <div className="flex justify-between gap-4 py-1.5 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="text-right font-medium text-foreground [overflow-wrap:anywhere]">{children}</dd>
    </div>
  );
}

function Overview({ tenant }: { tenant: AdminTenant }): JSX.Element {
  const sub = tenant.subscription;
  const plan = sub?.plan;
  return (
    <div className="space-y-5">
      <div className="grid grid-cols-1 gap-x-8 sm:grid-cols-2">
        <dl className="divide-y divide-border">
          <Row label="Domains">{tenant.domains.join(", ") || "—"}</Row>
          <Row label="Currency">{tenant.currency}</Row>
          <Row label="Company">{tenant.details?.company_name ?? "—"}</Row>
          <Row label="Created">{formatDate(tenant.created_at)}</Row>
        </dl>
        <dl className="divide-y divide-border">
          <Row label="Plan">{plan?.name ?? "—"}</Row>
          <Row label="Price">{plan ? `${Number(plan.price).toFixed(2)} / ${plan.billing_cycle}` : "—"}</Row>
          <Row label="Status">{sub ? <Badge>{sub.status}</Badge> : "—"}</Row>
          <Row label="Started">{formatDate(sub?.starts_at)}</Row>
          <Row label="Renews / ends">{formatDate(sub?.ends_at)}</Row>
          {sub?.trial_ends_at ? <Row label="Trial ends">{formatDate(sub.trial_ends_at)}</Row> : null}
          <Row label="Billing contact">{tenant.details?.billing_email ?? "—"}</Row>
        </dl>
      </div>
      <div>
        <h3 className="mb-2 text-sm font-semibold text-foreground">Plan utilisation</h3>
        <UsageBars usage={tenant.usage} />
      </div>
    </div>
  );
}

function DetailsForm({ tenant, onSaved }: { tenant: AdminTenant; onSaved: (t: AdminTenant) => void }): JSX.Element {
  const { toast } = useAlerts();
  const initial = () => ({
    currency: tenant.currency,
    ...Object.fromEntries(TENANT_DETAIL_FIELDS.map((f) => [f, tenant.details?.[f] ?? ""])),
  }) as Record<string, string>;
  const [form, setForm] = useState<Record<string, string>>(initial);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      // Blank optional fields are sent as null so they can be cleared.
      const body: Record<string, string | null> = { currency: form.currency };
      for (const f of TENANT_DETAIL_FIELDS) body[f] = form[f].trim() === "" ? null : form[f].trim();
      const res = await adminApi.updateTenantDetails(tenant.id, body as Partial<TenantDetails> & { currency?: string });
      onSaved({ ...tenant, ...res.data, usage: tenant.usage });
      toast({ title: "Tenant updated", tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const set = (k: string) => (v: string) => setForm((f) => ({ ...f, [k]: v }));
  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Currency" required value={form.currency} onChange={set("currency")} />
        {TENANT_DETAIL_FIELDS.map((f) => (
          <Field
            key={f}
            label={DETAIL_LABELS[f]}
            type={f.endsWith("_email") ? "email" : "text"}
            value={form[f]}
            onChange={set(f)}
          />
        ))}
      </div>
      {error ? (
        <p className="text-sm text-danger" role="alert">
          {error}
        </p>
      ) : null}
      <div className="flex justify-end">
        <Button type="submit" disabled={busy || form.currency.length !== 3}>
          {busy ? "Saving…" : "Save details"}
        </Button>
      </div>
    </form>
  );
}

function PlanChange({ tenant, onChanged }: { tenant: AdminTenant; onChanged: (t: AdminTenant) => void }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [planId, setPlanId] = useState("");
  const [force, setForce] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const currentId = tenant.subscription?.plan_id;
  const currentPlan = plans.find((p) => p.id === currentId);

  useEffect(() => {
    adminApi
      .plans()
      .then((r) => setPlans(r.data.filter((p) => p.is_active)))
      .catch((err) => setError(errorMessage(err)));
  }, []);

  const target = plans.find((p) => String(p.id) === planId);
  const direction =
    target && currentPlan
      ? Number(target.price) > Number(currentPlan.price)
        ? "Upgrade"
        : Number(target.price) < Number(currentPlan.price)
          ? "Downgrade"
          : "Change"
      : "Change";

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!target) return;
    setError(null);
    const ok = await confirm({
      title: `${direction} to ${target.name}?`,
      message: `${tenant.subdomain} will move to ${target.name} (${Number(target.price).toFixed(2)} / ${target.billing_cycle}). This is recorded in the audit log.`,
      confirmLabel: direction,
    });
    if (!ok) return;
    setBusy(true);
    try {
      const res = await adminApi.changeTenantPlan(tenant.id, {
        plan_id: target.id,
        ...(force ? { force: true, reason: reason.trim() } : reason.trim() ? { reason: reason.trim() } : {}),
      });
      onChanged(res.data);
      setPlanId("");
      setForce(false);
      setReason("");
      toast({ title: "Plan changed", message: `${tenant.subdomain} is now on ${target.name}.`, tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <p className="text-sm text-muted-foreground">
        Current plan: <span className="font-medium text-foreground">{currentPlan?.name ?? tenant.subscription?.plan?.name ?? "none"}</span>
      </p>
      <Select
        label="New plan"
        value={planId}
        onChange={setPlanId}
        options={plans
          .filter((p) => p.id !== currentId)
          .map((p) => ({ value: String(p.id), label: `${p.name} — ${Number(p.price).toFixed(2)} / ${p.billing_cycle}` }))}
      />
      {target ? (
        <p className="text-xs text-muted-foreground">
          {direction} · limits:{" "}
          {target.limits.map((l) => `${l.key.replace(/^max_/, "")} ${l.value || "∞"}`).join(", ") || "none"}
        </p>
      ) : null}
      <div className="space-y-1.5">
        <Label htmlFor="plan-reason">Reason{force ? " *" : " (optional)"}</Label>
        <textarea
          id="plan-reason"
          value={reason}
          maxLength={500}
          onChange={(e) => setReason(e.target.value)}
          rows={2}
          className="w-full rounded-lg border border-border bg-card px-3 py-2 text-sm"
        />
      </div>
      <label className="flex items-start gap-2 text-sm">
        <input type="checkbox" checked={force} onChange={(e) => setForce(e.target.checked)} className="mt-0.5" />
        <span>
          Force the change even if current usage exceeds the new plan&apos;s limits (reason required).
        </span>
      </label>
      {error ? (
        <p className="text-sm text-danger" role="alert">
          {error}
        </p>
      ) : null}
      <div className="flex justify-end">
        <Button type="submit" disabled={busy || !target || (force && reason.trim() === "")}>
          {busy ? "Applying…" : `${direction} plan`}
        </Button>
      </div>
    </form>
  );
}

function History({ tenantId }: { tenantId: string }): JSX.Element {
  const [rows, setRows] = useState<AuditEntry[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    adminApi
      .tenantHistory(tenantId, page)
      .then((r) => {
        setRows(r.data);
        setLastPage(r.last_page);
      })
      .catch((err) => setError(errorMessage(err)))
      .finally(() => setLoading(false));
  }, [tenantId, page]);

  return (
    <div className="space-y-3">
      <AuditTable rows={rows} loading={loading} error={error} showTenant={false} />
      <Pagination page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
