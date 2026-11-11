# Requirements Document

## Introduction

This document specifies the requirements for a production-grade, multi-tenant, white-label SaaS platform for inventory and distribution management. The platform models a three-tier distribution hierarchy (Company → Distributor → Dealer) in which inventory is managed entirely within the platform's own MySQL database. The platform is the single source of truth for all inventory, ordering, payment, and reporting data; it integrates with no external ERP or inventory system.

Each tenant contains exactly one Company that owns master inventory. A Company sells to many Distributors, and each Distributor sells to many Dealers. On confirmed orders, stock is deducted from the seller and (for distributor purchases) added to the buyer. Payments are recorded manually. Each selling relationship carries configurable payment terms. Every tenant subscribes to a plan with enforced usage quotas and optional feature flags.

The platform supports white-labeling per tenant, role-based access control, immutable audit and stock-movement ledgers, a reusable reporting engine with an optional AI layer, and manual payment/accounting workflows. Multi-tenancy uses a database-per-tenant architecture via `stancl/tenancy`: a single central database holds tenants, domains, plans, plan limits, subscriptions, and Platform_Admin users, and each tenant's business data lives in its own dedicated MySQL database (physical isolation, with no `tenant_id` discriminator column on tenant-owned tables). The technology stack is a Next.js frontend (App Router, React, TypeScript, Tailwind + shadcn/ui, React Query/SWR) and a Laravel + MySQL 8 backend with Redis for queues and cache, Sanctum/JWT authentication, spatie/laravel-permission for RBAC, owen-it/laravel-auditing for audit, and PhpSpreadsheet for exports.

The following open-question defaults are applied throughout this document:
- Dealers do not hold stock by default; dealer stock-holding is optional and off by default.
- Out-of-stock confirmations are rejected by default; backorders are togglable per tenant.
- Each tenant operates in a single currency.
- Plans and quotas are enforced now; billing is recorded manually in v1.
- Starter tier defaults: 5 distributors, 50 dealers, 200 products, 3 stock points, 10 users.

## Glossary

- **Platform**: The entire multi-tenant SaaS application that is the single source of truth for inventory, ordering, payments, and reporting.
- **Tenant**: An isolated customer workspace backed by its own dedicated MySQL database (physical isolation), provisioned and resolved via `stancl/tenancy`.
- **Central_Database**: The shared MySQL database that holds tenants, domains, plans, plan limits, subscriptions, and Platform_Admin users.
- **Tenant_Database**: The per-tenant MySQL database that holds all tenant-owned business data; tenant-owned tables carry no `tenant_id` discriminator because isolation is physical.
- **Company**: The single top-level organization within a tenant that owns master inventory. Exactly one Company exists per Tenant.
- **Distributor**: An organization that buys stock from the Company and sells to Dealers.
- **Dealer**: An organization that buys from a Distributor and consumes stock (does not hold stock by default).
- **Buyer**: Any entity placing an order (a Distributor buying from the Company, or a Dealer buying from a Distributor).
- **Seller**: Any entity fulfilling an order (the Company fulfilling a Distributor order, or a Distributor fulfilling a Dealer order).
- **Stock_Point**: A named physical or logical location, owned polymorphically by a Company or Distributor, that holds Stock.
- **Stock**: The quantity of a specific Product at a specific Stock_Point for a specific owner.
- **Stock_Ledger**: An immutable, append-only record of every stock movement.
- **Transaction_Ledger**: An immutable, append-only running account balance record per Buyer.
- **Order**: A purchase order placed by a Buyer against a Seller, containing one or more Order Lines.
- **Order_Line**: A single Product-and-quantity entry within an Order.
- **Payment**: A manually recorded payment applied against a Buyer's outstanding balance.
- **Payment_Terms**: The configurable credit limit, overdue balance limit, overdue hold days, and payment type stored on a Buyer record for a selling relationship.
- **Plan**: A subscription tier defining price, billing cycle, and usage quotas.
- **Plan_Limit**: A flexible key/value usage quota attached to a Plan.
- **Subscription**: The association of a Tenant to a Plan with a lifecycle status.
- **Feature_Flag**: A per-plan boolean that gates optional modules.
- **Platform_Admin**: A cross-tenant super administrator who provisions tenants and manages plans and white-labeling.
- **Company_Admin**: A tenant-scoped administrator managing products, stock, distributors, terms, pricing, users, and reports.
- **Company_Staff**: A tenant-scoped user who processes distributor orders, records payments, and adjusts stock.
- **Report_Engine**: The reusable, tenant-scoped, role-aware subsystem that executes filtered, paginated, indexed report queries and renders/exports results.
- **AI_Layer**: The optional natural-language reporting subsystem gated behind both an environment master switch and a tenant feature flag.
- **LlmClient**: An interface exposing `complete(prompt): string`, resolved from configuration to a concrete provider implementation.
- **Audit_Log**: The append-only record of security-relevant and data-mutation actions.
- **Outstanding_Balance**: A Buyer's confirmed purchases minus recorded payments, derived from the Transaction_Ledger running balance.

