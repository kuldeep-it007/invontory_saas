"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useAuth } from "@/components/providers/auth-provider";
import {
  ImpersonationProvider,
  useEffectiveAuth,
} from "@/components/providers/impersonation-provider";

import { ImpersonationBanner } from "./impersonation-banner";
import { allowedActorKindsForPath, requiredAbilityForPath } from "./nav-items";
import { Sidebar } from "./sidebar";
import { Topbar } from "./topbar";

/**
 * Authenticated app shell + client-side route guard. Wraps every page under the
 * (app) route group: unauthenticated users are redirected to /login once the
 * auth status resolves. While hydrating we show a lightweight placeholder so we
 * never flash protected content.
 */
export function AppShell({ children }: { children: ReactNode }): JSX.Element {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [status, router]);

  if (status !== "authenticated") {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        Loading workspace…
      </div>
    );
  }

  return (
    <ImpersonationProvider>
      <ShellBody>{children}</ShellBody>
    </ImpersonationProvider>
  );
}

/**
 * Inner shell body — lives UNDER the ImpersonationProvider so it can enforce the
 * impersonated role's access. While impersonating, a direct navigation to a
 * page the acting-as party may not access is redirected to the ordering surface
 * (R8.6). Without impersonation this guard is a no-op.
 */
function ShellBody({ children }: { children: ReactNode }): JSX.Element {
  const pathname = usePathname();
  const router = useRouter();
  const { can, actorKind } = useEffectiveAuth();

  // Role-based route guard (applies to every actor, not only impersonation):
  // block direct navigation to a destination the current actor's ability set
  // or actor kind does not permit, so stock/order pages stay confined to the
  // role they belong to. Redirect to a safe landing the actor can always reach.
  useEffect(() => {
    const neededAbility = requiredAbilityForPath(pathname);
    const allowedKinds = allowedActorKindsForPath(pathname);
    const abilityDenied = neededAbility != null && !can(neededAbility);
    const kindDenied = allowedKinds != null && !allowedKinds.includes(actorKind);

    if (abilityDenied || kindDenied) {
      router.replace("/dashboard");
    }
  }, [pathname, can, actorKind, router]);

  return (
    <div className="flex h-screen overflow-hidden bg-muted/30">
      {/* Fixed, independently-scrolling sidebar: it stays put while only the
          main content area scrolls, so navigating never moves or reloads it. */}
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar />
        <ImpersonationBanner />
        <main className="flex-1 overflow-y-auto">
          <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
