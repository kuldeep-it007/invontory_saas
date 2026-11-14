"use client";

import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Card, Input, Label, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useIntegrations,
  useRemoveIntegration,
  useSaveIntegration,
  useTestIntegration,
  type CopyProgress,
  type SmtpIntegration,
  type StorageIntegration,
  type TestResult,
} from "@/lib/api/integrations";

const selectClass =
  "w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60";

/**
 * Pre-fill hints only. Every provider is reached through the same S3-compatible
 * client; the app does not treat any provider specially.
 */
const PROVIDERS: { id: string; label: string; endpointHint: string; pathStyle: boolean }[] = [
  { id: "aws", label: "Amazon S3", endpointHint: "Leave empty for Amazon S3", pathStyle: false },
  { id: "oci", label: "Oracle Cloud (OCI)", endpointHint: "https://<namespace>.compat.objectstorage.<region>.oraclecloud.com", pathStyle: true },
  { id: "minio", label: "MinIO", endpointHint: "https://minio.example.com", pathStyle: true },
  { id: "r2", label: "Cloudflare R2", endpointHint: "https://<account>.r2.cloudflarestorage.com", pathStyle: false },
  { id: "spaces", label: "DigitalOcean Spaces", endpointHint: "https://<region>.digitaloceanspaces.com", pathStyle: false },
  { id: "wasabi", label: "Wasabi", endpointHint: "https://s3.<region>.wasabisys.com", pathStyle: false },
  { id: "backblaze", label: "Backblaze B2", endpointHint: "https://s3.<region>.backblazeb2.com", pathStyle: false },
  { id: "other", label: "Other S3-compatible", endpointHint: "https://s3.example.com", pathStyle: false },
];

function Row({ id, label, children }: { id: string; label: string; children: ReactNode }): JSX.Element {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      {children}
    </div>
  );
}

function ResultMessage({ result, error }: { result: TestResult | null; error: string | null }): JSX.Element | null {
  if (error) {
    return (
      <p className="text-sm text-red-700 sm:col-span-2" role="alert">
        {error}
      </p>
    );
  }
  if (!result) return null;
  return (
    <p className={`text-sm sm:col-span-2 ${result.ok ? "text-green-700" : "text-red-700"}`} role={result.ok ? "status" : "alert"}>
      {result.ok ? "Connection works. " : "Connection failed. "}
      {result.message}
    </p>
  );
}

/** Test / Save / Remove buttons shared by both sections. */
function Actions(props: {
  canEdit: boolean;
  configured: boolean;
  busy: boolean;
  onTest: () => void;
  onRemove: () => void;
  extra?: ReactNode;
}): JSX.Element | null {
  if (!props.canEdit) return null;
  return (
    <div className="flex flex-wrap items-center gap-2 sm:col-span-2">
      <Button type="button" variant="outline" disabled={props.busy} onClick={props.onTest}>
        Test connection
      </Button>
      <Button type="submit" disabled={props.busy}>
        Test &amp; save
      </Button>
      {props.configured ? (
        <Button
          type="button"
          variant="ghost"
          disabled={props.busy}
          onClick={() => {
            if (window.confirm("Go back to the platform default? Your saved credentials will be deleted.")) props.onRemove();
          }}
        >
          Use platform default
        </Button>
      ) : null}
      {props.extra}
    </div>
  );
}

