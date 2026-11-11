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

import { useAuth } from "@/components/providers/auth-provider";
import { impersonationApi } from "@/lib/api/endpoints";
import { abilitiesForImpersonatedKind } from "@/lib/rbac";
import type {
  Identity,
  ImpersonationContext as PartyContext,
} from "@/lib/api/types";

/**
 * Party-impersonation context (R8.6).
 *
 * Holds the buyer the current user is "acting as" (a Distributor or Dealer) so
 * the ordering surfaces (Order Products / cart) can operate on that buyer's
 * behalf, and a banner can announce the active session. Starting a session
 * calls the backend, which enforces the company→distributor→dealer hierarchy
 * and audits the action (R34.3); the returned context is persisted to
 * localStorage so it survives navigation and reloads until explicitly stopped.
 */

const STORAGE_KEY = "impersonation.context";

interface ImpersonationState {
  /** The party being acted as, or null when acting as oneself (company user). */
  acting: PartyContext | null;
  /** Begin impersonating a party (validated + audited server-side). */
  start: (targetType: "distributor" | "dealer", targetId: number) => Promise<void>;
  /** End the impersonation session. No-op for a locked own-party panel user. */
  stop: () => void;
  /**
   * True when `acting` is the logged-in panel user's OWN party (R8.6): the
   * context is permanent and cannot be started/stopped. The impersonation
   * banner and "Act as" controls hide themselves when locked.
   */
  locked: boolean;
}

const Ctx = createContext<ImpersonationState | null>(null);

function read(): PartyContext | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as PartyContext) : null;
  } catch {
    return null;
  }
}

/**
 * The implicit acting context for a logged-in panel user — a distributor or
 * dealer user is permanently "acting as" its OWN party (R8.6). This is derived
 * from the user's identity party link, not from an explicit impersonation
 * session, so these users auto-scope to their own data with no action needed
 * and cannot stop it. Returns null for company-side users (no party link).
 */
function ownPartyContext(party: Identity["party"]): PartyContext | null {
  if (!party) return null;
  return {
    buyer_type: party.type,
    buyer_id: party.id,
    label: party.name,
    kind: party.type,
    parent: null,
  };
}

export function ImpersonationProvider({ children }: { children: ReactNode }): JSX.Element {
  const { identity } = useAuth();
  const [stored, setStored] = useState<PartyContext | null>(null);

  // Hydrate the explicit impersonation session from storage on mount.
  useEffect(() => {
    setStored(read());
  }, []);

  // A logged-in panel user's OWN party acts as a permanent, non-stoppable
  // acting context; it takes precedence over any stale stored session so the
  // panel always scopes to the user's own data (R8.6). Company-side users fall
  // through to the explicit impersonation session (if any).
  const own = ownPartyContext(identity?.party ?? null);
  const acting = own ?? stored;
  const locked = own !== null;

  const start = useCallback(
    async (targetType: "distributor" | "dealer", targetId: number): Promise<void> => {
      // A panel user is locked to its own party and cannot impersonate others.
      if (locked) return;
      const res = await impersonationApi.start(targetType, targetId);
      const ctx = res.data;
      setStored(ctx);
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ctx));
      } catch {
        // Non-fatal: session still works in-memory for this tab.
      }
    },
    [locked],
  );

  const stop = useCallback((): void => {
    // Own-party acting is permanent; only an explicit session can be stopped.
    if (locked) return;
    setStored(null);
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
  }, [locked]);

  const value = useMemo<ImpersonationState>(
    () => ({ acting, start, stop, locked }),
    [acting, start, stop, locked],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useImpersonation(): ImpersonationState {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error("useImpersonation must be used within an ImpersonationProvider");
  return ctx;
}

/**
 * Ability/role checks that honor the active impersonation session (R8.6).
 *
 * When acting as a Distributor or Dealer, the effective ability set is that
 * ROLE's catalogue (not the real Company user's full set), so the nav and pages
 * restrict to exactly what the impersonated party can do. When NOT impersonating
 * it falls through to the real auth identity unchanged.
 *
 * Use this instead of `useAuth().can`/`hasRole` wherever the UI gates on
 * capabilities, so impersonation restricts the whole surface consistently.
 */
export function useEffectiveAuth(): {
  can: (ability: string) => boolean;
  hasRole: (role: string) => boolean;
  impersonating: boolean;
  /**
   * The kind of actor currently driving the UI: a Distributor/Dealer when
   * acting as a party (own-party panel user or an impersonation session), else
   * the Company for a company-side user. Drives the role-based nav + guards.
   */
  actorKind: "company" | "distributor" | "dealer";
} {
  const { can: realCan, hasRole: realHasRole } = useAuth();
  const { acting } = useImpersonation();

  return useMemo(() => {
    if (!acting) {
      return {
        can: realCan,
        hasRole: realHasRole,
        impersonating: false,
        actorKind: "company" as const,
      };
    }

    const allowed = new Set(abilitiesForImpersonatedKind(acting.kind));
    // The impersonated role name, mapped from the party kind.
    const role = acting.kind === "distributor" ? "Distributor" : "Dealer";

    return {
      can: (ability: string) => allowed.has(ability),
      hasRole: (r: string) => r === role,
      impersonating: true,
      actorKind: acting.kind,
    };
  }, [acting, realCan, realHasRole]);
}
