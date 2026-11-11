# Implementation Plan: Inventory & Distribution Platform

## Overview

This plan converts the approved design into incremental, test-driven coding tasks organized by the design's milestone sequence (M1 → M1b → M2 → M3 → M4 → M5 → M5b → M6). Each milestone leaves the platform in a working, testable state and builds on the previous one, ending with wiring that integrates new code into the running application.

Backend is PHP 8.x / Laravel (MySQL 8.x authoritative store, Redis queue/cache); frontend is Next.js (App Router) / TypeScript. Property-based tests use an existing library (PHP: Pest/PHPUnit with a generator library such as `innmind/black-box`; TypeScript: `fast-check` with Vitest) — never hand-rolled — run a minimum of 100 generated iterations, and carry the traceability tag `Feature: inventory-distribution-platform, Property {number}: {property_text}`.

Multi-tenancy uses `stancl/tenancy` in a database-per-tenant architecture: a single central database holds tenants, domains, plans, plan limits, subscriptions, and Platform_Admin users, and each tenant's business data lives in its own dedicated MySQL database. Isolation is physical — tenant-owned tables carry no `tenant_id` discriminator column, there is no global Eloquent scope, and there is no per-record `tenant_id` comparison guard; tenant identification by domain/subdomain switches Laravel's default database connection to the resolved tenant's database.

All stock mutations route through a single `StockMovementService`; its atomic `SELECT ... FOR UPDATE` locking, immutable ledger writes, and all-or-nothing negative-stock logic are explicit tasks. There is no co-distributor functionality anywhere in this plan.

Conventions enforced in every task: thin controllers delegate to focused single-responsibility services; every list endpoint is paginated and runs against the active tenant connection; queries select only needed columns and eager-load; every new table/column that participates in WHERE/JOIN/ORDER BY gets a documented index (filter-column indexes within each tenant database, plus the central-database resolution indexes).

## Tasks

- [x] 1. M1 — Foundation: scaffold, stancl/tenancy (central + tenant connections), auth, RBAC, audit, base indexes
  - [x] 1.1 Scaffold Laravel + MySQL + Redis project skeleton with stancl/tenancy
    - Initialize Laravel app, configure central and tenant MySQL database connections and Redis for queue + cache, add `.env.example` with zero optional config required to boot
    - Install and configure dependencies: `stancl/tenancy` (central + per-tenant database connections, tenant identification + connection switching), Sanctum, spatie/laravel-permission, owen-it/laravel-auditing, PhpSpreadsheet, and the chosen PBT library
    - Add centralized exception handler returning the `{ "error": { code, message, details } }` envelope
    - _Requirements: 13.1, 13.2, 36.1, 37.2_

  - [x] 1.2 Create central and tenant base migration sets with documented indexes
    - Central migrations (run on the central connection): `tenants` (unique `subdomain`, `currency`, `backorders_enabled`, `dealer_stock_enabled`), `domains` (unique `domain` for subdomain/custom-domain resolution), `plans`, `plan_limits`, `subscriptions`, and Platform_Admin `users`
    - Tenant migrations (run on each tenant connection): `companies` (singleton — exactly one row per tenant database, enforced by a one-company rule rather than a `unique(tenant_id)` constraint), `users`, spatie roles/permissions tables, `audit_logs`, `tenant_settings`; remaining tenant tables are added later per milestone
    - Add FK indexes and filter-column indexes; add inline comments documenting each index's query purpose. Central indexes: `tenants.subdomain` unique, `domains.domain` unique. Tenant-table indexes carry no leading `tenant_id` (isolation is physical)
    - _Requirements: 1.1, 1.4, 1.5, 38.2, 38.3, 38.4_

  - [x] 1.3 Write smoke/static checks for connection routing, provisioning, and index existence
    - Assert central migrations run on the central connection and tenant migrations run on the tenant connection; provisioning a tenant creates the tenant schema in a separate database (R1.1)
    - Assert tenant identification by domain/subdomain switches the default connection correctly, and required indexes exist in both the central and tenant schemas (R38.2–R38.4)
    - Assert the app boots with zero optional/AI configuration
    - _Requirements: 1.1, 38.2, 38.3, 38.4_

  - [x] 1.4 Configure stancl/tenancy tenant model, central/tenant connections, and tenancy bootstrappers
    - Define the `Tenant` model and central/tenant connection configuration; register the tenancy bootstrappers that switch Laravel's default database connection when a tenant is initialized
    - Tenant resolved from domain/subdomain switches the default DB connection to that tenant's database; there is no `tenant_id` column, no global Eloquent scope, and no `BelongsToTenant` trait (isolation is physical)
    - _Requirements: 1.1, 1.2_

  - [x] 1.5 Implement middleware pipeline: HTTPS, tenancy initialization, Sanctum, RBAC
    - Ordered middleware stack per design: HTTPS enforcement, then `stancl/tenancy` tenant identification/initialization (`InitializeTenancyByDomain` / `InitializeTenancyByDomainOrSubdomain`) that resolves the tenant from the subdomain/custom domain and switches the default connection (reject requests that resolve to no tenant), then Sanctum authentication, then RBAC
    - The central `platform.admin` route group runs WITHOUT tenancy initialization (operating against the central database for cross-tenant/platform work) and still authorizes via RBAC
    - _Requirements: 1.2, 1.3, 1.6, 36.1, 36.3_

  - [x] 1.6 Write property test for tenant isolation invariant (physical database separation)
    - **Property 1: Tenant isolation invariant (physical database separation)**
    - **Validates: Requirements 1.2, 1.3, 1.6**
    - Provision two tenant databases over a minimum of 100 generated iterations; assert a request initialized for tenant A cannot read or write any record in tenant B's database, while central/provisioning routes (run without tenancy initialization) can access cross-tenant data; otherwise access is confined to the active tenant database. Platform_Admin cross-tenant access only on central/provisioning routes or via audited impersonation

  - [x] 1.7 Implement Sanctum authentication and RBAC roles
    - Login issuing token on valid credentials; 401 + audited failed attempt on invalid; bcrypt/argon2 hashing; `GET /api/me` returning identity/roles/abilities; password reset email link
    - Seed spatie roles: Platform_Admin, Company_Admin, Company_Staff, Distributor, Dealer, Read-only; authorize every action against abilities; Read-only denies mutations
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 1.8 Write property test for password hashing
    - **Property 21: Password hashing**
    - **Validates: Requirements 7.3**
    - For any password, stored credential is a bcrypt/argon2 hash never equal to plaintext and verifies against the original

  - [x] 1.9 Write property test for RBAC authorization
    - **Property 20: RBAC authorization**
    - **Validates: Requirements 8.2, 8.3, 8.4, 36.3**
    - For any (role, action) pair, action executes iff role holds the ability; Read-only permitted views, denied all mutations

  - [x] 1.10 Wire audit logging and append-only audit model
    - Configure owen-it/laravel-auditing on tenant-owned models; audit login/logout/password-reset/OTP-verify; reject update/delete of audit entries
    - _Requirements: 7.6, 34.1, 34.2_

  - [x] 1.11 Write property test for append-only audit entries
    - **Property 9 (audit portion): Append-only ledgers reject mutation**
    - **Validates: Requirements 34.2**
    - For any existing `audit_log` entry, every update/delete attempt is rejected and the entry is unchanged

  - [x] 1.12 Write property test for audit coverage of data mutations
    - **Property 35: Audit coverage for data mutations**
    - **Validates: Requirements 34.1**
    - For any create/update/delete of a tenant-owned record, an audit entry captures actor, timestamp, affected record identity

