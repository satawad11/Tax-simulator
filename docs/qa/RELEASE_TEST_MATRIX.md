# Release 1.0 test matrix

| Area | Verification | Status | Evidence |
|---|---|---|---|
| PND91 | baseline, brackets, expense, allowances, credits | PASS | SQLite and MySQL regression suites |
| PND90 | supported income/expense paths and minimum tax | PASS | SQLite and MySQL regression suites |
| Family | spouse, child, parent and disabled-person derivation; draft 2568.2 family/other discriminator | PASS | tax engine and final-gap regression tests |
| Guest | calculate, result, trace, warnings, guidance, planning; no persistence | PASS | API/UI tests and browser smoke |
| Member | auth, draft, resume, calculate, history, duplicate, complete, scenario | PASS | Member feature suites |
| Planning | recommendations, before/after and guarded inputs | PASS | planning feature suites |
| Refund/payment | withholding comparison and wording | PASS | M6 regression suites |
| Content | knowledge, news, article, FAQ and draft exclusion | PASS | CMS/API/UI suites and browser smoke |
| Admin | login, member denial, CMS, source/rule reads, clone/validate/publish/audit | PASS | admin feature suites; protected-shell regression |
| Rule lifecycle | published immutable; drafts validated before publish | PASS | rule administration tests |
| Security | auth, IDOR, mass assignment, XSS, limits and headers | PASS | security and M10 tests |
| Responsive | 375, 768 and 1280 px layouts | PASS | Windows Chromium visual smoke |
| Browser | Windows Chromium | PASS | Playwright smoke via `npm run test:browser` |
| Browser | Safari, Firefox, mobile device hardware | BLOCKED | not available in this environment |
| Production capacity | load/capacity test | NOT_APPLICABLE | no SLA or production-sized environment supplied |

The matrix uses synthetic test data. Guarded M7.5 limitations are expected controlled errors and are not marked as failed supported behavior.

Form fidelity and the field-role audit were completed before the final release verification. The final regression includes the PND90/PND91 coverage matrices, conditional field behavior, Member resume fidelity, Guest statelessness, API compatibility, and security controls.

## Browser smoke — how to run it

The node image is Alpine, and the Chromium that Playwright downloads is linked against glibc, so
it cannot start there. The image therefore installs Alpine's own Chromium and points Playwright at
it; `smoke.mjs` already honours `PLAYWRIGHT_EXECUTABLE_PATH`.

The smoke must run against the **built** assets, not the Vite dev server: the dev server is
advertised in `public/hot` at the host-facing port, which does not resolve from inside the
container network. Move that file aside for the run, and put it back afterwards.

```sh
docker compose exec app sh -c 'mv public/hot public/hot.bak'
docker compose exec -e BASE_URL=http://nginx node npm run test:browser
docker compose exec app sh -c 'mv public/hot.bak public/hot'
```

Running it this way is also the more faithful release check, because it exercises exactly the
bundle a deployment would serve.
