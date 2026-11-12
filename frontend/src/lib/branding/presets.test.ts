import { describe, expect, it } from "vitest";

import { presetIdFromColorTheme, presetToColorTheme, THEME_PRESETS } from "./presets";
import { parseColorTheme } from "./resolve";

/** WCAG relative-luminance contrast ratio between two "h s% l%" colours. */
function contrast(a: string, b: string): number {
  const lum = (hsl: string): number => {
    const [h, s, l] = hsl.split(" ").map((v) => parseFloat(v));
    const sat = s / 100;
    const lig = l / 100;
    const k = (n: number) => (n + h / 30) % 12;
    const f = (n: number) => lig - sat * Math.min(lig, 1 - lig) * Math.max(-1, Math.min(k(n) - 3, 9 - k(n), 1));
    const lin = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    return 0.2126 * lin(f(0)) + 0.7152 * lin(f(8)) + 0.0722 * lin(f(4));
  };
  const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

describe("contrast helper (sanity)", () => {
  it("matches known WCAG reference values", () => {
    expect(contrast("0 0% 0%", "0 0% 100%")).toBeCloseTo(21, 1);
    expect(contrast("0 0% 100%", "0 0% 100%")).toBeCloseTo(1, 5);
    // #767676 on white is the classic 4.54:1 AA threshold colour.
    expect(contrast("0 0% 46.27%", "0 0% 100%")).toBeCloseTo(4.54, 1);
    // A failing pair must fail: light grey #aaaaaa on white is ~2.3:1.
    expect(contrast("0 0% 66.67%", "0 0% 100%")).toBeLessThan(4.5);
  });
});

describe("theme presets", () => {
  it("offers several themes with unique ids", () => {
    expect(THEME_PRESETS.length).toBeGreaterThanOrEqual(6);
    expect(new Set(THEME_PRESETS.map((p) => p.id)).size).toBe(THEME_PRESETS.length);
  });

  it("defines the same complete set of colours for every theme", () => {
    const keys = Object.keys(THEME_PRESETS[0].tokens).sort();
    for (const p of THEME_PRESETS) expect(Object.keys(p.tokens).sort()).toEqual(keys);
  });

  it.each(THEME_PRESETS.map((p) => [p.label, p] as const))("%s meets WCAG AA text contrast", (_label, p) => {
    const t = p.tokens;
    expect(contrast(t.primary, t.primaryForeground)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(t.foreground, t.background)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(t.cardForeground, t.card)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(t.accentForeground, t.accent)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(t.mutedForeground, t.background)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(t.mutedForeground, t.card)).toBeGreaterThanOrEqual(4.5);
  });

  it("round-trips a saved preset back to its id and its colours", () => {
    for (const p of THEME_PRESETS) {
      const saved = presetToColorTheme(p.id);
      expect(presetIdFromColorTheme(saved)).toBe(p.id);
      expect(parseColorTheme(saved)).toEqual(p.tokens);
    }
  });

  it("recognises 'not set' and custom values", () => {
    expect(presetIdFromColorTheme(null)).toBe("");
    expect(presetIdFromColorTheme("")).toBe("");
    expect(presetIdFromColorTheme("#b91c1c")).toBe("custom");
    expect(presetIdFromColorTheme('{"primary":"#ff0000"}')).toBe("custom");
    expect(presetIdFromColorTheme('{"preset":"no-such-theme"}')).toBe("custom");
  });
});