## Requirements

### Requirement 1: Multi-Tenant Isolation

**User Story:** As a Platform_Admin, I want strict tenant isolation, so that no tenant can access another tenant's data.

#### Acceptance Criteria

1. THE Platform SHALL store each Tenant's data in a dedicated Tenant_Database separate from every other Tenant's Tenant_Database and from the Central_Database.
2. WHEN a request is identified to a Tenant, THE Platform SHALL route all tenant-owned data access to that Tenant's Tenant_Database.
3. THE Platform SHALL confine a request initialized for one Tenant_Database so that the request cannot read or write any other Tenant's data.
4. IF a request's tenant identification is unresolved or does not match an initialized Tenant_Database, THEN THE Platform SHALL reject the request with an HTTP 403 response.
5. THE Platform SHALL enforce a unique constraint on `Tenant(subdomain)` in the Central_Database.
6. THE Platform SHALL maintain exactly one Company record within each Tenant_Database.
7. WHERE a user is a Platform_Admin, THE Platform SHALL permit cross-tenant access only on central provisioning or administration routes that run without tenant initialization, or via audited impersonation that initializes the target Tenant_Database, and SHALL otherwise confine that Platform_Admin to the active Tenant_Database.

### Requirement 2: Tenant Provisioning

**User Story:** As a Platform_Admin, I want to provision new tenants, so that new customers can begin using the Platform.

#### Acceptance Criteria

1. WHEN a Platform_Admin submits a valid tenant provisioning request, THE Platform SHALL create and migrate a new Tenant_Database and seed that database's single Company record and initial Company_Admin user.
2. WHEN a Tenant is provisioned, THE Platform SHALL record the Tenant, its domain, and a Subscription with a selected Plan in the Central_Database.
3. IF a provisioning request specifies a subdomain that already exists, THEN THE Platform SHALL reject the request with an HTTP 409 response and a descriptive message.
4. WHEN a Tenant is provisioned, THE Platform SHALL record the provisioning action in the Audit_Log.

### Requirement 3: White-Labeling

**User Story:** As a Company_Admin, I want to brand my tenant workspace, so that end users see my organization's identity.

#### Acceptance Criteria

1. THE Platform SHALL store per-Tenant white-label settings including logo, brand name, colour theme, favicon, custom subdomain or domain, and outbound email sender identity.
2. WHEN a user loads a tenant-branded page, THE Platform SHALL apply that Tenant's logo, brand name, colour theme, and favicon.
3. WHEN the Platform sends an outbound email for a Tenant, THE Platform SHALL use that Tenant's configured outbound email sender identity.
4. WHERE a Tenant has a configured custom domain, THE Platform SHALL serve that Tenant's workspace on that domain.
5. WHEN a Company_Admin updates white-label settings, THE Platform SHALL apply the update and SHALL record the change in the Audit_Log, applying the update regardless of whether the Audit_Log write succeeds.

### Requirement 4: Subscription Plans and Quotas

**User Story:** As a Platform_Admin, I want plans with usage quotas and feature flags, so that tenant usage is bounded and optional modules are controlled.

#### Acceptance Criteria

1. THE Platform SHALL represent each Plan with an identifier, name, a price greater than or equal to 0.0, billing cycle of `monthly` or `yearly`, and an `is_active` indicator.
2. THE Platform SHALL represent each Plan_Limit as a flexible key/value pair associated with a Plan.
3. THE Platform SHALL support the quota keys `max_distributors`, `max_dealers`, `max_dealers_per_distributor`, `max_products`, `max_stock_points`, `max_users`, `max_orders_per_month`, and `max_storage_mb`.
4. WHERE a quota value is `0` or `NULL`, THE Platform SHALL treat that quota as unlimited.
5. THE Platform SHALL support the feature flags `whatsapp_notifications`, `otp_login`, `advance_orders`, `exports`, `api_access`, and `ai_reports`.
6. THE Platform SHALL represent each Subscription with a `tenant_id`, `plan_id`, status of `active`, `past_due`, `cancelled`, or `trial`, `starts_at`, `ends_at`, and `trial_ends_at`.
7. WHEN a new Tenant is provisioned on the Starter tier without overriding quotas, THE Platform SHALL set `max_distributors` to 5, `max_dealers` to 50, `max_products` to 200, `max_stock_points` to 3, and `max_users` to 10.

### Requirement 5: Quota Enforcement

