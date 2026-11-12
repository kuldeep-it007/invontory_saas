import type { Branding, ThemeTokens } from "./types";

/**
 * Zero-config defaults (R37.4).
 *
 * When branding is unavailable (backend unreachable, host unresolved, or every
 * setting null) the app must still render a complete, responsive, branded-looking
 * UI. These defaults mirror the fallback CSS variables declared in
 * `globals.css`, so applying them is idempotent and the first paint already
 * looks correct before any fetch resolves.
 */
export const DEFAULT_BRAND_NAME = "Inventory Platform";

/** HSL channel values (no `hsl()` wrapper) matching globals.css. */
export const DEFAULT_THEME: Required<ThemeTokens> = {
  background: "0 0% 100%",
  foreground: "222 47% 11%",
  primary: "222 47% 31%",
  primaryForeground: "0 0% 100%",
  accent: "210 40% 96%",
  accentForeground: "222 47% 11%",
  muted: "210 40% 96%",
  mutedForeground: "215 16% 47%",
  border: "214 32% 91%",
  card: "0 0% 100%",
  cardForeground: "222 47% 11%",
  ring: "222 60% 45%",
  radius: "0.5rem",
};

export const DEFAULT_BRANDING: Branding = {
  brandName: DEFAULT_BRAND_NAME,
  logo: null,
  favicon: null,
  theme: {},
};
