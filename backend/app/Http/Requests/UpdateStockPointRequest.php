<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates updating a Company stock point's details (R13.4, R13.8).
 *
 * Authorization is handled by the route pipeline (`permission:inventory.adjust`
 * + `subscription.active`); ownership (the point belongs to the active tenant's
 * Company) is asserted in {@see \App\Services\StockPointService::update()}. A
 * partial payload is accepted so the name or sort order may be updated alone.
 */
class UpdateStockPointRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
