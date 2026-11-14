<?php

use App\Http\Controllers\Admin\CentralAuthController;
use App\Http\Controllers\Admin\DomainAdminController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PlanAdminController;
use App\Http\Controllers\Admin\PlatformAdminController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\Admin\TenantProvisioningController;
use App\Http\Controllers\AdvancedOrderController;
use App\Http\Controllers\AiReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DeliveryChallanController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\OrderConfirmationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\OrderTransitionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PartyImpersonationController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPointController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TenantDomainController;
use App\Http\Controllers\TenantIntegrationController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\UsageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Clean JSON REST API organized into two route groups with distinct request
| pipelines (see design: "Request Pipeline / Middleware Order"):
|
|   • Tenant routes            — HTTPS → tenancy init (by domain/subdomain) →
|                                Sanctum → RBAC. Resolve to the active tenant
|                                database; a request that matches no tenant is
|                                rejected with 403 (R1.2, R1.3, R36.1).
|   • platform.admin routes    — HTTPS → Sanctum → RBAC, with NO tenancy
|                                initialization. Operate against the central
|                                database for cross-tenant/platform work
|                                (R1.6, R36.1, R36.3).
|
| Domain endpoints are filled in per milestone; this file wires the pipeline
| and exposes a lightweight, un-pipelined API health probe.
|
*/

/*
| Health probe — intentionally outside both pipelines so liveness checks work
| without TLS, a resolvable tenant, or authentication.
*/
Route::get('/health', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| The `tenant` middleware group applies, in order: HTTPS enforcement, central-
| domain rejection, stancl/tenancy identification + connection switching, and
| Sanctum authentication. Per-route RBAC is attached with the `role` /
| `permission` aliases because the required ability varies by endpoint; the
| concrete role/permission definitions arrive with task 1.7.
*/
/*
| Unauthenticated tenant endpoints — the tenant is resolved (so credentials and
| reset tokens are checked against the correct tenant database) but no Sanctum
| token is required yet (R7.1, R7.4).
*/
Route::middleware('tenant.public')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    /*
    | Public prospect sign-up (R9.1). The tenant is resolved from the request
    | host so the prospect lands in the correct tenant database, but no Sanctum
    | token is required — a prospective buyer has no account yet. The prospect is
    | recorded with status `pending` for a Company_Admin to approve/reject later.
    */
    Route::post('/sign-up', [ProspectController::class, 'store']);

    /*
    | Public display branding (R3.2): name, logo, favicon and colours for the
    | tenant resolved from the host, so the login page and the first render show
    | the company's identity before sign-in. Read-only; no sensitive settings.
    */
    Route::get('/tenant/branding', [TenantSettingsController::class, 'branding']);

    /*
    | Uploaded files (logo, favicon, profile pictures), streamed from the private
    | uploads bucket. Public so the login page can show the company logo; the
    | controller only serves files of THIS domain's tenant.
    */
    Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*');
});

