"use client";

import { useMutation, useQuery, useQueryClient, type UseQueryResult } from "@tanstack/react-query";

import {
  cartApi,
  catalogApi,
  dashboardApi,
  dealerApi,
  distributorApi,
  notificationApi,
  orderApi,
  priceApi,
  productApi,
  prospectApi,
  reportApi,
  settingsApi,
  stockApi,
  tenantUserApi,
  usageApi,
} from "./endpoints";
import type { CartBuyer, StockAllocation, TenantSettings, TenantUserInput } from "./types";
import { useSetBranding } from "@/components/branding/branding-provider";
import { resolveBranding } from "@/lib/branding/resolve";

/**
 * React Query hooks over the typed endpoints. Query keys are stable arrays so
 * mutations can invalidate precisely. All hooks are client-only.
 */

/* Dashboard & usage */
/**
 * Dashboard snapshot. The cache key includes WHO is looking (user id) and the
 * acting-as party, so a different account or party never reuses another's data.
 */
export const useDashboard = (
  userId?: number,
  party?: { buyer_type: string; buyer_id: number } | null,
) =>
  useQuery({
    queryKey: ["dashboard", userId ?? null, party?.buyer_type ?? null, party?.buyer_id ?? null],
    queryFn: () => dashboardApi.snapshot(party ?? undefined),
  });

export const useUsage = () => useQuery({ queryKey: ["usage"], queryFn: () => usageApi.index() });

/* Distributors */
export const useDistributors = (page = 1) =>
  useQuery({ queryKey: ["distributors", page], queryFn: () => distributorApi.list(page) });

/** A single distributor (for the edit page). */
export const useDistributor = (id: number | null) =>
  useQuery({
    queryKey: ["distributor", id],
    queryFn: () => distributorApi.get(id as number),
    enabled: id !== null && !Number.isNaN(id),
  });

export function useCreateDistributor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => distributorApi.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["distributors"] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

export function useUpdateDistributor(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => distributorApi.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["distributors"] });
      void qc.invalidateQueries({ queryKey: ["distributor", id] });
    },
  });
}

/* Dealers */
export const useDealers = (distributorId: number | null, page = 1) =>
  useQuery({
    queryKey: ["dealers", distributorId, page],
    queryFn: () => dealerApi.list(distributorId as number, page),
    enabled: distributorId !== null,
  });

/** A single dealer under a distributor (for the edit page). */
export const useDealer = (distributorId: number | null, dealerId: number | null) =>
  useQuery({
    queryKey: ["dealer", distributorId, dealerId],
    queryFn: () => dealerApi.get(distributorId as number, dealerId as number),
    enabled:
      distributorId !== null &&
      dealerId !== null &&
      !Number.isNaN(distributorId) &&
      !Number.isNaN(dealerId),
  });

export function useCreateDealer(distributorId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => dealerApi.create(distributorId, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["dealers", distributorId] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

export function useUpdateDealer(distributorId: number, dealerId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      dealerApi.update(distributorId, dealerId, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["dealers", distributorId] });
      void qc.invalidateQueries({ queryKey: ["dealer", distributorId, dealerId] });
    },
  });
}

/* Panel login users for distributors/dealers (R8.6) */

/** The distributor's panel login users. */
export const useDistributorUsers = (distributorId: number | null, page = 1) =>
  useQuery({
    queryKey: ["distributor-users", distributorId, page],
    queryFn: () => tenantUserApi.listForDistributor(distributorId as number, page),
    enabled: distributorId !== null && !Number.isNaN(distributorId),
  });

/** The dealer's panel login users (dealer nested under a distributor). */
export const useDealerUsers = (
  distributorId: number | null,
  dealerId: number | null,
  page = 1,
) =>
  useQuery({
    queryKey: ["dealer-users", distributorId, dealerId, page],
    queryFn: () => tenantUserApi.listForDealer(distributorId as number, dealerId as number, page),
    enabled:
      distributorId !== null &&
      dealerId !== null &&
      !Number.isNaN(distributorId) &&
      !Number.isNaN(dealerId),
  });