- [x] 2. M1 Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. M1b — Provisioning (tenant-DB creation) & white-label
  - [x] 3.1 Implement TenantProvisioningService and platform.admin provisioning routes
    - Create and migrate a new tenant database (queued provisioning job via `stancl/tenancy`), then seed that tenant database's single Company record and initial Company_Admin user inside the tenant database
    - Record the Tenant, its domain, and a Subscription with the selected plan in the central database; return 409 on duplicate subdomain; audit the provisioning action on the central side
    - Thin controller under the `platform.admin` route group (runs without tenancy initialization) delegating to the service
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.2 Write unit tests for provisioning
    - Provisioning creates tenant+company+admin+subscription (R2.1, R2.2); duplicate subdomain returns 409 (R2.3); provisioning audited (R2.4)
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.3 Implement subdomain / custom-domain resolution via the central domains table
    - Resolve subdomain and custom domain to a tenant through the central `domains` table used by `stancl/tenancy` identification; reconcile against the authenticated user and reject mismatches
    - _Requirements: 3.4, 1.6_

  - [x] 3.4 Implement WhiteLabelService and tenant_settings
    - `tenant_settings` live in the tenant database (logo, brand name, colour theme, favicon, custom domain, outbound email sender identity) with indexes; `GET/PATCH /tenant/settings`
    - Apply updates even if the best-effort audit write fails; per-tenant mailer sender identity for outbound email
    - _Requirements: 3.1, 3.3, 3.5_

  - [x] 3.5 Implement frontend white-label theme loader (Next.js)
    - Fetch branding for the resolved domain and apply logo/theme/favicon/brand name on page load
    - _Requirements: 3.2, 37.4_

  - [x] 3.6 Implement Platform_Admin impersonation
    - Initialize the target tenant's database for the impersonated session and issue a short-lived scoped token bound to that tenant; audit each impersonation session
    - _Requirements: 8.6, 34.3_

  - [x] 3.7 Write unit test for impersonation token + audit
    - Impersonation issues a scoped token bound to target tenant and records the session in the audit log
    - _Requirements: 8.6, 34.3_

