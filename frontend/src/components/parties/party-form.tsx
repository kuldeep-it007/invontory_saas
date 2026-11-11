"use client";

import { useState } from "react";

import { Field, Select } from "@/components/ui/form";
import type { PartyProfile } from "@/lib/api/types";

/**
 * Shared "party" (Distributor / Dealer) detail form (R10.1, R10.2).
 *
 * Distributors and Dealers carry the identical Rosetta-style profile, so the
 * field set lives here and is reused by both create/edit modals. The form is
 * grouped into the same sections as the reference design: Contact, Logistics &
 * Terms, Tax (GST + Income Tax), and Credit Details.
 *
 * State is held as a flat record and converted to the API payload shape
 * (top-level contact fields + nested `profile` + `payment_terms`) by
 * {@link toPartyPayload}.
 */

export interface PartyFormState {
  name: string;
  contact_name: string;
  contact_email: string;
  contact_phone: string;
  fax: string;
  address: string;
  // Logistics & terms
  territory_code: string;
  billing_location_code: string;
  shipment_method: string;
  shipping_advice: string;
  reserve: string;
  // GST
  gst_customer_type: string;
  gst_registration_type: string;
  gst_registration_no: string;
  // Income tax
  pan_status: string;
  pan_no: string;
  pan_reference_no: string;
  // Credit
  credit_limit: string;
}

export const EMPTY_PARTY_FORM: PartyFormState = {
  name: "",
  contact_name: "",
  contact_email: "",
  contact_phone: "",
  fax: "",
  address: "",
  territory_code: "",
  billing_location_code: "",
  shipment_method: "",
  shipping_advice: "",
  reserve: "",
  gst_customer_type: "",
  gst_registration_type: "",
  gst_registration_no: "",
  pan_status: "",
  pan_no: "",
  pan_reference_no: "",
  credit_limit: "",
};

/** Small hook to manage the flat party form state. */
export function usePartyForm(initial: PartyFormState = EMPTY_PARTY_FORM) {
  const [form, setForm] = useState<PartyFormState>(initial);
  const set = (key: keyof PartyFormState) => (v: string) => setForm((p) => ({ ...p, [key]: v }));
  const reset = () => setForm(EMPTY_PARTY_FORM);
  return { form, set, reset, setForm };
}

/**
 * The subset of a Distributor/Dealer record this form reads from. Kept
 * structural so both {@link Distributor} and {@link Dealer} satisfy it.
 */
export interface PartyRecord {
  name: string;
  contact_name?: string | null;
  contact_email?: string | null;
  contact_phone?: string | null;
  fax?: string | null;
  address?: string | null;
  profile?: PartyProfile | null;
  payment_terms?: { credit_limit?: number | string | null } | null;
}

/** Hydrate the flat form state from an existing party record (edit flow). */
export function partyFormFromRecord(r: PartyRecord): PartyFormState {
  const p = r.profile ?? {};
  const str = (v: unknown): string => (v === null || v === undefined ? "" : String(v));
  return {
    name: r.name ?? "",
    contact_name: str(r.contact_name),
    contact_email: str(r.contact_email),
    contact_phone: str(r.contact_phone),
    fax: str(r.fax),
    address: str(r.address),
    territory_code: str(p.territory_code),
    billing_location_code: str(p.billing_location_code),
    shipment_method: str(p.shipment_method),
    shipping_advice: str(p.shipping_advice),
    reserve: str(p.reserve),
    gst_customer_type: str(p.gst_customer_type),
    gst_registration_type: str(p.gst_registration_type),
    gst_registration_no: str(p.gst_registration_no),
    pan_status: str(p.pan_status),
    pan_no: str(p.pan_no),
    pan_reference_no: str(p.pan_reference_no),
    credit_limit: str(r.payment_terms?.credit_limit),
  };
}

/** Option lists mirror the backend `in:` validation for the enumerated fields. */
const SHIPPING_ADVICE = ["Full", "Partial", "As Agreed"];
const RESERVE = ["Optional", "Mandatory", "None"];
const GST_CUSTOMER_TYPE = ["Registered", "Unregistered", "Composition", "SEZ", "Overseas"];
const GST_REGISTRATION_TYPE = ["GSTIN", "UIN", "Other"];
const PAN_STATUS = ["PAN Available", "PAN Applied", "Not Available"];

