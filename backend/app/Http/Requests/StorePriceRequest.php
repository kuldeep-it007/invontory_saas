<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Price;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a price-creation request (R12.1, R12.2).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:catalog.manage`), so this request only validates the payload.
 * The `level` is constrained to `product`/`collection` (R12.1) and the
 * `markup_type`, when present, to `markup`/`mup` (R12.2) — mirroring the
 * database enum and model constants. `ref_id` must reference an existing
 * Product or Collection in the active tenant database depending on `level`.
 */
class StorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $level = $this->input('level');
        $refTable = $level === Price::LEVEL_PRODUCT ? 'tenant.products' : 'tenant.collections';

        return [
            'level' => ['required', 'string', Rule::in(Price::LEVELS)],
            'ref_id' => ['required', 'integer', Rule::exists($refTable, 'id')],
            'amount' => ['required', 'numeric', 'min:0'],
            'markup_type' => ['sometimes', 'nullable', Rule::in(Price::MARKUP_TYPES)],
            'markup_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
