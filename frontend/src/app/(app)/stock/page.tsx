import { redirect } from "next/navigation";

/**
 * The combined Stock screen was split into dedicated pages (Distributor Stock
 * Management, Stock Point Management, Stocks Management, Low Stock Report). This
 * legacy route redirects to Stocks Management so old links keep working.
 */
export default function StockIndexPage(): never {
  redirect("/stock/stocks");
}
