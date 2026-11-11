# Automated accessibility scans

Automated accessibility (a11y) scanning for the white-label frontend
(task **15.10**, requirement **R37.5** — WCAG 2.1 Level AA).

## What this is

- **Tooling:** [`axe-core`](https://github.com/dequelabs/axe-core) run via
  [`jest-axe`](https://github.com/NickColley/jest-axe) inside the project's
  Vitest suite (`jsdom` environment), with
  [`@testing-library/react`](https://testing-library.com/) for rendering.
- **Scope:** key branded surfaces — the home dashboard shell and the
  `BrandLogo` component (text fallback and image variants). See
  `branded-ui.a11y.test.tsx`.
- **Rule set:** WCAG 2.0/2.1 Level A and AA tags (`wcag2a`, `wcag2aa`,
  `wcag21a`, `wcag21aa`). See `axe-helper.ts`.

## Running

```bash
cd frontend
npm run test        # full Vitest suite (includes a11y scans)
npm run test:a11y   # a11y scans only (src/a11y)
```

CI runs the scans on every push/PR that touches `frontend/**` via
`.github/workflows/frontend-a11y.yml`.

## Adding a scan for a new page/component

1. Render the component with `@testing-library/react` (wrap client components
   that need branding in `BrandingProvider`).
2. Run `scanForA11y(container)` from `./axe-helper`.
3. Assert `expect(results).toHaveNoViolations()`.

## Important limitation — this is not full WCAG conformance

Automated scans detect only a **subset** of accessibility barriers
(empirically about a third). A clean scan is necessary but **not sufficient**
for WCAG 2.1 AA. Full conformance still requires:

- Manual testing with assistive technologies (screen readers, keyboard-only
  navigation, zoom/reflow).
- Expert accessibility review.
- Colour-contrast verification in a real browser — the `color-contrast` rule
  is disabled in these jsdom scans because jsdom cannot measure rendered
  pixels; verify contrast via a real-browser pass or manual review.
