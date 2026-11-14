import { describe, expect, it } from "vitest";

import { parseColorTheme, resolveBranding, toHslChannels } from "./resolve";

describe("toHslChannels", () => {
  it("converts hex colours to the HSL channels the theme expects", () => {
    expect(toHslChannels("#ffffff")).toBe("0 0% 100%");
    expect(toHslChannels("#000")).toBe("0 0% 0%");
    expect(toHslChannels("#ff0000")).toBe("0 100% 50%");
    expect(toHslChannels("#1d4ed8")).toBe("224 76% 48%");
  });

  it("leaves values that are already channels (or other formats) untouched", () => {
    expect(toHslChannels("222 47% 31%")).toBe("222 47% 31%");
    expect(toHslChannels("rgb(1, 2, 3)")).toBe("rgb(1, 2, 3)");
    expect(toHslChannels("fff")).toBe("fff");
  });
});

describe("parseColorTheme", () => {
  it("turns a single hex brand colour into a valid primary token", () => {
    expect(parseColorTheme("#b91c1c")).toEqual({ primary: "0 74% 42%" });
  });

  it("converts hex values inside a JSON theme but keeps the radius as given", () => {
    expect(parseColorTheme('{"primary":"#ff0000","radius":"1rem"}')).toEqual({
      primary: "0 100% 50%",
      radius: "1rem",
    });
  });
});

describe("resolveBranding", () => {
  it("passes the company logo and favicon through", () => {
    const b = resolveBranding({
      brand_name: "Acme Textiles",
      logo: "https://cdn.acme.test/logo.png",
      favicon: "https://cdn.acme.test/favicon.ico",
      color_theme: null,
      custom_domain: null,
      mail_from_address: null,
      mail_from_name: null,
    });
    expect(b).toMatchObject({
      brandName: "Acme Textiles",
      logo: "https://cdn.acme.test/logo.png",
      favicon: "https://cdn.acme.test/favicon.ico",
    });
  });
});

describe("resolveBranding canonical host", () => {
  const base = {
    logo: null,
    brand_name: null,
    color_theme: null,
    favicon: null,
    custom_domain: null,
    mail_from_address: null,
    mail_from_name: null,
  };

  it("carries the primary address so other hosts can redirect to it", () => {
    expect(resolveBranding({ ...base, canonical_host: "orders.example.com" }).canonicalHost).toBe("orders.example.com");
  });

  it("leaves canonicalHost out when no primary address is set", () => {
    expect(resolveBranding({ ...base, canonical_host: null })).not.toHaveProperty("canonicalHost");
    expect(resolveBranding(base)).not.toHaveProperty("canonicalHost");
  });
});
