"use client";

import { useEffect, useRef, type ReactNode } from "react";

import { Button } from "./button";

/**
 * Accessible modal dialog used for all add/edit flows across the app.
 *
 * - Renders nothing when `open` is false.
 * - Closes on Escape and on backdrop click.
 * - Traps initial focus to the dialog and restores focus to the trigger on close.
 * - Locks background scroll while open.
 *
 * Kept dependency-free (no portal lib) — it renders a fixed overlay. Content is
 * supplied as children; a title and optional footer standardise the chrome.
 */
export function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  size = "md",
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  size?: "sm" | "md" | "lg";
}): JSX.Element | null {
  const dialogRef = useRef<HTMLDivElement | null>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);
  // Keep the latest onClose in a ref. Callers pass a new function every render;
  // if the effect depended on it, every keystroke in a form field would re-run
  // the effect and pull focus out of the input (restore, then refocus dialog).
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) return;

    previouslyFocused.current = document.activeElement as HTMLElement | null;
    const { body } = document;
    const prevOverflow = body.style.overflow;
    body.style.overflow = "hidden";

    // Focus the dialog container for screen readers / keyboard users.
    dialogRef.current?.focus();

    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") onCloseRef.current();
    }
    document.addEventListener("keydown", onKey);

    return () => {
      document.removeEventListener("keydown", onKey);
      body.style.overflow = prevOverflow;
      previouslyFocused.current?.focus?.();
    };
  }, [open]);

  if (!open) return null;

  const width = size === "sm" ? "max-w-sm" : size === "lg" ? "max-w-3xl" : "max-w-xl";

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-foreground/40 p-4 backdrop-blur-sm sm:items-center"
      onMouseDown={(e) => {
        // Only close when the backdrop itself (not inner content) is clicked.
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-label={title}
        tabIndex={-1}
        className={`w-full ${width} rounded-xl border border-border bg-card shadow-2xl outline-none`}
      >
        <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            {description ? <p className="mt-0.5 text-sm text-muted-foreground">{description}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close dialog"
            className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
          >
            <span aria-hidden className="text-lg leading-none">
              ×
            </span>
          </button>
        </div>

        <div className="px-5 py-4">{children}</div>

        {footer ? (
          <div className="flex justify-end gap-2 border-t border-border px-5 py-4">{footer}</div>
        ) : null}
      </div>
    </div>
  );
}

/**
 * Convenience footer with Cancel + primary submit buttons for form modals. The
 * submit button is wired via `form` attribute so it can live in the footer
 * while the <form> is in the body.
 */
export function ModalFormFooter({
  formId,
  submitLabel,
  busy,
  onCancel,
  disabled,
}: {
  formId: string;
  submitLabel: string;
  busy?: boolean;
  onCancel: () => void;
  disabled?: boolean;
}): JSX.Element {
  return (
    <>
      <Button type="button" variant="ghost" onClick={onCancel}>
        Cancel
      </Button>
      <Button type="submit" form={formId} disabled={busy || disabled}>
        {busy ? "Saving…" : submitLabel}
      </Button>
    </>
  );
}
