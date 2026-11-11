"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";

import { BrandLogo } from "@/components/branding/brand-logo";
import { useAuth } from "@/components/providers/auth-provider";
import { Button } from "@/components/ui/button";
import { Card, Input, Label } from "@/components/ui/primitives";
import { adminApi, setAdminToken } from "@/lib/api/admin";
import { authApi } from "@/lib/api/endpoints";
import { errorMessage } from "@/lib/api/errors";
import { setToken } from "@/lib/api/token";
import { ApiError } from "@/lib/api/types";

/**
 * Unified login (R7.1). ONE login page for everyone — it decides who is logging
 * in by trying authentication in two layers and routing by the result:
 *
 *   1. CENTRAL login (`/api/platform/login`) — succeeds for a Platform_Admin
 *      (superadmin) in the central database. On success we store the central
 *      token and send them to the `/platform` console.
 *   2. TENANT login (`/api/login`) — if central auth returns 401 (not a central
 *      user), we fall back to the tenant login resolved by the request host
 *      (subdomain). On success we store the tenant token and send them to
 *      `/dashboard`.
 *
 * This mirrors how multi-tenant SaaS platforms expose a single sign-in whose
 * behaviour depends on the host + the account: superadmins land in the platform
 * console, tenant users land in their workspace.
 */
export default function LoginPage(): JSX.Element {
  const { status } = useAuth();
  const router = useRouter();

  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [resetMode, setResetMode] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    if (status === "authenticated") router.replace("/dashboard");
  }, [status, router]);

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();
    setError(null);
    setNotice(null);
    setSubmitting(true);

    if (resetMode) {
      try {
        const res = await authApi.forgotPassword(loginValue);
        setNotice(res.message);
      } catch (err) {
        setError(errorMessage(err));
      } finally {
        setSubmitting(false);
      }
      return;
    }

    // Layer 1: central (Platform_Admin) login.
    try {
      const central = await adminApi.login(loginValue, password);
      setAdminToken(central.token);
      router.replace("/platform");
      return;
    } catch (err) {
      // Only fall through to tenant login on an auth failure (401). Other
      // errors (network, 500) are surfaced directly.
      if (!(err instanceof ApiError) || err.status !== 401) {
        setError(errorMessage(err));
        setSubmitting(false);
        return;
      }
    }

    // Layer 2: tenant login (resolved by host subdomain).
    try {
      const tenant = await authApi.login(loginValue, password);
      setToken(tenant.token);
      router.replace("/dashboard");
    } catch (err) {
      setError(errorMessage(err));
      setSubmitting(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 flex justify-center">
          <BrandLogo className="h-10 w-auto" />
        </div>

        <h1 className="mb-1 text-center text-xl font-semibold text-foreground">
          {resetMode ? "Reset your password" : "Sign in"}
        </h1>
        <p className="mb-6 text-center text-sm text-muted-foreground">
          {resetMode
            ? "Enter your email and we'll send a reset link."
            : "Sign in to your workspace or the platform console."}
        </p>

        <form onSubmit={handleSubmit} className="space-y-4" noValidate>
          <div className="space-y-1.5">
            <Label htmlFor="login">{resetMode ? "Email" : "Email or username"}</Label>
            <Input
              id="login"
              name="login"
              type={resetMode ? "email" : "text"}
              autoComplete="username"
              required
              value={loginValue}
              onChange={(e) => setLoginValue(e.target.value)}
            />
          </div>

          {!resetMode ? (
            <div className="space-y-1.5">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>
          ) : null}

          {error ? (
            <p role="alert" className="text-sm text-red-700">
              {error}
            </p>
          ) : null}
          {notice ? (
            <p role="status" className="text-sm text-green-700">
              {notice}
            </p>
          ) : null}

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? "Please wait…" : resetMode ? "Send reset link" : "Sign in"}
          </Button>
        </form>

        <button
          type="button"
          className="mt-4 w-full text-center text-sm text-muted-foreground hover:text-foreground"
          onClick={() => {
            setResetMode((v) => !v);
            setError(null);
            setNotice(null);
          }}
        >
          {resetMode ? "Back to sign in" : "Forgot your password?"}
        </button>
      </Card>
    </main>
  );
}
