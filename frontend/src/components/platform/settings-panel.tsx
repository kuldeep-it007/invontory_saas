"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { adminApi, type AdminProfile, type PlatformSettings } from "@/lib/api/admin";
import { errorMessage } from "@/lib/api/errors";
import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/form";
import { Card, StateMessage } from "@/components/ui/primitives";

const ASSET_RULES = {
  logo: {
    label: "Logo",
    accept: "image/png,image/jpeg,image/webp",
    types: ["image/png", "image/jpeg", "image/webp"],
    maxBytes: 1024 * 1024,
    help: "PNG, JPEG or WebP, up to 1 MB.",
  },
  favicon: {
    label: "Favicon",
    accept: "image/png,image/x-icon,image/vnd.microsoft.icon,image/webp,.ico",
    types: ["image/png", "image/x-icon", "image/vnd.microsoft.icon", "image/webp"],
    maxBytes: 256 * 1024,
    help: "PNG, ICO or WebP, up to 256 KB. A square 32×32 or 64×64 image works best.",
  },
} as const;

/** Platform_Admin profile + platform-wide settings and branding. */
export function SettingsPanel({
  onProfileChanged,
  onSettingsChanged,
}: {
  onProfileChanged?: (p: AdminProfile) => void;
  onSettingsChanged?: (s: PlatformSettings) => void;
}): JSX.Element {
  return (
    <div className="space-y-6">
      <ProfileCard onChanged={onProfileChanged} />
      <SettingsCard onChanged={onSettingsChanged} />
    </div>
  );
}

