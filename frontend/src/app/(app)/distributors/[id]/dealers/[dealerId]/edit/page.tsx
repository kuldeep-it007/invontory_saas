"use client";

import { useParams, useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";

import {
  PartyProfileFields,
  partyFormFromRecord,
  toPartyPayload,
  usePartyForm,
} from "@/components/parties/party-form";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { Card, ListPageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useDealer, useUpdateDealer } from "@/lib/api/hooks";
import { useBack } from "@/lib/use-back";

/**
 * Edit a dealer under a distributor (R10.5) — a full page (not a modal) that
 * reuses the same party form distributors use, so dealers carry identical
 * contact, logistics, tax, and credit details. On save it returns to the
 * distributor's dealers list.
 */
export default function EditDealerPage(): JSX.Element {
  const params = useParams<{ id: string; dealerId: string }>();
  const distributorId = Number(params.id);
  const dealerId = Number(params.dealerId);
  const router = useRouter();
  const backHref = `/distributors/${distributorId}`;
  const goBack = useBack(backHref);

  const { data, isLoading, error } = useDealer(distributorId, dealerId);
  const update = useUpdateDealer(distributorId, dealerId);
  const { form, set, setForm } = usePartyForm();
  const [saveError, setSaveError] = useState<string | null>(null);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    if (data?.data && !hydrated) {
      setForm(partyFormFromRecord(data.data));
      setHydrated(true);
    }
  }, [data, hydrated, setForm]);

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setSaveError(null);
    try {
      await update.mutateAsync(toPartyPayload(form));
      router.push(backHref);
    } catch (err) {
      setSaveError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Edit dealer"
        subtitle="Update contact, logistics, tax, and credit details"
        actions={
          <Button variant="outline" onClick={goBack}>
            <Icon.chevronLeft size={16} /> Back
          </Button>
        }
      />

      {isLoading ? (
        <StateMessage>Loading dealer…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : (
        <Card>
          <form onSubmit={submit} className="space-y-6">
            <PartyProfileFields form={form} set={set} />
            {saveError ? (
              <p className="text-sm text-danger" role="alert">
                {saveError}
              </p>
            ) : null}
            <div className="flex justify-end gap-2 border-t border-border pt-4">
              <Button type="button" variant="ghost" onClick={goBack}>
                Cancel
              </Button>
              <Button type="submit" disabled={update.isPending || !form.name}>
                {update.isPending ? "Saving…" : "Save changes"}
              </Button>
            </div>
          </form>
        </Card>
      )}
    </div>
  );
}
