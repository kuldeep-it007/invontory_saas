<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to configure the low-stock threshold on a stock row
 * (R19.1).
 *
 * The `low_stock_threshold` is REQUIRED but NULLABLE: the field must be present
 * so the intent is explicit (set a value, or clear it with null). When a value
 * is given it must be a non-negative number within the DECIMAL(15,3) range the
 * `stock` column stores — a threshold is a quantity, so it cannot be negative,
 * and the fractional scale matches the stored quantity precision so the
 * at-or-below comparison stays exact (R13.3). A null value clears the
 * threshold, meaning the row can no longer breach (R19.2).
 */
class SetLowStockThresholdRequest extends FormRequest
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
        return [
            // Present-but-nullable: `null` clears the threshold; a value must be
            // a non-negative number inside DECIMAL(15,3)'s magnitude.
            'low_stock_threshold' => ['present', 'nullable', 'numeric', 'min:0', 'max:999999999999.999'],
        ];
    }
}
