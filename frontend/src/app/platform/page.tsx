"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { ProvisionTenantForm } from "@/components/platform/provision-tenant-form";
import { TenantsPanel } from "@/components/platform/tenants-panel";
import { PlansPanel } from "@/components/platform/plans-panel";
import { SubscriptionsPanel } from "@/components/platform/subscriptions-panel";
import { DomainsPanel } from "@/components/platform/domains-panel";
import { SettingsPanel } from "@/components/platform/settings-panel";
import { AuditLogPanel, UsagePanel } from "@/components/platform/usage-panel";
import { adminApi } from "@/lib/api/admin";
import { useAdminAuth } from "@/components/providers/admin-auth-provider";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/ui/primitives";

type Tab = "tenants" | "domains" | "plans" | "subscriptions" | "usage" | "audit" | "settings";

interface PlatformBrand {
  platform_name: string;
  logo: string | null;
  favicon: string | null;
}

/**
 * Superadmin (Platform_Admin) console. Guarded by the central admin session:
 * unauthenticated visitors are sent to the unified /login. Provides master
 * configuration — tenants (incl. provisioning, details and plan changes), plans,
 * subscriptions, tenant usage, the audit log, and the admin profile/platform
 * settings — all backed by the central /api/admin/* routes.
 */
export default function PlatformConsolePage(): JSX.Element {
  const { identity, status, signOut } = useAdminAuth();
  const router = useRouter();
  const [tab, setTab] = useState<Tab>("tenants");
  const [tenantsRefresh, setTenantsRefresh] = useState(0);
  const [brand, setBrand] = useState<PlatformBrand | null>(null);
  const [displayName, setDisplayName] = useState<string | null>(null);

  useEffect(() => {
    adminApi
      .branding()
      .then((r) => setBrand(r.data))
      .catch(() => undefined); // branding is cosmetic; keep defaults on failure
  }, []);

  // Apply the platform name and favicon to the browser tab.
  useEffect(() => {
    if (!brand) return;
    document.title = `${brand.platform_name} · Platform console`;
    if (!brand.favicon) return;
    let link = document.querySelector<HTMLLinkElement>("link[rel~='icon']");
    if (!link) {
      link = document.createElement("link");
      link.rel = "icon";
      document.head.appendChild(link);
    }
    link.href = brand.favicon;
  }, [brand]);

  useEffect(() => {
    if (status === "unauthenticated") router.replace("/login");
  }, [status, router]);

  if (status !== "authenticated") {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        Loading platform console…
      </div>
    );
  }

  const tabs: { id: Tab; label: string }[] = [
    { id: "tenants", label: "Tenants" },
    { id: "domains", label: "Domains" },
    { id: "plans", label: "Plans" },
    { id: "subscriptions", label: "Subscriptions" },
    { id: "usage", label: "Usage" },
    { id: "audit", label: "Audit log" },
    { id: "settings", label: "Settings" },
  ];

  return (
    <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-8 sm:px-6">
      <PageHeader
        title={brand?.platform_name ? `${brand.platform_name} console` : "Platform console"}
        description={`Signed in as ${displayName ?? identity?.name ?? "Platform Admin"} (Platform_Admin)`}
        action={
          <Button variant="ghost" onClick={() => void signOut().then(() => router.replace("/login"))}>
            Sign out
          </Button>
        }
      />

      {brand?.logo ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={brand.logo} alt={`${brand.platform_name} logo`} className="max-h-12 max-w-[10rem] object-contain" />
      ) : null}
      <nav className="flex gap-2 overflow-x-auto border-b border-border" aria-label="Platform sections">
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            aria-current={tab === t.id ? "page" : undefined}
            className={`-mb-px shrink-0 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
              tab === t.id
                ? "border-primary text-foreground"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {tab === "tenants" ? (
        <div className="space-y-6">
          <ProvisionTenantForm onProvisioned={() => setTenantsRefresh((n) => n + 1)} />
          <TenantsPanel refreshKey={tenantsRefresh} />
        </div>
      ) : null}
      {tab === "domains" ? <DomainsPanel /> : null}
      {tab === "plans" ? <PlansPanel /> : null}
      {tab === "subscriptions" ? <SubscriptionsPanel /> : null}
      {tab === "usage" ? <UsagePanel /> : null}
      {tab === "audit" ? <AuditLogPanel /> : null}
      {tab === "settings" ? (
        <SettingsPanel
          onProfileChanged={(p) => setDisplayName(p.name)}
          onSettingsChanged={(s) =>
            setBrand({ platform_name: s.platform_name, logo: s.logo, favicon: s.favicon })
          }
        />
      ) : null}
    </main>
  );
}
