"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";

import { adminApi, type AdminPlan } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { Field, Select } from "@/components/ui/form";
import { Icon } from "@/components/ui/icons";
import { IconButton } from "@/components/ui/list-chrome";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { Badge, Card, StateMessage } from "@/components/ui/primitives";

/**
 * Plans master config for the superadmin (R4). Lists plans with their quota
 * limits and feature flags, supports creating a plan, toggling active state,
 * and deleting an unused plan. Limits/features editing is surfaced read-first
 * (the common superadmin actions are create + activate/deactivate + delete).
 */
export function PlansPanel(): JSX.Element {
  const { confirm, toast } = useAlerts();
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [addOpen, setAddOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminApi.plans();
      setPlans(res.data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggleActive(p: AdminPlan) {
    try {
      await adminApi.updatePlan(p.id, { is_active: !p.is_active });
      await load();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function remove(p: AdminPlan) {
    const ok = await confirm({
      title: "Delete plan?",
      message: `"${p.name}" will be permanently removed. This cannot be undone.`,
      tone: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) return;
    try {
      await adminApi.deletePlan(p.id);
      await load();
      toast({ title: "Plan deleted", message: `"${p.name}" was removed.`, tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
      toast({ title: "Delete failed", message: errorMessage(err), tone: "danger" });
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Plans</h2>
        <Button onClick={() => setAddOpen(true)}>Add plan</Button>
      </div>

      <CreatePlanModal open={addOpen} onClose={() => setAddOpen(false)} onCreated={load} />
      {loading ? (
        <StateMessage>Loading plans…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{error}</StateMessage>
      ) : plans.length === 0 ? (
        <StateMessage>No plans yet.</StateMessage>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {plans.map((p) => (
            <Card key={p.id}>
              <div className="flex items-start justify-between">
                <div>
                  <h3 className="font-medium text-foreground">{p.name}</h3>
                  <p className="text-sm text-muted-foreground">
                    {Number(p.price).toFixed(2)} / {p.billing_cycle}
                  </p>
                </div>
                <Badge>{p.is_active ? "active" : "inactive"}</Badge>
              </div>

              <div className="mt-3 space-y-2 text-xs">
                <div>
                  <p className="font-medium text-foreground">Limits</p>
                  <ul className="mt-1 flex flex-wrap gap-2 text-muted-foreground">
                    {p.limits.length === 0 ? (
                      <li>none</li>
                    ) : (
                      p.limits.map((l) => (
                        <li key={l.key} className="rounded bg-muted px-2 py-0.5">
                          {l.key}: {l.value ?? "∞"}
                        </li>
                      ))
                    )}
                  </ul>
                </div>
                <div>
                  <p className="font-medium text-foreground">Features</p>
                  <ul className="mt-1 flex flex-wrap gap-2 text-muted-foreground">
                    {p.features.filter((f) => f.enabled).length === 0 ? (
                      <li>none enabled</li>
                    ) : (
                      p.features
                        .filter((f) => f.enabled)
                        .map((f) => (
                          <li key={f.key} className="rounded bg-green-100 px-2 py-0.5 text-green-800">
                            {f.key}
                          </li>
                        ))
                    )}
                  </ul>
                </div>
              </div>

              <div className="mt-4 flex items-center gap-2">
                <Button variant="secondary" onClick={() => void toggleActive(p)}>
                  {p.is_active ? "Deactivate" : "Activate"}
                </Button>
                <IconButton label={`Delete plan ${p.name}`} tone="danger" onClick={() => void remove(p)}>
                  <Icon.trash size={16} />
                </IconButton>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

function CreatePlanModal({
  open,
  onClose,
  onCreated,
}: {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
}): JSX.Element {
  const [form, setForm] = useState({ name: "", price: "0", billing_cycle: "monthly" });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function reset() {
    setForm({ name: "", price: "0", billing_cycle: "monthly" });
    setError(null);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await adminApi.createPlan({
        name: form.name,
        price: Number(form.price),
        billing_cycle: form.billing_cycle as "monthly" | "yearly",
        is_active: true,
      });
      reset();
      onCreated();
      onClose();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open={open}
      onClose={() => {
        reset();
        onClose();
      }}
      title="Create plan"
      footer={
        <ModalFormFooter
          formId="create-plan-form"
          submitLabel="Create plan"
          busy={busy}
          disabled={!form.name}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id="create-plan-form" onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Field label="Name" required value={form.name} onChange={(v) => setForm({ ...form, name: v })} />
        <Field label="Price" value={form.price} onChange={(v) => setForm({ ...form, price: v })} />
        <Select
          label="Billing cycle"
          value={form.billing_cycle}
          onChange={(v) => setForm({ ...form, billing_cycle: v })}
          options={[
            { value: "monthly", label: "monthly" },
            { value: "yearly", label: "yearly" },
          ]}
        />
        {error ? (
          <p className="text-sm text-red-700 sm:col-span-3" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