**User Story:** As a Company_Admin, I want quota enforcement, so that usage never exceeds the subscribed plan.

#### Acceptance Criteria

1. WHEN a user requests creation of a Distributor, Dealer, Product, Stock_Point, or User, THE Platform SHALL compare the current tenant count against the active Plan_Limit for that resource before creating the record.
2. IF the current count for a resource equals or exceeds its active Plan_Limit, THEN THE Platform SHALL reject the creation request with an HTTP 403 response and a descriptive message naming the exceeded quota.
3. WHEN a Buyer attempts to place an Order and the Tenant has already reached `max_orders_per_month` for the current calendar month, THE Platform SHALL reject the Order with an HTTP 403 response and a descriptive message.
4. WHERE a Feature_Flag for an optional module is disabled on the active Plan, THE Platform SHALL deny access to that module with an HTTP 403 response.
5. THE Platform SHALL expose a usage-versus-limits endpoint and a dashboard widget reporting current counts against active Plan_Limits for the Tenant.
6. THE Platform SHALL cache per-Tenant resource counts and limits and invalidate the cache when a counted resource is created or deleted.
7. WHEN a Subscription status is `past_due` or `cancelled`, THE Platform SHALL deny creation of new tenant-owned resources with an HTTP 403 response.

### Requirement 6: Plan Changes

**User Story:** As a Platform_Admin, I want to upgrade and downgrade tenant plans, so that subscriptions match customer needs.

#### Acceptance Criteria

1. WHEN a Platform_Admin assigns, upgrades, or downgrades a Tenant's Plan, THE Platform SHALL update the Tenant's Subscription and record the change in the Audit_Log.
2. IF a requested downgrade would set a Plan_Limit below the Tenant's current usage of that resource, THEN THE Platform SHALL block the downgrade with a descriptive message identifying the violating resource.
3. WHERE a Platform_Admin explicitly forces a downgrade that violates current usage, THE Platform SHALL apply the downgrade and record the forced override in the Audit_Log.

### Requirement 7: Authentication

**User Story:** As a user, I want secure authentication, so that only authorized users access the Platform.

#### Acceptance Criteria

1. WHEN a user submits a valid email or username with a correct password, THE Platform SHALL authenticate the user and issue a session token.
2. IF a user submits invalid credentials, THEN THE Platform SHALL reject the login with an HTTP 401 response and record the failed attempt in the Audit_Log.
3. THE Platform SHALL store user passwords hashed with bcrypt or argon2.
4. WHEN a user requests a password reset, THE Platform SHALL send a password reset link to the user's registered email address.
5. WHERE the `otp_login` Feature_Flag is enabled for the Tenant, THE Platform SHALL offer one-time-password login as an authentication option.
6. WHEN a user completes a security-relevant action including login, logout, password reset, or OTP verification, THE Platform SHALL record the action in the Audit_Log.

### Requirement 8: Role-Based Access Control

**User Story:** As a Company_Admin, I want role-based access control, so that each user performs only permitted actions.

#### Acceptance Criteria

1. THE Platform SHALL support the roles Platform_Admin, Company_Admin, Company_Staff, Distributor, Dealer, and Read-only.
2. WHEN any user action is requested, THE Platform SHALL authorize the action against the user's assigned permissions before executing it.
3. IF a user lacks permission for a requested action, THEN THE Platform SHALL reject the action with an HTTP 403 response.
4. WHERE a user holds the Read-only role, THE Platform SHALL permit viewing dashboards and reports and SHALL deny all mutation requests.
5. THE Platform SHALL expose an endpoint returning the authenticated user's identity, roles, and abilities.
6. WHERE a user is a Platform_Admin, THE Platform SHALL permit audited impersonation of a tenant user, and SHALL record each impersonation session in the Audit_Log.

### Requirement 9: Sign-Up and Prospect Approval

**User Story:** As a Company_Admin, I want to capture prospective buyer sign-ups and approve them, so that only vetted parties transact.

#### Acceptance Criteria

1. WHEN a prospect submits a sign-up request, THE Platform SHALL record the prospect with contact details and a status of pending.
2. WHEN a Company_Admin approves a pending prospect, THE Platform SHALL create the corresponding Distributor or Dealer and record the approval in the Audit_Log.
3. IF a Company_Admin rejects a pending prospect, THEN THE Platform SHALL mark the prospect rejected and record the rejection in the Audit_Log.
4. WHEN a Distributor or Dealer account is created from an approved prospect, THE Platform SHALL send an account-created email notification.

### Requirement 10: Distributor and Dealer Management

**User Story:** As a Company_Admin, I want to manage distributors and their dealers, so that the distribution network is maintained accurately.

#### Acceptance Criteria

