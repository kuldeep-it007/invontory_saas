"use client";

import { useRouter } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { authApi } from "@/lib/api/endpoints";
import { clearToken, getToken, setToken } from "@/lib/api/token";
import { ApiError, type Identity } from "@/lib/api/types";

/**
 * Auth context — holds the authenticated identity, drives login/logout, and
 * exposes an ability check so RBAC-aware UI (R8.2, R8.4) can hide/disable
 * actions the user cannot perform. The backend is still the source of truth;
 * these checks are UX only.
 *
 * On mount, if a persisted token exists we hydrate the identity from GET /me.
 * A 401 clears the stale token.
 */

interface AuthState {
  identity: Identity | null;
  status: "loading" | "authenticated" | "unauthenticated";
  login: (login: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  can: (ability: string) => boolean;
  hasRole: (role: string) => boolean;
  /** Update the in-memory identity (e.g. a new profile picture) without a reload. */
  setAvatar: (avatar: string | null) => void;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }): JSX.Element {
  const router = useRouter();
  const [identity, setIdentity] = useState<Identity | null>(null);
  const [status, setStatus] = useState<AuthState["status"]>("loading");

  // Hydrate from a persisted token on first mount.
  useEffect(() => {
    let active = true;

    async function hydrate(): Promise<void> {
      if (!getToken()) {
        if (active) setStatus("unauthenticated");
        return;
      }
      try {
        const me = await authApi.me();
        if (!active) return;
        setIdentity(me);
        setStatus("authenticated");
      } catch (err) {
        if (!active) return;
        if (err instanceof ApiError && err.status === 401) clearToken();
        setIdentity(null);
        setStatus("unauthenticated");
      }
    }

    void hydrate();
    return () => {
      active = false;
    };
  }, []);

  const login = useCallback(
    async (loginValue: string, password: string): Promise<void> => {
      const result = await authApi.login(loginValue, password);
      setToken(result.token);
      setIdentity(result.user);
      setStatus("authenticated");
    },
    [],
  );

  const logout = useCallback(async (): Promise<void> => {
    try {
      await authApi.logout();
    } catch {
      // Even if the revoke call fails, drop the local session.
    } finally {
      clearToken();
      setIdentity(null);
      setStatus("unauthenticated");
      router.push("/login");
    }
  }, [router]);

  const can = useCallback(
    (ability: string): boolean => identity?.abilities.includes(ability) ?? false,
    [identity],
  );

  const hasRole = useCallback(
    (role: string): boolean => identity?.roles.includes(role) ?? false,
    [identity],
  );

  const setAvatar = useCallback((avatar: string | null): void => {
    setIdentity((prev) => (prev ? { ...prev, avatar } : prev));
  }, []);

  const value = useMemo<AuthState>(
    () => ({ identity, status, login, logout, can, hasRole, setAvatar }),
    [identity, status, login, logout, can, hasRole, setAvatar],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within an AuthProvider");
  return ctx;
}