function ProfileCard({ onChanged }: { onChanged?: (p: AdminProfile) => void }): JSX.Element {
  const { toast } = useAlerts();
  const [form, setForm] = useState({ name: "", email: "", phone: "" });
  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    adminApi
      .profile()
      .then(({ data }) => {
        setForm({ name: data.name, email: data.email, phone: data.phone ?? "" });
        setLoaded(true);
      })
      .catch((err) => setError(errorMessage(err)));
  }, []);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (pw.password && pw.password !== pw.password_confirmation) {
      setError("The new password and its confirmation do not match.");
      return;
    }
    setBusy(true);
    try {
      const res = await adminApi.updateProfile({
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        ...(pw.password ? pw : {}),
      });
      setPw({ current_password: "", password: "", password_confirmation: "" });
      onChanged?.(res.data);
      toast({ title: "Profile updated", tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <h2 className="text-sm font-semibold text-foreground">My profile</h2>
      {!loaded && !error ? (
        <StateMessage>Loading…</StateMessage>
      ) : (
        <form onSubmit={submit} className="mt-4 space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field label="Name" required value={form.name} onChange={(v) => setForm({ ...form, name: v })} />
            <Field label="Email" type="email" required value={form.email} onChange={(v) => setForm({ ...form, email: v })} />
            <Field label="Phone" value={form.phone} onChange={(v) => setForm({ ...form, phone: v })} />
          </div>
          <fieldset className="space-y-2">
            <legend className="text-xs text-muted-foreground">Change password (leave blank to keep the current one)</legend>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Field label="Current password" type="password" value={pw.current_password} onChange={(v) => setPw({ ...pw, current_password: v })} />
              <Field label="New password" type="password" value={pw.password} onChange={(v) => setPw({ ...pw, password: v })} />
              <Field label="Confirm new password" type="password" value={pw.password_confirmation} onChange={(v) => setPw({ ...pw, password_confirmation: v })} />
            </div>
          </fieldset>
          {error ? (
            <p className="text-sm text-danger" role="alert">
              {error}
            </p>
          ) : null}
          <div className="flex justify-end">
            <Button type="submit" disabled={busy || !form.name.trim() || !form.email.trim()}>
              {busy ? "Saving…" : "Save profile"}
            </Button>
          </div>
        </form>
      )}
    </Card>
  );
}

function SettingsCard({ onChanged }: { onChanged?: (s: PlatformSettings) => void }): JSX.Element {
  const { toast } = useAlerts();
  const [settings, setSettings] = useState<PlatformSettings | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function apply(s: PlatformSettings) {
    setSettings(s);
    onChanged?.(s);
  }

  useEffect(() => {
    adminApi
      .settings()
      .then(({ data }) => {
        setSettings(data);
        setForm(
          Object.fromEntries(
            (
              [
                "platform_name", "tagline", "legal_name", "support_email", "support_phone",
                "website", "address", "default_currency", "timezone", "primary_color",
              ] as const
            ).map((k) => [k, data[k] ?? ""]),
          ),
        );
      })
      .catch((err) => setError(errorMessage(err)));
  }, []);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const body: Record<string, string | null> = {};
      for (const [k, v] of Object.entries(form)) body[k] = v.trim() === "" ? null : v.trim();
      apply((await adminApi.updateSettings(body as Partial<PlatformSettings>)).data);
      toast({ title: "Settings saved", tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const set = (k: string) => (v: string) => setForm((f) => ({ ...f, [k]: v }));
  if (!settings) return <Card>{error ? <StateMessage tone="error">{error}</StateMessage> : <StateMessage>Loading…</StateMessage>}</Card>;

  return (
    <Card>
      <h2 className="text-sm font-semibold text-foreground">Platform settings &amp; branding</h2>
      <div className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
        <AssetUpload asset="logo" current={settings.logo} onChanged={apply} />
        <AssetUpload asset="favicon" current={settings.favicon} onChanged={apply} />
      </div>
      <form onSubmit={submit} className="mt-6 space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Platform name" required value={form.platform_name ?? ""} onChange={set("platform_name")} />
          <Field label="Tagline" value={form.tagline ?? ""} onChange={set("tagline")} />
          <Field label="Legal name" value={form.legal_name ?? ""} onChange={set("legal_name")} />
          <Field label="Support email" type="email" required value={form.support_email ?? ""} onChange={set("support_email")} />
          <Field label="Support phone" value={form.support_phone ?? ""} onChange={set("support_phone")} />
          <Field label="Website" type="url" value={form.website ?? ""} onChange={set("website")} />
          <Field label="Default currency" required value={form.default_currency ?? ""} onChange={set("default_currency")} />
          <Field label="Timezone" required value={form.timezone ?? ""} onChange={set("timezone")} />
          <Field label="Brand colour (#RRGGBB)" value={form.primary_color ?? ""} onChange={set("primary_color")} />
          <Field label="Address" value={form.address ?? ""} onChange={set("address")} />
        </div>
        {error ? (
          <p className="text-sm text-danger" role="alert">
            {error}
          </p>
        ) : null}
        <div className="flex justify-end">
          <Button type="submit" disabled={busy || !(form.platform_name ?? "").trim() || !(form.support_email ?? "").trim()}>
            {busy ? "Saving…" : "Save settings"}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function AssetUpload({
  asset,
  current,
  onChanged,
}: {
  asset: "logo" | "favicon";
  current: string | null;
  onChanged: (s: PlatformSettings) => void;
}): JSX.Element {
  const rules = ASSET_RULES[asset];
  const { toast } = useAlerts();
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function run(action: () => Promise<{ data: PlatformSettings }>, done: string) {
    setError(null);
    setBusy(true);
    try {
      onChanged((await action()).data);
      toast({ title: done, tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  function onFile(file: File | undefined) {
    if (!file) return;
    const isIco = file.name.toLowerCase().endsWith(".ico");
    if (!(rules.types as readonly string[]).includes(file.type) && !(asset === "favicon" && isIco)) {
      setError(asset === "logo" ? "Please choose a PNG, JPEG or WebP image." : "Please choose a PNG, ICO or WebP image.");
      return;
    }
    if (file.size > rules.maxBytes) {
      setError(`The ${rules.label.toLowerCase()} is too large.`);
      return;
    }
    void run(() => adminApi.uploadAsset(asset, file), `${rules.label} updated`);
  }

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-foreground">{rules.label}</p>
      <div className="flex items-center gap-3">
        <div
          className={`flex shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted/40 ${
            asset === "logo" ? "h-14 w-36" : "h-14 w-14"
          }`}
        >
          {current ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={current} alt={`Current ${rules.label.toLowerCase()}`} className={asset === "logo" ? "max-h-12 max-w-[8.5rem] object-contain" : "h-8 w-8 object-contain"} />
          ) : (
            <span className="text-xs text-muted-foreground">None</span>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          <input
            ref={inputRef}
            type="file"
            accept={rules.accept}
            className="sr-only"
            aria-label={`Upload ${rules.label.toLowerCase()}`}
            onChange={(e) => {
              const f = e.target.files?.[0];
              e.target.value = "";
              onFile(f);
            }}
          />
          <Button type="button" size="sm" disabled={busy} onClick={() => inputRef.current?.click()}>
            {busy ? "Working…" : current ? "Replace" : "Upload"}
          </Button>
          {current ? (
            <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={() => void run(() => adminApi.removeAsset(asset), `${rules.label} removed`)}>
              Remove
            </Button>
          ) : null}
        </div>
      </div>
      <p className="text-xs text-muted-foreground">{rules.help}</p>
      {error ? (
        <p className="text-sm text-danger" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}
