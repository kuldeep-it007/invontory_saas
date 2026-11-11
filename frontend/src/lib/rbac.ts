/**
 * Frontend mirror of the backend RBAC ability catalogue (see
 * `backend/app/Auth/Roles.php`). Used to restrict the UI to an impersonated
 * party's capabilities (R8.2, R8.4, R8.6): while a Company user "acts as" a
 * Distributor or Dealer, the nav and pages must show only what that role can do
 * — not the full Company_Admin surface. The backend remains the source of truth
 * for authorization; this only drives what the UI offers.
 */

/**
 * The buyer-portal abilities a Distributor sees while impersonated: browse the
 * dashboard and catalog, place orders, see its own orders, and manage its
 * dealers. Admin/config surfaces (catalog.manage, pricing, settings, users,
 * stock adjust, payments) are intentionally withheld — a buyer portal is for
 * ordering, not configuring the tenant (R8.2, R8.4, R8.6).
 */
export const DISTRIBUTOR_ABILITIES: string[] = [
  "dashboard.view",
  "catalog.view",
  // A distributor views (and, on the Distributor Stock page, manages) its OWN
  // stock; the backend confines every stock call to its own distributor.
  "inventory.view",
  "orders.view",
  "orders.create",
  // A distributor drives the lifecycle of the orders its dealers place against
  // ITS OWN stock (R21); the backend's seller-ownership check confines these to
  // the distributor's own orders, so it can never touch Company-stock orders.
  "orders.confirm",
  "orders.cancel",
  "dealers.view",
  "dealers.manage",
];

/**
 * The buyer-portal abilities a Dealer sees while impersonated: browse the
 * dashboard and catalog, place orders, and see its own orders. A Dealer manages
 * nothing (R8.1).
 */
export const DEALER_ABILITIES: string[] = [
  "dashboard.view",
  "catalog.view",
  "orders.view",
  "orders.create",
];

/**
 * The effective ability set for an impersonated party kind. Returns the
 * Distributor or Dealer ability list so the UI restricts to that role while
 * impersonating (R8.6).
 */
export function abilitiesForImpersonatedKind(kind: "distributor" | "dealer"): string[] {
  return kind === "distributor" ? DISTRIBUTOR_ABILITIES : DEALER_ABILITIES;
}
