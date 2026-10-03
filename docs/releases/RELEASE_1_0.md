# Release 1.0

Release 1.0 provides source-backed Thai personal income tax simulation for tax year 2568 with PND91 and the supported PND90 baseline. It includes Guest stateless calculation, Member drafts/history/snapshots, planning and recommendations, refund/payment guidance, public knowledge content, and an authorized CMS/tax-rule administration workflow.

Published rule version `2568.1` is immutable. `TaxCalculationService` is the shared authority for Guest, Member and planning flows. Browser JavaScript presents API results and does not duplicate formulas.

Known guarded limitations remain: positive pension-insurance and social-security claims are blocked where source-backed limits are incomplete; unsupported allowance umbrella codes and credits return controlled 422 responses; unmodelled PND90 property/elected-out/dividend paths are excluded; domestic-travel and other source/schema gaps remain unavailable; legal final rounding is not asserted and `ROUNDING_RULE_PENDING` remains. See `docs/tax/TAX_ENGINE_BASELINE_2568.md` and the PND90/PND91 production baseline documents.

The final-gap pass adds a nullable disabled-person family/other-person discriminator and a
source-backed one-other-person limit in **draft** rule version `2568.2`. It is intentionally not
published and does not change Release 1.0 calculations on published `2568.1`. The web control is
metadata-driven and becomes available only when that rule is later reviewed and published.

M10 adds security headers, public calculation/planning rate limits, an audit query index, and removes protected database data from unauthenticated admin HTML. Admin and Member tokens are browser-session scoped and must be protected from script injection; the content pipeline rejects markup and renders escaped text.

Deploy only through `docs/ops/DEPLOYMENT.md`, after backup and production-safe seeding. Current Docker Compose is for development/verification, not a production orchestrator. Monitor 5xx/429 responses, authorization failures, calculation exceptions, admin publish activity, database health, latency, storage and backup jobs.

Form fidelity and the field-role audit were completed before final release verification. The final M10 re-run confirmed that M9.2 and M9.2.1 did not change the tax baseline, shared API contract, security boundary, or Guest/Member persistence behavior.

Repository operations now include a read-only readiness command, an isolated backup/restore drill,
and Chromium browser smoke automation. Safari, Firefox, physical-device and capacity evidence
remain external gates described in the final closure matrix.

## Final M10 re-run — verification record

This pass re-ran the production-readiness checks after the Milestone 09.1 UI reconciliation and
seeded development environment, alongside the M9.2 / M9.2.1 form-fidelity work. It added no tax
rule, no feature and no schema change.

Three defects were found and fixed during the pass, none of them in the tax engine:

- The ภ.ง.ด.90 minimum-tax row had lost its form-code guard during the result-page rewrite and
  would have appeared on a ภ.ง.ด.91 result. Guard restored and asserted.
- The result page overflowed a 375px viewport by 65px: a responsive grid with no base column
  count, and a flex item that could not shrink below its content. Both fixed and locked by
  `ResponsiveLayoutInvariantTest`.
- Validation messages reached the reader with the internal constant the backend prefixed them
  with (`REQUIRED_CHILD_BIRTH_ORDER: …`). The constant is now stripped; the Thai sentence remains.

The browser smoke could not run at all because Playwright's downloaded Chromium is glibc-linked
and the node image is Alpine. The image now installs Alpine's Chromium and points Playwright at
it, and the smoke is run against the built bundle rather than the dev server — see
`docs/qa/RELEASE_TEST_MATRIX.md`.

Verified in this pass: published baseline `2568.1` unchanged and immutable at the model layer;
Guest calculate and plan persist nothing; a Member draft round-trips every detailed income fact
(subtype, Table 2 activity, holding years, expense method, tax treatment) plus family, allowance,
donation and prepayment rows; guest and member are denied admin; cross-user reads and writes
return 404; draft content is absent from both the public API and the public page; `role` cannot be
mass-assigned at registration; content bodies reject markup; login throttles after six failures
and public calculation after twenty; and no rendered page leaks an internal enum, a validation
path or a debug marker.