- [x] 4. M1b Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. M2 — Plans, subscriptions & quotas
  - [x] 5.1 Create plans, plan_limits, subscriptions migrations and models
    - `plans` (name, price>=0, billing_cycle monthly|yearly, is_active), `plan_limits` (key/value, 0/NULL = unlimited), `subscriptions` (status active|past_due|cancelled|trial, starts_at, ends_at, trial_ends_at) with indexes
    - Support quota keys and feature flags enumerated in R4.3/R4.5; starter defaults for provisioning
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [x] 5.2 Write unit test for starter-tier defaults
    - New starter tenant without overrides sets max_distributors=5, max_dealers=50, max_products=200, max_stock_points=3, max_users=10
    - _Requirements: 4.7_

  - [x] 5.3 Implement QuotaService with cached counts and limits
    - Cache per-tenant count + active limit per counted resource; invalidate on create/delete; treat 0/NULL as unlimited
    - Enforce count-vs-limit before creation returning 403 naming the exceeded quota, including `max_dealers_per_distributor` and `max_orders_per_month`
    - _Requirements: 5.1, 5.2, 5.3, 5.6, 10.4_

  - [x] 5.4 Write property test for quota comparison enforcement
    - **Property 15: Quota comparison enforcement**
    - **Validates: Requirements 4.4, 5.1, 5.2, 5.3, 10.4**
    - Creation permitted iff unlimited or count strictly less than limit; else 403 naming the quota; covers distributors, dealers (incl. per-distributor), products, stock points, users, monthly orders

  - [x] 5.5 Write property test for quota cache consistency
    - **Property 16: Quota cache consistency**
    - **Validates: Requirements 5.6**
    - After any create/delete sequence and invalidation, cached count equals actual DB count

  - [x] 5.6 Implement subscription-status gating and feature-flag guard
    - Deny creation of new tenant-owned resources with 403 when status is past_due/cancelled; deny disabled feature-flag modules with 403
    - _Requirements: 5.4, 5.7_

  - [x] 5.7 Write property test for subscription-status gating
    - **Property 17: Subscription-status gating**
    - **Validates: Requirements 5.7**
    - Creation denied with 403 iff status is past_due or cancelled

  - [x] 5.8 Write property test for feature-flag gating
    - **Property 19: Feature-flag gating**
    - **Validates: Requirements 5.4, 7.5, 23.2, 27.5, 30.6, 30.7**
    - Access permitted iff flag enabled; AI permitted iff both master switch and tenant flag enabled, else 403

  - [x] 5.9 Implement SubscriptionService plan change with usage protection and usage endpoint
    - Upgrade/downgrade updates subscription + audits; block downgrade that drops a limit below current usage (name violating resource); allow forced override with audited reason
    - `GET /usage` endpoint and dashboard widget reporting counts vs active limits
    - _Requirements: 6.1, 6.2, 6.3, 5.5, 34.3_

  - [x] 5.10 Write property test for downgrade usage protection
    - **Property 18: Downgrade usage protection**
    - **Validates: Requirements 6.2, 6.3**
    - Downgrade blocked (naming violating resource) iff target limit below current usage and not explicitly forced

