<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Dealer;
use App\Models\Distributor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the `GET /catalog` buyer selector (R11.4, R11.5, R11.6).
 *
 * The catalog shown is scoped to a Buyer — a Distributor or a Dealer — whose
 * active product attributes drive visibility. The Buyer is identified by a
 * `buyer_type`/`buyer_id` pair resolved against the active tenant database.
 * Both are optional: when neither is supplied the endpoint returns the full
 * tenant catalog, matching the "no restriction configured ⇒ show everything"
 * rule (R11.6).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:catalog.view`), so this request only validates the payload.
 */
class CatalogVisibilityRequest extends FormRequest
{
    /**
     * Short aliases accepted on the wire for the polymorphic Buyer type, mapped
     * to the concrete model used as the morph class.
     *
     * @var array<string, class-string>
     */
    public const BUYER_TYPES = [
        'distributor' => Distributor::class,
        'dealer' => Dealer::class,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Both buyer fields are optional, but if either is present the other
            // is required so a Buyer can always be resolved unambiguously.
            'buyer_type' => [
                'required_with:buyer_id',
                'nullable',
                'string',
                Rule::in(array_keys(self::BUYER_TYPES)),
            ],
            'buyer_id' => [
                'required_with:buyer_type',
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * Resolve the concrete Buyer morph class for the request, or null when no
     * Buyer selector was supplied (R11.6 "show everything").
     *
     * @return class-string|null
     */
    public function buyerClass(): ?string
    {
        $type = $this->validated()['buyer_type'] ?? null;

        return $type !== null ? self::BUYER_TYPES[$type] : null;
    }
}
