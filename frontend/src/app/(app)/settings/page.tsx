"use client";

import { useEffect, useState, type FormEvent } from "react";

import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/form";
import { Card, PageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useSettings, useUpdateSettings } from "@/lib/api/hooks";

/**
 * White-label tenant settings (R3.1, R3.3). Any role with settings.view can see
 * the current branding; only Company_Admin can edit it (R8.4). On save the
 * backend applies the update and best-effort audits it (R3.5).
 */
export default function SettingsPage(): JSX.Element {
  const { hasRole } = useAuth();
  const canEdit = hasRole("Company_Admin");
  const { data, isLoading, error } = useSettings();
  const update = useUpdateSettings();

  const [form, setForm] = useState({
    brand_name: "",
    logo: "",
    favicon: "",
    color_theme: "",
    custom_domain: "",
    mail_from_address: "",
    mail_from_name: "",
  });
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (data?.data) {
      const s = data.data;
      setForm({
        brand_name: s.brand_name ?? "",
        logo: s.logo ?? "",
        favicon: s.favicon ?? "",
        color_theme: s.color_theme ?? "",
        custom_domain: s.custom_domain ?? "",
        mail_from_address: s.mail_from_address ?? "",
        mail_from_name: s.mail_from_name ?? "",
      });
    }
  }, [data]);

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setSaveError(null);
    setSaved(false);
    try {
      await update.mutateAsync({
        brand_name: form.brand_name || null,
        logo: form.logo || null,
        favicon: form.favicon || null,
        color_theme: form.color_theme || null,
        custom_domain: form.custom_domain || null,
        mail_from_address: form.mail_from_address || null,
        mail_from_name: form.mail_from_name || null,
      });
      setSaved(true);
    } catch (err) {
      setSaveError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Settings" description="White-label branding for your workspace." />

      {isLoading ? (
        <StateMessage>Loading settings…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : (
        <Card>
          <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Brand name" value={form.brand_name} onChange={(v) => setForm({ ...form, brand_name: v })} />
            <Field label="Custom domain" value={form.custom_domain} onChange={(v) => setForm({ ...form, custom_domain: v })} />
            <Field label="Logo URL" value={form.logo} onChange={(v) => setForm({ ...form, logo: v })} />
            <Field label="Favicon URL" value={form.favicon} onChange={(v) => setForm({ ...form, favicon: v })} />
            <Field label="Color theme" value={form.color_theme} onChange={(v) => setForm({ ...form, color_theme: v })} />
            <Field label="Mail from name" value={form.mail_from_name} onChange={(v) => setForm({ ...form, mail_from_name: v })} />
            <Field label="Mail from address" type="email" value={form.mail_from_address} onChange={(v) => setForm({ ...form, mail_from_address: v })} />

            {saveError ? (
              <p className="text-sm text-red-700 sm:col-span-2" role="alert">
                {saveError}
              </p>
            ) : null}
            {saved ? (
              <p className="text-sm text-green-700 sm:col-span-2" role="status">
                Settings saved.
              </p>
            ) : null}

            {canEdit ? (
              <div className="sm:col-span-2">
                <Button type="submit" disabled={update.isPending}>
                  {update.isPending ? "Saving…" : "Save settings"}
                </Button>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground sm:col-span-2">
                Only a Company Admin can change branding.
              </p>
            )}
          </form>
        </Card>
      )}
    </div>
  );
}
