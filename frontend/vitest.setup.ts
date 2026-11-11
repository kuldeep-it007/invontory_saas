import "@testing-library/jest-dom/vitest";
import { afterEach, expect } from "vitest";
import { cleanup } from "@testing-library/react";
import { toHaveNoViolations } from "jest-axe";

/**
 * Global test setup.
 *
 * - Registers Testing Library's DOM matchers (`toBeInTheDocument`, etc.).
 * - Registers jest-axe's `toHaveNoViolations` matcher so accessibility
 *   scans read fluently: `expect(await axe(container)).toHaveNoViolations()`.
 *   The matcher is framework-agnostic and works unchanged under Vitest.
 * - Unmounts rendered components between tests to keep the jsdom document
 *   clean for each a11y scan.
 */
expect.extend(toHaveNoViolations);

afterEach(() => {
  cleanup();
});
