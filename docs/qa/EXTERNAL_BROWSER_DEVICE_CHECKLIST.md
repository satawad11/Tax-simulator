# External browser and device acceptance checklist

Use this checklist against the same release candidate and HTTPS staging URL. Record browser/device,
OS version, viewport, tester, timestamp, result, and evidence link for every run. Do not substitute
Playwright WebKit for real Safari acceptance.

## Required environments

- macOS Safari current supported version
- iPhone Safari on physical hardware, narrow viewport
- Android Chrome on physical hardware, narrow viewport
- Firefox desktop current supported version
- one tablet in portrait and landscape

## Flows

For each environment verify:

1. `/` loads with navigation, hero, form choices, content cards, and disclaimer.
2. `/tax-simulator/pnd91` completes the supported 40(1) flow; validation focuses the correct Thai-labelled field; result, trace, warning, and planning sections remain usable.
3. `/tax-simulator/pnd90` shows the correct subtype/activity/holding-period fields only when applicable; unsupported tax paths remain disabled and explained.
4. `/login` accepts a dedicated staging Member and routes to `/dashboard`; logout clears access.
5. `/dashboard` lists only that Member's records; a draft resumes with all saved conditional fields.
6. `/knowledge`, `/news`, an article, and `/faq` render without overflow or unsafe markup.
7. Keyboard focus is visible; the skip link, mobile navigation, labels, alerts, and disclosure controls are usable.
8. No page has horizontal viewport overflow; only wide steppers/tables may scroll inside their own container.
9. Browser console contains no uncaught error and network calls contain no unexpected 4xx/5xx response.
10. Simulator copy consistently says simulation/estimate and never claims an official filing.

## Release evidence

Acceptance is complete only when every required environment has a recorded PASS or an approved,
time-bounded exception. Until then QA-01, QA-02, and QA-03 remain
`BLOCKED_BY_EXTERNAL_ENVIRONMENT`.
