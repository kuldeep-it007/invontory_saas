"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { ProvisionTenantForm } from "@/components/platform/provision-tenant-form";
import { TenantsPanel } from "@/components/platform/tenants-panel";
import { PlansPanel } from "@/components/platform/plans-panel";
import { SubscriptionsPanel } from "@/components/platform/subscriptions-panel";
import { useAdminAuth } from "@/components/providers/admin-auth-provider";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/ui/primitives";

type Tab = "tenants" | "plans" | "subscriptions";

/**
 * Superadmin (Platform_Admin) console. Guarded by the central admin session:
 * unauthenticated visitors are sent to the unified /login. Provides master
 * configuration — tenants (incl. provisioning), plans, and subscriptions —
 * all backed by the central /api/admin/* routes.
 */
export default function PlatformConsolePage(): JSX.Element {
  const { identity, status, signOut } = useAdminAuth();
  const router = useRouter();
  const [tab, setTab] = useState<Tab>("tenants");
  const [tenantsRefresh, setTenantsRefresh] = useState(0);

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
    { id: "plans", label: "Plans" },
    { id: "subscriptions", label: "Subscriptions" },
  ];

  return (
    <main className="mx-auto w-full max-w-5xl space-y-6 px-4 py-8 sm:px-6">
      <PageHeader
        title="Platform console"
        description={`Signed in as ${identity?.name ?? "Platform Admin"} (Platform_Admin)`}
        action={
          <Button variant="ghost" onClick={() => void signOut().then(() => router.replace("/login"))}>
            Sign out
          </Button>
        }
      />

      <nav className="flex gap-2 border-b border-border" aria-label="Platform sections">
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            aria-current={tab === t.id ? "page" : undefined}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
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
      {tab === "plans" ? <PlansPanel /> : null}
      {tab === "subscriptions" ? <SubscriptionsPanel /> : null}
    </main>
  );
}
