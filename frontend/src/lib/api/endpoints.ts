import { api } from "./client";
import type {
  Cart,
  CartBuyer,
  SellerStockSourcing,
  StockAllocation,
  ImpersonationContext,
  ImpersonationTargets,
  CatalogAttribute,
  Collection,
  Colour,
  DashboardSnapshot,
  Dealer,
  Discount,
  Distributor,
  Identity,
  LoginResponse,
  NotificationList,
  Order,
  Paginated,
  Price,
  Product,
  Prospect,
  Resource,
  StockPoint,
  StockRow,
  TenantSettings,
  TenantUser,
  TenantUserInput,
  UsageSnapshot,
} from "./types";

/**
 * Typed endpoint functions grouped by domain. Each mirrors a route in
 * `backend/routes/api.php`. Thin — they only shape params and delegate to the
 * API client, so React Query hooks stay declarative.
 */

/* ─── Auth (R7, R8.5) ─────────────────────────────────────────────────────── */
export const authApi = {
  login: (login: string, password: string, deviceName = "web") =>
    api.post<LoginResponse>("/login", { login, password, device_name: deviceName }),
  logout: () => api.post<{ message: string }>("/logout"),
  me: () => api.get<Identity>("/me"),
  forgotPassword: (email: string) => api.post<{ message: string }>("/forgot-password", { email }),
};

/* ─── My profile ──────────────────────────────────────────────────────────── */
export const profileApi = {
  /** Upload the caller's profile picture (already cropped/resized in the browser). */
  uploadAvatar: (file: Blob, filename = "avatar.jpg") => {
    const form = new FormData();
    form.append("avatar", file, filename);
    return api.post<Resource<{ avatar: string | null }>>("/me/avatar", form);
  },
  removeAvatar: () => api.del<Resource<{ avatar: null }>>("/me/avatar"),
};

/* ─── Dashboard & usage (R33.1, R5.5) ─────────────────────────────────────── */
export const dashboardApi = {
  snapshot: (party?: { buyer_type: string; buyer_id: number }) =>
    api.get<Resource<DashboardSnapshot>>(
      "/dashboard",
      party ? { party_type: party.buyer_type, party_id: party.buyer_id } : undefined,
    ),
  view: (view: string, query?: Record<string, string | number>) =>
    api.get<Paginated<Record<string, unknown>>>(`/inventory/views/${view}`, query),
};

export const usageApi = {
  index: () => api.get<Resource<UsageSnapshot>>("/usage"),
};

/* ─── Distributors & dealers (R10) ────────────────────────────────────────── */
export const distributorApi = {
  list: (page = 1, perPage = 15) =>
    api.get<Paginated<Distributor>>("/distributors", { page, per_page: perPage }),
  get: (id: number) => api.get<Resource<Distributor>>(`/distributors/${id}`),
  create: (payload: Partial<Distributor> & Record<string, unknown>) =>
    api.post<Resource<Distributor>>("/distributors", payload),
  update: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Distributor>>(`/distributors/${id}`, payload),
};

