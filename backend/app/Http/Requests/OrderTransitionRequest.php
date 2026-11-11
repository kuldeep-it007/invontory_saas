<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a `POST /orders/{id}/transition` request (R21.2, R21.4).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum → RBAC), so
 * this request only validates the payload. The sole input is the lifecycle
 * `event` to apply (e.g. `submit`, `approve`, `reject`, `edit`, `confirm`,
 * `dispatch`, `deliver`, `close`, `cancel`). Whether the event is actually
 * legal from the order's CURRENT state is decided by
 * {@see \App\Services\OrderStateMachine} — an event that is well-formed but
 * undefined for the current state is rejected there with a 422 (R21.4). This
 * request only guarantees a non-empty string event reached the controller.
 */
class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the event before validation so a whitespace-only event is seen
     * as empty and rejected with a 422.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('event') && is_string($this->input('event'))) {
            $this->merge(['event' => trim($this->input('event'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event.required' => 'A transition event is required.',
            'event.min' => 'A transition event is required.',
        ];
    }
}
