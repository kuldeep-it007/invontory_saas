"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

/**
 * React Query provider (design stack: React Query/SWR). One client per browser
 * session; created lazily in state so it is not shared across requests on the
 * server. Client components (e.g. a settings page that refetches branding) use
 * this to cache and revalidate tenant data.
 */
export function QueryProvider({ children }: { children: ReactNode }): JSX.Element {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60_000,
            refetchOnWindowFocus: false,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
