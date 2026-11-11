<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates checkout of a database-backed cart into an Order (R20.3, R20.4).
 *
 * Unlike {@see SubmitCartRequest} (which carries the lines inline), the cart's
 * lines already live in the database, so only the owning Buyer and optional
 * order type are needed. Authorization is handled by the route pipeline
 * (`permission:orders.create`).
 */
class SubmitCartViaCartRequest extends FormRequest
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
            'buyer_type' => ['required', 'string', Rule::in(['distributor', 'dealer'])],
            'buyer_id' => ['required', 'integer'],
            'order_type' => ['sometimes', Rule::in(['standard', 'blanket', 'advance', 'depot'])],
        ];
    }
}