export const dealerApi = {
  list: (distributorId: number, page = 1, perPage = 15) =>
    api.get<Paginated<Dealer>>(`/distributors/${distributorId}/dealers`, {
      page,
      per_page: perPage,
    }),
  get: (distributorId: number, dealerId: number) =>
    api.get<Resource<Dealer>>(`/distributors/${distributorId}/dealers/${dealerId}`),
  create: (distributorId: number, payload: Record<string, unknown>) =>
    api.post<Resource<Dealer>>(`/distributors/${distributorId}/dealers`, payload),
  update: (distributorId: number, dealerId: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Dealer>>(`/distributors/${distributorId}/dealers/${dealerId}`, payload),
};

/* ─── Panel login users for distributors/dealers (R7.1, R8.6, R10.3) ──────── */
export const tenantUserApi = {
  listForDistributor: (distributorId: number, page = 1, perPage = 15) =>
    api.get<Paginated<TenantUser>>(`/distributors/${distributorId}/users`, {
      page,
      per_page: perPage,
    }),
  createForDistributor: (distributorId: number, payload: TenantUserInput) =>
    api.post<Resource<TenantUser>>(`/distributors/${distributorId}/users`, payload),
  listForDealer: (distributorId: number, dealerId: number, page = 1, perPage = 15) =>
    api.get<Paginated<TenantUser>>(
      `/distributors/${distributorId}/dealers/${dealerId}/users`,
      { page, per_page: perPage },
    ),
  createForDealer: (distributorId: number, dealerId: number, payload: TenantUserInput) =>
    api.post<Resource<TenantUser>>(
      `/distributors/${distributorId}/dealers/${dealerId}/users`,
      payload,
    ),
  // Admin password resets (R7.3): Company resets a distributor user; Company or
  // the owning Distributor resets a dealer user. `password_confirmation` mirrors
  // Laravel's `confirmed` rule on the backend request.
  resetDistributorUserPassword: (
    distributorId: number,
    userId: number,
    password: string,
  ) =>
    api.patch<Resource<TenantUser>>(
      `/distributors/${distributorId}/users/${userId}/password`,
      { password, password_confirmation: password },
    ),
  resetDealerUserPassword: (
    distributorId: number,
    dealerId: number,
    userId: number,
    password: string,
  ) =>
    api.patch<Resource<TenantUser>>(
      `/distributors/${distributorId}/dealers/${dealerId}/users/${userId}/password`,
      { password, password_confirmation: password },
    ),
};

/* ─── Buyer-side panel: logged-in distributor/dealer self view (R8.6, R20) ── */
export const panelApi = {
  me: () => api.get<{ party_type: "distributor" | "dealer"; data: Distributor | Dealer }>(
    "/panel/me",
  ),
  ordersPlaced: (page = 1) =>
    api.get<Paginated<Order>>("/panel/orders/placed", { page }),
  ordersReceived: (page = 1) =>
    api.get<Paginated<Order>>("/panel/orders/received", { page }),
};

/* ─── Prospects (R9) ──────────────────────────────────────────────────────── */
export const prospectApi = {
  list: (page = 1) => api.get<Paginated<Prospect>>("/prospects", { page }),
  approve: (id: number) => api.post<Resource<Prospect>>(`/prospects/${id}/approve`),
  reject: (id: number) => api.post<Resource<Prospect>>(`/prospects/${id}/reject`),
  signUp: (payload: Record<string, unknown>) => api.post<Resource<Prospect>>("/sign-up", payload),
};

/* ─── Catalog & products (R11, R12) ───────────────────────────────────────── */
export const catalogApi = {
  catalog: (query?: Record<string, string | number>) =>
    api.get<Paginated<Product>>("/catalog", query),
  categories: () => api.get<Paginated<CatalogAttribute>>("/categories"),
  collections: () => api.get<Paginated<Collection>>("/collections"),
  colours: () => api.get<Paginated<Colour>>("/colours"),
  createCategory: (name: string) =>
    api.post<Resource<CatalogAttribute>>("/categories", { name }),
  createCollection: (categoryId: number, name: string) =>
    api.post<Resource<Collection>>("/collections", { category_id: categoryId, name }),
  createColour: (collectionId: number, name: string) =>
    api.post<Resource<Colour>>("/colours", { collection_id: collectionId, name }),
  updateCategory: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<CatalogAttribute>>(`/categories/${id}`, payload),
  updateCollection: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Collection>>(`/collections/${id}`, payload),
  updateColour: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Colour>>(`/colours/${id}`, payload),
  deleteCategory: (id: number) => api.del<{ message: string }>(`/categories/${id}`),
  deleteCollection: (id: number) => api.del<{ message: string }>(`/collections/${id}`),
  deleteColour: (id: number) => api.del<{ message: string }>(`/colours/${id}`),
};

/* ─── Pricing & discounts (R12) ───────────────────────────────────────────── */
export const priceApi = {
  prices: (page = 1) => api.get<Paginated<Price>>("/prices", { page }),
  createPrice: (payload: Record<string, unknown>) => api.post<Resource<Price>>("/prices", payload),
  deletePrice: (id: number) => api.del<{ message: string }>(`/prices/${id}`),

  discounts: (page = 1) => api.get<Paginated<Discount>>("/discounts", { page }),
  createDiscount: (payload: Record<string, unknown>) =>
    api.post<Resource<Discount>>("/discounts", payload),
  updateDiscount: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Discount>>(`/discounts/${id}`, payload),
  deleteDiscount: (id: number) => api.del<{ message: string }>(`/discounts/${id}`),
};

export const productApi = {
  list: (page = 1, perPage = 15) =>
    api.get<Paginated<Product>>("/products", { page, per_page: perPage }),
  create: (payload: Record<string, unknown>) => api.post<Resource<Product>>("/products", payload),
  update: (id: number, payload: Record<string, unknown>) =>
    api.patch<Resource<Product>>(`/products/${id}`, payload),
  delete: (id: number) => api.del<{ message: string }>(`/products/${id}`),
};

/* ─── Stock / inventory (R13, R14, R18, R19) ──────────────────────────────── */
export const stockApi = {
  low: () => api.get<Paginated<StockRow>>("/stock/low"),
  inward: (payload: Record<string, unknown>) => api.post<Resource<StockRow>>("/stock/inward", payload),
  adjust: (payload: Record<string, unknown>) => api.post<Resource<StockRow>>("/stock/adjust", payload),
  transfer: (payload: Record<string, unknown>) =>
    api.post<Resource<unknown>>("/stock/transfer", payload),
  /** Edit a stock row's on-hand quantity to an absolute value (reason required). */
  setQuantity: (stockId: number, payload: { quantity: number; reason: string }) =>
    api.patch<Resource<StockRow>>(`/stock/${stockId}/quantity`, payload),
  /** `null` clears the threshold. */
  setThreshold: (stockId: number, threshold: number | null) =>
    api.patch<Resource<StockRow>>(`/stock/${stockId}/threshold`, { low_stock_threshold: threshold }),
  availability: (stockId: number) =>
    api.get<Resource<{ on_hand: number; reserved: number; available: number }>>(
      `/stock/${stockId}/availability`,
    ),

  // Stock points + stock-by-point listing (R13.4, R14).
  points: () => api.get<Resource<StockPoint[]>>("/stock-points"),
  createPoint: (payload: { name: string; sort_order?: number }) =>
    api.post<Resource<StockPoint>>("/stock-points", payload),
  updatePoint: (id: number, payload: { name?: string; sort_order?: number }) =>
    api.patch<Resource<StockPoint>>(`/stock-points/${id}`, payload),
  all: () => api.get<Resource<StockRow[]>>("/stock"),

  // Distributor-owned stock across the tenant (R13.4, R13.5).
  distributorStock: (query?: Record<string, string | number>) =>
    api.get<Resource<StockRow[]>>("/stock/distributors", query),
  /** Add stock held by a specific distributor (owned by it, separate from Company stock). */
  addDistributorStock: (payload: Record<string, unknown>) =>
    api.post<Resource<unknown>>("/stock/distributors", payload),
};

/* ─── Orders (R16, R20, R21, R22) ─────────────────────────────────────────── */
export const orderApi = {
  list: (query?: Record<string, string | number>) =>
    api.get<Paginated<Order>>("/orders", query),
  get: (id: number) => api.get<Resource<Order>>(`/orders/${id}`),
  create: (payload: Record<string, unknown>) => api.post<Resource<Order>>("/orders", payload),
  transition: (id: number, event: string) =>
    api.post<Resource<Order>>(`/orders/${id}/transition`, { event }),
  confirm: (id: number) => api.post<Resource<Order>>(`/orders/${id}/confirm`),
  cancel: (id: number) => api.post<Resource<Order>>(`/orders/${id}/cancel`),
  return: (id: number, payload: Record<string, unknown>) =>
    api.post<Resource<Order>>(`/orders/${id}/return`, payload),
};

/* ─── Shopping cart (R20.3, R20.4) ────────────────────────────────────────── */
export const cartApi = {
  show: (buyer: CartBuyer) =>
    api.get<Resource<Cart>>("/cart", { buyer_type: buyer.buyer_type, buyer_id: buyer.buyer_id }),
  sourcing: (buyer: CartBuyer, productId: number) =>
    api.get<Resource<SellerStockSourcing>>("/cart/sourcing", {
      buyer_type: buyer.buyer_type,
      buyer_id: buyer.buyer_id,
      product_id: productId,
    }),
  add: (buyer: CartBuyer, productId: number, quantity: number, allocations?: StockAllocation[]) =>
    api.post<Resource<Cart>>("/cart/items", {
      ...buyer,
      product_id: productId,
      quantity,
      ...(allocations ? { allocations } : {}),
    }),
  update: (buyer: CartBuyer, itemId: number, quantity: number, allocations?: StockAllocation[]) =>
    api.patch<Resource<Cart>>(`/cart/items/${itemId}`, {
      ...buyer,
      quantity,
      ...(allocations ? { allocations } : {}),
    }),
  remove: (buyer: CartBuyer, itemId: number) =>
    api.del<Resource<Cart>>(`/cart/items/${itemId}`, { ...buyer }),
  clear: (buyer: CartBuyer) => api.del<Resource<Cart>>("/cart", { ...buyer }),
  submit: (buyer: CartBuyer, orderType = "standard") =>
    api.post<Resource<Order>>("/cart/submit", { ...buyer, order_type: orderType }),
};

/* ─── Party impersonation (R8.6) ──────────────────────────────────────────── */
export const impersonationApi = {
  targets: () => api.get<Resource<ImpersonationTargets>>("/impersonation/targets"),
  start: (targetType: "distributor" | "dealer", targetId: number) =>
    api.post<Resource<ImpersonationContext>>("/impersonation", {
      target_type: targetType,
      target_id: targetId,
    }),
};

/* ─── Notifications (R27.6) ───────────────────────────────────────────────── */
export const notificationApi = {
  list: (page = 1) => api.get<NotificationList>("/notifications", { page }),
  read: (id: number) => api.post<Resource<unknown>>(`/notifications/${id}/read`),
  readAll: () => api.post<Resource<unknown>>("/notifications/read-all"),
};

/* ─── Tenant settings / white-label (R3) ──────────────────────────────────── */
export const settingsApi = {
  show: () => api.get<Resource<TenantSettings>>("/tenant/settings"),
  update: (payload: Partial<TenantSettings>) =>
    api.patch<Resource<TenantSettings>>("/tenant/settings", payload),
  /** Upload the company logo or favicon (stored in object storage). */
  uploadAsset: (asset: "logo" | "favicon", file: File) => {
    const form = new FormData();
    form.append("file", file, file.name);
    return api.post<Resource<TenantSettings>>(`/tenant/settings/${asset}`, form);
  },
  removeAsset: (asset: "logo" | "favicon") =>
    api.del<Resource<TenantSettings>>(`/tenant/settings/${asset}`),
};

/* ─── Reports (R28, R29) ──────────────────────────────────────────────────── */
export const reportApi = {
  run: (type: string, query?: Record<string, string | number>) =>
    api.get<Paginated<Record<string, unknown>>>(`/reports/${type}`, query),
};
