# Final gap closure matrix

Audit date: 2026-09-14. Scope: Release 1.x, tax year 2568. This matrix is authoritative for the
final-gap pass and supersedes the planning language in `REMAINING_IMPLEMENTATION_GAPS.md`.

Statuses are limited to `CLOSED`, `GUARDED`, `BLOCKED_BY_SOURCE`,
`BLOCKED_BY_EXTERNAL_ENVIRONMENT`, and `OUT_OF_SCOPE`.

## Backend

| Gap | Current status before pass | Can close now | Blocking dependency | Planned action | Final status | Evidence |
|---|---|---:|---|---|---|---|
| BE-01 pension insurance | Positive amount refused | No | Source does not settle how the 90,000 life-insurance remainder, 15%/200,000 pension limit, and 500,000 retirement basket nest | Retain stable 422 | BLOCKED_BY_SOURCE | PND90 instructions p.10; `PENSION_INSURANCE_RULE_PARTIAL` |
| BE-02 social security | Positive amount refused | No | Ceiling is delegated to the Social Security Act, which is not an approved repository source | Retain stable 422 | BLOCKED_BY_SOURCE | PND90 instructions p.12; `SOCIAL_SECURITY_RULE_UNSUPPORTED` |
| BE-03 allowance umbrella codes | Positive amount refused | Already safe | No single form line/rule exists for the umbrella values | Retain explicit 422 and disabled UI | GUARDED | `AllowanceCoverageCatalogue`; baseline closure tests |
| BE-04 domestic tourism | No master/rule/input | No | The second secondary-city band prints 1.5x but no ceiling | Retain unknown/unsupported guard | BLOCKED_BY_SOURCE | PND90 instructions p.16 item 22; source coverage matrix |
| BE-05 dividend credit/gross-up | No request/calculation path | No | Source gives a corporate-rate fraction but does not settle intermediate/final rounding, and the current contract lacks resident/eligible-dividend evidence | Document a future structured contract; keep unavailable | BLOCKED_BY_SOURCE | PND90 instructions p.2 item 5; form p.2 item 5 |
| BE-06 separate-tax property election | No request/calculation path | No | Source removes the 150,000 exemption without defining the rate applicable to that first band; implementing it would infer a rate | Document required contract; keep excluded from progressive base | BLOCKED_BY_SOURCE | PND90 instructions p.4 box 8; form p.4 box 8 |
| BE-07 excluded-from-aggregation election | No request/calculation path | No | Eligible subtype/election coding and complete settlement interaction are not specified as an implementable contract | Document required contract; keep unavailable | BLOCKED_BY_SOURCE | PND90 form p.4 box 10 and instructions pp.2-3 |
| BE-08 foreign tax credit | Positive amount refused | No | Form states a broad Thai-tax limit and redirects to an external calculator; per-income/country limitation and unused treatment are absent | Retain stable 422 | BLOCKED_BY_SOURCE | PND90 instructions p.1; form p.4 item 13 |
| BE-09 other credit | Positive amount refused | Already safe | No matching source line or rule | Retain stable 422 | GUARDED | `TAX_CREDIT_TYPE_UNSUPPORTED` tests |
| BE-10 legal final rounding | Exact decimals plus warning | No | No source states rounding stage, unit, or direction | Retain exact arithmetic and warning | BLOCKED_BY_SOURCE | `ROUNDING_RULE_PENDING`; baseline tests |
| BE-11 exempt fund components | Aggregate taxpayer declaration | No | Individual values are printed, but interaction with the shared retirement basket and calculation order is incomplete | Document future component schema; retain declaration warning | BLOCKED_BY_SOURCE | PND90 instructions pp.2, 10-11; production baseline |
| BE-12 disabled-person relationship limit | Undifferentiated disabled dependent; warning only | Yes | None for the family-vs-other discriminator and one-other-person limit | Add nullable discriminator, versioned draft rule, validation, persistence, calculation, trace warning, and tests | CLOSED | PND90 instructions pp.8-9 item 5; draft 2568.2 |
| BE-13 independent eligibility verification | Taxpayer declaration | Already safe | Government/evidence integrations are absent by design | Preserve declaration language and warnings | GUARDED | Project simulation scope; family baseline |
| BE-14 other tax years | Architecture only | Already safe | No approved source package for another year | Keep metadata/version resolution strict | GUARDED | Only year 2568 is active and seeded |
| BE-15 official e-filing | Not implemented | No | Explicit task exclusion and external government integration | Keep simulator wording | OUT_OF_SCOPE | Project requirements section 2 |
| BE-16 filing identity/signature | Not implemented | No | Explicit task exclusion and external identity/signature integration | Keep simulator wording | OUT_OF_SCOPE | Project requirements section 2 |
| BE-17 official attachment submission | Not implemented | No | Explicit task exclusion and government submission integration | Keep simulator wording | OUT_OF_SCOPE | Form-fidelity classification |
| BE-18 payment/refund submission | Guidance only | No | Explicit task exclusion and financial/government integration | Keep estimate wording | OUT_OF_SCOPE | Refund guidance and release scope |

