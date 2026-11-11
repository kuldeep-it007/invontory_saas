<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates an administrative password reset for a buyer-side PANEL login user
 * of a Distributor or a Dealer (R7.3, R8.6, R10.3).
 *
 * Authorization is handled by the route pipeline:
 *   • a distributor user's password may be reset by the Company
 *     (`permission:users.manage`);
 *   • a dealer user's password may be reset by the Company OR by the owning
 *     Distributor (`permission:users.manage|dealers.manage`).
 *
 * The target user is the route-model-bound `{user}` parameter, and the
 * controller asserts the user actually belongs to the addressed party before
 * the service re-hashes the new password, so only the new secret is supplied in
 * the body. There is no "current password" field: this is an admin-initiated
 * reset, not a self-service change.
 */
class ResetPartyUserPasswordRequest extends FormRequest
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
            // A strong password, stored hashed by the User model's cast (R7.3).
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
