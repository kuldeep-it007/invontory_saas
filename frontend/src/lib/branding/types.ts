/**
 * White-label branding types (R3).
 *
 * `TenantSettings` mirrors the backend `GET /api/tenant/settings` payload:
 * `{ data: { logo, brand_name, color_theme, favicon, custom_domain,
 * mail_from_address, mail_from_name } }` where every key is nullable (null when
 * the tenant has not set it). `Branding` is the resolved, non-null shape the UI
 * consumes after defaults are applied (R37.4).
 */

/** Raw settings object returned by the backend; every field may be null. */
export interface TenantSettings {
  logo: string | null;
  brand_name: string | null;
  /**
   * Colour theme. The backend stores this as a free-form string; we accept
   * either a JSON object of theme tokens (preferred) or a single hex/HSL
   * primary colour. See `parseColorTheme`.
   */
  color_theme: string | null;
  favicon: string | null;
  custom_domain: string | null;
  mail_from_address: string | null;
  mail_from_name: string | null;
  /** Public branding only: the company's primary address, when it has chosen one. */
  canonical_host?: string | null;
}

/** The backend envelope wrapping the settings object. */
export interface TenantSettingsResponse {
  data: TenantSettings;
}

/**
 * A set of theme colour tokens expressed as raw HSL channels
 * ("222 47% 11%") so they drop straight into the CSS variables that Tailwind
 * reads (see `tailwind.config.ts`). All optional — missing tokens fall back to
 * the defaults baked into `globals.css`.
 */
export interface ThemeTokens {
  background?: string;
  foreground?: string;
  primary?: string;
  primaryForeground?: string;
  accent?: string;
  accentForeground?: string;
  muted?: string;
  mutedForeground?: string;
  border?: string;
  card?: string;
  cardForeground?: string;
  ring?: string;
  radius?: string;
}

/** Fully-resolved branding the UI renders. No null fields — defaults applied. */
export interface Branding {
  brandName: string;
  logo: string | null;
  favicon: string | null;
  theme: ThemeTokens;
  /** Host other addresses should redirect to; absent when no primary address is set. */
  canonicalHost?: string;
}
