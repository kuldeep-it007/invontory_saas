import type { IconName } from "@/components/ui/icons";

/**
 * Sidebar navigation definition (DreamsPOS-style, hierarchical).
 *
 * The sidebar is organised into collapsible SECTIONS. A section is either a
 * flat list of links (e.g. "Main", "Catalog") or an expandable parent whose
 * children are dedicated pages/routes. Every link may declare the ability
 * required to see it, mirroring the route gates in the backend so the nav only
 * surfaces destinations the user can actually use (R8.2, R8.4); links with no
 * ability are visible to any authenticated user.
 *
 * Links may ALSO declare `actorKinds` to restrict a destination to a specific
 * kind of actor — the Company, a Distributor, or a Dealer. This drives the
 * role-based split of Stock Management and Order Management so each role sees
 * only its own stock and the order sections that apply to it:
 *   • Company   → its own stock (Stocks/Points/Low) + Sales Orders.
 *   • Distributor → its own stock (Distributor Stock) + Purchase & Sales Orders.
 *   • Dealer    → Purchase Orders only.
 *
 * Each module is a SEPARATE child route (one page per concern) so it is its own
 * navigable destination, never combined on one page.
 */

/** The kind of actor currently using the app (drives role-based nav). */
export type ActorKind = "company" | "distributor" | "dealer";

export interface NavLink {
  href: string;
  label: string;
  icon: IconName;
  /** Ability required to see this link; omitted = visible to any user. */
  ability?: string;
  /**
   * Actor kinds allowed to see this link; omitted = visible to every kind.
   * Used to confine stock/order destinations to the role they belong to.
   */
  actorKinds?: ActorKind[];
  /** Per-actor label overrides, e.g. a distributor sees "My Stock". */
  labelByKind?: Partial<Record<ActorKind, string>>;
}

/** The label to show for a link, honouring any per-actor override. */
export function navLabel(link: NavLink, actorKind: ActorKind): string {
  return link.labelByKind?.[actorKind] ?? link.label;
}

export interface NavSection {
  /** Section title shown as the group header / collapsible parent label. */
  title: string;
  /** Icon shown next to a collapsible parent (omitted for flat groups). */
  icon?: IconName;
  /** When true the section renders as an expand/collapse parent with children. */
  collapsible?: boolean;
  /** Whether a collapsible section starts expanded. */
  defaultOpen?: boolean;
  children: NavLink[];
}

export const NAV_SECTIONS: NavSection[] = [
  {
    title: "Main",
    children: [
      { href: "/dashboard", label: "Dashboard", icon: "dashboard", ability: "dashboard.view" },
      { href: "/usage", label: "Usage", icon: "gauge", ability: "settings.view" },
    ],
  },
  {
    title: "Catalog",
    children: [
      { href: "/catalog", label: "Catalog", icon: "box", ability: "catalog.view" },
      { href: "/catalog/categories", label: "Categories", icon: "tag", ability: "catalog.manage" },
      { href: "/catalog/collections", label: "Collections", icon: "layers", ability: "catalog.manage" },
      { href: "/catalog/colours", label: "Colours", icon: "palette", ability: "catalog.manage" },
      { href: "/pricing", label: "Pricing", icon: "dollar", ability: "catalog.manage" },
      { href: "/discounts", label: "Discounts", icon: "tag", ability: "catalog.manage" },
      { href: "/products", label: "Products", icon: "box", ability: "catalog.manage" },
    ],
  },
  {
    title: "Distributor & Inventory Management",
    icon: "warehouse",
    collapsible: true,
    defaultOpen: true,
    children: [
      // Company-only: managing the distributor network + the Company's own stock.
      { href: "/distributors", label: "Distributor Management", icon: "users", ability: "distributors.view", actorKinds: ["company"] },
      { href: "/stock/points", label: "Stock Point Management", icon: "warehouse", ability: "inventory.view", actorKinds: ["company"] },
      { href: "/stock/stocks", label: "Stocks Management", icon: "layers", ability: "inventory.view", actorKinds: ["company"] },
      { href: "/stock/low", label: "Low Stock Management Report", icon: "alert", ability: "inventory.view", actorKinds: ["company"] },
      // Distributor-only: a distributor's view of ITS OWN stock.
      { href: "/stock/distributors", label: "Distributor Stock Management", labelByKind: { distributor: "My Stock" }, icon: "box", ability: "inventory.view", actorKinds: ["company", "distributor"] },
    ],
  },
  {
    title: "Order Management",
    icon: "receipt",
    collapsible: true,
    defaultOpen: true,
    children: [
      { href: "/orders/cart", label: "Order Products", icon: "cart", ability: "orders.create", actorKinds: ["distributor", "dealer"] },
      // Sales Orders — orders placed AGAINST the actor's stock (they are the
      // seller): the Company's distributor orders, or a distributor's dealer
      // orders. Dealers hold no stock, so they have no Sales Orders.
      { href: "/orders/sales", label: "Sales Orders", icon: "receipt", ability: "orders.view", actorKinds: ["company", "distributor"] },
      // Purchase Orders — orders the actor PLACED (they are the buyer): a
      // distributor's orders to the Company, or a dealer's orders to its
      // distributor. The Company buys from no one, so it has no Purchase Orders.
      { href: "/orders/purchase", label: "Purchase Orders", icon: "receipt", ability: "orders.view", actorKinds: ["distributor", "dealer"] },
    ],
  },
  {
    title: "Operations",
    children: [
      { href: "/prospects", label: "Prospects", icon: "userPlus", ability: "distributors.manage", actorKinds: ["company"] },
      { href: "/reports", label: "Reports", icon: "chart", ability: "reports.view" },
    ],
  },
  {
    title: "Account",
    children: [
      { href: "/notifications", label: "Notifications", icon: "bell" },
      { href: "/settings", label: "Settings", icon: "settings", ability: "settings.view" },
    ],
  },
];

/** Every nav link, flattened — used for route-guard ability resolution. */
export const ALL_NAV_LINKS: NavLink[] = NAV_SECTIONS.flatMap((s) => s.children);

/**
 * The ability a given pathname requires, derived from the nav definition. Used
 * by the route guard to block direct navigation to a page the current (possibly
 * impersonated) user may not access. Matches the most specific nav href that is
 * a prefix of the path; returns null when no gated nav link matches (the page
 * is open to any authenticated user).
 */
export function requiredAbilityForPath(pathname: string): string | null {
  const match = matchNavLink(pathname);
  return match?.ability ?? null;
}

/**
 * The actor kinds allowed on a given pathname, derived from the nav definition.
 * Returns null when the matched destination (or no match) places no kind
 * restriction, so the route guard only blocks kinds that are explicitly scoped.
 */
export function allowedActorKindsForPath(pathname: string): ActorKind[] | null {
  const match = matchNavLink(pathname);
  return match?.actorKinds ?? null;
}

/** The most specific nav link whose href prefixes the given path. */
function matchNavLink(pathname: string): NavLink | undefined {
  return ALL_NAV_LINKS
    .filter((i) => pathname === i.href || pathname.startsWith(`${i.href}/`))
    // Prefer the longest (most specific) matching href.
    .sort((a, b) => b.href.length - a.href.length)[0];
}
