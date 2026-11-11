import { DEFAULT_BRAND_NAME } from "./defaults";
import type { Branding, TenantSettings, ThemeTokens } from "./types";

/**
 * Map the loosely-typed JSON theme object to our `ThemeTokens`. Accepts both
 * camelCase and the kebab/CSS-var names a backend might store, and ignores any
 * non-string values so a malformed payload can never throw (R37.4).
 */
function tokensFromObject(obj: Record<string, unknown>): ThemeTokens {
  const str = (v: unknown): string | undefined =>
    typeof v === "string" && v.trim() !== "" ? v.trim() : undefined;

  const pick = (...keys: string[]): string | undefined => {
    for (const k of keys) {
      const found = str(obj[k]);
      if (found !== undefined) return found;
    }
    return undefined;
  };

  const tokens: ThemeTokens = {
    background: pick("background", "--background"),
    foreground: pick("foreground", "--foreground"),
    primary: pick("primary", "--primary"),
    primaryForeground: pick("primaryForeground", "primary-foreground", "--primary-foreground"),
    accent: pick("accent", "--accent"),
    accentForeground: pick("accentForeground", "accent-foreground", "--accent-foreground"),
    muted: pick("muted", "--muted"),
    mutedForeground: pick("mutedForeground", "muted-foreground", "--muted-foreground"),
    border: pick("border", "--border"),
    radius: pick("radius", "--radius"),
  };

  // Drop undefined keys so callers only override what the tenant actually set.
  return Object.fromEntries(
    Object.entries(tokens).filter(([, v]) => v !== undefined),
  ) as ThemeTokens;
}

/**
 * Parse the backend `color_theme` string into theme tokens.
 *
 * Supported, in order:
 *  1. JSON object of theme tokens (preferred) → mapped via `tokensFromObject`.
 *  2. A single colour string (hex `#1d4ed8`, `rgb(...)`, or HSL channels) →
 *     treated as the `primary` colour.
 *  3. Anything unparseable → no tokens (defaults win). Never throws (R37.4).
 */
export function parseColorTheme(raw: string | null): ThemeTokens {
  if (!raw || raw.trim() === "") return {};

  const value = raw.trim();

  if (value.startsWith("{")) {
    try {
      const parsed = JSON.parse(value) as unknown;
      if (parsed && typeof parsed === "object") {
        return tokensFromObject(parsed as Record<string, unknown>);
      }
    } catch {
      // Fall through to single-colour handling / defaults.
    }
    return {};
  }

  // Treat a lone colour string as the primary brand colour.
  return { primary: value };
}

/**
 * Resolve a raw settings payload (or null) into a complete `Branding` object.
 * Null/empty fields fall back to defaults so the result is always renderable
 * (R3.2 applied values, R37.4 resilient boot).
 */
export function resolveBranding(settings: TenantSettings | null): Branding {
  if (!settings) {
    return { brandName: DEFAULT_BRAND_NAME, logo: null, favicon: null, theme: {} };
  }

  const brandName =
    settings.brand_name && settings.brand_name.trim() !== ""
      ? settings.brand_name.trim()
      : DEFAULT_BRAND_NAME;

  return {
    brandName,
    logo: settings.logo && settings.logo.trim() !== "" ? settings.logo.trim() : null,
    favicon: settings.favicon && settings.favicon.trim() !== "" ? settings.favicon.trim() : null,
    theme: parseColorTheme(settings.color_theme),
  };
}