1. WHEN a Company_Admin creates a Distributor with valid contact, GSTIN, address, and Payment_Terms, THE Platform SHALL create the Distributor under the Company.
2. WHEN a Distributor creates a Dealer with valid contact, GSTIN, address, and Payment_Terms, THE Platform SHALL create the Dealer under that Distributor.
3. THE Platform SHALL associate each Distributor with exactly one Company and each Dealer with exactly one Distributor.
4. WHERE `max_dealers_per_distributor` is set to a positive value, THE Platform SHALL reject creation of a Dealer that would exceed that limit for the Distributor with an HTTP 403 response.
5. WHEN a Company_Admin or Distributor updates a Buyer's Payment_Terms, THE Platform SHALL store the updated terms on the Buyer record and record the change in the Audit_Log.

### Requirement 11: Product Catalog

**User Story:** As a Company_Admin, I want a structured product catalog, so that products are organized and controlled per buyer.

#### Acceptance Criteria

1. THE Platform SHALL enforce a unique constraint on Product `sku` within each Tenant_Database.
2. THE Platform SHALL represent the catalog hierarchy as Category, Collection, and Colour, with Products carrying SKU, tax type, and unit of measure.
3. WHEN a Company_Admin creates or updates a Product within quota, THE Platform SHALL persist the Product with its Category, Collection, Colour, tax type, and unit of measure.
4. WHERE active product attributes are configured for a specific Distributor or Dealer, THE Platform SHALL restrict the Categories, Collections, and Colours available to that Buyer to the configured set.
5. WHEN a Buyer browses the catalog, THE Platform SHALL display only Products the Buyer is permitted to view based on active product attributes.
6. WHERE no active product attribute restriction is configured for a Buyer, THE Platform SHALL display all Products to that Buyer.

### Requirement 12: Pricing and Discounts

**User Story:** As a Company_Admin, I want configurable pricing and discounts, so that each buyer receives correct prices.

#### Acceptance Criteria

1. THE Platform SHALL support Prices defined at the Product and Collection levels.
2. THE Platform SHALL support markup or MUP configured per Collection.
3. THE Platform SHALL support discount types `customer` and `online`.
4. WHEN an Order_Line price is calculated, THE Platform SHALL apply the applicable Price and discounts for the Buyer and resolve all monetary values in the Tenant's single configured currency.
5. WHERE multiple discount types are simultaneously applicable to the same Buyer and Order_Line, THE Platform SHALL apply all applicable discounts cumulatively.

### Requirement 13: Inventory Ownership and Source of Truth

**User Story:** As a Company_Admin, I want inventory managed entirely within the Platform, so that the Platform is the single source of truth.

#### Acceptance Criteria

1. THE Platform SHALL store and manage all inventory data within the Platform's own MySQL database.
2. THE Platform SHALL manage inventory without calling any external ERP or inventory system.
3. THE Platform SHALL track Stock as a per-Product, per-Stock_Point quantity for each owner.
4. THE Platform SHALL permit a Company to own one or more Stock_Points.
5. THE Platform SHALL permit a Distributor to own one or more Stock_Points.
6. WHERE dealer stock-holding is disabled for the Tenant, THE Platform SHALL treat Dealers as consumers that hold no Stock.
7. WHERE dealer stock-holding is enabled for the Tenant, THE Platform SHALL permit a Dealer to own one or more Stock_Points.
8. THE Platform SHALL support multiple named Stock_Points per owner, each with a sort order and a display name.

### Requirement 14: Stock Inward and Adjustments

**User Story:** As a Company_Admin, I want to record stock inward and adjustments, so that quantities reflect real-world events.

#### Acceptance Criteria

1. WHEN a Company records stock inward of type opening, received, or adjustment-in, THE Platform SHALL increase the corresponding Stock quantity and write a Stock_Ledger entry within a single atomic database transaction.
2. IF the Stock quantity increase for a stock inward fails, THEN THE Platform SHALL roll back the associated Stock_Ledger entry and return an error.
3. WHEN a user records a manual stock adjustment, THE Platform SHALL require a non-empty reason and SHALL record the adjustment in the Audit_Log.
4. IF a manual stock adjustment is submitted without a reason, THEN THE Platform SHALL reject the adjustment with an HTTP 422 response.
5. WHEN a stock adjustment is applied, THE Platform SHALL write a Stock_Ledger entry capturing the adjustment.

### Requirement 15: Immutable Stock Ledger

**User Story:** As a Company_Admin, I want an immutable stock ledger, so that every movement is traceable and auditable.

#### Acceptance Criteria

