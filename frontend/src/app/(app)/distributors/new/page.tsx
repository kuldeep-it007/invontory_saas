"use client";

import { useRouter } from "next/navigation";
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
import { useCreateDistributor } from "@/lib/api/hooks";
import { useBack } from "@/lib/use-back";

/**
 * Create a distributor (R10.1) — a full page instead of a modal, matching the
 * edit flow so the complete Rosetta-style profile has room to breathe. On
 * success it navigates to the new distributor's dealers page; Back/Cancel
 * return to wherever the user came from (falling back to the list).
 */
export default function NewDistributorPage(): JSX.Element {
  const router = useRouter();
  const goBack = useBack("/distributors");
  const create = useCreateDistributor();
  const { form, set } = usePartyForm();
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    try {
      const res = await create.mutateAsync(toPartyPayload(form));
      router.push(`/distributors/${res.data.id}`);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <ListPageHeader
        title="Add distributor"
        subtitle="Capture contact, logistics, tax, and credit details"
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
              {create.isPending ? "Creating…" : "Create distributor"}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
