"use client";

import { useMemo, useState, type FormEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { Field, Select } from "@/components/ui/form";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { errorMessage } from "@/lib/api/errors";
import { useProductOptions, useStockMutation, useStockPoints } from "@/lib/api/hooks";

/**
 * Stock movement actions (inward / adjust / transfer), extracted so both the
 * Stocks Management page and any other surface can reuse the same modals with
 * product/stock-point DROPDOWNS everywhere (no raw IDs). Each mutation routes
 * through StockMovementService server-side. Pass `only` to render a subset.
 */

export type Option = { value: string; label: string };

/** Loads product + stock-point dropdown options for the movement modals. */
export function useMovementOptions(): { productOptions: Option[]; pointOptions: Option[] } {
  const points = useStockPoints();
  const productOptions = useProductOptions();

  const productOpts = (productOptions.data?.data ?? []).map((p) => ({
    value: String(p.id),
    label: `${p.name} (${p.sku})`,
  }));
  const pointOpts = (points.data?.data ?? []).map((p) => ({
    value: String(p.id),
    label: p.name,
  }));

  return { productOptions: productOpts, pointOptions: pointOpts };
}

type ActiveModal = "inward" | "adjust" | "transfer" | null;

export function StockActions({
  productOptions,
  pointOptions,
}: {
  productOptions: Option[];
  pointOptions: Option[];
}): JSX.Element {
  const { inward, adjust, transfer } = useStockMutation();
  const { toast } = useAlerts();
  const [active, setActive] = useState<ActiveModal>(null);
  const close = () => setActive(null);

  return (
    <div className="flex flex-wrap gap-2">
      <Button variant="secondary" onClick={() => setActive("inward")}>
        Stock inward
      </Button>
      <Button variant="secondary" onClick={() => setActive("adjust")}>
        Adjust
      </Button>
      <Button variant="secondary" onClick={() => setActive("transfer")}>
        Transfer
      </Button>

      <MovementModal
        open={active === "inward"}
        onClose={close}
        formId="stock-inward-form"
        title="Stock inward"
        description="Assign / receive stock into a stock point."
        busy={inward.isPending}
        onSubmit={async (v) => {
          await inward.mutateAsync({
            product_id: Number(v.product_id),
            stock_point_id: Number(v.stock_point_id),
            quantity: Number(v.quantity),
            type: "received",
          });
          toast({ title: "Stock received", tone: "success" });
        }}
        fields={[
          { key: "product_id", label: "Product", type: "select", options: productOptions, required: true },
          { key: "stock_point_id", label: "Stock point", type: "select", options: pointOptions, required: true },
          { key: "quantity", label: "Quantity", type: "number", required: true },
        ]}
      />

      <MovementModal
        open={active === "adjust"}
        onClose={close}
        formId="stock-adjust-form"
        title="Manual adjustment"
        description="Correct a quantity (+/-). Reason is required."
        busy={adjust.isPending}
        onSubmit={async (v) => {
          await adjust.mutateAsync({
            product_id: Number(v.product_id),
            stock_point_id: Number(v.stock_point_id),
            quantity: Number(v.quantity),
            reason: v.reason,
          });
          toast({ title: "Stock adjusted", tone: "success" });
        }}
        fields={[
          { key: "product_id", label: "Product", type: "select", options: productOptions, required: true },
          { key: "stock_point_id", label: "Stock point", type: "select", options: pointOptions, required: true },
          { key: "quantity", label: "Delta (+/-)", type: "number", required: true },
          { key: "reason", label: "Reason", type: "text", required: true },
        ]}
      />

      <MovementModal
        open={active === "transfer"}
        onClose={close}
        formId="stock-transfer-form"
        title="Transfer"
        description="Move stock between points of the same owner."
        busy={transfer.isPending}
        onSubmit={async (v) => {
          await transfer.mutateAsync({
            product_id: Number(v.product_id),
            source_stock_point_id: Number(v.source_stock_point_id),
            destination_stock_point_id: Number(v.destination_stock_point_id),
            quantity: Number(v.quantity),
          });
          toast({ title: "Stock transferred", tone: "success" });
        }}
        fields={[
          { key: "product_id", label: "Product", type: "select", options: productOptions, required: true },
          { key: "source_stock_point_id", label: "From point", type: "select", options: pointOptions, required: true },
          { key: "destination_stock_point_id", label: "To point", type: "select", options: pointOptions, required: true },
          { key: "quantity", label: "Quantity", type: "number", required: true },
        ]}
      />
    </div>
  );
}

interface MovementField {
  key: string;
  label: string;
  type: "text" | "number" | "select";
  options?: Option[];
  required?: boolean;
}

function MovementModal({
  open,
  onClose,
  formId,
  title,
  description,
  fields,
  busy,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  formId: string;
  title: string;
  description: string;
  fields: MovementField[];
  busy: boolean;
  onSubmit: (values: Record<string, string>) => Promise<unknown>;
}): JSX.Element {
  const [values, setValues] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);

  function reset() {
    setValues({});
    setError(null);
  }

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    try {
      await onSubmit(values);
      reset();
      onClose();
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const valid = useMemo(
    () => fields.every((f) => !f.required || (values[f.key] ?? "").trim() !== ""),
    [fields, values],
  );

  const set = (key: string) => (v: string) => setValues((prev) => ({ ...prev, [key]: v }));

  return (
    <Modal
      open={open}
      onClose={() => {
        reset();
        onClose();
      }}
      title={title}
      description={description}
      footer={
        <ModalFormFooter
          formId={formId}
          submitLabel="Submit"
          busy={busy}
          disabled={!valid}
          onCancel={() => {
            reset();
            onClose();
          }}
        />
      }
    >
      <form id={formId} onSubmit={submit} className="space-y-3">
        {fields.map((f) =>
          f.type === "select" ? (
            <Select key={f.key} label={f.label} value={values[f.key] ?? ""} onChange={set(f.key)} options={f.options ?? []} />
          ) : (
            <Field
              key={f.key}
              label={f.label}
              type={f.type}
              step={f.type === "number" ? "any" : undefined}
              required={f.required}
              value={values[f.key] ?? ""}
              onChange={set(f.key)}
            />
          ),
        )}
        {error ? (
          <p className="text-sm text-danger" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