1. WHEN any Stock quantity changes, THE Platform SHALL write a Stock_Ledger entry recording movement type, quantity, resulting balance, reason, actor, timestamp, and reference.
2. THE Platform SHALL treat Stock_Ledger entries as immutable and SHALL reject any update or deletion of an existing Stock_Ledger entry.
3. THE Platform SHALL set each Stock_Ledger entry's resulting balance equal to the owner's Stock quantity for that Product and Stock_Point after the movement is applied.

### Requirement 16: Order Confirmation Stock Movement

**User Story:** As a Company_Staff member, I want stock to move automatically on order confirmation, so that seller and buyer inventories stay accurate.

#### Acceptance Criteria

1. WHEN a Distributor order is confirmed, THE Platform SHALL decrement Company Stock by each Order_Line quantity, increment Distributor Stock by each Order_Line quantity, and write Stock_Ledger entries for each movement.
2. WHEN a Dealer order is confirmed and dealer stock-holding is disabled, THE Platform SHALL decrement Distributor Stock by each Order_Line quantity and write Stock_Ledger entries for each decrement.
3. WHERE dealer stock-holding is enabled, WHEN a Dealer order is confirmed, THE Platform SHALL decrement Distributor Stock and increment Dealer Stock by each Order_Line quantity and write Stock_Ledger entries for each movement.
4. WHEN an order confirmation applies stock movements, THE Platform SHALL perform all Stock decrements, Stock increments, and Stock_Ledger writes within a single database transaction.
5. WHEN an order confirmation reads Stock rows for update, THE Platform SHALL acquire row-level locks using `SELECT ... FOR UPDATE` to prevent overselling under concurrency.

### Requirement 17: Negative Stock Prevention

**User Story:** As a Company_Admin, I want negative stock prevented, so that the Platform never oversells.

#### Acceptance Criteria

1. IF confirming an Order would reduce any Stock quantity below zero and backorders are disabled for the Tenant, THEN THE Platform SHALL reject the entire confirmation and apply no Stock changes.
2. WHERE backorders are enabled for the Tenant, THE Platform SHALL permit confirmation of an Order that reduces Stock below zero and record the resulting backordered quantity.
3. WHEN an order confirmation is rejected for insufficient stock, THE Platform SHALL return an HTTP 422 response identifying the insufficient Products.

### Requirement 18: Stock Transfer and Reversal

**User Story:** As a Company_Admin, I want stock transfers and reversals, so that inventory can be moved and corrected safely.

#### Acceptance Criteria

1. WHEN a user transfers Stock between two Stock_Points of the same owner, THE Platform SHALL decrement the source Stock, increment the destination Stock, and write Stock_Ledger entries for both movements within a single database transaction.
2. WHEN an Order is cancelled after confirmation, THE Platform SHALL write reversing Stock_Ledger entries that restore the Stock quantities affected by the confirmation.
3. WHEN a return is processed against a confirmed Order, THE Platform SHALL re-enter the returned quantities into Stock and write Stock_Ledger entries for the re-entry.

### Requirement 19: Low-Stock Thresholds and Alerts

**User Story:** As a Company_Admin, I want low-stock thresholds and alerts, so that I can replenish before stockouts.

#### Acceptance Criteria

1. THE Platform SHALL permit configuration of a low-stock threshold per Product and Stock_Point.
2. WHEN a Stock quantity falls to or below its configured low-stock threshold, THE Platform SHALL raise a low-stock alert.
3. THE Platform SHALL provide a report and export of all Products whose Stock is at or below the configured low-stock threshold.

### Requirement 20: Order Placement and Cart

**User Story:** As a Buyer, I want to build multi-line orders in a cart, so that I can purchase several products at once.

#### Acceptance Criteria

1. WHEN a Distributor places an Order, THE Platform SHALL create the Order against the Company catalogue.
2. WHEN a Dealer places an Order, THE Platform SHALL create the Order against the serving Distributor's Stock.
3. THE Platform SHALL support a cart that accumulates multiple Order_Lines before an Order is placed.
4. WHEN a Buyer submits a cart, THE Platform SHALL create one Order containing an Order_Line for each cart entry.

### Requirement 21: Order Lifecycle

**User Story:** As a Seller, I want a defined order lifecycle with approval, so that I control fulfillment.

#### Acceptance Criteria

1. THE Platform SHALL support the Order lifecycle states Draft, Placed, Confirmed, Dispatched, Delivered, Closed, and Cancelled.
2. WHILE an Order is in the Placed state, THE Platform SHALL permit the Seller to approve, reject, or edit the Order before confirmation.
3. WHEN a Seller confirms an Order, THE Platform SHALL validate available Stock and Payment_Terms limits before transitioning the Order to Confirmed.
4. IF a requested Order state transition is not permitted from the Order's current state, THEN THE Platform SHALL reject the transition with an HTTP 422 response.
5. WHEN an Order is confirmed, THE Platform SHALL generate an invoice using the Seller's own invoice series synchronously within the confirmation transaction before returning the confirmation response.