const opts = (values: string[]) => values.map((v) => ({ value: v, label: v }));

/**
 * Convert the flat form state into the API payload: top-level contact columns,
 * a nested `profile` object, and `payment_terms.credit_limit`. Empty strings
 * are sent as null so unspecified fields clear cleanly.
 */
export function toPartyPayload(form: PartyFormState): Record<string, unknown> {
  const orNull = (v: string) => (v.trim() === "" ? null : v.trim());
  const profile: PartyProfile = {
    territory_code: orNull(form.territory_code),
    billing_location_code: orNull(form.billing_location_code),
    shipment_method: orNull(form.shipment_method),
    shipping_advice: orNull(form.shipping_advice),
    reserve: orNull(form.reserve),
    gst_customer_type: orNull(form.gst_customer_type),
    gst_registration_type: orNull(form.gst_registration_type),
    gst_registration_no: orNull(form.gst_registration_no),
    pan_status: orNull(form.pan_status),
    pan_no: orNull(form.pan_no),
    pan_reference_no: orNull(form.pan_reference_no),
  };

  return {
    name: form.name,
    contact_name: orNull(form.contact_name),
    contact_email: orNull(form.contact_email),
    contact_phone: orNull(form.contact_phone),
    fax: orNull(form.fax),
    address: orNull(form.address),
    // Keep gstin in sync with the GST registration number for back-compat.
    gstin: orNull(form.gst_registration_no),
    profile,
    payment_terms: {
      credit_limit: form.credit_limit.trim() === "" ? null : Number(form.credit_limit),
    },
  };
}

function SectionTitle({ children }: { children: string }): JSX.Element {
  return (
    <h3 className="col-span-full border-b border-border pb-1 text-sm font-semibold text-foreground">
      {children}
    </h3>
  );
}

/**
 * The full party detail fieldset. Rendered inside a `<form>` owned by the
 * caller (the modal), so it only emits grouped fields in a responsive grid.
 */
export function PartyProfileFields({
  form,
  set,
}: {
  form: PartyFormState;
  set: (key: keyof PartyFormState) => (v: string) => void;
}): JSX.Element {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <SectionTitle>Contact</SectionTitle>
      <Field label="Name" required value={form.name} onChange={set("name")} />
      <Field label="Contact person" value={form.contact_name} onChange={set("contact_name")} />
      <Field label="Email" type="email" value={form.contact_email} onChange={set("contact_email")} />
      <Field label="Phone" value={form.contact_phone} onChange={set("contact_phone")} />
      <Field label="Fax" value={form.fax} onChange={set("fax")} />
      <Field label="Address" value={form.address} onChange={set("address")} />

      <SectionTitle>Logistics &amp; Terms</SectionTitle>
      <Field label="Territory code" value={form.territory_code} onChange={set("territory_code")} />
      <Field label="Billing location code" value={form.billing_location_code} onChange={set("billing_location_code")} />
      <Field label="Shipment method" value={form.shipment_method} onChange={set("shipment_method")} />
      <Select label="Shipping advice" value={form.shipping_advice} onChange={set("shipping_advice")} options={opts(SHIPPING_ADVICE)} />
      <Select label="Reserve" value={form.reserve} onChange={set("reserve")} options={opts(RESERVE)} />

      <SectionTitle>Tax — GST</SectionTitle>
      <Select label="GST customer type" value={form.gst_customer_type} onChange={set("gst_customer_type")} options={opts(GST_CUSTOMER_TYPE)} />
      <Select label="GST registration type" value={form.gst_registration_type} onChange={set("gst_registration_type")} options={opts(GST_REGISTRATION_TYPE)} />
      <Field label="GST registration no." value={form.gst_registration_no} onChange={set("gst_registration_no")} />

      <SectionTitle>Tax — Income Tax</SectionTitle>
      <Select label="P.A.N. status" value={form.pan_status} onChange={set("pan_status")} options={opts(PAN_STATUS)} />
      <Field label="P.A.N. no." value={form.pan_no} onChange={set("pan_no")} />
      <Field label="P.A.N. reference no." value={form.pan_reference_no} onChange={set("pan_reference_no")} />

      <SectionTitle>Credit Details</SectionTitle>
      <Field label="Credit limit" type="number" value={form.credit_limit} onChange={set("credit_limit")} />
    </div>
  );
}
