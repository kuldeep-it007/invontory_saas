<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a manual stock-adjustment request (R14.3, R14.4).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:inventory.adjust` → `subscription.active`), so this request only
 * validates the payload.
 *
 * A manual adjustment MUST carry a non-empty, non-whitespace reason (R14.3); an
 * adjustment submitted without one is rejected with an HTTP 422 (R14.4). The
 * `required` + `string` + a trim-aware `min:1` on the trimmed value together
 * reject both an absent reason and a whitespace-only reason. The signed
 * `quantity` (positive to increase, negative to decrease) is a DECIMAL(15,3)
 * value and may not be zero (a zero adjustment is a no-op).
 */
class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the reason before validation so a whitespace-only reason is
     * seen as empty and rejected with a 422 (R14.4).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:tenant.products,id'],
            'stock_point_id' => ['required', 'integer', 'exists:tenant.stock_points,id'],
            // Signed DECIMAL(15,3); non-zero so an adjustment always moves stock.
            'quantity' => ['required', 'numeric', 'not_in:0', 'decimal:0,3'],
            // A non-empty, non-whitespace reason is required (R14.3). After the
            // trim in prepareForValidation, an empty/whitespace reason fails
            // `required`/`min:1` and returns a 422 (R14.4).
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A manual stock adjustment requires a non-empty reason.',
            'reason.min' => 'A manual stock adjustment requires a non-empty reason.',
        ];
    }
}
