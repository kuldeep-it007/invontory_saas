"use client";

import { useEffect, useState, type FormEvent } from "react";

import { useAuth } from "@/components/providers/auth-provider";
import { BrandAssetUpload } from "@/components/settings/brand-asset-upload";
import { CustomDomains } from "@/components/settings/custom-domains";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/form";
import { Card, PageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useSettings, useUpdateSettings } from "@/lib/api/hooks";
import { presetIdFromColorTheme, presetToColorTheme, THEME_PRESETS } from "@/lib/branding/presets";
import { parseColorTheme } from "@/lib/branding/resolve";

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
    color_theme: "",
    mail_from_address: "",
    mail_from_name: "",
  });
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  // Which theme the dropdown shows: a preset id, "" (built-in default) or "custom".
  const [themeId, setThemeId] = useState("");

  function onThemeChange(id: string): void {
    setThemeId(id);
    setSaved(false);
    if (id === "") setForm((f) => ({ ...f, color_theme: "" }));
    else if (id !== "custom") setForm((f) => ({ ...f, color_theme: presetToColorTheme(id) ?? "" }));
    // "custom": keep the current value so it can be edited in the text field.
  }

  useEffect(() => {
    if (data?.data) {
      const s = data.data;
      setThemeId(presetIdFromColorTheme(s.color_theme));
      setForm({
        brand_name: s.brand_name ?? "",
        color_theme: s.color_theme ?? "",
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
        // logo/favicon are uploaded separately and deliberately NOT sent here.
        color_theme: form.color_theme || null,
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
            <BrandAssetUpload asset="logo" current={data?.data.logo ?? null} canEdit={canEdit} />
            <BrandAssetUpload asset="favicon" current={data?.data.favicon ?? null} canEdit={canEdit} />
            <div className="space-y-3 sm:col-span-2">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label htmlFor="theme-select" className="block text-sm font-medium text-foreground">
                    Theme
                  </label>
                  <select
                    id="theme-select"
                    value={themeId}
                    disabled={!canEdit}
                    onChange={(e) => onThemeChange(e.target.value)}
                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60"
                  >
                    <option value="">Built-in default</option>
                    {THEME_PRESETS.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.label}
                      </option>
                    ))}
                    <option value="custom">Custom…</option>
                  </select>
                </div>
                {themeId === "custom" ? (
                  <Field
                    label="Custom colour (hex or JSON)"
                    value={form.color_theme}
                    onChange={(v) => setForm({ ...form, color_theme: v })}
                  />
                ) : null}
              </div>

              {/* Swatch preview of the selected theme (the live app updates on save). */}
              <ThemePreview colorTheme={form.color_theme} />
            </div>
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
      <CustomDomains canEdit={canEdit} />
    </div>
  );
}

/**
 * A small preview of a colour theme: page, card, primary and secondary buttons,
 * muted text. Colours not set by the theme fall back to the built-in default.
 */
function ThemePreview({ colorTheme }: { colorTheme: string }): JSX.Element {
  const base = THEME_PRESETS[0].tokens;
  const t = { ...base, ...parseColorTheme(colorTheme || null) };
  const c = (v: string) => `hsl(${v})`;

  return (
    <div
      aria-label="Theme preview"
      role="img"
      className="rounded-lg border p-4"
      style={{ background: c(t.background), borderColor: c(t.border) }}
    >
      <div className="rounded-md border p-3" style={{ background: c(t.card), borderColor: c(t.border) }}>
        <p className="text-sm font-semibold" style={{ color: c(t.cardForeground) }}>
          Preview
        </p>
        <p className="mt-0.5 text-xs" style={{ color: c(t.mutedForeground) }}>
          Secondary text on a card
        </p>
        <div className="mt-3 flex flex-wrap gap-2">
          <span
            className="rounded-md px-3 py-1.5 text-xs font-medium"
            style={{ background: c(t.primary), color: c(t.primaryForeground) }}
          >
            Primary button
          </span>
          <span
            className="rounded-md px-3 py-1.5 text-xs font-medium"
            style={{ background: c(t.accent), color: c(t.accentForeground) }}
          >
            Secondary
          </span>
          <span
            className="rounded-md px-3 py-1.5 text-xs"
            style={{ background: c(t.muted), color: c(t.mutedForeground) }}
          >
            Muted
          </span>
        </div>
      </div>
    </div>
  );
}
