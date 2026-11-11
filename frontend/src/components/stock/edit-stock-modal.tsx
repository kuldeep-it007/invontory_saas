"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Field } from "@/components/ui/form";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { errorMessage } from "@/lib/api/errors";
import { useStockMutation } from "@/lib/api/hooks";
import type { StockRow } from "@/lib/api/types";

/**
 * Edit a stock row: its on-hand quantity and/or its low-stock threshold.
 *
 * A quantity change is saved as a ledgered, audited adjustment (a reason is
 * required) and is refused below what active orders have reserved. The
 * threshold is optional configuration (blank clears it).
 */
export function EditStockModal({
  stock,
  pointName,
  ownerName,
  onClose,
}: {
  stock: StockRow | null;
  pointName: string;
  /** Shown for distributor-owned rows so it is clear whose stock is edited. */
  ownerName?: string;
  onClose: () => void;
}): JSX.Element | null {
  const { setQuantity, setThreshold } = useStockMutation();
  const { toast } = useAlerts();
  const [quantity, setQty] = useState<string | null>(null);
  const [threshold, setThresholdValue] = useState<string | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  if (!stock) return null;

  const current = Number(stock.quantity);
  const currentThreshold = stock.low_stock_threshold != null ? String(Number(stock.low_stock_threshold)) : "";
  const qtyValue = quantity ?? String(current);
  const thresholdValue = threshold ?? currentThreshold;

  const quantityChanged = qtyValue.trim() !== "" && Number(qtyValue) !== current;
  const thresholdChanged = thresholdValue.trim() !== currentThreshold;
  const valid =
    qtyValue.trim() !== "" &&
    Number(qtyValue) >= 0 &&
    (thresholdValue.trim() === "" || Number(thresholdValue) >= 0) &&
    (quantityChanged ? reason.trim() !== "" : thresholdChanged);

  const busy = setQuantity.isPending || setThreshold.isPending;

  function close(): void {
    setQty(null);
    setThresholdValue(null);
    setReason("");
    setError(null);
    onClose();
  }

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    if (!stock) return;
    setError(null);
    try {
      if (quantityChanged) {
        await setQuantity.mutateAsync({ stockId: stock.id, quantity: Number(qtyValue), reason: reason.trim() });
      }
      if (thresholdChanged) {
        await setThreshold.mutateAsync({
          stockId: stock.id,
          threshold: thresholdValue.trim() === "" ? null : Number(thresholdValue),
        });
      }
      toast({
        title: "Stock updated",
        message: quantityChanged
          ? `${stock.product?.name ?? "Product"}: ${current} → ${Number(qtyValue)}`
          : `${stock.product?.name ?? "Product"}: threshold updated`,
        tone: "success",
      });
      close();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <Modal
      open
      onClose={close}
      title="Edit stock"
      description={`${ownerName ? `${ownerName} · ` : ""}${stock.product?.name ?? `Product #${stock.product_id}`} at ${pointName}. Currently ${current} on hand.`}
      footer={
        <ModalFormFooter
          formId="stock-edit-form"
          submitLabel="Save"
          busy={busy}
          disabled={!valid}
          onCancel={close}
        />
      }
    >
      <form id="stock-edit-form" onSubmit={submit} className="space-y-3">
        <Field label="New quantity" type="number" step="any" required value={qtyValue} onChange={setQty} />
        <Field
          label={quantityChanged ? "Reason" : "Reason (needed when changing quantity)"}
          required={quantityChanged}
          value={reason}
          onChange={setReason}
        />
        <Field
          label="Low-stock threshold (blank = none)"
          type="number"
          step="any"
          value={thresholdValue}
          onChange={setThresholdValue}
        />
        {error ? (
          <p className="text-sm text-danger" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
