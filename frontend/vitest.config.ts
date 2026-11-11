import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

/**
 * Vitest configuration for the frontend test suite.
 *
 * The suite includes automated accessibility (a11y) scans (task 15.10,
 * R37.5). a11y scans run in a jsdom environment so axe-core can inspect the
 * rendered DOM. The `@/*` path alias mirrors `tsconfig.json` so tests import
 * app code the same way the app does.
 *
 * NOTE: Automated axe scans catch only a subset of WCAG 2.1 AA issues
 * (empirically ~30-40%). Full conformance still requires manual
 * assistive-technology review and expert evaluation.
 */
export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: "jsdom",
    setupFiles: ["./vitest.setup.ts"],
    include: ["src/**/*.{test,spec}.{ts,tsx}"],
    css: false,
  },
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
});