- [x] 6. M2 Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. M3 — Catalog & inventory core
  - [x] 7.1 Implement distributors/dealers models, migrations, and management endpoints
    - `distributors` (belongs to one company, GSTIN, address, payment_terms json) and `dealers` (belongs to one distributor) with indexes; create endpoints enforcing quota and `max_dealers_per_distributor`
    - Payment_Terms updates stored and audited
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 7.2 Implement sign-up and prospect approval flow
    - Record prospect as pending; approve creates Distributor/Dealer + audit + account-created email; reject marks rejected + audit
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [x] 7.3 Write unit tests for prospect approve/reject flows
    - Pending capture, approve creates buyer + audit + email, reject marks rejected + audit
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [x] 7.4 Implement catalog hierarchy and products
    - `categories`, `collections`, `colours`, `products` (unique `sku` within the tenant database, tax type, uom) with indexes; create/update products within quota
    - _Requirements: 11.1, 11.2, 11.3_

  - [x] 7.5 Write unit test for SKU uniqueness scope
    - Duplicate SKU within a tenant database rejected; SKU uniqueness is per-tenant automatically because each tenant has its own database, so the same SKU is allowed across tenants
    - _Requirements: 11.1_

  - [x] 7.6 Implement active product attributes and catalog visibility
    - Per-buyer active-attribute configuration restricting visible Categories/Collections/Colours; show all when unconfigured; `GET /catalog` paginated and run against the active tenant database
    - _Requirements: 11.4, 11.5, 11.6_

  - [x] 7.7 Write property test for catalog visibility restriction
    - **Property 22: Catalog visibility restriction**
    - **Validates: Requirements 11.4, 11.5, 11.6**
    - Catalog contains only allowed attributes when configured; all tenant products when unconfigured

  - [x] 7.8 Implement pricing and discount resolution
    - `prices` at product and collection levels; markup/MUP per collection; discount types `customer` and `online` only; order-line price resolver applying cumulative discounts in the tenant's single currency, never negative
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 39.1_

  - [x] 7.9 Write property test for pricing and discount calculation
    - **Property 23: Pricing and discount calculation**
    - **Validates: Requirements 12.4, 12.5, 39.1**
    - Computed price equals base with all applicable discounts cumulatively, never negative, in the tenant's single currency

  - [x] 7.10 Write unit test for discount-type enumeration rejection
    - Discount types other than `customer`/`online` are rejected
    - _Requirements: 12.3_

  - [x] 7.11 Implement stock ownership models (polymorphic) and migrations
    - `stock_points` (polymorphic owner Company/Distributor, sort order, display name), `stock` (polymorphic owner, product, stock_point, quantity DECIMAL(15,3), low_stock_threshold); dealers hold no stock unless dealer stock-holding enabled
    - Targeted indexes on stock tables per R38.4
    - _Requirements: 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 38.4_

  - [x] 7.12 Implement StockMovementService core: atomic FOR UPDATE locking + immutable ledger
    - Single DB transaction; lock every affected `stock` row with `SELECT ... FOR UPDATE` ordered deterministically by primary key to avoid deadlocks
    - Apply increments/decrements, write one immutable `stock_ledger` entry per movement with `balance_after` = post-movement quantity, movement type, reason, actor, timestamp, reference; roll back entirely on any failure
    - `stock_ledger` model rejects update/delete (immutable ledger)
    - _Requirements: 15.1, 15.2, 15.3, 16.4, 16.5, 35.1, 35.2, 35.3, 38.4_

  - [x] 7.13 Write property test for ledger balance-after invariant
    - **Property 3: Ledger balance-after invariant**
    - **Validates: Requirements 14.5, 15.1, 15.3**
    - Each ledger entry records signed delta and balance_after equal to the owner's quantity after that movement, with type/reason/actor/timestamp/reference

  - [x] 7.14 Write property test for append-only stock ledger
    - **Property 9 (stock-ledger portion): Append-only ledgers reject mutation**
    - **Validates: Requirements 15.2**
    - For any existing `stock_ledger` entry, every update/delete attempt is rejected and the entry is unchanged

  - [x] 7.15 Write property test for atomic all-or-nothing writes
    - **Property 6: Atomic all-or-nothing writes**
    - **Validates: Requirements 14.2, 16.4, 35.2, 35.3**
    - Any inventory operation failing before completion rolls back entirely; no stock or ledger change persists

  - [x] 7.16 Implement stock inward and manual adjustments via StockMovementService
    - Inward of type opening/received/adjustment-in increases stock + ledger in one transaction; manual adjustment requires non-empty reason (422 otherwise) + audit; `POST /stock/inward`, `POST /stock/adjust`
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 34.3_

  - [x] 7.17 Write property test for stock-adjustment reason validation
    - **Property 37: Stock-adjustment reason validation**
    - **Validates: Requirements 14.3, 14.4**
    - Adjustment accepted iff reason is a non-empty, non-whitespace string; else 422

  - [x] 7.18 Implement stock transfer between stock points of the same owner
    - Decrement source, increment destination, two ledger entries, one transaction via StockMovementService; `POST /stock/transfer`
    - _Requirements: 18.1_

  - [x] 7.19 Implement low-stock thresholds, alerts, and report/export
    - Per-(product, stock_point) threshold; raise alert when quantity <= threshold; `GET /stock/low` report listing all breaching products
    - _Requirements: 19.1, 19.2, 19.3_

  - [x] 7.20 Write property test for low-stock alerting and reporting
    - **Property 14: Low-stock alerting and reporting**
    - **Validates: Requirements 19.2, 19.3**
    - Alert raised and product appears in low-stock report iff quantity is at or below threshold

  - [x] 7.21 Write property test for stock movement correctness and conservation
    - **Property 2: Stock movement correctness and conservation**
    - **Validates: Requirements 13.6, 14.1, 16.1, 16.2, 16.3, 18.1, 18.3, 22.2**
    - Movements decrement seller and (where buyer holds stock) increment buyer by each line qty; no dealer stock when disabled; transfers conserve owner total

