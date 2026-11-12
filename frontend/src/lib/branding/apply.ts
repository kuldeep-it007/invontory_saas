import type { Branding, ThemeTokens } from "./types";

/**
 * Map a `ThemeTokens` object to the CSS custom properties Tailwind reads
 * (see `tailwind.config.ts`). Returned as a `React.CSSProperties`-compatible
 * record so it can be spread onto an element's `style` for server-rendered
 * first paint (R3.2 — theme applied on load, no flash).
 */
export function themeToCssVars(theme: ThemeTokens): Record<string, string> {
  const vars: Record<string, string> = {};
  const set = (name: string, value: string | undefined): void => {
    if (value && value.trim() !== "") vars[name] = value;
  };

  set("--background", theme.background);
  set("--foreground", theme.foreground);
  set("--primary", theme.primary);
  set("--primary-foreground", theme.primaryForeground);
  set("--accent", theme.accent);
  set("--accent-foreground", theme.accentForeground);
  set("--muted", theme.muted);
  set("--muted-foreground", theme.mutedForeground);
  set("--border", theme.border);
  set("--card", theme.card);
  set("--card-foreground", theme.cardForeground);
  set("--ring", theme.ring);
  set("--radius", theme.radius);

  return vars;
}

/** Every CSS variable a theme may set (kept in sync with themeToCssVars). */
const THEME_VARS = [
  "--background",
  "--foreground",
  "--primary",
  "--primary-foreground",
  "--accent",
  "--accent-foreground",
  "--muted",
  "--muted-foreground",
  "--border",
  "--card",
  "--card-foreground",
  "--ring",
  "--radius",
];

/**
 * Imperatively apply branding to the live document (client-side). Used by the
 * provider to re-apply theme/favicon when branding updates after hydration.
 * Safe to call with partial/empty branding — only present values are written,
 * so defaults in `globals.css` remain in force (R37.4).
 */
export function applyBrandingToDocument(branding: Branding): void {
  if (typeof document === "undefined") return;

  const root = document.documentElement;
  // Clear every theme variable first: otherwise switching to a theme that sets
  // fewer colours (or back to the built-in default) would keep the previous
  // theme's colours until a full reload.
  for (const name of THEME_VARS) root.style.removeProperty(name);
  const vars = themeToCssVars(branding.theme);
  for (const [name, value] of Object.entries(vars)) {
    root.style.setProperty(name, value);
  }

  if (branding.brandName) {
    document.title = branding.brandName;
  }

  if (branding.favicon) {
    applyFavicon(branding.favicon);
  }
}

/** Replace (or create) the favicon <link> so the tenant's icon is used (R3.2). */
function applyFavicon(href: string): void {
  const existing = document.querySelector<HTMLLinkElement>('link[rel~="icon"]');
  if (existing) {
    existing.href = href;
    return;
  }
  const link = document.createElement("link");
  link.rel = "icon";
  link.href = href;
  document.head.appendChild(link);
}