/** Create a login user for a distributor's panel; refresh its user list. */
export function useCreateDistributorUser(distributorId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: TenantUserInput) =>
      tenantUserApi.createForDistributor(distributorId, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["distributor-users", distributorId] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

/** Create a login user for a dealer's panel; refresh its user list. */
export function useCreateDealerUser(distributorId: number, dealerId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: TenantUserInput) =>
      tenantUserApi.createForDealer(distributorId, dealerId, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["dealer-users", distributorId, dealerId] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

/**
 * Reset a distributor panel user's password (Company-only, R7.3). The backend
 * gate is `users.manage`.
 */
export function useResetDistributorUserPassword(distributorId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, password }: { userId: number; password: string }) =>
      tenantUserApi.resetDistributorUserPassword(distributorId, userId, password),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["distributor-users", distributorId] });
    },
  });
}

/**
 * Reset a dealer panel user's password (R7.3). The backend gate is
 * `users.manage|dealers.manage`, so the Company or the owning Distributor may
 * do it.
 */
export function useResetDealerUserPassword(distributorId: number, dealerId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, password }: { userId: number; password: string }) =>
      tenantUserApi.resetDealerUserPassword(distributorId, dealerId, userId, password),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["dealer-users", distributorId, dealerId] });
    },
  });
}

/* Prospects */
export const useProspects = (page = 1) =>
  useQuery({ queryKey: ["prospects", page], queryFn: () => prospectApi.list(page) });

export function useProspectAction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, action }: { id: number; action: "approve" | "reject" }) =>
      action === "approve" ? prospectApi.approve(id) : prospectApi.reject(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["prospects"] });
      void qc.invalidateQueries({ queryKey: ["distributors"] });
    },
  });
}

/* Catalog & products */
export const useCatalog = (query?: Record<string, string | number>) =>
  useQuery({ queryKey: ["catalog", query], queryFn: () => catalogApi.catalog(query) });

export const useProducts = (page = 1) =>
  useQuery({ queryKey: ["products", page], queryFn: () => productApi.list(page) });

export const useCategories = () =>
  useQuery({ queryKey: ["categories"], queryFn: () => catalogApi.categories() });
export const useCollections = () =>
  useQuery({ queryKey: ["collections"], queryFn: () => catalogApi.collections() });
export const useColours = () =>
  useQuery({ queryKey: ["colours"], queryFn: () => catalogApi.colours() });

/** Create a category, then refresh the categories list. */
export function useCreateCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => catalogApi.createCategory(name),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["categories"] }),
  });
}

/** Create a collection under a category; refresh collections. */
export function useCreateCollection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ categoryId, name }: { categoryId: number; name: string }) =>
      catalogApi.createCollection(categoryId, name),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["collections"] }),
  });
}

/** Create a colour under a collection; refresh colours. */
export function useCreateColour() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ collectionId, name }: { collectionId: number; name: string }) =>
      catalogApi.createColour(collectionId, name),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["colours"] }),
  });
}

export function useCreateProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => productApi.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["products"] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

/** Update an existing product (name, price, SKU, catalog links, tax, uom). */
export function useUpdateProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      productApi.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["products"] });
    },
  });
}

export function useDeleteProduct() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => productApi.delete(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["products"] });
      void qc.invalidateQueries({ queryKey: ["usage"] });
    },
  });
}

/** Update a catalog attribute (rename/recode/activate), refreshing lists. */
export function useUpdateCatalogAttribute() {
  const qc = useQueryClient();
  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ["categories"] });
    void qc.invalidateQueries({ queryKey: ["collections"] });
    void qc.invalidateQueries({ queryKey: ["colours"] });
  };
  return {
    category: useMutation({
      mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
        catalogApi.updateCategory(id, payload),
      onSuccess: invalidate,
    }),
    collection: useMutation({
      mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
        catalogApi.updateCollection(id, payload),
      onSuccess: invalidate,
    }),
    colour: useMutation({
      mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
        catalogApi.updateColour(id, payload),
      onSuccess: invalidate,
    }),
  };
}

/* Pricing & discounts */
export const usePrices = (page = 1) =>
  useQuery({ queryKey: ["prices", page], queryFn: () => priceApi.prices(page) });
export const useDiscounts = (page = 1) =>
  useQuery({ queryKey: ["discounts", page], queryFn: () => priceApi.discounts(page) });

export function usePriceMutations() {
  const qc = useQueryClient();
  const invalidate = () => void qc.invalidateQueries({ queryKey: ["prices"] });
  return {
    create: useMutation({ mutationFn: priceApi.createPrice, onSuccess: invalidate }),
    remove: useMutation({ mutationFn: (id: number) => priceApi.deletePrice(id), onSuccess: invalidate }),
  };
}