- [x] 8. M3 Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. M4 — Ordering & accounts (includes required concurrency release gate)
  - [x] 9.1 Implement cart and order/order_line models and migrations
    - `orders` (buyer/seller polymorphic, status, order_type, total, indexed) and `order_lines`; cart accumulates lines; submitting cart creates one order with one line per entry; distributor orders against company catalogue, dealer orders against serving distributor
    - Targeted indexes on order tables per R38.4
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 38.4_

  - [x] 9.2 Write property test for cart-to-order fidelity
    - **Property 12: Cart-to-order fidelity**
    - **Validates: Requirements 20.3, 20.4**
    - Submitting a cart of N entries creates exactly one order with N lines matching products and quantities

  - [x] 9.3 Implement OrderStateMachine transition map and transition endpoint
    - Declarative `{ fromState: { event: toState } }` map covering Draft→Placed→Confirmed→Dispatched→Delivered→Closed and Cancelled from pre-terminal states; reject undefined transitions with 422; seller approve/reject/edit while Placed; `POST /orders/{id}/transition`
    - _Requirements: 21.1, 21.2, 21.4_

  - [x] 9.4 Write property test for order state-machine transition legality
    - **Property 10: Order state-machine transition legality**
    - **Validates: Requirements 21.2, 21.4**
    - Transition accepted iff defined in the map for the current state; undefined transitions rejected with 422 leaving the order unchanged

  - [x] 9.5 Implement negative-stock prevention (all-or-nothing) and backorder toggle in StockMovementService
    - Pre-apply validation: if any line would drive stock below zero and backorders disabled, reject the whole operation (no stock/ledger change) with 422 listing insufficient products; with backorders enabled, proceed and record backordered quantity
    - _Requirements: 17.1, 17.2, 17.3_

  - [x] 9.6 Write property test for negative-stock prevention (single operation)
    - **Property 4: Negative-stock prevention is all-or-nothing (single operation)**
    - **Validates: Requirements 17.1, 17.2**
    - If any line would go below zero with backorders off, entire confirmation rejected and nothing persists; with backorders on, succeeds and recorded backorder equals the shortfall

  - [x] 9.7 Implement OrderConfirmationService (synchronous, transactional)
    - On confirm within one transaction: assert transition, build movements, apply via StockMovementService (FOR UPDATE, all-or-nothing), credit-limit check, synchronous invoice generation with seller series, append purchase to account ledger, set Confirmed; queue order-confirmed notification after commit
    - Distributor confirm decrements company + increments distributor; dealer confirm decrements distributor (+ increments dealer only when dealer stock enabled)
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 21.3, 21.5_

  - [x] 9.8 Implement per-seller invoice generation and series
    - `invoices` with per-seller series and strictly increasing numbers within each series; generated inside the confirmation transaction before response
    - _Requirements: 21.5_

  - [x] 9.9 Write property test for invoice generation on confirmation
    - **Property 11: Invoice generation on confirmation**
    - **Validates: Requirements 21.5**
    - Exactly one invoice per confirmed order using the seller's own series; numbers strictly increasing within each series

  - [x] 9.10 Implement manual payments and immutable transaction ledger
    - `payments`, `payment_allocations`, `transaction_ledger` (immutable, append-only, indexed); record payment (amount/method/reference/date/recorded-by) + ledger entry + running balance update; partial payments and allocation to one or more invoices; payment-recorded email; Outstanding_Balance = latest balance_after
    - _Requirements: 24.1, 24.2, 24.3, 24.4, 25.1, 25.2, 25.3, 38.4_

  - [x] 9.11 Write property test for transaction-ledger balance consistency
    - **Property 8: Transaction-ledger balance consistency**
    - **Validates: Requirements 22.3, 24.2, 25.1, 25.2**
    - Each entry's balance_after equals previous plus signed amount; Outstanding_Balance equals latest balance_after

  - [x] 9.12 Write property test for append-only transaction ledger
    - **Property 9 (transaction-ledger portion): Append-only ledgers reject mutation**
    - **Validates: Requirements 25.3**
    - For any existing `transaction_ledger` entry, every update/delete attempt is rejected and the entry is unchanged

  - [x] 9.13 Write property test for partial payment allocation
    - **Property 26: Partial payment allocation**
    - **Validates: Requirements 24.3**
    - Sum of allocations never exceeds payment amount; each targeted invoice's outstanding reduced by exactly its allocated amount

  - [x] 9.14 Implement credit and overdue enforcement
    - Per-buyer payment terms (credit limit, overdue balance limit, overdue hold days, payment type); block confirmation iff projected outstanding exceeds credit limit (only at confirmation time); flag invoices overdue beyond hold days; block new orders when overdue balance exceeds limit; authorized override with audited reason
    - _Requirements: 26.1, 26.2, 26.3, 26.4, 26.5_

  - [x] 9.15 Write property test for credit-limit enforcement at confirmation
    - **Property 24: Credit-limit enforcement at confirmation**
    - **Validates: Requirements 26.2**
    - Confirmation blocked iff O + T exceeds L, applied only at confirmation time

  - [x] 9.16 Write property test for overdue flagging and blocking
    - **Property 25: Overdue flagging and blocking**
    - **Validates: Requirements 26.3, 26.4**
    - Invoice flagged overdue iff age exceeds hold days; new orders blocked iff overdue balance exceeds limit

  - [x] 9.17 Write unit test for authorized override of blocked action
    - Authorized override with reason permits the blocked confirmation/order and is audited
    - _Requirements: 26.5, 34.3_

  - [x] 9.18 Implement order cancellation and returns reversal
    - Cancellation writes reversing ledger entries restoring confirmation quantities; returns re-enter returned quantities + ledger entries, adjust buyer account via transaction ledger, recompute Outstanding_Balance; `POST /orders/{id}/cancel`, `POST /orders/{id}/return`
    - _Requirements: 18.2, 18.3, 22.1, 22.2, 22.3_

  - [x] 9.19 Write property test for confirmation/cancellation round-trip
    - **Property 7: Confirmation/cancellation round-trip restores stock**
    - **Validates: Requirements 18.2, 22.1**
    - Cancelling a confirmed order writes reversing entries restoring every affected stock quantity to its exact pre-confirmation value

  - [x] 9.20 Wire ordering routes to confirmation/cancel/return/transition and notifications
    - `POST /orders`, `POST /orders/{id}/confirm` integrated with StockMovementService, invoices, account ledger, and queued notifications; thin controllers delegating to services
    - _Requirements: 16.1, 21.3, 21.5, 27.2, 27.3_

  - [x] 9.21 Write the required parallel concurrency test (release gate — no oversell)
    - **Property 5: No overselling under concurrency**
    - **Validates: Requirements 16.5, 35.1**
    - Integration-level stress test against a real MySQL instance within a single tenant's database (all separate connections initialized for the same tenant database): seed quantity S, fire N parallel confirmations whose demand exceeds S with backorders disabled; across >=100 randomized (S, N, demand) configs assert final quantity >= 0 and never observed negative, sum of successful quantities <= S, rejected confirmations applied zero changes, and successful count matches what S can satisfy. A regression allowing oversell fails the build.

