<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the order-history listing query (R20, R33).
 *
 * The listing is scoped by an optional party context:
 *   • no party               → the Company's full history (every order);
 *   • party + scope=buyer    → orders that party PLACED (buyer = party);
 *   • party + scope=seller   → orders placed AGAINST that party (seller = party).
 *
 * Authorization is handled by the route pipeline (`permission:orders.view`).
 */
class ListOrdersRequest extends FormRequest
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
            'scope' => ['sometimes', Rule::in(['buyer', 'seller'])],
            'party_type' => ['sometimes', 'nullable', Rule::in(['company', 'distributor', 'dealer'])],
            'party_id' => ['sometimes', 'nullable', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            // Optional lifecycle-status filter: one status or a comma-separated
            // list (e.g. `confirmed` or `confirmed,dispatched,delivered,closed`).
            'status' => [
                'sometimes',
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (explode(',', (string) $value) as $status) {
                        if (! OrderStatus::isValid(trim($status))) {
                            $fail('The selected status is invalid.');

                            return;
                        }
                    }
                },
            ],
            'order_type' => ['sometimes', 'nullable', Rule::in([
                Order::TYPE_STANDARD,
                Order::TYPE_BLANKET,
                Order::TYPE_ADVANCE,
                Order::TYPE_DEPOT,
            ])],
        ];
    }
}