## Frontend

| Gap | Current status before pass | Can close now | Blocking dependency | Planned action | Final status | Evidence |
|---|---|---:|---|---|---|---|
| FE-01 pension/social-security controls | Visible unsupported guidance | No | BE-01 and BE-02 | Keep disabled and explained | GUARDED | Form coverage tests |
| FE-02 umbrella allowance controls | No misleading amount input | Already safe | BE-03 | Keep disabled and explained | GUARDED | Form-fidelity guidelines |
| FE-03 domestic-tourism control | Informational only | No | BE-04 | Keep unavailable | GUARDED | PND90 coverage matrix |
| FE-04 dividend/property/excluded elections | Informational only | No | BE-05 to BE-07 | Keep unavailable; do not add JS formulas | GUARDED | PND90 coverage matrix |
| FE-05 foreign/other credit controls | Disabled options | No | BE-08 and BE-09 | Keep disabled and explained | GUARDED | M9.2 regression tests |
| FE-06 filing workflow | Not implemented | No | Outside simulator scope | Keep absent | OUT_OF_SCOPE | Project requirements section 2 |
| FE-07 native mobile application | REST-ready roadmap | No | Separate product delivery | Preserve shared API contract | OUT_OF_SCOPE | Project architecture |

BE-12 is implemented in draft 2568.2 only. Its input remains hidden from the Release 1.0 web UI
until that draft is separately reviewed and published, so no frontend status is changed by this pass.

## QA

| Gap | Current status before pass | Can close now | Blocking dependency | Planned action | Final status | Evidence |
|---|---|---:|---|---|---|---|
| QA-01 Safari | Not run | No | Real macOS/iOS Safari unavailable | Provide exact manual checklist | BLOCKED_BY_EXTERNAL_ENVIRONMENT | `EXTERNAL_BROWSER_DEVICE_CHECKLIST.md` |
| QA-02 Firefox | Not run | No | Firefox runtime unavailable | Provide exact manual checklist | BLOCKED_BY_EXTERNAL_ENVIRONMENT | `EXTERNAL_BROWSER_DEVICE_CHECKLIST.md` |
| QA-03 physical phone/tablet | Not run | No | Physical iOS/Android hardware unavailable | Provide viewport/device checklist | BLOCKED_BY_EXTERNAL_ENVIRONMENT | `EXTERNAL_BROWSER_DEVICE_CHECKLIST.md` |
| QA-04 browser E2E | No repository suite | Yes, Chromium scope | Firefox/Safari remain external | Add dependency-light Playwright Chromium smoke for public/auth/member-gate routes | CLOSED | `tests/browser/smoke.mjs`; `npm run test:browser` |
| QA-05 load/capacity | Not applicable | No | No approved SLA or production-sized environment | Do not invent a target | BLOCKED_BY_EXTERNAL_ENVIRONMENT | Release test matrix |

