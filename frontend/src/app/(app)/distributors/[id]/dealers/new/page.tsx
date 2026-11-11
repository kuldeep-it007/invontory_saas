"use client";

import { useParams, useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

import {
  PartyProfileFields,
  toPartyPayload,
  usePartyForm,
} from "@/components/parties/party-form";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { Card, ListPageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useCreateDealer } from "@/lib/api/hooks";
import { useBack } from "@/lib/use-back";

/**
 * Create a dealer under a distributor (R10.2) — a full page (not a modal) that
 * reuses the same party form distributors use, so dealers carry identical
 * contact, logistics, tax, and credit details. On success it returns to the
 * distributor's dealers list; Back/Cancel return to wherever the user came
 * from (falling back to that list).
 */
export default function NewDealerPage(): JSX.Element {
  const params = useParams<{ id: string }>();
  const distributorId = Number(params.id);
  const router = useRouter();
  const backHref = `/distributors/${distributorId}`;
  const goBack = useBack(backHref);

  const create = useCreateDealer(distributorId);
  const { form, set } = usePartyForm();
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    try {
      await create.mutateAsync(toPartyPayload(form));
      router.push(backHref);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Add dealer"
        subtitle="Dealers carry the same contact, logistics, tax, and credit details as distributors"
        actions={
          <Button variant="outline" onClick={goBack}>
            <Icon.chevronLeft size={16} /> Back
          </Button>
        }
      />

      <Card>
        <form onSubmit={submit} className="space-y-6">
          <PartyProfileFields form={form} set={set} />
          {error ? (
            <p className="text-sm text-danger" role="alert">
              {error}
            </p>
          ) : null}
          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <Button type="button" variant="ghost" onClick={goBack}>
              Cancel
            </Button>
            <Button type="submit" disabled={create.isPending || !form.name}>
              {create.isPending ? "Creating…" : "Create dealer"}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