### Requirement 22: Order Cancellation and Returns

**User Story:** As a Seller, I want cancellations and returns to adjust stock and accounts, so that records stay correct.

#### Acceptance Criteria

1. WHEN an Order is cancelled, THE Platform SHALL reverse the Stock movements associated with that Order's confirmation.
2. WHEN a return is processed, THE Platform SHALL re-enter the returned quantities into Stock and adjust the Buyer's account by writing a Transaction_Ledger entry.
3. WHEN a return adjusts the Buyer's account, THE Platform SHALL recompute the Buyer's Outstanding_Balance.

### Requirement 23: Advanced Order Types

**User Story:** As a Seller, I want blanket, advance, and depot order types with reservations, so that varied fulfillment models are supported.

#### Acceptance Criteria

1. THE Platform SHALL support blanket orders that define scheduled releases.
2. WHERE the `advance_orders` Feature_Flag is enabled for the Tenant, THE Platform SHALL support advance orders placed against future or blocked Stock with a reservation.
3. THE Platform SHALL support depot orders and invoices recorded at the Stock_Point level.
4. WHEN an Order is in progress, THE Platform SHALL block the reserved Stock so that the reserved quantity is unavailable to other Orders.
5. WHEN an Order with blocked Stock is cancelled or expires, THE Platform SHALL release the blocked Stock back to available quantity.

### Requirement 24: Manual Payment Recording

**User Story:** As a Company_Staff member, I want to record payments manually, so that buyer balances reflect received funds.

#### Acceptance Criteria

1. WHEN an authorized user records a Payment with amount, method, reference, date, and recorded-by identity, THE Platform SHALL persist the Payment against the Buyer's outstanding balance.
2. WHEN a Payment is recorded, THE Platform SHALL write a Transaction_Ledger entry and update the Buyer's running balance.
3. THE Platform SHALL support partial Payments and allocation of a Payment to one or more invoices.
4. WHEN a Payment is recorded, THE Platform SHALL send a payment-recorded email notification.

### Requirement 25: Transaction Ledger and Outstanding Balance

**User Story:** As a Company_Admin, I want a per-buyer transaction ledger, so that outstanding balances are accurate and auditable.

#### Acceptance Criteria

1. WHEN a confirmed purchase or a Payment affects a Buyer's account, THE Platform SHALL append a Transaction_Ledger entry recording the amount and the resulting balance-after value.
2. THE Platform SHALL derive a Buyer's Outstanding_Balance from the latest Transaction_Ledger balance-after value.
3. THE Platform SHALL treat Transaction_Ledger entries as immutable and SHALL reject any update or deletion of an existing entry.

### Requirement 26: Credit and Overdue Enforcement

**User Story:** As a Company_Admin, I want credit and overdue limits enforced, so that buyers cannot exceed agreed terms.

#### Acceptance Criteria

1. THE Platform SHALL store per-Buyer Payment_Terms including credit limit, overdue balance limit, overdue hold days, and payment type.
2. IF an Order confirmation is requested and confirming the Order would cause the Buyer's projected Outstanding_Balance to exceed the credit limit, THEN THE Platform SHALL block the confirmation and return a descriptive message only at the time the confirmation is requested.
3. WHEN an invoice remains unpaid for more than the configured overdue hold days, THE Platform SHALL flag the invoice as overdue.
4. IF a Buyer's overdue balance exceeds the overdue balance limit, THEN THE Platform SHALL block the Buyer from placing new Orders with a descriptive message.
5. WHERE an authorized administrator records a temporary limit override with a reason, THE Platform SHALL permit the otherwise-blocked action and record the override in the Audit_Log.

### Requirement 27: Notifications

**User Story:** As a user, I want timely notifications, so that I stay informed of key events.

#### Acceptance Criteria

1. WHEN an account is created, THE Platform SHALL send an account-created email notification.
2. WHEN an Order is placed, THE Platform SHALL send an order-placed email notification.
3. WHEN an Order is confirmed, THE Platform SHALL send an order-confirmed email notification.
4. WHEN a Payment is recorded, THE Platform SHALL send a payment-recorded email notification.
5. WHERE the `whatsapp_notifications` Feature_Flag is enabled for the Tenant, THE Platform SHALL offer SMS and WhatsApp notification channels in addition to email.
6. THE Platform SHALL present in-app notifications with history and support marking a single notification read and marking all notifications read.

### Requirement 28: Standard Reports

**User Story:** As a Company_Admin, I want standard reports, so that I can analyze operations.

#### Acceptance Criteria

