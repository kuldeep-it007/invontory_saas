"use client";

import { useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Field, Select } from "@/components/ui/form";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { errorMessage } from "@/lib/api/errors";
import { useDistributorOptions, useProductOptions, useStockMutation } from "@/lib/api/hooks";

/**
 * Add stock held by a distributor. The stock is owned by the chosen distributor
 * (its own stock point and ledger), so it stays separate from Company stock.
 */
export function AddDistributorStockModal({
  open,
  onClose,
  defaultDistributorId,
  fixedDistributorId,
}: {
  open: boolean;
  onClose: () => void;
  defaultDistributorId?: string;
  /** A distributor managing its OWN stock: no choice, the stock is always its own. */
  fixedDistributorId?: number;
}): JSX.Element | null {
  const distributors = useDistributorOptions();
  const products = useProductOptions();
  const { addDistributorStock } = useStockMutation();
  const { toast } = useAlerts();

  const [distributorId, setDistributorId] = useState<string | null>(null);
  const [productId, setProductId] = useState("");
  const [quantity, setQuantity] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  const distributor = fixedDistributorId != null ? String(fixedDistributorId) : (distributorId ?? defaultDistributorId ?? "");
  const valid = distributor !== "" && productId !== "" && Number(quantity) > 0;

  function close(): void {
    setDistributorId(null);
    setProductId("");
    setQuantity("");
    setReason("");
    setError(null);
    onClose();
  }

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    try {
      await addDistributorStock.mutateAsync({
        distributor_id: Number(distributor),
        product_id: Number(productId),
        quantity: Number(quantity),
        ...(reason.trim() ? { reason: reason.trim() } : {}),
      });
      toast({ title: "Distributor stock added", tone: "success" });
      close();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Add distributor stock"
      description="Assign stock to a distributor. It is held by the distributor and does not change Company stock."
      footer={
        <ModalFormFooter
          formId="distributor-stock-add-form"
          submitLabel="Add stock"
          busy={addDistributorStock.isPending}
          disabled={!valid}
          onCancel={close}
        />
      }
    >
      <form id="distributor-stock-add-form" onSubmit={submit} className="space-y-3">
        {fixedDistributorId != null ? null : (
          <Select
            label="Distributor"
            value={distributor}
            onChange={setDistributorId}
            options={(distributors.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))}
          />
        )}
        <Select
          label="Product"
          value={productId}
          onChange={setProductId}
          options={(products.data?.data ?? []).map((p) => ({ value: String(p.id), label: `${p.name} (${p.sku})` }))}
        />
        <Field label="Quantity" type="number" step="any" required value={quantity} onChange={setQuantity} />
        <Field label="Reason (optional)" value={reason} onChange={setReason} />
        {error ? (
          <p className="text-sm text-danger" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
