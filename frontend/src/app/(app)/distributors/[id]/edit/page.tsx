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
import { useDistributor, useUpdateDistributor } from "@/lib/api/hooks";
import { useBack } from "@/lib/use-back";

/**
 * Edit a distributor (R10.5) — a full page instead of a modal, so the complete
 * Rosetta-style profile (contact, logistics, GST, income-tax, credit) has room
 * to breathe. Loads the record, hydrates the shared party form, and PATCHes on
 * save; on success it returns to the distributor's dealers page.
 */
export default function EditDistributorPage(): JSX.Element {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();

  const goBack = useBack(`/distributors/${id}`);
  const { data, isLoading, error } = useDistributor(id);
  const update = useUpdateDistributor(id);
  const { form, set, setForm } = usePartyForm();
  const [saveError, setSaveError] = useState<string | null>(null);
  const [hydrated, setHydrated] = useState(false);

  // Populate the form once the record arrives.
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
      router.push(`/distributors/${id}`);
    } catch (err) {
      setSaveError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Edit distributor"
        subtitle="Update contact, logistics, tax, and credit details"
        actions={
          <Button variant="outline" onClick={goBack}>
            <Icon.chevronLeft size={16} /> Back
          </Button>
        }
      />

      {isLoading ? (
        <StateMessage>Loading distributor…</StateMessage>
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
