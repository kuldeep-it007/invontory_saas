import type { ReactNode } from "react";

import { AppShell } from "@/components/shell/app-shell";

/**
 * Layout for all authenticated pages. The AppShell renders the sidebar and
 * guards the route group — redirecting unauthenticated users to /login.
 */
export default function AppGroupLayout({ children }: { children: ReactNode }): JSX.Element {
  return <AppShell>{children}</AppShell>;
}
