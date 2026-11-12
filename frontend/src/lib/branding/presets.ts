import type { ThemeTokens } from "./types";

/**
 * Built-in colour themes a Company Admin can pick from in Settings.
 *
 * Every preset defines the SAME complete set of colour tokens, so switching
 * from one theme to another never leaves a colour behind from the previous
 * theme. Values are raw HSL channels ("222 60% 36%") as the CSS variables
 * expect. All themes are light (white cards) because some UI elements use
 * fixed light colours; text/background pairs meet WCAG AA contrast (checked in
 * presets.test.ts).
 *
 * The chosen theme is saved in the tenant's `color_theme` setting as JSON:
 * `{"preset":"ocean", ...tokens}`. Storing the tokens (not just the id) means
 * the server-rendered first paint and the public branding endpoint need no
 * knowledge of this list, and a later change to a preset never silently
 * restyles a tenant until they choose it again.
 */
export interface ThemePreset {
  id: string;
  label: string;
  tokens: Required<Omit<ThemeTokens, "radius">>;
}

/** Shared light neutrals, tinted slightly towards each theme's hue. */
function neutrals(hue: number): Pick<
  ThemePreset["tokens"],
  "background" | "foreground" | "card" | "cardForeground" | "muted" | "mutedForeground" | "border"
> {
  return {
    background: `${hue} 33% 98%`,
    foreground: "222 47% 11%",
    card: "0 0% 100%",
    cardForeground: "222 47% 11%",
    muted: `${hue} 25% 94%`,
    mutedForeground: `${hue} 18% 38%`,
    border: `${hue} 22% 88%`,
  };
}

export const THEME_PRESETS: ThemePreset[] = [
  {
    id: "default",
    label: "Indigo (default)",
    tokens: {
      // Mirrors globals.css so "Indigo" looks exactly like the built-in look.
      background: "210 40% 98%",
      foreground: "222 47% 11%",
      card: "0 0% 100%",
      cardForeground: "222 47% 11%",
      primary: "222 60% 36%",
      primaryForeground: "0 0% 100%",
      accent: "222 44% 95%",
      accentForeground: "222 55% 28%",
      muted: "215 32% 94%",
      mutedForeground: "215 20% 42%",
      border: "215 25% 88%",
      ring: "222 60% 45%",
    },
  },
  {
    id: "ocean",
    label: "Ocean Blue",
    tokens: {
      ...neutrals(205),
      primary: "205 90% 32%",
      primaryForeground: "0 0% 100%",
      accent: "205 75% 95%",
      accentForeground: "205 85% 25%",
      ring: "205 85% 42%",
    },
  },
  {
    id: "teal",
    label: "Teal",
    tokens: {
      ...neutrals(180),
      primary: "180 75% 25%",
      primaryForeground: "0 0% 100%",
      accent: "178 50% 93%",
      accentForeground: "180 75% 20%",
      ring: "180 70% 33%",
    },
  },
  {
    id: "emerald",
    label: "Emerald",
    tokens: {
      ...neutrals(152),
      primary: "152 70% 26%",
      primaryForeground: "0 0% 100%",
      accent: "150 50% 93%",
      accentForeground: "152 70% 20%",
      ring: "152 60% 34%",
    },
  },
  {
    id: "amber",
    label: "Amber",
    tokens: {
      ...neutrals(36),
      primary: "28 85% 33%",
      primaryForeground: "0 0% 100%",
      accent: "40 90% 93%",
      accentForeground: "28 80% 25%",
      ring: "32 90% 42%",
    },
  },
  {
    id: "sunset",
    label: "Sunset Orange",
    tokens: {
      ...neutrals(18),
      primary: "14 78% 40%",
      primaryForeground: "0 0% 100%",
      accent: "18 85% 95%",
      accentForeground: "14 70% 30%",
      ring: "14 80% 48%",
    },
  },
  {
    id: "rose",
    label: "Rose",
    tokens: {
      ...neutrals(345),
      primary: "346 72% 40%",
      primaryForeground: "0 0% 100%",
      accent: "346 75% 96%",
      accentForeground: "346 65% 30%",
      ring: "346 70% 48%",
    },
  },
  {
    id: "violet",
    label: "Violet",
    tokens: {
      ...neutrals(262),
      primary: "262 55% 44%",
      primaryForeground: "0 0% 100%",
      accent: "262 65% 96%",
      accentForeground: "262 50% 32%",
      ring: "262 55% 52%",
    },
  },
  {
    id: "slate",
    label: "Slate",
    tokens: {
      ...neutrals(215),
      primary: "215 28% 25%",
      primaryForeground: "0 0% 100%",
      accent: "215 25% 93%",
      accentForeground: "215 28% 20%",
      ring: "215 20% 40%",
    },
  },
];

/** The `color_theme` value to save for a preset. */
export function presetToColorTheme(id: string): string | null {
  const preset = THEME_PRESETS.find((p) => p.id === id);
  return preset ? JSON.stringify({ preset: preset.id, ...preset.tokens }) : null;
}

/**
 * Which preset a saved `color_theme` value corresponds to: a preset id, "" when
 * nothing is set (built-in default), or "custom" for any other value.
 */
export function presetIdFromColorTheme(raw: string | null | undefined): string {
  if (!raw || raw.trim() === "") return "";
  try {
    const parsed = JSON.parse(raw) as { preset?: unknown };
    if (typeof parsed?.preset === "string" && THEME_PRESETS.some((p) => p.id === parsed.preset)) {
      return parsed.preset;
    }
  } catch {
    // Not JSON (e.g. a single colour) — a custom theme.
  }
  return "custom";
}
