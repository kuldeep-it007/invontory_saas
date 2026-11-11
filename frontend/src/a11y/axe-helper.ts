import { axe, type JestAxeConfigureOptions } from "jest-axe";

/**
 * Shared axe-core runner for accessibility scans (task 15.10, R37.5).
 *
 * Configures axe to run the WCAG 2.0/2.1 Level A and AA rule sets — the tags
 * that map to the "WCAG 2.1 Level AA" conformance target in R37.5. Individual
 * tests can override options (e.g. to scope rules) by passing their own.
 *
 * Reminder: axe-core finds roughly a third of accessibility barriers. A clean
 * scan is necessary but not sufficient for WCAG 2.1 AA — manual testing with
 * screen readers/keyboard and expert review remain required.
 */
export const WCAG_21_AA_TAGS = [
  "wcag2a",
  "wcag2aa",
  "wcag21a",
  "wcag21aa",
] as const;

export const defaultAxeOptions: JestAxeConfigureOptions = {
  runOnly: {
    type: "tag",
    values: [...WCAG_21_AA_TAGS],
  },
  rules: {
    /**
     * Colour-contrast (WCAG 1.4.3) cannot be measured reliably in jsdom:
     * it needs real rendered pixels (canvas) and computed styles that jsdom
     * does not implement. We disable it here to avoid false noise and leave
     * contrast verification to a real-browser pass (Playwright/manual review).
     */
    "color-contrast": { enabled: false },
  },
};

/**
 * Run an axe accessibility scan against a rendered DOM node using the
 * WCAG 2.1 AA rule set. Returns the axe results for assertion with the
 * `toHaveNoViolations()` matcher.
 */
export async function scanForA11y(
  container: Element,
  options: JestAxeConfigureOptions = defaultAxeOptions,
) {
  return axe(container, options);
}
