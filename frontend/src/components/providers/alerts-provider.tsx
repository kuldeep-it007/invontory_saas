"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";

import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";

/**
 * App-wide "sweet alert" system: a themed confirmation dialog and transient
 * toast notifications, built on the design tokens (no external dependency like
 * SweetAlert2). Replaces the browser's plain `window.confirm`/`alert` with
 * accessible, on-brand dialogs, and gives mutations a consistent success/error
 * toast surface.
 *
 * Usage:
 *   const { confirm, toast } = useAlerts();
 *   if (await confirm({ title: "Delete item?", tone: "danger" })) { ... }
 *   toast({ title: "Saved", tone: "success" });
 */

type Tone = "danger" | "warning" | "info" | "success";

interface ConfirmOptions {
  title: string;
  message?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: Tone;
}

interface ToastOptions {
  title: string;
  message?: string;
  tone?: Tone;
  /** Auto-dismiss after this many ms (default 4000; 0 disables). */
  duration?: number;
}

interface AlertsState {
  confirm: (options: ConfirmOptions) => Promise<boolean>;
  toast: (options: ToastOptions) => void;
}

const AlertsContext = createContext<AlertsState | null>(null);

interface ActiveConfirm extends ConfirmOptions {
  resolve: (value: boolean) => void;
}

interface ActiveToast extends ToastOptions {
  id: number;
}

const TONE_ICON: Record<Tone, keyof typeof Icon> = {
  danger: "trash",
  warning: "alert",
  info: "bell",
  success: "check",
};

const TONE_ACCENT: Record<Tone, string> = {
  danger: "bg-danger/10 text-danger",
  warning: "bg-warning/10 text-warning",
  info: "bg-primary/10 text-primary",
  success: "bg-success/10 text-success",
};

/**
 * Fully-coloured toast skin per tone: a soft tinted background, matching border,
 * and readable foreground so the whole toast reads as red (delete/danger),
 * green (success), amber (warning), or brand (info) — not just an accent bar.
 */
const TOAST_SKIN: Record<Tone, { container: string; icon: string; title: string; message: string }> = {
  danger: {
    container: "border-danger/30 bg-danger/10",
    icon: "bg-danger text-danger-foreground",
    title: "text-danger",
    message: "text-danger/80",
  },
  success: {
    container: "border-success/30 bg-success/10",
    icon: "bg-success text-success-foreground",
    title: "text-success",
    message: "text-success/80",
  },
  warning: {
    container: "border-warning/30 bg-warning/10",
    icon: "bg-warning text-warning-foreground",
    title: "text-warning",
    message: "text-warning/80",
  },
  info: {
    container: "border-primary/30 bg-primary/10",
    icon: "bg-primary text-primary-foreground",
    title: "text-primary",
    message: "text-primary/80",
  },
};

export function AlertsProvider({ children }: { children: ReactNode }): JSX.Element {
  const [active, setActive] = useState<ActiveConfirm | null>(null);
  const [toasts, setToasts] = useState<ActiveToast[]>([]);
  const nextId = useRef(1);

  const confirm = useCallback((options: ConfirmOptions): Promise<boolean> => {
    return new Promise<boolean>((resolve) => {
      setActive({ ...options, resolve });
    });
  }, []);

  const dismiss = useCallback(
    (value: boolean) => {
      active?.resolve(value);
      setActive(null);
    },
    [active],
  );

  const toast = useCallback((options: ToastOptions): void => {
    const id = nextId.current++;
    setToasts((prev) => [...prev, { ...options, id }]);
    const duration = options.duration ?? 4000;
    if (duration > 0) {
      window.setTimeout(() => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
      }, duration);
    }
  }, []);

  const removeToast = useCallback((id: number) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const value = useMemo<AlertsState>(() => ({ confirm, toast }), [confirm, toast]);

  return (
    <AlertsContext.Provider value={value}>
      {children}
      {active ? <ConfirmDialog confirm={active} onResolve={dismiss} /> : null}
      <ToastViewport toasts={toasts} onDismiss={removeToast} />
    </AlertsContext.Provider>
  );
}

export function useAlerts(): AlertsState {
  const ctx = useContext(AlertsContext);
  if (!ctx) throw new Error("useAlerts must be used within an AlertsProvider");
  return ctx;
}

/* ─── Confirm dialog ──────────────────────────────────────────────────────── */

function ConfirmDialog({
  confirm,
  onResolve,
}: {
  confirm: ActiveConfirm;
  onResolve: (value: boolean) => void;
}): JSX.Element {
  const tone = confirm.tone ?? "warning";
  const IconComp = Icon[TONE_ICON[tone]];
  const confirmRef = useRef<HTMLButtonElement | null>(null);

  useEffect(() => {
    confirmRef.current?.focus();
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") onResolve(false);
      if (e.key === "Enter") onResolve(true);
    }
    document.addEventListener("keydown", onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = prev;
    };
  }, [onResolve]);

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-foreground/40 p-4 backdrop-blur-sm"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onResolve(false);
      }}
    >
      <div
        role="alertdialog"
        aria-modal="true"
        aria-label={confirm.title}
        className="w-full max-w-sm rounded-2xl border border-border bg-card p-6 text-center shadow-2xl"
      >
        <div className={`mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full ${TONE_ACCENT[tone]}`}>
          <IconComp size={28} />
        </div>
        <h2 className="text-lg font-semibold text-foreground">{confirm.title}</h2>
        {confirm.message ? (
          <p className="mt-1.5 text-sm text-muted-foreground">{confirm.message}</p>
        ) : null}
        <div className="mt-6 flex justify-center gap-3">
          <Button variant="outline" onClick={() => onResolve(false)}>
            {confirm.cancelLabel ?? "Cancel"}
          </Button>
          <Button
            ref={confirmRef}
            variant={tone === "danger" ? "danger" : "primary"}
            onClick={() => onResolve(true)}
          >
            {confirm.confirmLabel ?? (tone === "danger" ? "Delete" : "Confirm")}
          </Button>
        </div>
      </div>
    </div>
  );
}

/* ─── Toasts ──────────────────────────────────────────────────────────────── */

function ToastViewport({
  toasts,
  onDismiss,
}: {
  toasts: ActiveToast[];
  onDismiss: (id: number) => void;
}): JSX.Element {
  return (
    <div className="pointer-events-none fixed right-4 top-4 z-[70] flex w-full max-w-sm flex-col gap-2">
      {toasts.map((t) => {
        const tone = t.tone ?? "info";
        const IconComp = Icon[TONE_ICON[tone]];
        const skin = TOAST_SKIN[tone];
        return (
          <div
            key={t.id}
            role="status"
            className={`pointer-events-auto flex items-start gap-3 overflow-hidden rounded-xl border p-3 shadow-lg backdrop-blur-sm ${skin.container}`}
          >
            <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${skin.icon}`}>
              <IconComp size={16} />
            </span>
            <div className="min-w-0 flex-1">
              <p className={`text-sm font-semibold ${skin.title}`}>{t.title}</p>
              {t.message ? <p className={`mt-0.5 text-xs ${skin.message}`}>{t.message}</p> : null}
            </div>
            <button
              onClick={() => onDismiss(t.id)}
              aria-label="Dismiss"
              className={`rounded-md p-1 transition-colors hover:bg-black/10 ${skin.title}`}
            >
              <span aria-hidden className="text-base leading-none">
                ×
              </span>
            </button>
          </div>
        );
      })}
    </div>
  );
}