function SmtpSection({ data, canEdit }: { data: SmtpIntegration; canEdit: boolean }): JSX.Element {
  const save = useSaveIntegration("smtp");
  const test = useTestIntegration("smtp");
  const remove = useRemoveIntegration("smtp");
  const [form, setForm] = useState({
    host: "",
    port: "587",
    encryption: "tls",
    username: "",
    password: "",
    from_address: "",
    from_name: "",
    enabled: true,
  });
  const [sendTest, setSendTest] = useState(false);
  const [result, setResult] = useState<TestResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    setForm({
      host: data.host ?? "",
      port: String(data.port ?? 587),
      encryption: data.encryption ?? "tls",
      username: data.username ?? "",
      password: "",
      from_address: data.from_address ?? "",
      from_name: data.from_name ?? "",
      enabled: data.configured ? data.enabled : true,
    });
  }, [data]);

  const payload = () => ({ ...form, port: Number(form.port) || 0, send_test_email: sendTest });
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => {
    setSaved(false);
    setForm({ ...form, [key]: e.target.value });
  };

  async function onTest(): Promise<void> {
    setError(null);
    setResult(null);
    try {
      setResult((await test.mutateAsync(payload())).data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function onSave(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    setResult(null);
    setSaved(false);
    try {
      await save.mutateAsync(payload());
      setSaved(true);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const busy = save.isPending || test.isPending || remove.isPending;

  return (
    <Card>
      <form onSubmit={onSave} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h2 className="text-base font-semibold text-foreground">Email (SMTP)</h2>
          <p className="text-sm text-muted-foreground">
            Send your workspace&apos;s email through your own SMTP server. If it is not set up or is unavailable, the
            platform&apos;s mail service is used instead.
          </p>
        </div>
        <Row id="smtp-host" label="Server">
          <Input id="smtp-host" value={form.host} placeholder="smtp.example.com" onChange={set("host")} disabled={!canEdit} />
        </Row>
        <Row id="smtp-port" label="Port">
          <Input id="smtp-port" type="number" value={form.port} onChange={set("port")} disabled={!canEdit} />
        </Row>
        <Row id="smtp-encryption" label="Encryption">
          <select id="smtp-encryption" className={selectClass} value={form.encryption} onChange={set("encryption")} disabled={!canEdit}>
            <option value="tls">STARTTLS (usually port 587)</option>
            <option value="ssl">SSL/TLS (usually port 465)</option>
            <option value="none">None</option>
          </select>
        </Row>
        <Row id="smtp-username" label="Username">
          <Input id="smtp-username" value={form.username} autoComplete="off" onChange={set("username")} disabled={!canEdit} />
        </Row>
        <Row id="smtp-password" label="Password">
          <Input
            id="smtp-password"
            type="password"
            value={form.password}
            autoComplete="new-password"
            placeholder={data.has_password ? "Saved. Leave blank to keep it" : ""}
            onChange={set("password")}
            disabled={!canEdit}
          />
        </Row>
        <Row id="smtp-from" label="Sender email">
          <Input id="smtp-from" type="email" value={form.from_address} placeholder="orders@yourcompany.com" onChange={set("from_address")} disabled={!canEdit} />
        </Row>
        <Row id="smtp-from-name" label="Sender name">
          <Input id="smtp-from-name" value={form.from_name} onChange={set("from_name")} disabled={!canEdit} />
        </Row>
        <label className="flex items-center gap-2 text-sm sm:col-span-2">
          <input
            type="checkbox"
            checked={form.enabled}
            disabled={!canEdit}
            onChange={(e) => {
              setSaved(false);
              setForm({ ...form, enabled: e.target.checked });
            }}
          />
          Use this SMTP server for my workspace&apos;s email
        </label>
        {canEdit ? (
          <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-2">
            <input type="checkbox" checked={sendTest} onChange={(e) => setSendTest(e.target.checked)} />
            Also send a test email to my own address
          </label>
        ) : null}
        <ResultMessage result={result} error={error} />
        {saved ? (
          <p className="text-sm text-green-700 sm:col-span-2" role="status">
            Tested and saved.
          </p>
        ) : null}
        {data.last_test?.ok && !saved ? (
          <p className="text-xs text-muted-foreground sm:col-span-2">
            Last successful test: {new Date(data.last_test.at).toLocaleString()}
          </p>
        ) : null}
        <Actions
          canEdit={canEdit}
          configured={data.configured}
          busy={busy}
          onTest={() => void onTest()}
          onRemove={() => void remove.mutateAsync().catch((err) => setError(errorMessage(err)))}
        />
      </form>
    </Card>
  );
}

function copyMessage(copy: CopyProgress): string {
  switch (copy.status) {
    case "queued":
    case "running":
      return "Copying your existing files into the new bucket…";
    case "completed":
      return `Existing files copied (${copy.copied ?? 0} new, ${copy.skipped ?? 0} already there).`;
    case "completed_with_errors":
      return `Existing files copied, but ${copy.failed ?? 0} could not be copied. Save again to retry.`;
    default:
      return copy.error ?? "Copying existing files failed. Save again to retry.";
  }
}

function StorageSection({ data, canEdit }: { data: StorageIntegration; canEdit: boolean }): JSX.Element {
  const save = useSaveIntegration("storage");
  const test = useTestIntegration("storage");
  const remove = useRemoveIntegration("storage");
  const [form, setForm] = useState({
    provider: "aws",
    endpoint: "",
    region: "",
    bucket: "",
    access_key: "",
    secret_key: "",
    path_style: false,
    enabled: true,
  });
  const [result, setResult] = useState<TestResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    setForm({
      provider: data.provider ?? "aws",
      endpoint: data.endpoint ?? "",
      region: data.region ?? "",
      bucket: data.bucket ?? "",
      access_key: "",
      secret_key: "",
      path_style: data.path_style,
      enabled: data.configured ? data.enabled : true,
    });
  }, [data]);

  const provider = PROVIDERS.find((p) => p.id === form.provider) ?? PROVIDERS[PROVIDERS.length - 1];
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => {
    setSaved(false);
    setForm({ ...form, [key]: e.target.value });
  };

  async function onTest(): Promise<void> {
    setError(null);
    setResult(null);
    try {
      setResult((await test.mutateAsync(form)).data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function onSave(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    setResult(null);
    setSaved(false);
    try {
      await save.mutateAsync(form);
      setSaved(true);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const busy = save.isPending || test.isPending || remove.isPending;

  return (
    <Card>
      <form onSubmit={onSave} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h2 className="text-base font-semibold text-foreground">File storage</h2>
          <p className="text-sm text-muted-foreground">
            Keep your logo, profile pictures and report exports in your own S3-compatible bucket. Files already stored are
            copied over when you save.
          </p>
        </div>
        <Row id="storage-provider" label="Provider">
          <select
            id="storage-provider"
            className={selectClass}
            value={form.provider}
            disabled={!canEdit}
            onChange={(e) => {
              const next = PROVIDERS.find((p) => p.id === e.target.value);
              setSaved(false);
              setForm({ ...form, provider: e.target.value, path_style: next?.pathStyle ?? form.path_style });
            }}
          >
            {PROVIDERS.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </select>
        </Row>
        <Row id="storage-endpoint" label="Endpoint">
          <Input id="storage-endpoint" value={form.endpoint} placeholder={provider.endpointHint} onChange={set("endpoint")} disabled={!canEdit} />
        </Row>
        <Row id="storage-region" label="Region">
          <Input id="storage-region" value={form.region} placeholder="us-east-1" onChange={set("region")} disabled={!canEdit} />
        </Row>
        <Row id="storage-bucket" label="Bucket">
          <Input id="storage-bucket" value={form.bucket} onChange={set("bucket")} disabled={!canEdit} />
        </Row>
        <Row id="storage-access-key" label="Access key">
          <Input
            id="storage-access-key"
            value={form.access_key}
            autoComplete="off"
            placeholder={data.access_key_hint ? `Saved (${data.access_key_hint}). Leave blank to keep it` : ""}
            onChange={set("access_key")}
            disabled={!canEdit}
          />
        </Row>
        <Row id="storage-secret-key" label="Secret key">
          <Input
            id="storage-secret-key"
            type="password"
            value={form.secret_key}
            autoComplete="new-password"
            placeholder={data.has_secret_key ? "Saved. Leave blank to keep it" : ""}
            onChange={set("secret_key")}
            disabled={!canEdit}
          />
        </Row>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.path_style}
            disabled={!canEdit}
            onChange={(e) => {
              setSaved(false);
              setForm({ ...form, path_style: e.target.checked });
            }}
          />
          Use path-style addressing (needed by MinIO and some other providers)
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.enabled}
            disabled={!canEdit}
            onChange={(e) => {
              setSaved(false);
              setForm({ ...form, enabled: e.target.checked });
            }}
          />
          Use this bucket for my workspace&apos;s files
        </label>
        <ResultMessage result={result} error={error} />
        {saved ? (
          <p className="text-sm text-green-700 sm:col-span-2" role="status">
            Tested and saved.
          </p>
        ) : null}
        {data.copy ? <p className="text-sm text-muted-foreground sm:col-span-2">{copyMessage(data.copy)}</p> : null}
        <Actions
          canEdit={canEdit}
          configured={data.configured}
          busy={busy}
          onTest={() => void onTest()}
          onRemove={() => void remove.mutateAsync().catch((err) => setError(errorMessage(err)))}
        />
      </form>
    </Card>
  );
}

/**
 * Settings sections where a Company_Admin connects the workspace to its own SMTP
 * server and its own object-storage bucket. Everything is optional: without them
 * the platform's mail service and storage keep being used.
 */
export function TenantIntegrations({ canEdit }: { canEdit: boolean }): JSX.Element {
  const { data, isLoading, error } = useIntegrations();

  if (isLoading) return <StateMessage>Loading email and storage settings…</StateMessage>;
  if (error || !data) return <StateMessage tone="error">{errorMessage(error)}</StateMessage>;

  const { smtp, storage } = data.data;

  return (
    <>
      {smtp.available ? <SmtpSection data={smtp} canEdit={canEdit} /> : null}
      {storage.available ? <StorageSection data={storage} canEdit={canEdit} /> : null}
    </>
  );
}