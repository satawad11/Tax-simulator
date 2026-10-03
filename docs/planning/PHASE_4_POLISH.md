# Phase 4 — polish and hardening

2026-09-16. Closes gaps 7, 8, 9 and 10 of
[ROLE_GAP_ANALYSIS.md](ROLE_GAP_ANALYSIS.md) — the last of the plan.

Four items that share nothing except being what a real deployment notices in its first week.
Unlike Phase 3, all four were verified still-missing before any was built.

---

## 7. Error pages in the product's own language

`resources/views/errors/` did not exist, so a reader who mistyped an article slug left the product
entirely: Laravel's page, in English, in a different typeface, with no navigation back.

Six pages — `403`, `404`, `419`, `429`, `500`, `503` — sharing one `errors/layout.blade.php` on
the existing public shell. Each states plainly what happened and offers the route that is actually
useful from where the reader is standing: home, the simulator, and the knowledge index.

Being plain matters. An error page that explains nothing leaves the reader unsure whether to
retry, wait, or go elsewhere, which is the only decision they have. `419` says to reload and
resubmit; `429` says to wait and that saved work is untouched; `500` says the fault is the
system's and not their data — and deliberately says nothing about the cause, because an error page
is not a debugging surface.

The shell's navbar touches no database, so a `500` caused by a database outage renders rather than
cascading into a second failure.

## 8. A result the reader can keep

There was no print, no PDF, no export. A guest who spent ten minutes entering a household got a
screen to photograph — and the trace, the most defensible thing this product makes (sixteen named
stages, each citing the ใบแนบ line behind it), existed only in the DOM.

A **พิมพ์ / บันทึกเป็น PDF** button on the result, and an `@media print` block in `app.css`.
Browser print, not a PDF library: the page is already laid out, and every desktop browser can save
that to PDF. A server-side renderer would be a new dependency to reproduce a page we already have.

What the printout keeps and drops is the whole design:

| Kept | Dropped |
| --- | --- |
| summary, bracket table, step-by-step trace | navigation, buttons, the wizard's earlier steps |
| every warning | the planning panel — a what-if, not the result |
| the disclaimer | tinted cards, shadows, link URLs |

**Warnings and the disclaimer are never hidden.** A printed figure that has outlived its caveats is
worse than no printout, because on paper it looks official.
`Phase4PolishTest::test_printing_keeps_the_caveats_and_drops_the_chrome` fails if either joins the
hide list.

`<details>` cannot be opened by CSS, and the trace lives inside one, so `beforeprint` opens every
collapsed section and `afterprint` closes it again — what prints is what the reader would see
expanded, and the screen is unchanged when they come back to it. A print-only header names the
product and repeats that this is a simulation, because the page has left the browser that said so.

## 9. Rate limits on a member's own writes

Five `throttle` declarations existed — register, login, calculate, plan, recommend. Every
authenticated write had none: one token could create returns in an unbounded loop, and nothing
caps how many incomes or dependents a single return may hold.

Two named limiters, defined in `AppServiceProvider::registerMemberWriteLimits()`:

| Limiter | Per minute | Applies to | Why |
| --- | --- | --- | --- |
| `member-create` | 20 | `POST /tax-returns`, `POST {id}/duplicate` | the unbounded-growth vector, and rare in normal use — a member makes a handful a year |
| `member-write` | 300 | every other write on the group | chatty but bounded by a return that already exists |

### The mistake worth recording

The first attempt stacked two inline middlewares: `throttle:300,1` on the group and
`throttle:20,1` on the creation routes. It looked simpler and was wrong.

`ThrottleRequests` derives its key from the route and the user, **not** from the limit. Two inline
throttles on one request therefore share a counter, and each request is counted twice — a "20 per
minute" tier that actually refused the eleventh request. The test caught it immediately; it would
have been very hard to diagnose from a bug report.

Named limiters namespace their key by name, so the two tiers count independently.

### Sizing

`test_a_realistic_save_is_nowhere_near_the_limit` walks a return with 40 income rows — more than
any real filing — and expects every write to succeed. A limit a genuine heavy return could trip
would be worse than none.

The theoretical maximum the guest calculator accepts, 100 items in each of five collections, would
exceed 300 in one minute and finish in the next. That is accepted: a 500-line return is not a
filing anyone makes, and the alternative was no bound at all.

## 10. The account settings page

`PUT /auth/me` has existed since M5 with nothing calling it — a member could not correct their own
name. `/dashboard/account`, on the existing member shell and linked from the member tabs, holds
exactly three things:

- the two editable fields, name and email, with a note that changing the address restarts
  verification and sends a new link;
- the verification state, and a button to send the link again when it is not verified;
- sign-out-everywhere, with what it does spelled out.

**Changing the password is not duplicated here.** It has its own page from Phase 1, with its own
rate limit and its own current-password check; two forms writing the same credential is how they
drift apart. This page links there.
`test_the_account_page_does_not_duplicate_the_password_form` asserts no password field exists on it.

`UserResource` gained `email_verified` — a boolean, not the timestamp: when it happened is of no
use to a reader, and nothing in the product is gated on it. The closed-list assertion in
`AuthenticationApiTest` was extended rather than relaxed, so the payload stays a closed list.

## Verification

| Check | Result |
| --- | --- |
| `Phase4PolishTest` (new) | 14 passed, 173 assertions |
| SQLite full suite | 1,015 passed, 2 skipped, 5,711 assertions |
| MySQL full suite | 1,015 passed, 2 skipped, 5,711 assertions |
| Pint | 289 files, pass |
| `npm run build` | built; the `@media print` block and its printed header are in the shipped CSS |
| Live dev server | `/article/<missing>` → 404 rendering the Thai page with its ways onward; `/dashboard/account` → 200; rendered in the browser and confirmed visually |

No development data was created or modified by this phase's checks.
