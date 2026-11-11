<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Shared validation rules for the Rosetta-style party profile carried by both
 * Distributors and Dealers (R10.1, R10.2).
 *
 * The profile groups contact (fax), logistics/terms (territory, billing
 * location, shipment method, shipping advice, reserve), GST registration, and
 * Income-Tax (PAN) details. All fields are optional so the same rule set works
 * for both create and partial-update requests. Enumerated fields are validated
 * against the allowed option lists so the stored data stays consistent with the
 * form's dropdowns.
 */
trait PartyProfileRules
{
    /**
     * The party profile validation rules, nested under the `profile` key.
     *
     * @return array<string, mixed>
     */
    protected function partyProfileRules(): array
    {
        return [
            'fax' => ['sometimes', 'nullable', 'string', 'max:32'],

            'profile' => ['sometimes', 'nullable', 'array'],

            // Logistics / terms
            'profile.territory_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'profile.billing_location_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'profile.shipment_method' => ['sometimes', 'nullable', 'string', 'max:64'],
            'profile.shipping_advice' => ['sometimes', 'nullable', 'string', 'in:Full,Partial,As Agreed'],
            'profile.reserve' => ['sometimes', 'nullable', 'string', 'in:Optional,Mandatory,None'],

            // GST
            'profile.gst_customer_type' => ['sometimes', 'nullable', 'string', 'in:Registered,Unregistered,Composition,SEZ,Overseas'],
            'profile.gst_registration_type' => ['sometimes', 'nullable', 'string', 'in:GSTIN,UIN,Other'],
            'profile.gst_registration_no' => ['sometimes', 'nullable', 'string', 'max:32'],

            // Income Tax
            'profile.pan_status' => ['sometimes', 'nullable', 'string', 'in:PAN Available,PAN Applied,Not Available'],
            'profile.pan_no' => ['sometimes', 'nullable', 'string', 'max:16'],
            'profile.pan_reference_no' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