- [x] 10. M4 Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. M5 — Reporting engine
  - [x] 11.1 Implement ReportQueryService shared query layer
    - `ReportQuerySpec` (type, filters, allow-listed grouping/sort, limit, page); tenant + role authorization scoping; default date range to previous calendar month when omitted; SQL-side aggregation with indexed, paginated queries selecting only needed columns; left-join/null-coalesce so missing/soft-deleted relations are omitted
    - _Requirements: 28.10, 29.1, 29.2, 29.3, 29.7, 38.1_

  - [x] 11.2 Write property test for default report date range
    - **Property 27: Default report date range**
    - **Validates: Requirements 29.2**
    - Omitted date range resolves to first-through-last day of the previous calendar month relative to request date

  - [x] 11.3 Write property test for report filter conformance and pagination
    - **Property 28: Report filter conformance and pagination**
    - **Validates: Requirements 29.1, 29.3**
    - Every returned row satisfies all filters; successive pages are non-overlapping, complete, consistently ordered slices

  - [x] 11.4 Write property test for report robustness to missing relations
    - **Property 30: Report robustness to missing relations**
    - **Validates: Requirements 29.7**
    - Reports with missing/soft-deleted relations complete without error and omit them

  - [x] 11.5 Write property test for tenant/role report scoping
    - **Property 1 (report scope portion): Tenant isolation invariant**
    - **Validates: Requirements 28.10**
    - Report results restricted to the user's tenant and role-permitted data

  - [x] 11.6 Implement standard report catalog
    - stock-on-hand (per owner/stock_point), stock-ledger, sales (period/distributor/dealer/product/category), purchases (period/buyer), outstanding-ageing (current/1-30/31-60/61-90/90+), payments/collections, order-status/delivery-challan listing, low-stock, plan-usage; `GET /reports/{type}`
    - _Requirements: 28.1, 28.2, 28.3, 28.4, 28.5, 28.6, 28.7, 28.8, 28.9_

  - [x] 11.7 Write property test for report aggregation correctness
    - **Property 29: Report aggregation correctness**
    - **Validates: Requirements 28.3, 28.5, 28.6**
    - Sales totals, payment/collection totals, and ageing bucket sums equal a straightforward reference implementation over the same data

  - [x] 11.8 Implement report caching and invalidation
    - Cache expensive aggregates per (tenant, report, filter) with short TTL; invalidate on relevant writes
    - _Requirements: 29.8_

  - [x] 11.9 Write property test for report cache consistency
    - **Property 31: Report cache consistency**
    - **Validates: Requirements 29.8**
    - A read following an invalidating write equals a fresh computation (no stale aggregates)

  - [x] 11.10 Implement exports and scheduled/subscription reports
    - `ReportExportService` to Excel/CSV/printable PDF gated by `exports` flag; large exports as queued streaming jobs returning a download link; scheduled reports default to previous month and email recipients; `POST /reports/export`, `POST /reports/schedule`
    - _Requirements: 29.4, 29.5, 29.6_

  - [x] 11.11 Write integration tests for async export and scheduled report
    - Streaming export produces a download link on completion (R29.5); scheduled report defaults to previous month and emails recipients (R29.6)
    - _Requirements: 29.5, 29.6_