1. THE Report_Engine SHALL produce a stock-on-hand report per owner and Stock_Point.
2. THE Report_Engine SHALL produce a stock-ledger movement report.
3. THE Report_Engine SHALL produce sales reports grouped by period, Distributor, Dealer, Product, and Category.
4. THE Report_Engine SHALL produce purchase reports grouped by period and Buyer.
5. THE Report_Engine SHALL produce an outstanding-and-ageing report using the buckets current, 1-30, 31-60, 61-90, and 90-plus days.
6. THE Report_Engine SHALL produce payment and collection reports grouped by period.
7. THE Report_Engine SHALL produce order-status and delivery-challan listing reports.
8. THE Report_Engine SHALL produce a low-stock and threshold-breach report.
9. THE Report_Engine SHALL produce a plan-usage report per Tenant.
10. WHEN a report is generated, THE Report_Engine SHALL restrict results to the authenticated user's Tenant and to data permitted by the user's role.

### Requirement 29: Report Engine Behavior

**User Story:** As a Company_Admin, I want a reusable, efficient report engine, so that reports are consistent, fast, and exportable.

#### Acceptance Criteria

1. THE Report_Engine SHALL accept filters for date range, buyer or seller, Product or Category, Stock_Point, and status.
2. WHERE a report request omits a date range, THE Report_Engine SHALL default the date range to the previous calendar month.
3. WHEN a report is executed, THE Report_Engine SHALL paginate results, compute aggregates in SQL, and use indexed queries.
4. WHERE the `exports` Feature_Flag is enabled for the Tenant, THE Report_Engine SHALL export reports to Excel, CSV, and printable PDF formats.
5. WHEN an export is large, THE Report_Engine SHALL process the export as an asynchronous queued job that streams rows and provides a download link on completion.
6. WHEN a scheduled or subscription report is due, THE Report_Engine SHALL execute the report defaulting to the previous calendar month and email the result to the subscribed recipients.
7. IF a report references a missing or soft-deleted related record, THEN THE Report_Engine SHALL produce the report without error and silently omit the missing relation.
8. THE Report_Engine SHALL cache expensive aggregates per Tenant, report, and filter combination with a short time-to-live and invalidate the cache on relevant writes.

### Requirement 30: AI Reporting Enablement

**User Story:** As a Platform operator, I want AI reporting fully gated and environment-driven, so that the Platform runs with zero AI configuration by default.

#### Acceptance Criteria

1. THE AI_Layer SHALL default the environment master switch `AI_REPORTS_ENABLED` to `false`.
2. THE AI_Layer SHALL read configuration from the environment keys `AI_REPORTS_ENABLED`, `LLM_PROVIDER` with allowed values `ollama`, `openai_compatible`, or `none`, `LLM_MODEL`, `LLM_BASE_URL`, `LLM_API_KEY`, and `LLM_TIMEOUT`.
3. THE Platform SHALL build and run with zero AI configuration present.
4. THE AI_Layer SHALL expose a single `LlmClient` interface with the method `complete(prompt): string` resolved from configuration to an `OllamaClient`, `HttpLlmClient`, or `NullLlmClient` implementation.
5. WHERE `LLM_PROVIDER` is `none` or AI is disabled, THE AI_Layer SHALL resolve the `NullLlmClient` and degrade gracefully without error.
6. IF either the `AI_REPORTS_ENABLED` master switch is `false` or the Tenant's `ai_reports` Feature_Flag is disabled, THEN THE AI_Layer SHALL deny AI reporting requests.
7. WHERE both the `AI_REPORTS_ENABLED` master switch is `true` and the Tenant's `ai_reports` Feature_Flag is enabled, THE AI_Layer SHALL permit AI reporting requests.

### Requirement 31: Natural-Language Report Generation

**User Story:** As a Company_Admin, I want to generate reports from natural language, so that I can obtain insights without building filters manually.

#### Acceptance Criteria

1. WHEN a user submits a natural-language report request, THE AI_Layer SHALL map the request to a structured report query specifying type, filters, grouping, sort, and limit.
2. THE AI_Layer SHALL validate every generated report query against an allow-listed schema before execution.
3. THE AI_Layer SHALL execute the validated report query through the same tenant-scoped, indexed Report_Engine query layer used by standard reports.
4. THE AI_Layer SHALL never execute raw SQL produced by the language model.
5. IF a generated report query fails schema validation, THEN THE AI_Layer SHALL reject the request without executing any query.
6. WHEN a natural-language report completes, THE AI_Layer SHALL return results through the standard Report_Engine render and export pipeline.

### Requirement 32: AI Report Summarization and Guardrails

**User Story:** As a Company_Admin, I want AI summaries with strict guardrails, so that insights are safe and privacy-preserving.

#### Acceptance Criteria

