<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a colour-creation request (R11.2). The parent collection must
 * exist in the active tenant database. Authorization is handled by the route
 * pipeline (`permission:catalog.manage`).
 */
class StoreColourRequest extends FormRequest
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
            'collection_id' => ['required', 'integer', 'exists:tenant.collections,id'],
            'code' => ['sometimes', 'nullable', 'string', 'max:64', 'unique:tenant.colours,code'],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