## Production and operations

| Work item | Current status before pass | Can close now | Blocking dependency | Planned action | Final status | Evidence |
|---|---|---:|---|---|---|---|
| Environment validation | Manual checklist | Yes | None | Add read-only Artisan validation command and tests | CLOSED | `ops:production-readiness` |
| Production-safe seeding verification | Documented | Yes | None | Verify baseline and demo-seeder exclusions | CLOSED | command/test plus `PRODUCTION_SEEDING.md` |
| Backup command verification | Documented | Yes | None in local MySQL | Execute non-destructive dump verification | CLOSED | `BACKUP_RESTORE.md`; command log in final report |
| Restore command verification | Documented | Yes | None in isolated temporary database | Restore and validate only a uniquely named temporary database | CLOSED | `BACKUP_RESTORE.md`; command log in final report |
| Rollback procedure | Documented | Yes | None | Add decision gates and verification record fields | CLOSED | `ROLLBACK.md` |
| Health/readiness procedure | Liveness plus DB health documented | Yes | None | Include in command/checklist | CLOSED | `DEPLOYMENT.md`; readiness command |
| Admin bootstrap procedure | Tinker example only | Yes | None | Document named-account procedure without secrets | CLOSED | `PRODUCTION_SEEDING.md` |
| Monitoring checklist | High-level list | Yes | None | Add signals and ownership/evidence fields | CLOSED | `PRODUCTION_READINESS_CHECKLIST.md` |
| Secret checklist | High-level list | Yes | None | Add explicit non-secret checklist | CLOSED | `PRODUCTION_READINESS_CHECKLIST.md` |
| Actual production deployment | Not performed | No | Production environment, secrets, change window, and explicit deployment authorization | Do not deploy in this task | BLOCKED_BY_EXTERNAL_ENVIRONMENT | `DEPLOYMENT.md` |

## Closure counts

Counts include BE-01..18, FE-01..07, QA-01..05, and the ten operations rows above (40 rows total).

| Status | Count |
|---|---:|
| CLOSED | 11 |
| GUARDED | 9 |
| BLOCKED_BY_SOURCE | 9 |
| BLOCKED_BY_EXTERNAL_ENVIRONMENT | 5 |
| OUT_OF_SCOPE | 6 |

## Verification evidence

- Draft `2568.2` structural validation: valid, zero errors, zero warnings; it remains `draft`.
- Published `2568.1` hash before/after: `54da89f0fde00c73c9d81f6cb4e55c2cf844a6fa6edd6e71e188da63d563103f`.
- Historical return/calculation hash before/after: `1b84dbc68101defa410f395a30cf7683b52cd654418fb1f6d27d895f78f53cbb` (13 returns, 14 calculations).
- SQLite: 840 passed, 2 skipped, 4,654 assertions. MySQL: 842 passed, 5,084 assertions.
- Pint: 314 files passed. Vite production build and Chromium Playwright smoke passed.
- Backup/isolated restore drill: 467,063-byte dump, SHA-256
  `481C513EAD2C0CC82216F94BF4FCEE043E848AE3A379F1D74DEB11D85D16870F`;
  restored 19 migrations, 13 returns, 14 calculations, published `2568.1` and draft `2568.2`;
  the uniquely named drill database was then removed.
- `/` and `/api/v1/health` returned HTTP 200. Repository readiness passed; full readiness correctly
  refused the local development environment because production mode, HTTPS, debug-off and secure
  cookies are deployment-environment gates.

## Release decision

`READY_FOR_CONTROLLED_PRODUCTION_DEPLOYMENT`

This decision covers the documented Release 1.0 supported scope on published `2568.1`. The
deployment operator must still satisfy the production-environment gates before enabling traffic.
Draft `2568.2` requires its own review and publish decision.