/*
| Authenticated tenant endpoints — full pipeline (HTTPS → tenancy → Sanctum).
| `GET /me` returns the authenticated identity, roles, and abilities (R8.5).
*/
Route::middleware('tenant')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // The caller's own profile picture (any authenticated user; own record only).
    Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [ProfileController::class, 'deleteAvatar']);

    /*
    | Buyer-side PANEL (R8.6, R20.1, R20.2, R28.10). What a logged-in
    | Distributor/Dealer user sees about ITSELF: its party details and the
    | orders it placed (as buyer) / received (as seller — distributor only). The
    | party is derived STRICTLY from the authenticated user's link, never from
    | query params, so a panel user can only ever reach its own data. No
    | resource ability beyond authentication is required (mirroring `GET /me`);
    | a user with no party link gets a 403 from the controller.
    */
    Route::get('/panel/me', [PanelController::class, 'me']);
    Route::get('/panel/orders/placed', [PanelController::class, 'ordersPlaced']);
    Route::get('/panel/orders/received', [PanelController::class, 'ordersReceived']);

    /*
    | In-app notification centre (R27.6). Per-user: every action is scoped to
    | the authenticated caller's OWN notifications, so no resource ability is
    | required beyond authentication — any role (including Read-only) may read
    | and clear its own notifications, mirroring `GET /me`. The durable history
    | is the in-app mirror of the queued email side-effects (R27.1–R27.4) and
    | the optional SMS/WhatsApp channels (R27.5). All logic lives in
    | NotificationService; the controller is thin.
    |
    |   • GET  /notifications            — the caller's paginated history + unread count.
    |   • POST /notifications/{id}/read  — mark a single notification read.
    |   • POST /notifications/read-all   — mark all the caller's notifications read.
    */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereNumber('notification');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

    /*
    | White-label tenant settings (R3.1, R3.3, R3.5). Both actions run inside
    | the active tenant database. GET requires the `settings.view` ability so
    | Read-only may view branding but not change it (R8.4); PATCH is gated to
    | the Company_Admin role, the tenant administrator who brands the workspace.
    */
    Route::get('/tenant/settings', [TenantSettingsController::class, 'show'])
        ->middleware('permission:settings.view');

    Route::patch('/tenant/settings', [TenantSettingsController::class, 'update'])
        ->middleware('role:Company_Admin');

    // Upload / remove the company logo or favicon (stored in object storage).
    Route::post('/tenant/settings/{asset}', [TenantSettingsController::class, 'uploadAsset'])
        ->whereIn('asset', ['logo', 'favicon'])
        ->middleware('role:Company_Admin');
    Route::delete('/tenant/settings/{asset}', [TenantSettingsController::class, 'deleteAsset'])
        ->whereIn('asset', ['logo', 'favicon'])
        ->middleware('role:Company_Admin');

        /*
    | Tenant-owned SMTP server and object storage (Company_Admin). Test-then-save;
    | secrets are write-only and never returned. Tests are throttled because they
    | open outbound connections.
    */
    Route::middleware('role:Company_Admin')->prefix('/tenant/integrations')->group(function () {
        Route::get('/', [TenantIntegrationController::class, 'index']);
        Route::put('/{type}', [TenantIntegrationController::class, 'update'])
            ->whereIn('type', ['smtp', 'storage'])->middleware('throttle:20,1');
        Route::post('/{type}/test', [TenantIntegrationController::class, 'test'])
            ->whereIn('type', ['smtp', 'storage'])->middleware('throttle:10,1');
        Route::delete('/{type}', [TenantIntegrationController::class, 'destroy'])
            ->whereIn('type', ['smtp', 'storage']);
    });

    /*
    | Custom domains (Company_Admin). Map the company's own domain or subdomain
    | to this workspace: add -> publish DNS records -> verify -> active (HTTPS is
    | issued automatically once DNS routes here). Mutations are throttled.
    */
    Route::middleware('role:Company_Admin')->prefix('/tenant/domains')->group(function () {
        Route::get('/', [TenantDomainController::class, 'index']);
        Route::post('/', [TenantDomainController::class, 'store'])->middleware('throttle:20,1');
        Route::post('/{domain}/verify', [TenantDomainController::class, 'verify'])
            ->whereNumber('domain')->middleware('throttle:12,1');
        Route::post('/{domain}/primary', [TenantDomainController::class, 'primary'])->whereNumber('domain');
        Route::delete('/{domain}', [TenantDomainController::class, 'destroy'])->whereNumber('domain');
    });
    /*
    | Usage vs active plan limits (R5.5). Reports the active tenant's current
    | resource counts against the plan's limits and backs the dashboard usage
    | widget. Gated to the `settings.view` ability so any role that can view
    | the workspace configuration (including Read-only) may see usage, mirroring
    | the white-label view gate (R8.4).
    */
    Route::get('/usage', [UsageController::class, 'index'])
        ->middleware('permission:settings.view');

    /*
    | Dashboards & inventory views (R33.1, R33.2, R33.3).
    |
    |   • GET /dashboard — the company master dashboard of key operational
    |     metrics for the active tenant (R33.1).
    |   • GET /inventory/views/{view} — the stock-on-hand, item-detail, and
    |     pending-orders views; `pending-orders` lists Orders placed but not yet
    |     confirmed or fulfilled (R33.2).
    |
    | Both are READ surfaces, so they require only the `dashboard.view` ability
    | (granted to Read-only too — R8.4) and are physically confined to the
    | active tenant database (R28.10). DashboardController delegates to
    | DashboardService / InventoryViewService — the latter runs the views
    | through the shared ReportQueryService, so they inherit the engine's
    | scoping, indexed aggregation, and pagination (R29.3).
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view');

    Route::get('/inventory/views/{view}', [DashboardController::class, 'view'])
        ->middleware('permission:dashboard.view');

    /*
    | Dashboard & inventory-view export (R33.3). WHERE the `exports` Feature_Flag
    | is enabled, any Company_Admin may export dashboard and inventory view data
    | WITHOUT further authorization checks (R33.3) — so the gate is exactly the
    | `exports` feature flag plus the Company_Admin role, and the controller
    | performs no additional per-action authorization. A disabled flag yields a
    | 403 before the controller runs. The export reuses the same streaming
    | pipeline a report export uses (inline for small, queued job for large —
    | R29.5).
    */
    Route::post('/inventory/views/{view}/export', [DashboardController::class, 'exportView'])
        ->middleware(['role:Company_Admin', 'feature:exports']);

    /*
    | Distributor & Dealer management (R10). Reads require the matching `.view`
    | ability (so Read-only may browse the network); mutations require the
    | `.manage` ability (R8.2, R8.4). Creation endpoints additionally pass the
    | `subscription.active` gate so a past_due/cancelled tenant cannot add new
    | tenant-owned resources (R5.7). Quota and `max_dealers_per_distributor`
    | enforcement happens inside the services (R5.1, R5.2, R10.4).
    */
    Route::get('/distributors', [DistributorController::class, 'index'])
        ->middleware('permission:distributors.view');
    Route::get('/distributors/{distributor}', [DistributorController::class, 'show'])
        ->middleware('permission:distributors.view');
    Route::post('/distributors', [DistributorController::class, 'store'])
        ->middleware(['permission:distributors.manage', 'subscription.active']);
    Route::patch('/distributors/{distributor}', [DistributorController::class, 'update'])
        ->middleware('permission:distributors.manage');

    /*
    | Prospect approval queue (R9.2, R9.3). Managing prospects is the gateway to
    | creating Distributors/Dealers, so it is gated by the `distributors.manage`
    | ability (held by Company_Admin/Platform_Admin) — the same manage ability
    | that guards distributor creation. Approval delegates to the
    | Distributor/Dealer services, so quota + `max_dealers_per_distributor` are
    | enforced there (R10.4). Listing is scoped to the same ability so only
    | managers see the pending queue.
    */
    Route::get('/prospects', [ProspectController::class, 'index'])
        ->middleware('permission:distributors.manage');
    Route::post('/prospects/{prospect}/approve', [ProspectController::class, 'approve'])
        ->middleware(['permission:distributors.manage', 'subscription.active']);
    Route::post('/prospects/{prospect}/reject', [ProspectController::class, 'reject'])
        ->middleware('permission:distributors.manage');

    Route::get('/distributors/{distributor}/dealers', [DealerController::class, 'index'])
        ->middleware('permission:dealers.view');
    Route::get('/distributors/{distributor}/dealers/{dealer}', [DealerController::class, 'show'])
        ->middleware('permission:dealers.view');
    Route::post('/distributors/{distributor}/dealers', [DealerController::class, 'store'])
        ->middleware(['permission:dealers.manage', 'subscription.active']);
    Route::patch('/distributors/{distributor}/dealers/{dealer}', [DealerController::class, 'update'])
        ->middleware('permission:dealers.manage');

    /*
    | Buyer-side PANEL login users (R7.1, R8.6, R10.3). A Distributor or Dealer
    | business record has no login of its own; these endpoints create the User
    | rows a distributor/dealer logs in with to reach its panel. Listing is a
    | read surface (`users.view`, granted to Read-only too — R8.4); creation is
    | a mutation gated on `users.manage` plus the `subscription.active` gate so a
    | past_due/cancelled tenant cannot add users (R5.7), and the `max_users`
    | quota is enforced inside TenantUserService (R5.1, R5.2). The created user
    | is assigned the matching Distributor/Dealer role and linked to its party
    | via distributor_id/dealer_id, which lights up panel + report scoping and
    | impersonation automatically. Users are nested under the route-model-bound
    | party so the owner cannot be spoofed from the body.
    */
    Route::get('/distributors/{distributor}/users', [TenantUserController::class, 'indexForDistributor'])
        ->middleware('permission:users.view');
    Route::post('/distributors/{distributor}/users', [TenantUserController::class, 'storeForDistributor'])
        ->middleware(['permission:users.manage', 'subscription.active']);
    // Company resets a distributor panel user's password (R7.3): users.manage only.
    Route::patch('/distributors/{distributor}/users/{user}/password', [TenantUserController::class, 'resetDistributorUserPassword'])
        ->middleware(['permission:users.manage', 'subscription.active'])
        ->whereNumber('user');

    Route::get('/distributors/{distributor}/dealers/{dealer}/users', [TenantUserController::class, 'indexForDealer'])
        ->middleware('permission:users.view');
    Route::post('/distributors/{distributor}/dealers/{dealer}/users', [TenantUserController::class, 'storeForDealer'])
        ->middleware(['permission:users.manage', 'subscription.active']);
    // Company OR the owning Distributor resets a dealer panel user's password
    // (R7.3): users.manage (Company) OR dealers.manage (Distributor).
    Route::patch('/distributors/{distributor}/dealers/{dealer}/users/{user}/password', [TenantUserController::class, 'resetDealerUserPassword'])
        ->middleware(['permission:users.manage|dealers.manage', 'subscription.active'])
        ->whereNumber('user');

    /*
    | Catalog hierarchy & products (R11). The hierarchy Category → Collection →
    | Colour and products are tenant-owned catalog data; reads require the
    | `catalog.view` ability (so Read-only may browse) and mutations require the
    | `catalog.manage` ability (R8.2, R8.4). Product creation additionally
    | passes the `subscription.active` gate (R5.7) and the `max_products` quota
    | is enforced inside ProductService (R5.1, R5.2). List endpoints are
    | paginated and eager-loaded in the services, running against the active
    | tenant database.
    */
    /*
    | Buyer-scoped catalog browse (R11.4, R11.5, R11.6). Paginated and run
    | against the active tenant database by CatalogVisibilityService. The
    | optional `buyer_type`/`buyer_id` selector restricts the catalog to a
    | Distributor's/Dealer's configured active attributes; absent or
    | unconfigured, the full tenant catalog is returned. Requires `catalog.view`
    | so Read-only may browse (R8.4).
    */
    Route::get('/catalog', [CatalogController::class, 'catalog'])
        ->middleware('permission:catalog.view');

    Route::get('/categories', [CatalogController::class, 'categories'])
        ->middleware('permission:catalog.view');
    Route::post('/categories', [CatalogController::class, 'storeCategory'])
        ->middleware('permission:catalog.manage');
    // Update a category: rename / recode / activate-deactivate (R11.2).
    Route::patch('/categories/{category}', [CatalogController::class, 'updateCategory'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('category');
    // Delete a category (R11.2); 422 if it still has collections.
    Route::delete('/categories/{category}', [CatalogController::class, 'destroyCategory'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('category');

    Route::get('/collections', [CatalogController::class, 'collections'])
        ->middleware('permission:catalog.view');
    Route::post('/collections', [CatalogController::class, 'storeCollection'])
        ->middleware('permission:catalog.manage');
    Route::patch('/collections/{collection}', [CatalogController::class, 'updateCollection'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('collection');
    // Delete a collection (R11.2); 422 if it still has colours or products.
    Route::delete('/collections/{collection}', [CatalogController::class, 'destroyCollection'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('collection');

    Route::get('/colours', [CatalogController::class, 'colours'])
        ->middleware('permission:catalog.view');
    Route::post('/colours', [CatalogController::class, 'storeColour'])
        ->middleware('permission:catalog.manage');
    Route::patch('/colours/{colour}', [CatalogController::class, 'updateColour'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('colour');
    // Delete a colour (R11.2); 422 if it is still used by products.
    Route::delete('/colours/{colour}', [CatalogController::class, 'destroyColour'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('colour');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:catalog.view');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware(['permission:catalog.manage', 'subscription.active']);
    Route::patch('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:catalog.manage');
    // Delete a product (R11.3); frees a max_products quota slot.
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('product');

    /*
    | Pricing & discounts (R12). Prices and discounts are tenant-owned catalog
    | configuration; reads require the `catalog.view` ability and mutations the
    | `catalog.manage` ability (R8.2, R8.4). Discount creation rejects any type
    | other than `customer`/`online` with a 422 (R12.3). The order-line price
    | RESOLUTION (product-level overriding collection-level, markup/MUP, then
    | cumulative discounts clamped at zero in the tenant's single currency) is
    | performed by PriceResolutionService during ordering (R12.4, R12.5, R39.1).
    */
    Route::get('/prices', [PriceController::class, 'index'])
        ->middleware('permission:catalog.view');
    Route::post('/prices', [PriceController::class, 'store'])
        ->middleware(['permission:catalog.manage', 'subscription.active']);
    Route::delete('/prices/{price}', [PriceController::class, 'destroyPrice'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('price');

    Route::get('/discounts', [PriceController::class, 'discounts'])
        ->middleware('permission:catalog.view');
    Route::post('/discounts', [PriceController::class, 'storeDiscount'])
        ->middleware(['permission:catalog.manage', 'subscription.active']);
    Route::patch('/discounts/{discount}', [PriceController::class, 'updateDiscount'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('discount');
    Route::delete('/discounts/{discount}', [PriceController::class, 'destroyDiscount'])
        ->middleware('permission:catalog.manage')
        ->whereNumber('discount');

    /*
    | Stock inward & manual adjustments (R14). Both mutate inventory, so they
    | require the `inventory.adjust` ability (held by Company_Admin/Staff — R8.2)
    | and pass the `subscription.active` gate so a past_due/cancelled tenant
    | cannot change stock (R5.7). Inward increases stock and writes a ledger
    | entry atomically (R14.1); a manual adjustment additionally requires a
    | non-empty reason (422 otherwise — R14.3, R14.4) and is audited (R34.3).
    | All changes route through StockMovementService inside StockOperationsService.
    */
    Route::post('/stock/inward', [StockController::class, 'inward'])
        ->middleware(['permission:inventory.adjust', 'subscription.active']);
    Route::post('/stock/adjust', [StockController::class, 'adjust'])
        ->middleware(['permission:inventory.adjust', 'subscription.active']);
    // Add stock held by a specific Distributor (kept separate from Company stock).  // Distributors may add to their OWN stock only; StockController confines them.
    Route::post('/stock/distributors', [StockController::class, 'distributorInward'])
        ->middleware(['role_or_permission:Distributor|inventory.adjust', 'subscription.active']);

    /*
    | Stock transfer (R18.1). Relocates stock between two stock points of the
    | SAME owner — decrement source, increment destination, two ledger entries,
    | one transaction via StockMovementService (R18.1). Requires the
    | `inventory.adjust` ability and passes the `subscription.active` gate like
    | the other stock mutations. A cross-owner transfer is rejected with a 422
    | inside StockTransferService.
    */
    Route::post('/stock/transfer', [StockTransferController::class, 'store'])
        ->middleware(['permission:inventory.adjust', 'subscription.active']);

    /*
    | Low-stock thresholds, alerts, and report (R19). The per-(product,
    | stock_point) threshold is CONFIGURED on a single stock row — a mutation,
    | so PATCH /stock/{stock}/threshold requires `inventory.adjust` and passes
    | the `subscription.active` gate (R19.1); its response carries an `alert`
    | flag when the row is now at or below its threshold (R19.2). The low-stock
    | report (GET /stock/low) lists every breaching row and is a read surface,
    | so it only requires `inventory.view` (also granted to Read-only — R8.4)
    | and is restricted to the active tenant's data by physical isolation
    | (R19.3, R28.8, R28.10). All logic lives in LowStockService.
    */
    Route::get('/stock/low', [StockController::class, 'low'])
        ->middleware('permission:inventory.view');
    Route::patch('/stock/{stock}/threshold', [StockController::class, 'setThreshold'])
        ->middleware(['role_or_permission:Distributor|inventory.adjust', 'subscription.active']);

    // Edit a stock row's on-hand quantity to an absolute value (reason required).
    // Applied as a ledgered adjustment via StockOperationsService::setQuantity().
    Route::patch('/stock/{stock}/quantity', [StockController::class, 'setQuantity'])
        ->whereNumber('stock')
        ->middleware(['role_or_permission:Distributor|inventory.adjust', 'subscription.active']);

    /*
    | Stock points & stock listing (R13.4, R14). Stock points are the Company's
    | physical locations stock is held at; the Stock screen lists them (and all
    | per-(product, point) stock rows) to drive the product/point dropdowns and
    | a "stock by point" view, and lets a manager create a new point. Listing is
    | a read surface (`inventory.view`); creating a point mutates configuration,
    | so it requires `inventory.adjust` + an active subscription.
    */
    Route::get('/stock-points', [StockPointController::class, 'index'])
        ->middleware('permission:inventory.view');
    Route::post('/stock-points', [StockPointController::class, 'store'])
        ->middleware(['permission:inventory.adjust', 'subscription.active']);
    // Edit an existing Company stock point's name/sort order (R13.4, R13.8).
    Route::patch('/stock-points/{stockPoint}', [StockPointController::class, 'update'])
        ->middleware(['permission:inventory.adjust', 'subscription.active'])
        ->whereNumber('stockPoint');
    Route::get('/stock', [StockPointController::class, 'stock'])
        ->middleware('permission:inventory.view');

    /*
    | Distributor Stock Management (R13.4, R13.5). Lists the stock held by the
    | tenant's DISTRIBUTORS (stock that moved Company→Distributor on order
    | confirmation), with each row's owning distributor, product, and point so
    | the Company can oversee inventory across its distribution tree. A read
    | surface, so it requires only `inventory.view` (granted to Read-only too).
    | Declared before the dynamic `/stock/{stock}` routes so the literal segment
    | wins the match.
    */
    Route::get('/stock/distributors', [StockPointController::class, 'distributorStock'])
        ->middleware('permission:inventory.view');

    /*
    | Order placement & cart submission (R20). A Buyer (Distributor/Dealer)
    | submits a cart of one or more lines; the Platform creates exactly one
    | Order with one Order_Line per entry (R20.3, R20.4). Distributor orders are
    | placed against the Company catalogue (R20.1); Dealer orders against the
    | serving Distributor's stock (R20.2). Placing an order requires the
    | `orders.create` ability (held by Distributor/Dealer/Company_Staff — R8.2)
    | and passes the `subscription.active` gate so a past_due/cancelled tenant
    | cannot place new orders (R5.7). The `max_orders_per_month` quota is
    | enforced inside CartSubmissionService (R5.3). All logic lives in the
    | service; the controller is thin.
    */
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware(['permission:orders.create', 'subscription.active']);

    /*
    | Order history (R20, R33). The listing is scoped to the viewer: a party-less
    | query returns the Company's full history; `scope=buyer` returns orders a
    | party placed; `scope=seller` returns orders placed against a party (a
    | Distributor's dealer orders). Reads require `orders.view` (granted to every
    | role that can see orders — R8.4).
    */
    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view');

    /*
    | Database-backed shopping cart (R20.3). A Buyer builds a durable cart of
    | line items — each carrying the full resolved price breakdown (RRP,
    | discount %, discounted price, net, tax, total) — then checks it out into
    | exactly one Order (R20.4). Reads require `orders.create` (the ability that
    | lets a buyer place orders); mutations additionally pass the
    | `subscription.active` gate so a past_due/cancelled tenant cannot build or
    | submit carts (R5.7). All logic lives in CartService; the controller is
    | thin.
    */
    Route::get('/cart', [CartController::class, 'show'])
        ->middleware('permission:orders.create');
    Route::get('/cart/sourcing', [CartController::class, 'sourcing'])
        ->middleware('permission:orders.create');
    Route::delete('/cart', [CartController::class, 'clear'])
        ->middleware(['permission:orders.create', 'subscription.active']);
    Route::post('/cart/items', [CartController::class, 'add'])
        ->middleware(['permission:orders.create', 'subscription.active']);
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])
        ->middleware(['permission:orders.create', 'subscription.active']);
    Route::delete('/cart/items/{item}', [CartController::class, 'remove'])
        ->middleware(['permission:orders.create', 'subscription.active']);
    Route::post('/cart/submit', [CartController::class, 'submit'])
        ->middleware(['permission:orders.create', 'subscription.active']);

    /*
    | Party impersonation (R8.6, R34.3). A tenant actor "acts as" a buyer party
    | (Distributor or Dealer) to browse its catalog and place orders on its
    | behalf. Company_Admin/Company_Staff may impersonate any Distributor or any
    | Dealer (direct company→dealer); a Distributor may impersonate only its own
    | Dealers. The hierarchy is enforced in PartyImpersonationService; every
    | session is audited (R34.3). Requires `orders.create` — the ability a buyer
    | needs to place orders.
    */
    Route::get('/impersonation/targets', [PartyImpersonationController::class, 'targets'])
        ->middleware('permission:orders.create');
    Route::post('/impersonation', [PartyImpersonationController::class, 'store'])
        ->middleware(['permission:orders.create', 'subscription.active']);

    /*
    | Advanced order placement & stock availability (R23). Blanket, advance, and
    | depot orders reserve stock via a stock_reservation that reduces AVAILABLE
    | (available = on-hand − Σ active reservations) WITHOUT moving the owner's
    | on-hand quantity (R23.4). Placement requires the `orders.create` ability
    | and passes the `subscription.active` gate like standard order placement
    | (R5.7); an ADVANCE order is additionally gated inside the service by the
    | `advance_orders` feature flag (R23.2), so a disabled plan yields a 403. A
    | reservation that would exceed available is rejected with a 422 (R23.4).
    | The availability read is a view of a single stock row's on-hand/reserved/
    | available figures, so it only requires `inventory.view` (also granted to
    | Read-only — R8.4). Cancellation/expiry releases reservations back to
    | available (R23.5), handled in the cancellation path and the expiry sweep.
    | All logic lives in AdvancedOrderService / StockReservationService; the
    | controller is thin.
    */
    Route::post('/orders/advanced', [AdvancedOrderController::class, 'store'])
        ->middleware(['permission:orders.create', 'subscription.active']);

    Route::get('/stock/{stock}/availability', [AdvancedOrderController::class, 'availability'])
        ->middleware('permission:inventory.view');

    /*
    | Order lifecycle transition (R21.2, R21.4). Drives the declarative Order
    | state machine: the seller's approve/reject/edit while Placed (R21.2) and
    | the dispatch/deliver/close + cancel moves across the lifecycle (R21.1). A
    | move not defined for the order's current state is rejected with a 422 and
    | the order is left unchanged (R21.4). Gated on the `orders.confirm` ability
    | — the seller-side mutation that drives approvals/fulfillment — and passes
    | the `subscription.active` gate like the other mutating endpoints (R5.7).
    | All lifecycle rules live in OrderStateMachine; the controller is thin.
    */
    Route::post('/orders/{order}/transition', [OrderTransitionController::class, 'store'])
        ->middleware(['permission:orders.confirm', 'subscription.active']);

    /*
    | Order confirmation (R16, R21.3, R21.5, R27.3). Confirming a placed order
    | moves seller→buyer stock FOR UPDATE (R16), checks the credit limit at
    | confirmation time (R21.3), generates EXACTLY ONE invoice under the
    | seller's own series synchronously inside the transaction (R21.5), appends
    | the buyer's account-ledger purchase (R25.1), transitions the order to
    | Confirmed, and queues the order-confirmed notification after commit
    | (R27.3) — all orchestrated by OrderConfirmationService in one transaction.
    | An illegal `confirm`, an oversell with backorders off, or a credit breach
    | is rejected with a 422, leaving the order unchanged (R21.4, R17.1, R26.2).
    | Gated, like the other seller-side order mutations, on the seller-side
    | `orders.confirm` ability and the `subscription.active` gate (R5.7). The
    | controller is thin; all business rules live in the service.
    */
    Route::post('/orders/{order}/confirm', [OrderConfirmationController::class, 'store'])
        ->middleware(['permission:orders.confirm', 'subscription.active']);

    /*
    | Order cancellation (R18.2, R22.1). Cancelling a confirmed order writes
    | reversing stock-ledger entries that restore the confirmation's quantities
    | and reverses the buyer's account so the cancelled purchase no longer
    | counts, then transitions the order to Cancelled — all in one transaction
    | via OrderCancellationService. An illegal `cancel` from the order's current
    | state is rejected with a 422, leaving the order unchanged (R21.4). Gated,
    | like the transition endpoint, on the seller-side `orders.confirm` ability
    | and the `subscription.active` gate (R5.7).
    */
    Route::post('/orders/{order}/cancel', [OrderCancellationController::class, 'store'])
        ->middleware(['permission:orders.confirm', 'subscription.active']);

    /*
    | Order returns (R18.3, R22.2, R22.3). A return re-enters the returned
    | quantities into stock with ledger entries, adjusts the buyer's account via
    | a transaction-ledger return entry, and recomputes the Outstanding_Balance
    | — all in one transaction via OrderReturnService. A line for a product not
    | on the order is rejected with a 422. Gated on the seller-side
    | `orders.confirm` ability and the `subscription.active` gate (R5.7).
    */
    Route::post('/orders/{order}/return', [OrderReturnController::class, 'store'])
        ->middleware(['permission:orders.confirm', 'subscription.active']);

    /*
    | Delivery-challan printing (R33.4, R28.7). Renders the delivery challan for
    | a dispatched/delivered order — the document that accompanies the goods —
    | either as structured JSON or, with `?format=pdf` (or an
    | `Accept: application/pdf` header), a printable PDF download. It is a READ
    | surface, so it only requires the `orders.view` ability (also granted to
    | Read-only and the buyer roles — R8.4). The thin DeliveryChallanController
    | delegates assembly and PDF rendering to DeliveryChallanService, which
    | reuses the invoice number as the challan reference (mirroring the
    | order-status/delivery-challan listing report) and the shared printable-PDF
    | writer, and rejects a not-yet-dispatched order with a 422. Physical
    | isolation confines the challan to the active tenant's data (R28.10).
    */
    Route::get('/orders/{order}/challan', [DeliveryChallanController::class, 'show'])
        ->middleware('permission:orders.view');

    /*
    | Standard report catalog (R28, R29). A single endpoint serves every
    | standard report — stock-on-hand, stock-ledger, sales, purchases,
    | outstanding-ageing, payments/collections, order-status/delivery-challan,
    | low-stock, and plan-usage (R28.1–R28.9) — dispatched by the allow-listed
    | `{type}` segment. It is a READ surface, so it only requires the
    | `reports.view` ability (also granted to Read-only — R8.4). The thin
    | ReportController maps the query string to a ReportQuerySpec and delegates
    | to the shared ReportQueryService, which applies the previous-month default
    | (R29.2), tenant+role scoping (R28.10), allow-list validation (R31.2), and
    | SQL-side indexed pagination (R29.3). Physical isolation confines every
    | report to the active tenant's data (R28.10).
    */
    Route::get('/reports/{type}', [ReportController::class, 'show'])
        ->middleware('permission:reports.view');

    /*
    | Report exports + scheduled/subscription reports (R29.4, R29.5, R29.6).
    | BOTH endpoints are gated behind the `exports` Feature_Flag (R29.4) via the
    | `feature:exports` middleware, so the whole surface is reachable only when
    | the tenant's plan enables exports — a disabled flag yields a 403 before
    | the controller runs. They additionally require the `reports.view` ability
    | (a caller that can read a report may export/subscribe to it).
    |
    |   • POST /reports/export   — render a report to Excel/CSV/printable PDF; a
    |     large export runs as a queued streaming job and the response returns a
    |     202 with a pending export handle to poll for the download link (R29.5),
    |     while a small export returns 201 with the link already populated.
    |   • POST /reports/schedule — create a scheduled report that, when due, runs
    |     defaulting to the previous calendar month and emails the subscribed
    |     recipients (R29.6).
    */
    Route::post('/reports/export', [ReportExportController::class, 'export'])
        ->middleware(['permission:reports.view', 'feature:exports']);

    Route::post('/reports/schedule', [ReportExportController::class, 'schedule'])
        ->middleware(['permission:reports.view', 'feature:exports']);

Route::get('/reports/exports/{export}/download', [ReportExportController::class, 'download'])
    ->whereNumber('export')
    ->middleware(['permission:reports.view', 'feature:exports']);

    /*
    | AI natural-language reporting (R30.6, R30.7, R31). A single endpoint maps
    | a free-text request to an allow-listed ReportQuerySpec and runs it through
    | the SAME shared ReportQueryService every standard report uses (R31.3),
    | returning the standard list envelope (R31.6). It is mounted behind the
    | COMPOSITE AI gate (`ai.enabled`): the request is permitted only when BOTH
    | the `AI_REPORTS_ENABLED` env master switch AND the tenant's `ai_reports`
    | feature flag are enabled, otherwise a 403 is returned before the thin
    | AiReportController runs (R30.6, R30.7). It additionally requires the
    | `reports.view` ability — a caller that may read a report may ask for one
    | in natural language. The AiReportService performs no SQL: it only maps NL
    | to a validated spec and hands it to the query layer (R31.1, R31.2, R31.4).
    */
    Route::post('/ai/reports', [AiReportController::class, 'store'])
        ->middleware(['permission:reports.view', 'ai.enabled']);

    /*
    | AI summaries & follow-ups (R32). Both operate on a PERSISTED AI report
    | ({aiReport}) and compute their answers SOLELY from that report's stored
    | dataset (R32.1, R32.2) — the dataset is minimized to aggregates and
    | PII-redacted before anything is sent to the model (R32.3). Mounted behind
    | the SAME composite AI gate (`ai.enabled`) and `reports.view` ability as
    | generation; the service additionally enforces tenant+role read scope on
    | the referenced report (R32.4) and audits every request (R32.5).
    */
    Route::post('/ai/reports/{aiReport}/summary', [AiReportController::class, 'summary'])
        ->middleware(['permission:reports.view', 'ai.enabled']);

    Route::post('/ai/reports/{aiReport}/followup', [AiReportController::class, 'followup'])
        ->middleware(['permission:reports.view', 'ai.enabled']);
});

/*
|--------------------------------------------------------------------------
| Central platform.admin routes
|--------------------------------------------------------------------------
|
| The `platform.admin` middleware group applies HTTPS enforcement and Sanctum
| authentication WITHOUT initializing tenancy, so these endpoints run against
| the central database. RBAC still authorizes the action: the group is gated
| to the Platform_Admin role (the role itself is seeded in task 1.7).
*/
/*
| Central platform login (unified login, central path). Runs on the central
| `platform.public` pipeline (HTTPS, no tenancy). The single frontend login page
| posts here when the host is a central/apex domain; a Platform_Admin in the
| central `users` table is authenticated and issued a token (R7.1, R7.2).
*/
Route::middleware('platform.public')
    ->prefix('platform')
    ->group(function () {
        Route::post('/login', [CentralAuthController::class, 'login']);
        // Public platform branding (name/logo/favicon) + the files themselves.
        Route::get('/branding', [PlatformAdminController::class, 'branding']);
        Route::get('/media/{path}', [PlatformAdminController::class, 'media'])->where('path', '.*');
    });

/*
| Authenticated central identity endpoints (token issued by /platform/login).
*/
Route::middleware('platform.admin')
    ->prefix('platform')
    ->group(function () {
        Route::get('/me', [CentralAuthController::class, 'me']);
        Route::post('/logout', [CentralAuthController::class, 'logout']);
    });

Route::middleware('platform.admin')
    ->prefix('admin')
    ->group(function () {
        // All central master-config is gated to the Platform_Admin role: the
        // superadmin owns tenants, plans, plan-limits, and subscriptions
        // (R1.6, R2, R4, R6, R36.3).
        Route::middleware('role:Platform_Admin')->group(function () {
            Route::get('/ping', function () {
                return response()->json(['status' => 'ok', 'scope' => 'platform.admin']);
            });

            // ── Tenants (R2, R1) ──────────────────────────────────────────
            // Provision creates + migrates + seeds a dedicated tenant database
            // and records the Tenant/domain/Subscription centrally; 409 on
            // duplicate subdomain (R2.1–R2.4). CRUD covers list/show/update and
            // delete (delete drops the tenant database via TenantDeleted).
            Route::get('/tenants', [TenantAdminController::class, 'index']);
            Route::post('/tenants', [TenantProvisioningController::class, 'store']);
            Route::get('/tenants/{tenant}', [TenantAdminController::class, 'show']);
            Route::patch('/tenants/{tenant}', [TenantAdminController::class, 'update']);
            Route::delete('/tenants/{tenant}', [TenantAdminController::class, 'destroy']);
            // ── Custom domains: cross-tenant oversight + kill switch ───────────
            Route::get('/domains', [DomainAdminController::class, 'index']);
            Route::post('/domains/{domain}/disable', [DomainAdminController::class, 'disable'])->whereNumber('domain');
            Route::post('/domains/{domain}/enable', [DomainAdminController::class, 'enable'])->whereNumber('domain');
            Route::post('/tenants/{tenant}/plan', [TenantAdminController::class, 'changePlan']);
            Route::get('/tenants/{tenant}/history', [TenantAdminController::class, 'history']);
            Route::get('/usage', [TenantAdminController::class, 'usage']);

            // ── Platform admin profile, settings/branding, audit trail ──────
            Route::get('/profile', [PlatformAdminController::class, 'profile']);
            Route::patch('/profile', [PlatformAdminController::class, 'updateProfile']);
            Route::get('/settings', [PlatformAdminController::class, 'settings']);
            Route::patch('/settings', [PlatformAdminController::class, 'updateSettings']);
            Route::post('/settings/{asset}', [PlatformAdminController::class, 'uploadAsset']);
            Route::delete('/settings/{asset}', [PlatformAdminController::class, 'deleteAsset']);
            Route::get('/audit-logs', [PlatformAdminController::class, 'auditLogs']);

            // ── Plans + limits + features (R4) ────────────────────────────
            Route::get('/plans', [PlanAdminController::class, 'index']);
            Route::post('/plans', [PlanAdminController::class, 'store']);
            Route::get('/plans/{plan}', [PlanAdminController::class, 'show']);
            Route::patch('/plans/{plan}', [PlanAdminController::class, 'update']);
            Route::delete('/plans/{plan}', [PlanAdminController::class, 'destroy']);

            // ── Subscriptions (R4.6, R6) ──────────────────────────────────
            Route::get('/subscriptions', [SubscriptionAdminController::class, 'index']);
            Route::post('/subscriptions', [SubscriptionAdminController::class, 'store']);
            Route::patch('/subscriptions/{subscription}', [SubscriptionAdminController::class, 'update']);

            // ── Impersonation (R8.6, R34.3) ───────────────────────────────
            // Initializes the target tenant's database, issues a short-lived
            // scoped token, and audits the session.
            Route::post('/impersonate/{userId}', [ImpersonationController::class, 'store']);
        });
    });
