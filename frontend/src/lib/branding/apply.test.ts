import { describe, expect, it } from "vitest";

import { applyBrandingToDocument } from "./apply";
import { parseColorTheme } from "./resolve";
import { presetToColorTheme } from "./presets";

const brand = (colorTheme: string | null) => ({
  brandName: "Acme",
  logo: null,
  favicon: null,
  theme: parseColorTheme(colorTheme),
});

describe("applyBrandingToDocument", () => {
  it("applies a theme's colours to the page", () => {
    applyBrandingToDocument(brand(presetToColorTheme("rose")));
    expect(document.documentElement.style.getPropertyValue("--primary")).toBe("346 72% 40%");
    expect(document.documentElement.style.getPropertyValue("--ring")).toBe("346 70% 48%");
  });

  it("clears the previous theme when switching back to the built-in default", () => {
    applyBrandingToDocument(brand(presetToColorTheme("emerald")));
    expect(document.documentElement.style.getPropertyValue("--primary")).not.toBe("");

    applyBrandingToDocument(brand(null));
    expect(document.documentElement.style.getPropertyValue("--primary")).toBe("");
    expect(document.documentElement.style.getPropertyValue("--accent")).toBe("");
  });

  it("does not leave colours behind when switching to a theme that sets fewer", () => {
    applyBrandingToDocument(brand(presetToColorTheme("violet")));
    applyBrandingToDocument(brand("#1d4ed8")); // a single custom primary colour
    expect(document.documentElement.style.getPropertyValue("--primary")).toBe("224 76% 48%");
    expect(document.documentElement.style.getPropertyValue("--accent")).toBe("");
  });
});
