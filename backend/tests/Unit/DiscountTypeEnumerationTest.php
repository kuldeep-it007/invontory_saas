<?php

declare(strict_types=1);

use App\Http\Requests\StoreDiscountRequest;
use App\Models\Discount;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;

/*
|--------------------------------------------------------------------------
| Unit: Discount-type enumeration rejection (R12.3)
|--------------------------------------------------------------------------
|
| The platform supports EXACTLY two discount types — `customer` and `online`
| (R12.3). StoreDiscountRequest constrains the `type` field to that allow-list
| via `Rule::in(Discount::TYPES)`, so any other type fails validation and the
| HTTP layer answers with a 422. These checks run the request's own rules()
| through a plain validator (no booted app or database): the allowed types
| pass, every other candidate is rejected on the `type` field.
*/

/**
 * Build a validator for the given payload using StoreDiscountRequest's own
 * rules(), backed by a standalone translator so no application boot is needed.
 */
function validateDiscount(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $translator = new Translator(new ArrayLoader(), 'en');
    $factory = new ValidationFactory($translator);
    $rules = (new StoreDiscountRequest())->rules();

    return $factory->make($payload, $rules);
}

it('exposes customer and online as the only two discount types (R12.3)', function () {
    expect(Discount::TYPES)->toBe(['customer', 'online']);
});

it('accepts the customer discount type (R12.3)', function () {
    $validator = validateDiscount(['type' => Discount::TYPE_CUSTOMER, 'percentage' => 10.0]);

    expect($validator->passes())->toBeTrue();
    expect($validator->errors()->has('type'))->toBeFalse();
});

it('accepts the online discount type (R12.3)', function () {
    $validator = validateDiscount(['type' => Discount::TYPE_ONLINE, 'percentage' => 10.0]);

    expect($validator->passes())->toBeTrue();
    expect($validator->errors()->has('type'))->toBeFalse();
});

it('rejects a discount type other than customer/online (R12.3)', function (string $type) {
    $validator = validateDiscount(['type' => $type, 'percentage' => 10.0]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('type'))->toBeTrue();
})->with([
    'seasonal' => ['seasonal'],
    'bulk' => ['bulk'],
    'clearance' => ['clearance'],
    'wholesale' => ['wholesale'],
    'uppercase customer' => ['Customer'],
    'uppercase online' => ['ONLINE'],
    'empty string' => [''],
    'whitespace' => [' '],
    'numeric-like' => ['123'],
]);

it('rejects a missing discount type (R12.3)', function () {
    $validator = validateDiscount(['percentage' => 10.0]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('type'))->toBeTrue();
});
