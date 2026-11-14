<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CustomDomainService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for mapping a custom domain. Host normalisation, the
 * reserved-host rules and uniqueness are enforced by
 * {@see CustomDomainService}; authorization by the route.
 */
class StoreCustomDomainRequest extends FormRequest
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
            'domain' => ['required', 'string', 'max:300'],
        ];
    }
}
