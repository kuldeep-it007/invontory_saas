import type { ReactNode } from "react";

import { AdminAuthProvider } from "@/components/providers/admin-auth-provider";

/**
 * Platform (superadmin) area layout. Wraps the central Platform_Admin pages in
 * their own auth provider — independent of the tenant auth/session, because the
 * superadmin operates on the central API with a separate token.
 */
export default function PlatformLayout({ children }: { children: ReactNode }): JSX.Element {
  return <AdminAuthProvider>{children}</AdminAuthProvider>;
}
