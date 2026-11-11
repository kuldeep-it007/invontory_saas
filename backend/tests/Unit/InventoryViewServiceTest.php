<?php

declare(strict_types=1);

use App\Models\OrderStatus;
use App\Reports\ReportType;
use App\Services\InventoryViewService;
use App\Services\ReportQueryService;

/*
|--------------------------------------------------------------------------
| Task 15.1 — InventoryViewService spec mapping (unit, no DB)
|--------------------------------------------------------------------------
|
| The inventory views (R33.2) are thin mappings from a view name to a
| ReportQuerySpec the shared ReportQueryService executes. These unit tests pin
| that mapping — which report backs each view and the invariant filters each
| view forces — without touching a database, so the view contract is verified
| in every environment.
|
*/

function makeInventoryViewService(): InventoryViewService
{
    // The spec mapping needs no registered definitions — specFor() never
    // executes a query, it only builds the spec.
    return new InventoryViewService(new ReportQueryService([]));
}

it('exposes exactly the three allow-listed inventory views (R33.2)', function () {
    expect(InventoryViewService::views())->toBe([
        InventoryViewService::VIEW_STOCK_ON_HAND,
        InventoryViewService::VIEW_ITEM_DETAIL,
        InventoryViewService::VIEW_PENDING_ORDERS,
    ]);

    expect(InventoryViewService::supports('stock-on-hand'))->toBeTrue();
    expect(InventoryViewService::supports('pending-orders'))->toBeTrue();
    expect(InventoryViewService::supports('unknown-view'))->toBeFalse();
});

it('maps the stock-on-hand view to the stock-on-hand report (R33.2)', function () {
    $spec = makeInventoryViewService()->specFor(
        InventoryViewService::VIEW_STOCK_ON_HAND,
        ['stockPointId' => 3],
    );

    expect($spec->type)->toBe(ReportType::STOCK_ON_HAND);
    expect($spec->filters->stockPointId)->toBe(3);
});

it('maps the item-detail view to a single-product stock-on-hand spec (R33.2)', function () {
    $spec = makeInventoryViewService()->specFor(
        InventoryViewService::VIEW_ITEM_DETAIL,
        ['product_id' => 42],
    );

    expect($spec->type)->toBe(ReportType::STOCK_ON_HAND);
    expect($spec->filters->productId)->toBe(42);
});

it('requires a product id for the item-detail view (R33.2)', function () {
    expect(fn () => makeInventoryViewService()->specFor(InventoryViewService::VIEW_ITEM_DETAIL, []))
        ->toThrow(InvalidArgumentException::class);
});

it('maps the pending-orders view to order-status forced to placed (R33.2)', function () {
    // Even when a caller tries to widen the status, the pending contract forces
    // "placed" — orders placed but not yet confirmed or fulfilled.
    $spec = makeInventoryViewService()->specFor(
        InventoryViewService::VIEW_PENDING_ORDERS,
        ['status' => 'confirmed'],
    );

    expect($spec->type)->toBe(ReportType::ORDER_STATUS);
    expect($spec->filters->status)->toBe(OrderStatus::PLACED);
});

it('rejects an unknown view name (R33.2)', function () {
    expect(fn () => makeInventoryViewService()->specFor('nope', []))
        ->toThrow(InvalidArgumentException::class);
});