1. WHEN a user requests an AI summary of a report, THE AI_Layer SHALL produce takeaways, trends, and outliers computed from the report dataset.
2. WHEN a user asks a follow-up question about a report, THE AI_Layer SHALL answer using only the computed dataset.
3. THE AI_Layer SHALL send only the minimum aggregated data required and SHALL redact personally identifiable information before transmission to the language model.
4. THE AI_Layer SHALL restrict AI reporting to the user's Tenant and role.
5. WHEN any AI reporting request is made, including report generation, summary, and follow-up requests, THE AI_Layer SHALL record the request in the Audit_Log.

### Requirement 33: Dashboards and Inventory Views

**User Story:** As a Company_Admin, I want dashboards and inventory views, so that I can monitor operations at a glance.

#### Acceptance Criteria

1. THE Platform SHALL present a company master dashboard summarizing key operational metrics for the Tenant.
2. THE Platform SHALL present a stock-on-hand view, an item-detail view, and a pending-orders view listing Orders that are placed but not yet confirmed or fulfilled.
3. WHERE the `exports` Feature_Flag is enabled, THE Platform SHALL permit any Company_Admin to export dashboard and inventory view data without further authorization checks.
4. THE Platform SHALL support delivery-challan printing for Orders.

### Requirement 34: Audit Trail

**User Story:** As a Company_Admin, I want a full audit history, so that all security and data changes are traceable.

#### Acceptance Criteria

1. WHEN a tenant-owned record is created, updated, or deleted, THE Platform SHALL record the change in the Audit_Log with actor, timestamp, and affected record identity.
2. THE Platform SHALL treat Audit_Log entries as append-only and SHALL reject modification of existing entries.
3. WHEN a limit override, impersonation, plan change, or stock adjustment occurs, THE Platform SHALL record the action and its reason in the Audit_Log.

### Requirement 35: Concurrency and Transactional Integrity

**User Story:** As a Company_Admin, I want correct behavior under concurrency, so that inventory and payments remain consistent.

#### Acceptance Criteria

1. WHEN multiple Order confirmations compete for the same Stock concurrently, THE Platform SHALL serialize access using row-level locking so that total confirmed quantity never exceeds available Stock when backorders are disabled.
2. WHEN an inventory or payment operation fails partway, THE Platform SHALL roll back the entire operation so that no partial changes persist.
3. THE Platform SHALL execute inventory and payment write operations within database transactions that are atomic and recoverable.

### Requirement 36: Security and Data Protection

**User Story:** As a Platform operator, I want strong security controls, so that tenant data is protected.

#### Acceptance Criteria

1. THE Platform SHALL serve all traffic over HTTPS.
2. THE Platform SHALL validate and sanitize all inputs and SHALL execute database access through parameterized queries.
3. WHEN any action is requested, THE Platform SHALL enforce RBAC authorization before performing the action.
4. THE Platform SHALL take daily backups of the database.

### Requirement 37: Performance, Availability, and Accessibility

**User Story:** As a user, I want a fast, available, and accessible Platform, so that I can work efficiently.

#### Acceptance Criteria

1. WHEN a user requests an interactive page, THE Platform SHALL return the page within 2 seconds under normal operating load.
2. THE Platform SHALL process heavy tasks through an asynchronous queue.
3. THE Platform SHALL maintain availability of at least 99.5 percent during business hours.
4. THE Platform SHALL present a responsive user interface that reflects the Tenant's branding.
5. THE Platform SHALL conform to WCAG 2.1 Level AA accessibility criteria.

### Requirement 38: Query Efficiency and Indexing

**User Story:** As a Platform operator, I want efficient indexed queries, so that the Platform scales horizontally and performs well.

#### Acceptance Criteria

1. WHEN querying tenant-owned data, THE Platform SHALL select only required columns, paginate results, eager-load related records, and cache lookup data.
2. THE Platform SHALL define MySQL indexes on all foreign keys and on columns used in WHERE, JOIN, and ORDER BY clauses.
3. THE Platform SHALL define filter-column indexes within each Tenant_Database on the columns used in WHERE, JOIN, and ORDER BY clauses, without any `tenant_id` discriminator column on tenant-owned tables.
4. THE Platform SHALL index Central_Database lookups including Tenant subdomain, domain resolution, plan limits, and subscription status.
5. THE Platform SHALL define targeted indexes on the Stock, Stock_Ledger, Order, and Transaction tables within each Tenant_Database.
6. THE Platform SHALL support horizontal scalability of the application tier.

### Requirement 39: Single Currency and Scope Boundaries

**User Story:** As a Company_Admin, I want clear scope boundaries, so that v1 behaves predictably.

#### Acceptance Criteria

1. THE Platform SHALL operate each Tenant in a single configured currency.
2. THE Platform SHALL record billing manually and SHALL not process automated billing in v1.
