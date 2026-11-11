"use client";

import { useState, type FormEvent } from "react";

import { adminApi, type ProvisionTenantResponse } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/form";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Card } from "@/components/ui/primitives";

/**
 * Tenant provisioning (R2) for the superadmin console — rendered as an
 * "Add tenant" button that opens a modal. On success it shows the created
 * tenant's domain + a reminder of the Company_Admin login, and calls
 * `onProvisioned` so the tenants list refreshes.
 */
export function ProvisionTenantForm({
  onProvisioned,
}: {
  onProvisioned?: () => void;
} = {}): JSX.Element {
  const [open, setOpen] = useState(false);
  const [result, setResult] = useState<ProvisionTenantResponse | null>(null);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Tenants</h2>
        <Button onClick={() => setOpen(true)}>Add tenant</Button>
      </div>

      {result ? (
        <Card>
          <h3 className="mb-2 text-sm font-semibold text-green-700">Tenant provisioned</h3>
          <dl className="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
            <Detail label="Tenant ID" value={result.tenant.id} />
            <Detail label="Subdomain" value={result.tenant.subdomain} />
            <Detail label="Domain(s)" value={result.tenant.domains.join(", ")} />
            <Detail label="Currency" value={result.tenant.currency} />
            <Detail label="Subscription" value={result.subscription.status} />
            <Detail label="Company ID" value={String(result.company_id)} />
          </dl>
          <p className="mt-3 rounded-md bg-muted p-3 text-xs text-muted-foreground">
            Log in to this tenant at host{" "}
            <code className="font-mono text-foreground">{result.tenant.domains[0]}</code> using the
            Company_Admin email/password you just set.
          </p>
        </Card>
      ) : null}

      <ProvisionTenantModal
        open={open}
        onClose={() => setOpen(false)}
        onProvisioned={(res) => {
          setResult(res);
          onProvisioned?.();
        }}
      />
    </div>
  );
}

function ProvisionTenantModal({
  open,
  onClose,
  onProvisioned,
}: {
  open: boolean;
  onClose: () => void;
  onProvisioned: (res: ProvisionTenantResponse) => void;
}): JSX.Element {
  const empty = {
    subdomain: "",
    domain: "",
    plan_id: "1",
    company_name: "",
    admin_name: "",
    admin_email: "",
    admin_password: "",
  };
  const [form, setForm] = useState(empty);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof typeof form) => (v: string) => setForm({ ...form, [key]: v });

  function reset() {
    setForm(empty);
    setError(null);
  }

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await adminApi.provisionTenant({
        subdomain: form.subdomain,
        domain: form.domain || form.subdomain,
        plan_id: Number(form.plan_id),
        company_name: form.company_name,
        admin_name: form.admin_name,
        admin_email: form.admin_email,
        admin_password: form.admin_password,
      });
      reset();
      onProvisioned(res);
      onClose();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const valid =
    form.subdomain && form.company_name && form.admin_name && form.admin_email && form.admin_password;

  return (
    <Modal
      open={open}
      size="lg"
      onClose={() => {
        reset();
        onClose();
      }}
      title="Provision a tenant"
      description="Creates a dedicated tenant database, its Company, and an initial Company_Admin."
      footer={
        <ModalFormFooter
          formId="provision-tenant-form"
          submitLabel="Provision tenant"
          busy={busy}
          disabled={!valid}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="provision-tenant-form" onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Subdomain" required value={form.subdomain} onChange={set("subdomain")} />
        <Field label="Domain (optional)" value={form.domain} onChange={set("domain")} />
        <Field label="Plan ID" required value={form.plan_id} onChange={set("plan_id")} />
        <Field label="Company name" required value={form.company_name} onChange={set("company_name")} />
        <Field label="Admin name" required value={form.admin_name} onChange={set("admin_name")} />
        <Field label="Admin email" type="email" required value={form.admin_email} onChange={set("admin_email")} />
        <Field
          label="Admin password"
          type="password"
          required
          value={form.admin_password}
          onChange={set("admin_password")}
        />
        {error ? (
          <p className="text-sm text-red-700 sm:col-span-2" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}

function Detail({ label, value }: { label: string; value: string }): JSX.Element {
  return (
    <div className="flex gap-2">
      <dt className="text-muted-foreground">{label}:</dt>
      <dd className="font-medium text-foreground">{value}</dd>
    </div>
  );
}
