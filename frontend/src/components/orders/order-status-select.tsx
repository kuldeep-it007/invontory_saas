"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { orderApi } from "@/lib/api/endpoints";
import { errorMessage } from "@/lib/api/errors";
import type { Order } from "@/lib/api/types";

/**
 * Per-row Order lifecycle control (R21.1, R21.2, R21.4).
 *
 * Renders a "Change status" dropdown in the right-most column of each order
 * record so the order's SELLER can drive the lifecycle inline — no separate
 * "lifecycle console". The dropdown offers only the LEGAL next events for the
 * order's current status (mirroring the backend
 * {@see \App\Services\OrderStateMachine} map). On change it pops a themed
 * confirm dialog showing "Order #<number>: <current> → <next>", and only on
 * confirm does it call the matching endpoint, then refreshes the order lists
 * and toasts the outcome.
 */

/** The backend transition map, mirrored: { fromStatus: { event: toStatus } }. */
const TRANSITIONS: Record<string, Record<string, string>> = {
  draft: { submit: "placed" },
  placed: {
    approve: "confirmed",
    confirm: "confirmed",
    reject: "cancelled",
    edit: "placed",
    cancel: "cancelled",
  },
  confirmed: { dispatch: "dispatched", cancel: "cancelled" },
  dispatched: { deliver: "delivered", cancel: "cancelled" },
  delivered: { close: "closed" },
  // closed / cancelled are terminal.
};

/** Human labels for each lifecycle event. */
const EVENT_LABEL: Record<string, string> = {
  submit: "Submit",
  approve: "Approve",
  confirm: "Confirm",
  reject: "Reject",
  edit: "Keep placed (edit)",
  cancel: "Cancel",
  dispatch: "Dispatch",
  deliver: "Deliver",
  close: "Close",
};

/** Events that should use the danger (red) confirm tone. */
const DANGER_EVENTS = new Set(["cancel", "reject"]);

/** The legal events available from a status (empty for terminal states). */
export function availableEvents(status: string): string[] {
  return Object.keys(TRANSITIONS[status.toLowerCase()] ?? {});
}

export function OrderStatusSelect({ order }: { order: Order }): JSX.Element {
  const { confirm, toast } = useAlerts();
  const qc = useQueryClient();
  const [busy, setBusy] = useState(false);

  const status = order.status.toLowerCase();
  const events = availableEvents(status);
  const label = order.order_number ?? `#${order.id}`;

  async function apply(event: string): Promise<void> {
    const nextStatus = TRANSITIONS[status]?.[event];
    if (!nextStatus) return;

    const danger = DANGER_EVENTS.has(event);
    const ok = await confirm({
      title: `Order ${label}`,
      message: `Change status: ${titleCase(order.status)} → ${titleCase(nextStatus)}?`,
      confirmLabel: EVENT_LABEL[event] ?? "Apply",
      tone: danger ? "danger" : "info",
    });
    if (!ok) return;

    setBusy(true);
    try {
      if (event === "confirm" || event === "approve") await orderApi.confirm(order.id);
      else if (event === "cancel" || event === "reject") await orderApi.cancel(order.id);
      else await orderApi.transition(order.id, event);

      toast({
        title: `Order ${label} updated`,
        message: `${titleCase(order.status)} → ${titleCase(nextStatus)}`,
        tone: danger ? "danger" : "success",
      });
      void qc.invalidateQueries({ queryKey: ["orders"] });
      // Confirming/cancelling/dispatching reserves or releases stock and moves
      // sales figures, so refresh stock and the dashboard as well.
      void qc.invalidateQueries({ queryKey: ["stock"] });
      void qc.invalidateQueries({ queryKey: ["dashboard"] });
    } catch (err) {
      toast({ title: "Could not update order", message: errorMessage(err), tone: "danger" });
    } finally {
      setBusy(false);
    }
  }

  // Terminal states have no legal moves — show the status as read-only text.
  if (events.length === 0) {
    return <span className="text-xs text-muted-foreground">No actions</span>;
  }

  return (
    <select
      aria-label={`Change status for order ${label}`}
      disabled={busy}
      value=""
      onChange={(e) => {
        const event = e.target.value;
        e.target.value = "";
        if (event) void apply(event);
      }}
      className="w-full max-w-[10rem] rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-foreground shadow-sm transition-colors hover:border-muted-foreground/40 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50"
    >
      <option value="" disabled>
        {busy ? "Updating…" : "Change status…"}
      </option>
      {events.map((event) => (
        <option key={event} value={event}>
          {EVENT_LABEL[event] ?? event} → {titleCase(TRANSITIONS[status][event])}
        </option>
      ))}
    </select>
  );
}

function titleCase(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
}
