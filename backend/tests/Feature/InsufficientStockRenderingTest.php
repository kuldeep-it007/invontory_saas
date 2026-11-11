<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\InsufficientStockException;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Task 9.5 — InsufficientStock 422 identifies the insufficient products (R17.3)
|--------------------------------------------------------------------------
|
| The all-or-nothing negative-stock guard in StockMovementService throws
| InsufficientStockException with every offending line collected. This suite
| pins the HTTP boundary: the centralized ApiExceptionRenderer must map that
| exception to a 422 whose body IDENTIFIES the insufficient products (R17.3),
| not merely a generic validation failure.
|
| Pure boundary test (no DB): the renderer is exercised directly with a
| hand-built exception, so it runs everywhere regardless of MySQL availability.
|
*/

it('renders InsufficientStockException as a 422 that identifies the insufficient products (R17.3)', function () {
    $offenders = [
        ['product_id' => 7, 'stock_point_id' => 3, 'requested' => '-9.000', 'available' => '5.000'],
        ['product_id' => 11, 'stock_point_id' => 3, 'requested' => '-2.000', 'available' => '0.000'],
    ];

    $response = (new ApiExceptionRenderer)->render(
        new InsufficientStockException($offenders),
        Request::create('/orders/1/confirm', 'POST'),
    );

    expect($response->getStatusCode())->toBe(422);

    $payload = $response->getData(true);

    expect($payload['error']['code'])->toBe('INSUFFICIENT_STOCK');
    // The response body lists every insufficient product (R17.3).
    expect($payload['error']['details']['products'])->toBe($offenders);

    $productIds = array_column($payload['error']['details']['products'], 'product_id');
    expect($productIds)->toBe([7, 11]);
});