- [x] 12. M5 Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. M5b — AI reporting layer (ships behind flags; app runs with zero AI config)
  - [x] 13.1 Implement LlmClient interface and provider resolution
    - `LlmClient.complete(prompt): string`; resolve from `LLM_PROVIDER` to OllamaClient / HttpLlmClient / NullLlmClient; read env keys (`AI_REPORTS_ENABLED` default false, `LLM_PROVIDER`, `LLM_MODEL`, `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_TIMEOUT`); NullLlmClient degrades gracefully; app builds with zero AI config
    - _Requirements: 30.1, 30.2, 30.3, 30.4, 30.5_

  - [x] 13.2 Write property test for LlmClient resolution
    - **Property 33: LlmClient resolution**
    - **Validates: Requirements 30.4, 30.5**
    - Provider config maps to the correct client; NullLlmClient degrades gracefully without error

  - [x] 13.3 Implement AI gating and AiReportService over ReportQueryService
    - Deny unless both `AI_REPORTS_ENABLED` and tenant `ai_reports` flag enabled (403 otherwise); map NL request to a `ReportQuerySpec`, validate against allow-listed schema (types, groupable/sortable columns) before execution, never execute raw SQL, reject on validation failure without executing; run validated spec through the same `ReportQueryService`; `POST /ai/reports`
    - _Requirements: 30.6, 30.7, 31.1, 31.2, 31.3, 31.4, 31.5, 31.6_

  - [x] 13.4 Write property test for AI query-spec allow-list validation
    - **Property 32: AI query-spec allow-list validation**
    - **Validates: Requirements 31.2, 31.4, 31.5**
    - Spec executes through the standard query layer iff it passes allow-list validation; raw-SQL attempts and invalid specs rejected and never executed

  - [x] 13.5 Write integration test that AI path uses ReportQueryService (no raw SQL)
    - With a stubbed LlmClient, the AI path calls `ReportQueryService` and never a raw-SQL path
    - _Requirements: 31.3_

  - [x] 13.6 Implement AI summaries, follow-ups, PII redaction, and audit
    - Summaries (takeaways/trends/outliers) and follow-ups computed solely from the returned dataset; send only minimum aggregated data with PII redacted; tenant+role scoped; audit every AI request; results through the standard render/export pipeline; `POST /ai/reports/{id}/summary`, `POST /ai/reports/{id}/followup`
    - _Requirements: 32.1, 32.2, 32.3, 32.4, 32.5, 31.6_

  - [x] 13.7 Write property test for AI data minimization and PII redaction
    - **Property 34: AI data minimization and PII redaction**
    - **Validates: Requirements 32.1, 32.2, 32.3**
    - Payload to the model contains only aggregated/derived data and no PII values; summaries/follow-ups computed solely from the dataset

  - [x] 13.8 Write property test for AI request audit coverage
    - **Property 35 (AI portion): Audit coverage for AI reporting**
    - **Validates: Requirements 32.5**
    - Every AI report/summary/follow-up request produces an audit entry