export function useDiscountMutations() {
  const qc = useQueryClient();
  const invalidate = () => void qc.invalidateQueries({ queryKey: ["discounts"] });
  return {
    create: useMutation({ mutationFn: priceApi.createDiscount, onSuccess: invalidate }),
    update: useMutation({
      mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
        priceApi.updateDiscount(id, payload),
      onSuccess: invalidate,
    }),
    remove: useMutation({ mutationFn: (id: number) => priceApi.deleteDiscount(id), onSuccess: invalidate }),
  };
}

/** Delete a catalog attribute (category/collection/colour), refreshing lists. */
export function useDeleteCatalogAttribute() {
  const qc = useQueryClient();
  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ["categories"] });
    void qc.invalidateQueries({ queryKey: ["collections"] });
    void qc.invalidateQueries({ queryKey: ["colours"] });
  };
  return {
    category: useMutation({ mutationFn: (id: number) => catalogApi.deleteCategory(id), onSuccess: invalidate }),
    collection: useMutation({ mutationFn: (id: number) => catalogApi.deleteCollection(id), onSuccess: invalidate }),
    colour: useMutation({ mutationFn: (id: number) => catalogApi.deleteColour(id), onSuccess: invalidate }),
  };
}

/* Stock */
export const useLowStock = (userId?: number) =>
  useQuery({ queryKey: ["stock", "low", userId ?? null], queryFn: () => stockApi.low() });

/** The Company's stock points (for dropdowns + management). */
export const useStockPoints = () =>
  useQuery({ queryKey: ["stock-points"], queryFn: () => stockApi.points() });

/** The Company's per-(product, point) stock rows (stock-by-point view). */
export const useAllStock = () =>
  useQuery({ queryKey: ["stock", "all"], queryFn: () => stockApi.all() });

/** Distributor-owned stock across the tenant (Distributor Stock Management). */
export const useDistributorStock = (query?: Record<string, string | number>, userId?: number) =>
  useQuery({
    queryKey: ["stock", "distributors", userId ?? null, query ?? {}],
    queryFn: () => stockApi.distributorStock(query),
  });

/** Distributors for a dropdown — a single large page is enough for the selector. */
export const useDistributorOptions = () =>
  useQuery({ queryKey: ["distributors", "options"], queryFn: () => distributorApi.list(1, 100) });

/** Products for a dropdown — a single large page is enough for the selector. */
export const useProductOptions = () =>
  useQuery({ queryKey: ["products", "options"], queryFn: () => productApi.list(1, 100) });

export function useCreateStockPoint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { name: string; sort_order?: number }) => stockApi.createPoint(payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["stock-points"] }),
  });
}

/** Edit an existing stock point's name/sort order (R13.4, R13.8). */
export function useUpdateStockPoint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: { name?: string; sort_order?: number } }) =>
      stockApi.updatePoint(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["stock-points"] }),
  });
}

export function useStockMutation() {
  const qc = useQueryClient();
  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ["stock"] });
    void qc.invalidateQueries({ queryKey: ["stock-points"] });
    // Stock-derived figures on the dashboard must refresh too.
    void qc.invalidateQueries({ queryKey: ["dashboard"] });
  };
  return {
    addDistributorStock: useMutation({ mutationFn: stockApi.addDistributorStock, onSuccess: invalidate }),
    setThreshold: useMutation({
      mutationFn: ({ stockId, threshold }: { stockId: number; threshold: number | null }) =>
        stockApi.setThreshold(stockId, threshold),
      onSuccess: invalidate,
    }),
    setQuantity: useMutation({
      mutationFn: ({ stockId, quantity, reason }: { stockId: number; quantity: number; reason: string }) =>
        stockApi.setQuantity(stockId, { quantity, reason }),
      onSuccess: invalidate,
    }),
    inward: useMutation({ mutationFn: stockApi.inward, onSuccess: invalidate }),
    adjust: useMutation({ mutationFn: stockApi.adjust, onSuccess: invalidate }),
    transfer: useMutation({ mutationFn: stockApi.transfer, onSuccess: invalidate }),
  };
}

/* Shopping cart (R20.3) */
export const useCart = (buyer: CartBuyer | null) =>
  useQuery({
    queryKey: ["cart", buyer?.buyer_type, buyer?.buyer_id],
    queryFn: () => cartApi.show(buyer as CartBuyer),
    enabled: buyer !== null,
  });

