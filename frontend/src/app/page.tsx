"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { useAuth } from "@/components/providers/auth-provider";

/**
 * Root entry — routes the user based on auth status: authenticated users land
 * on the dashboard, everyone else on the login screen. Rendered client-side so
 * it can read the persisted token via the auth provider.
 */
export default function IndexPage(): JSX.Element {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "authenticated") router.replace("/dashboard");
    else if (status === "unauthenticated") router.replace("/login");
  }, [status, router]);

  return (
    <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
      Loading…
    </div>
  );
}