- [x] 14. M5b Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 15. M6 — Dashboards, notifications, advanced orders & hardening
  - [x] 15.1 Implement dashboards and inventory views
    - Company master dashboard metrics; stock-on-hand, item-detail, pending-orders view (Orders placed but not yet confirmed or fulfilled); dashboard/inventory export for Company_Admin where `exports` enabled without further authorization; `GET /dashboard`, `GET /inventory/views/{view}`
    - _Requirements: 33.1, 33.2, 33.3_

  - [x] 15.2 Implement delivery-challan printing
    - Delivery-challan generation/printing for orders; `GET /orders/{id}/challan`
    - _Requirements: 33.4, 28.7_

  - [x] 15.3 Implement notifications: email, in-app, optional SMS/WhatsApp
    - Queued email for account-created/order-placed/order-confirmed/payment-recorded; in-app notifications with history, mark-one-read, mark-all-read; optional SMS/WhatsApp channels where `whatsapp_notifications` enabled; `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`
    - _Requirements: 27.1, 27.2, 27.3, 27.4, 27.5, 27.6_

  - [x] 15.4 Write property test for notification read-state transitions
    - **Property 36: Notification read-state transitions**
    - **Validates: Requirements 27.6**
    - Mark-all-read leaves zero unread; marking one unread read decreases unread count by exactly one

  - [x] 15.5 Write unit tests for email side-effects
    - account-created (R27.1), order-placed (R27.2), order-confirmed (R27.3), payment-recorded (R27.4) emails are dispatched
    - _Requirements: 27.1, 27.2, 27.3, 27.4_

  - [x] 15.6 Implement stock reservations and advanced order types
    - `stock_reservations` reducing available (available = on-hand − active reservations) without moving owned quantity; blanket orders (scheduled releases), advance orders gated by `advance_orders` flag reserving future/blocked stock, depot orders/invoices at stock-point level; cancellation/expiry releases reservations
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

  - [x] 15.7 Write property test for stock reservation availability and round-trip
    - **Property 13: Stock reservation availability and round-trip**
    - **Validates: Requirements 23.4, 23.5**
    - Available equals on-hand minus active reservations; reserved quantity unavailable to others; reserve then cancel/expire restores available to prior value

  - [x] 15.8 Implement hardening: indexing audit, query efficiency, HTTPS, backups
    - Verify/define indexes on FKs and WHERE/JOIN/ORDER BY columns — per-tenant-database filter-column indexes within each tenant database and the central-database resolution indexes (tenant subdomain, domain resolution, plan limits, subscription status); enforce pagination/eager-loading/lookup caching; confirm HTTPS enforcement and daily backup schedule configuration; responsive branded UI pass
    - _Requirements: 38.1, 38.2, 38.3, 38.4, 38.5, 36.1, 36.2, 36.4, 37.4_

  - [x] 15.9 Write integration tests for performance and queueing
    - Heavy tasks dispatched to the Redis queue (R37.2); key interactive pages return under 2s under normal load (R37.1)
    - _Requirements: 37.1, 37.2_

  - [x] 15.10 Add automated accessibility scans
    - Run `axe` scans in CI on key branded pages toward WCAG 2.1 AA; note that full WCAG validation requires manual assistive-technology review
    - _Requirements: 37.5_

- [x] 16. M6 Final Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional test sub-tasks and can be skipped for a faster MVP; core implementation sub-tasks are never optional.
- Each of the 37 correctness properties maps to exactly one property-based test sub-task, tagged `Feature: inventory-distribution-platform, Property {number}: {property_text}`, running a minimum of 100 generated iterations with an existing PBT library (never from scratch). Property 1 and Property 9 and Property 35 each span more than one requirement area, so they appear as focused sub-tests placed near the relevant implementation.
- Task 9.21 is the required parallel concurrency test and acts as a release gate: a regression that allows oversell fails the build.
- All stock mutations route through `StockMovementService` (task 7.12); atomic `FOR UPDATE` locking, the immutable ledger, and all-or-nothing negative-stock logic are explicit tasks (7.12, 9.5, 9.6).
- Each task references specific requirement clauses for traceability; checkpoints provide incremental validation; no co-distributor functionality is included anywhere.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["1.3", "1.4"] },
    { "id": 3, "tasks": ["1.5", "1.7", "1.10"] },
    { "id": 4, "tasks": ["1.6", "1.8", "1.9", "1.11", "1.12"] },
    { "id": 5, "tasks": ["3.1", "3.3", "3.4", "3.6"] },
    { "id": 6, "tasks": ["3.2", "3.5", "3.7"] },
    { "id": 7, "tasks": ["5.1"] },
    { "id": 8, "tasks": ["5.3", "5.6", "5.9"] },
    { "id": 9, "tasks": ["5.2", "5.4", "5.5", "5.7", "5.8", "5.10"] },
    { "id": 10, "tasks": ["7.1", "7.4", "7.11"] },
    { "id": 11, "tasks": ["7.2", "7.6", "7.8", "7.12"] },
    { "id": 12, "tasks": ["7.16", "7.18", "7.19"] },
    { "id": 13, "tasks": ["7.3", "7.5", "7.7", "7.9", "7.10", "7.13", "7.14", "7.15", "7.17", "7.20", "7.21"] },
    { "id": 14, "tasks": ["9.1", "9.3", "9.5", "9.8", "9.10", "9.14"] },
    { "id": 15, "tasks": ["9.7", "9.18"] },
    { "id": 16, "tasks": ["9.20"] },
    { "id": 17, "tasks": ["9.2", "9.4", "9.6", "9.9", "9.11", "9.12", "9.13", "9.15", "9.16", "9.17", "9.19", "9.21"] },
    { "id": 18, "tasks": ["11.1"] },
    { "id": 19, "tasks": ["11.6", "11.8", "11.10"] },
    { "id": 20, "tasks": ["11.2", "11.3", "11.4", "11.5", "11.7", "11.9", "11.11"] },
    { "id": 21, "tasks": ["13.1"] },
    { "id": 22, "tasks": ["13.3", "13.6"] },
    { "id": 23, "tasks": ["13.2", "13.4", "13.5", "13.7", "13.8"] },
    { "id": 24, "tasks": ["15.1", "15.2", "15.3", "15.6", "15.8"] },
    { "id": 25, "tasks": ["15.4", "15.5", "15.7", "15.9", "15.10"] }
  ]
}
```
