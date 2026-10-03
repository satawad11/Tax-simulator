# Milestone 09 UI

The public and member interface follows `docs/ui-reference/tax-simulator-mockup.png`: blue and white surfaces, readable Thai typography, rounded cards, a visible wizard stepper, clear result hierarchy, and compact mobile navigation.

The implementation uses Blade, Tailwind CSS, vanilla JavaScript ES modules, `fetch()`, and the existing `/api/v1` APIs. It adds no frontend framework and contains no tax formulas. Guest financial state stays in `sessionStorage`; the backend remains authoritative for metadata, validation, calculation, guidance, planning, and persisted member records.

Public pages are `/`, `/tax-simulator`, `/tax-simulator/pnd90`, `/tax-simulator/pnd91`, `/knowledge`, `/news`, `/article/{slug}`, `/faq`, `/login`, and `/register`. Member shells are under `/dashboard`; JavaScript verifies the session token and all private data comes from Sanctum-protected APIs.

Responsive layouts target 375, 768, and 1280 CSS pixels. Forms collapse to a single column, navigation has an accessible toggle, focus indicators remain visible, result tables scroll within their container, and status text accompanies color.
