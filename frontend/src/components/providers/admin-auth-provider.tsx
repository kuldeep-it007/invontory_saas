"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { adminApi, getAdminToken, setAdminToken } from "@/lib/api/admin";
import { ApiError, type Identity } from "@/lib/api/types";

/**
 * Superadmin (Platform_Admin) auth context.
 *
 * The unified login page authenticates the superadmin via the CENTRAL login
 * (`POST /api/platform/login`) and stores the returned token here. This
 * provider hydrates/verifies that token (`/api/platform/me`) and exposes the
 * central identity so the `/platform` console can gate itself and route by
 * role. It is independent of the tenant session (separate token key).
 */
interface AdminAuthState {
  identity: (Identity & { scope?: string }) | null;
  status: "loading" | "authenticated" | "unauthenticated";
  /** Persist a token obtained from central login and verify it. */
  authenticate: (token: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AdminAuthContext = createContext<AdminAuthState | null>(null);

export function AdminAuthProvider({ children }: { children: ReactNode }): JSX.Element {
  const [identity, setIdentity] = useState<AdminAuthState["identity"]>(null);
  const [status, setStatus] = useState<AdminAuthState["status"]>("loading");

  useEffect(() => {
    let active = true;
    if (!getAdminToken()) {
      setStatus("unauthenticated");
      return;
    }
    adminApi
      .me()
      .then((me) => {
        if (!active) return;
        setIdentity(me);
        setStatus("authenticated");
      })
      .catch((err) => {
        if (!active) return;
        if (err instanceof ApiError) setAdminToken(null);
        setIdentity(null);
        setStatus("unauthenticated");
      });
    return () => {
      active = false;
    };
  }, []);

  const authenticate = useCallback(async (token: string): Promise<void> => {
    setAdminToken(token);
    const me = await adminApi.me();
    setIdentity(me);
    setStatus("authenticated");
  }, []);

  const signOut = useCallback(async (): Promise<void> => {
    try {
      await adminApi.logout();
    } catch {
      /* drop local session regardless */
    } finally {
      setAdminToken(null);
      setIdentity(null);
      setStatus("unauthenticated");
    }
  }, []);

  const value = useMemo<AdminAuthState>(
    () => ({ identity, status, authenticate, signOut }),
    [identity, status, authenticate, signOut],
  );

  return <AdminAuthContext.Provider value={value}>{children}</AdminAuthContext.Provider>;
}

export function useAdminAuth(): AdminAuthState {
  const ctx = useContext(AdminAuthContext);
  if (!ctx) throw new Error("useAdminAuth must be used within an AdminAuthProvider");
  return ctx;
}