export function useCartMutations(buyer: CartBuyer | null) {
  const qc = useQueryClient();
  const invalidate = () => {
    void qc.invalidateQueries({ queryKey: ["cart"] });
    void qc.invalidateQueries({ queryKey: ["usage"] });
  };
  const b = () => {
    if (!buyer) throw new Error("No buyer selected");
    return buyer;
  };
  return {
    add: useMutation({
      mutationFn: ({
        productId,
        quantity,
        allocations,
      }: {
        productId: number;
        quantity: number;
        allocations?: StockAllocation[];
      }) => cartApi.add(b(), productId, quantity, allocations),
      onSuccess: invalidate,
    }),
    update: useMutation({
      mutationFn: ({
        itemId,
        quantity,
        allocations,
      }: {
        itemId: number;
        quantity: number;
        allocations?: StockAllocation[];
      }) => cartApi.update(b(), itemId, quantity, allocations),
      onSuccess: invalidate,
    }),
    remove: useMutation({
      mutationFn: (itemId: number) => cartApi.remove(b(), itemId),
      onSuccess: invalidate,
    }),
    clear: useMutation({ mutationFn: () => cartApi.clear(b()), onSuccess: invalidate }),
    submit: useMutation({
      mutationFn: (orderType?: string) => cartApi.submit(b(), orderType),
      onSuccess: invalidate,
    }),
  };
}

/** Seller stock points + availability for a product (multi-point sourcing). */
export const useCartSourcing = (buyer: CartBuyer | null, productId: number | null) =>
  useQuery({
    queryKey: ["cart-sourcing", buyer?.buyer_type, buyer?.buyer_id, productId],
    queryFn: () => cartApi.sourcing(buyer as CartBuyer, productId as number),
    enabled: buyer !== null && productId !== null,
  });

/** A single order with its lines, product details and sourcing (order view). */
export const useOrder = (id: number | null) =>
  useQuery({
    queryKey: ["orders", "detail", id],
    queryFn: () => orderApi.get(id as number),
    enabled: id !== null,
  });

/* Order history (R20, R33) */
export const useOrders = (query?: Record<string, string | number>) =>
  useQuery({ queryKey: ["orders", query], queryFn: () => orderApi.list(query) });

/* Notifications */
export const useNotifications = (page = 1) =>
  useQuery({ queryKey: ["notifications", page], queryFn: () => notificationApi.list(page) });

export function useNotificationActions() {
  const qc = useQueryClient();
  const invalidate = () => void qc.invalidateQueries({ queryKey: ["notifications"] });
  return {
    read: useMutation({ mutationFn: (id: number) => notificationApi.read(id), onSuccess: invalidate }),
    readAll: useMutation({ mutationFn: () => notificationApi.readAll(), onSuccess: invalidate }),
  };
}

/* Settings */
export const useSettings = () =>
  useQuery({ queryKey: ["settings"], queryFn: () => settingsApi.show() });

export function useUpdateSettings() {
  const qc = useQueryClient();
  const setBranding = useSetBranding();
  return useMutation({
    mutationFn: settingsApi.update,
    onSuccess: (res) => {
      // Keep the settings form query fresh.
      void qc.invalidateQueries({ queryKey: ["settings"] });
      // Apply the just-saved white-label settings to the live portal/app
      // immediately — theme vars, brand name and favicon — instead of waiting
      // for a full host-based reload (R3.5).
      setBranding(resolveBranding(res.data));
    },
  });
}

/** Upload / remove the company logo or favicon; applies the new branding live. */
export function useBrandAssetMutations() {
  const qc = useQueryClient();
  const setBranding = useSetBranding();
  const onSuccess = (res: { data: TenantSettings }) => {
    void qc.invalidateQueries({ queryKey: ["settings"] });
    setBranding(resolveBranding(res.data));
  };
  return {
    upload: useMutation({
      mutationFn: ({ asset, file }: { asset: "logo" | "favicon"; file: File }) =>
        settingsApi.uploadAsset(asset, file),
      onSuccess,
    }),
    remove: useMutation({
      mutationFn: (asset: "logo" | "favicon") => settingsApi.removeAsset(asset),
      onSuccess,
    }),
  };
}

/* Reports */
export const useReport = (
  type: string,
  query?: Record<string, string | number>,
): UseQueryResult<Awaited<ReturnType<typeof reportApi.run>>> =>
  useQuery({ queryKey: ["report", type, query], queryFn: () => reportApi.run(type, query) });
