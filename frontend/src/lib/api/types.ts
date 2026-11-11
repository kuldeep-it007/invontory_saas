/**
 * Shared API types mirroring the Laravel backend contracts.
 *
 * The backend returns two consistent envelopes:
 *   - Single resource:  `{ data: T }`
 *   - Paginated list:   Laravel paginator `{ data: T[], current_page, last_page,
 *                        per_page, total, ... }`
 *   - Error:            `{ error: { code, message, details } }`
 */

/** Standard error envelope returned by the centralized exception renderer. */
export interface ApiErrorBody {
  error: {
    code: string;
    message: string;
    details?: Record<string, unknown> | null;
  };
}

/** Thrown by the API client for any non-2xx response. */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details: Record<string, unknown> | null;

  constructor(
    status: number,
    code: string,
    message: string,
    details: Record<string, unknown> | null = null,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

/** Single-resource envelope. */
export interface Resource<T> {
  data: T;
}

/** Laravel length-aware paginator envelope. */
export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

/**
 * The buyer party a logged-in panel user is linked to (R8.6). Present only for
 * distributor/dealer panel users; null for company-side users.
 */
export interface IdentityParty {
  type: "distributor" | "dealer";
  id: number;
  name: string;
}

/** Authenticated identity (GET /me, and the `user` on login). */
export interface Identity {
  id: number;
  name: string;
  email: string;
  roles: string[];
  abilities: string[];
  /** The linked distributor FK, when this is a distributor panel user. */
  distributor_id?: number | null;
  /** The linked dealer FK, when this is a dealer panel user. */
  dealer_id?: number | null;
  /** Compact buyer-party block for panel auto-scoping, or null. */
  party?: IdentityParty | null;
}

/** A buyer-side panel login user managed under a Distributor or Dealer (R8.6). */
export interface TenantUser {
  id: number;
  name: string;
  email: string;
  distributor_id?: number | null;
  dealer_id?: number | null;
  created_at?: string | null;
}

/** Payload to create a panel login user. */
export interface TenantUserInput {
  name: string;
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: Identity;
}

/* ─── Domain models (listing-shape subsets of the backend models) ─────────── */

/**
 * Rosetta-style party profile shared by Distributors and Dealers (R10.1,
 * R10.2): logistics/terms, GST registration, and Income-Tax (PAN) details.
 */
export interface PartyProfile {
  territory_code?: string | null;
  billing_location_code?: string | null;
  shipment_method?: string | null;
  shipping_advice?: string | null;
  reserve?: string | null;
  gst_customer_type?: string | null;
  gst_registration_type?: string | null;
  gst_registration_no?: string | null;
  pan_status?: string | null;
  pan_no?: string | null;
  pan_reference_no?: string | null;
}

/** Credit / payment terms block carried on each Buyer (R10.1, R26.1). */
export interface PaymentTerms {
  credit_limit?: number | string | null;
  overdue_balance_limit?: number | string | null;
  overdue_hold_days?: number | null;
  payment_type?: string | null;
}

export interface Distributor {
  id: number;
  company_id: number;
  name: string;
  contact_name?: string | null;
  contact_email: string | null;
  contact_phone?: string | null;
  fax?: string | null;
  gstin: string | null;
  address?: string | null;
  profile?: PartyProfile | null;
  payment_terms?: PaymentTerms | null;
  dealers_count?: number;
  created_at: string | null;
}

export interface Dealer {
  id: number;
  distributor_id: number;
  name: string;
  contact_name?: string | null;
  contact_email: string | null;
  contact_phone?: string | null;
  fax?: string | null;
  gstin: string | null;
  address?: string | null;
  profile?: PartyProfile | null;
  payment_terms?: PaymentTerms | null;
  created_at: string | null;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  /** Base selling amount maintained with the product (R11.3); null if unset. */
  price: string | number | null;
  tax_type: string | null;
  uom: string | null;
  category_id: number | null;
  collection_id: number | null;
  colour_id: number | null;
  created_at: string | null;
}

export interface CatalogAttribute {
  id: number;
  code?: string | null;
  name: string;
  active?: boolean;
}

/** A collection belongs to a category (hierarchy level 2). */
export interface Collection {
  id: number;
  code?: string | null;
  name: string;
  active?: boolean;
  category_id: number;
  category?: CatalogAttribute;
}

/** A colour belongs to a collection (hierarchy level 3). */
export interface Colour {
  id: number;
  code?: string | null;
  name: string;
  active?: boolean;
  collection_id: number;
  collection?: Collection;
}

/** A configured price (product- or collection-level) with optional markup/MUP. */
export interface Price {
  id: number;
  level: "product" | "collection";
  ref_id: number;
  amount: string | number;
  markup_type: "markup" | "mup" | null;
  markup_value: string | number | null;
}

/** A configured discount (customer/online) with Rosetta-style catalog scope. */
export interface Discount {
  id: number;
  type: "customer" | "online";
  customer_type: string | null;
  percentage: string | number;
  buyer_type: string | null;
  buyer_id: number | null;
  category_id: number | null;
  collection_id: number | null;
  colour_id: number | null;
  is_active: boolean;
}

export interface StockRow {
  id: number;
  product_id: number;
  stock_point_id: number;
  quantity: string | number;
  low_stock_threshold: string | number | null;
  product?: Pick<Product, "id" | "sku" | "name">;
  stock_point?: { id: number; name: string };
  /** Polymorphic owner type FQCN, present on the distributor-stock view. */
  stockable_type?: string | null;
  /** Polymorphic owner id, present on the distributor-stock view. */
  stockable_id?: number | null;
  /** The eager-loaded owner (e.g. the distributor), on the distributor-stock view. */
  stockable?: { id: number; name: string } | null;
  /** The same owner as serialized by the API (the relation is named `owner`). */
  owner?: { id: number; name: string } | null;
}

/** A stock point owned by the Company (R13.4). */
export interface StockPoint {
  id: number;
  name: string;
  sort_order: number;
}

/** A minimal party (Distributor/Dealer/Company) as embedded on an order. */
export interface OrderParty {
  id: number;
  name?: string | null;
  subdomain?: string | null;
}

export interface OrderLineRow {
  id: number;
  product_id: number;
  quantity: string | number;
  unit_price: string | number;
  discount: string | number;
  /**
   * The ordered product. Listings carry `id`/`sku`/`name`; the single-order
   * view also carries the remaining product details below.
   */
  product?: {
    id: number;
    sku: string;
    name: string;
    price?: string | number | null;
    tax_type?: string | null;
    uom?: string | null;
    collection?: { id: number; name: string; category?: { id: number; name: string } | null } | null;
    colour?: { id: number; name: string } | null;
  } | null;
  /** Stock points this line is sourced from (single-order view only). */
  allocations?: {
    id: number;
    stock_point_id: number;
    quantity: string | number;
    stock_point?: { id: number; name: string } | null;
  }[];
}

export interface Order {
  id: number;
  /** Unique, ≥8-digit human-facing order number (R20). */
  order_number?: string | null;
  status: string;
  order_type: string | null;
  total: string | number;
  buyer_type: string | null;
  buyer_id: number | null;
  seller_type: string | null;
  seller_id: number | null;
  created_at: string | null;
  /** Eager-loaded relations present on history/detail responses. */
  buyer?: OrderParty | null;
  seller?: OrderParty | null;
  lines?: OrderLineRow[];
}

/* ─── Shopping cart (R20.3) ───────────────────────────────────────────────── */

/** A single cart line with its full resolved price breakdown. */
export interface CartItem {
  id: number;
  product_id: number;
  product: {
    id: number;
    sku: string;
    name: string;
    collection: string | null;
    category: string | null;
    colour: string | null;
  } | null;
  quantity: string;
  allocations: StockAllocation[] | null;
  rrp: string;
  discount_pct: string;
  unit_price: string;
  net_amount: string;
  tax_rate: string;
  tax_amount: string;
  total_amount: string;
}

/** Aggregate cart totals summary. */
export interface CartTotals {
  items: number;
  net_amount: string;
  tax_amount: string;
  total_amount: string;
  currency: string;
}

/** The buyer's open cart with its items and totals. */
export interface Cart {
  id: number;
  status: string;
  items: CartItem[];
  totals: CartTotals;
}

/** The buyer placing cart operations (Distributor or Dealer). */
export interface CartBuyer {
  buyer_type: "distributor" | "dealer";
  buyer_id: number;
}

/** A single per-stock-point source allocation for a cart/order line (R16). */
export interface StockAllocation {
  stock_point_id: number;
  quantity: number | string;
}

/** A seller stock point with its availability for a product. */
export interface SellerStockPoint {
  stock_point_id: number;
  name: string;
  on_hand: string;
  reserved: string;
  available: string;
}

/** The seller's stock points for a product (for multi-point sourcing). */
export interface SellerStockSourcing {
  seller_type: string;
  seller_id: number;
  points: SellerStockPoint[];
}

/* ─── Party impersonation (R8.6) ──────────────────────────────────────────── */

/** The acting-as buyer context returned when starting impersonation. */
export interface ImpersonationContext {
  buyer_type: "distributor" | "dealer";
  buyer_id: number;
  label: string;
  kind: "distributor" | "dealer";
  parent: { id: number; name: string } | null;
}

/** The parties the current actor may impersonate. */
export interface ImpersonationTargets {
  distributors: { id: number; name: string }[];
  dealers: { id: number; name: string; distributor_id: number }[];
}

export interface Prospect {
  id: number;
  name: string;
  contact_email: string | null;
  type: string | null;
  status: string;
  created_at: string | null;
}

export interface NotificationItem {
  id: number;
  type: string;
  title: string | null;
  body: string | null;
  read_at: string | null;
  created_at: string | null;
}

/** `GET /notifications`: items plus paging and the unread count under `meta`. */
export interface NotificationList {
  data: NotificationItem[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    unread_count: number;
  };
}

/* ─── Usage & dashboard ───────────────────────────────────────────────────── */

export interface UsageEntry {
  key: string;
  count: number;
  limit: number | null; // null/0 = unlimited
  unlimited: boolean;
}

export type UsageSnapshot = Record<string, UsageEntry> | UsageEntry[];

export interface DashboardSnapshot {
  [key: string]: unknown;
}

/* ─── Tenant settings (white-label) ───────────────────────────────────────── */

export interface TenantSettings {
  logo: string | null;
  brand_name: string | null;
  color_theme: string | null;
  favicon: string | null;
  custom_domain: string | null;
  mail_from_address: string | null;
  mail_from_name: string | null;
}
