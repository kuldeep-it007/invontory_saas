<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates creation of a buyer-side PANEL login user for a Distributor or a
 * Dealer (R7.1, R8.6, R10.3).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:users.manage` + `subscription.active`). The owning party is the
 * route-model-bound `{distributor}` / `{dealer}` parameter, so it is not part
 * of the body — only the login identity is supplied here.
 *
 * `email` must be unique within the active tenant's `users` table (the
 * per-tenant login identity, R7.1); uniqueness is per-tenant automatically
 * because the rule runs against the tenant connection's `users` table.
 */
class StorePartyUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            // A strong password, stored hashed by the User model's cast (R7.3).
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
