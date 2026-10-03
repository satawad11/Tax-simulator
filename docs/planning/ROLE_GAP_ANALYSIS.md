# Gap analysis by audience — Guest, Member, Admin

2026-09-15. Written **before** development, as requested. Every gap below was confirmed against
the code, not inferred from the milestone prompts; each carries the file or route that proves it.

Scope note: this covers what the three audiences can and cannot *do*. It deliberately does not
propose any new tax rule. Every allowance still blocked by a source document — `PENSION_INSURANCE`,
`SOCIAL_SECURITY`, the annual measures — stays blocked, and nothing here would change a calculated
figure.

---

## Summary

The tax engine and the admin rule workflow are the mature parts of this product. The gaps are
almost all in the **account and operations layer around them** — the parts a real deployment needs
on day one and that no milestone has owned yet.

| # | Gap | Who | Severity | Evidence |
| --- | --- | --- | --- | --- |
| 1 | ~~No password reset~~ **DONE 2026-09-15** | Member, Admin | ~~Blocker~~ | `routes/api.php` — `auth` group has register/login/me/update/logout only |
| 2 | ~~No password change~~ **DONE 2026-09-15** | Member, Admin | ~~Blocker~~ | `UpdateProfileRequest` accepts `name`, `email` only |
| 3 | ~~No admin user management~~ **DONE 2026-09-16** | Admin | ~~Blocker~~ | no `admin/users` route; `users.role` not mass-assignable |
| 4 | ~~Email change strands the account unverified~~ **DONE 2026-09-15** | Member | ~~High~~ | `AuthService::update()` nulls `email_verified_at`; nothing ever sets it |
| 5 | ~~Article tags can be created but never attached~~ **WRONG — already worked** | Admin | — | tag checkboxes are rendered by `console.js`, not static markup; my grep could not see them |
| 6 | ~~Category and tag pages exist in the API, not on the web~~ **OVERSTATED** | Guest | — | no dedicated route, but `/knowledge` and `/news` already filter by `?category=` and `?tag=` |
| 7 | ~~Default English error pages~~ **DONE 2026-09-16** | All | ~~High~~ | `resources/views/errors/` does not exist |
| 8 | ~~No way to keep a result~~ **DONE 2026-09-16** | Guest, Member | ~~Medium~~ | no print, export or PDF anywhere in `resources/js` |
| 9 | ~~Member write endpoints are unthrottled~~ **DONE 2026-09-16** | Member | ~~Medium~~ | 5 `throttle` declarations in `routes/api.php`, none on `tax-returns` |
| 10 | ~~No account settings page~~ **DONE 2026-09-16** | Member | ~~Medium~~ | `PUT /auth/me` exists; no web route or UI reaches it |
| 11 | No account deletion | Member | Medium | no route; see the open question below |
| 12 | ~~`cover_image`, `source_name`, `source_url` unreachable~~ **DONE 2026-09-16** | Admin | ~~Low~~ | fillable on `ContentPost`; absent from `WriteContentRequest` |

---

## 1. Guest (ผู้ใช้ทั่วไป)

**Working well.** The wizard, the form recommendation, published content, and — since today —
family allowances claimed from declared facts. Guest and Member share one calculation path, which
is the property that makes the rest of this safe to build on.

### 1.1 A guest cannot keep their answer (Medium)

There is no print, no PDF, no export, no shareable link. A guest who spends ten minutes entering a
household gets a screen they must photograph. The trace, which is the most defensible thing this
product produces — sixteen named stages with the ใบแนบ line behind each — exists only in the DOM.

*Proposed:* a print stylesheet plus a "พิมพ์ / บันทึกเป็น PDF" control on the result, rendering the
result and trace with the disclaimer. Browser print only — no PDF library, no server rendering, no
new dependency. The same control serves Member.

### 1.2 Category and tag pages are missing (High)

`GET /content/categories/{slug}` and `GET /content/tags/{slug}` are public, tested API endpoints.
Nothing on the web reaches them: `routes/web.php` has `/knowledge`, `/news`, `/faq` and
`/article/{slug}`. The taxonomy page built in M9.1 lets an admin curate categories and tags that no
reader can browse by. This is the cheapest high-value gap in the list — the service layer, the
query and the card component all exist.

*Proposed:* `/category/{slug}` and `/tag/{slug}` reading through the same
`ContentService::publicQuery()`, reusing `content/index.blade.php` and `x-article-card`.

### 1.3 Error pages are Laravel's English defaults (High)

`resources/views/errors/` does not exist. A guest hitting a bad article slug leaves the product
entirely — different language, different typography, no navigation back.

*Proposed:* `404`, `403`, `419`, `429`, `500` and `503` on the existing `x-layout`, in Thai, each
offering a route back. Purely presentational; no behaviour changes.

---

## 2. Member (สมาชิก)

**Working well.** Returns, drafts, the completed snapshot, calculation history, scenarios and
planning, and the guest-to-member carry-over in `member-return.js`.

### 2.1 A member who forgets their password loses their account (Blocker)

There is no reset flow and no change-password endpoint. Every saved return becomes unreachable, and
support has no answer short of database access. This is the single largest gap in the product.

*Proposed:*
- `POST /auth/password/forgot` and `POST /auth/password/reset` on Laravel's own broker and
  `password_reset_tokens` table (the migration already exists), tightly throttled, answering the
  same success envelope whether or not the address is registered.
- `PUT /auth/password` requiring the current password, revoking all other tokens on success.
- Web pages `/password/forgot` and `/password/reset/{token}`.

**This one has a dependency the project has never had to satisfy: outbound mail.** `MAIL_MAILER`
must be configured and a sender verified before any of it is real. That belongs in the deployment
checklist, and it is why this is item 1 and not item 5 — it is the gap most likely to block a
launch date for a reason nobody costed.

### 2.2 Changing an email strands the account (High)

`AuthService::update()` clears `email_verified_at` when the address changes, and nothing in the
codebase ever sets it again. Today it is inert because nothing gates on verification — but the
moment anything does (password reset being the obvious candidate), every member who has ever edited
their email is locked out by a line written to be careful.

*Proposed:* decide it explicitly rather than leave it latent — either implement verification with
the reset work, since both need the same mail transport, or stop nulling the column until
verification exists. Recommend the former; they are one body of work.

### 2.3 No account settings page (Medium)

`PUT /auth/me` has existed since M5 with no UI. A member cannot correct their own name.

*Proposed:* `/dashboard/account` on the existing dashboard shell — name, email, change password,
active-session revocation via `logout-all`.

### 2.4 Write endpoints are unthrottled (Medium)

Five `throttle` declarations exist: register, login, `tax/calculate`, `tax/plan`,
`forms/recommend`. Every authenticated write — creating returns, incomes, dependents, scenarios —
has none. One token can create returns in an unbounded loop.

*Proposed:* a throttle on the `tax-returns` group, generous enough that the wizard's own save
(which writes one request per collection item) never trips it. Measure the wizard's worst case
first: `persistGuestState` issues one POST per item across five collections.

### 2.5 No account deletion (Medium, one open question)

A member cannot delete their account or their stored returns in bulk.

**Open question for you:** returns hold declared income, family composition and dependants'
details. Whether Thai personal-data law obliges this product to offer erasure, and on what terms,
is a legal question — and the same rule that governs tax figures applies here: I will not guess it
from general knowledge. If you want it built, either confirm the requirement yourself or add the
governing text to `docs/tax-source/`, and I will implement to that text. Until then I would build
the capability and let you decide what to promise about it.

---

## 3. Admin (ผู้ดูแลระบบ)

**Working well.** The rule-version lifecycle, draft rule editing, publication immutability, tax
years, tax sources, taxonomy, the audit log and the sidebar console.

### 3.1 An admin cannot manage users at all (Blocker)

There is no `admin/users` route, no `UserPolicy`, no user list. `users.role` is deliberately not
mass-assignable and is set only by `DevelopmentAccountSeeder`. Consequences in production:

- A second administrator can only be created by editing the database directly.
- An administrator who leaves cannot be demoted through the product.
- Nobody can see who holds an account, or respond to an abusive or compromised one.

`User.php` says M8 "deliberately does not build an RBAC platform", and that restraint was right —
but *no* user administration is a different thing from *no RBAC*. Two roles still need an
operator.

*Proposed, deliberately small:*
- `GET /admin/users` — paginated list with search; never exposes password hashes or token values.
- `PATCH /admin/users/{user}/role` — promote/demote between the two existing roles, through a new
  `UserPolicy`, audited through `AdminAuditService`, refusing self-demotion and refusing to remove
  the last remaining administrator.
- `POST /admin/users/{user}/revoke-tokens` — sign a compromised account out everywhere.
- An `/admin/users` console page on the existing layout.

No new role, no permission matrix, no invitations. Two roles, one operator screen.

### 3.2 Tags can be created but never attached (High)

`WriteContentRequest` accepts `tag_ids` (max 20, validated against `content_tags`). The content
form at `resources/views/admin/content-form.blade.php` offers `type`, `category_id`, `title`,
`slug`, `excerpt`, `body`, `meta_title`, `meta_description` and `featured` — no tag control. So the
taxonomy page built in M9.1 manages tags that can never reach an article, and the public tag
endpoint can only ever return empty. Three layers each work; the seam between them is missing.

*Proposed:* a tag multi-select on the content form, plus `tax_year_id` and `sort_order`, which the
API also accepts and the form also omits. Small, and it completes two features already paid for.

### 3.3 Three content fields are unreachable (Low)

`cover_image`, `source_name` and `source_url` are fillable on `ContentPost` but appear in neither
`WriteContentRequest` nor any view, so nothing can set or show them. There is also no upload
anywhere in the codebase — no `Storage::` call, no `UploadedFile` handling — so `cover_image` could
only ever hold a pasted URL.

*Proposed:* decide rather than drift. Either add `source_name`/`source_url` to the request and the
article page — they are genuinely useful on a tax-news item, and need no upload — or drop all three
from `$fillable` so the model stops advertising what the product cannot do. I recommend adding the
two source fields and dropping `cover_image` until there is an image story worth building.

---

## Proposed order of work

Sequenced by what blocks a deployment, and by what shares a dependency.

**Phase 1 — account recovery.** Gaps 1, 2, 4. Password reset, change password, and the email
verification decision, together, because they share the mail transport. Largest and most blocking;
carries an infrastructure prerequisite that should be confirmed before the work starts, not after.

**Phase 2 — user administration.** Gap 3. Independent of Phase 1 and shippable on its own. Needed
before a second administrator can exist without database access.

**Phase 3 — completing what is already built.** Gaps 5, 6, 12. Tags on the content form, public
category and tag pages, and the content-field decision. Each is small, each finishes a feature the
project already paid for, and none carries a new dependency. Good value per hour.

**Phase 4 — polish and hardening.** Gaps 7, 8, 9, 10. Thai error pages, printable results, the
throttle on member writes, the account settings page.

**Deferred pending your decision.** Gap 11, account deletion — see the open question in §2.5.

## What I need from you before starting

1. **Mail.** Is there an SMTP service or transactional provider for this deployment? Phase 1 is not
   real without one.
2. **Account deletion.** Confirm the requirement, or supply the governing text, or defer it.
3. **Order.** The sequence above is my recommendation. If a demo or a deadline makes Phase 3's
   visible wins more urgent than Phase 1's correctness, say so — it is a reasonable trade and I
   would rather hear it now than infer it.

---

## Phase 1 — delivered 2026-09-15

Gaps 1, 2 and 4 are closed. Password reset, change password, and email verification ship together
as planned, because all three need the same mail transport.

Full contract and rationale: [PHASE_1_ACCOUNT_RECOVERY.md](../api/PHASE_1_ACCOUNT_RECOVERY.md).

Two things the analysis did not anticipate, both found while building:

**There is no queue worker.** `QUEUE_CONNECTION` is `database` and no `queue:work` process exists
in the compose stack or the image, so anything queued is written to the jobs table and never runs.
Both notifications therefore send inline, and a test fails if that changes. This would have been a
silent production failure — mail queued, no error, no delivery.

**Account settings could not wait entirely for Phase 4.** A change-password endpoint with no page
to reach it closes nothing, so `/account/password` ships now. Name, email and session management
remain Phase 4's settings page (gap 10), which is correspondingly smaller.

One item from "what I need from you" is still open: **outbound mail for the deployment**. The code
is complete and verified against the `log` mailer; it is not real until `MAIL_MAILER` names a
transport that delivers. Everything needed is listed in the readiness checklist.

**Next:** Phase 2 — user administration (gap 3).

---

## Phase 2 — delivered 2026-09-16

Gap 3 is closed. Contract and rationale:
[USER_ADMINISTRATION.md](../admin/USER_ADMINISTRATION.md).

Built as scoped — a list, a role switch between the two existing roles, and a sign-out-everywhere,
all audited. No new role, no permission matrix, no account creation or deletion, and no way for an
administrator to set a password or read a member's tax figures.

Two guards keep anyone from locking every administrator out of the console: you cannot change your
own role, and the last administrator cannot be demoted. The second is enforced inside the
transaction with `lockForUpdate`, because over HTTP the first rule shadows it and a guard nobody
can reach is a guard nobody would notice losing.

**Next:** Phase 3 — completing what is already built (gaps 5, 6, 12): tags on the content form,
public category and tag pages, and the content-field decision.

---

## Phase 3 — delivered 2026-09-16, and two corrections

Full account: [CONTENT_EDITING.md](../admin/CONTENT_EDITING.md).

**Two of this phase's three gaps were not gaps.** Gap 5 was wrong — tag selection has worked since
M9.1; I grepped the form's markup for `name="…"`, and the tag checkboxes are rendered by
`console.js`, which a grep for static attributes cannot see. Gap 6 was overstated — there is no
dedicated `/tag/{slug}` route, but `/knowledge` and `/news` already filter by `?category=` and
`?tag=`, and article pages already link back into them, so a second path to the same result was
not worth building.

The lesson is recorded because it will recur: **a search that finds nothing is not evidence of
absence**, especially in a product whose interfaces are assembled by JavaScript at runtime.

**What was real, and is now done:**

- A defect worse than any gap listed: `AdminContentResource` emits `type` upper-cased while the
  form's options are lower case, so editing *any* existing post left the type select blank and
  saving failed with a 422 on a field nobody had touched. Fixed at both ends.
- `sort_order` and `tax_year_id` controls — the FAQ ordering and the public tax-year filter both
  existed with no way to populate their input.
- `source_name` / `source_url` added to the request, both resources, the service's own write
  allow-list, the form, and the article page, with `url:http,https` validation because the value
  is rendered as an href.
- `cover_image` dropped from `$fillable` — there is no upload anywhere in the codebase, so it
  could only ever have held a pasted URL.

**Next:** Phase 4 — polish and hardening (gaps 7, 8, 9, 10): Thai error pages, a printable result,
a throttle on member writes, and the account settings page.

---

## Phase 4 — delivered 2026-09-16

Gaps 7, 8, 9 and 10 are closed. Full account: [PHASE_4_POLISH.md](PHASE_4_POLISH.md).

Thai error pages, a printable result that keeps its warnings, two named rate limiters on member
writes, and the account settings page for an endpoint that had gone five milestones without a
caller.

One mistake worth carrying forward: two stacked `throttle:n,1` middlewares on the same request
**share a counter**, because `ThrottleRequests` keys on the route and user rather than the limit.
The "20 per minute" tier refused the eleventh request. Named limiters namespace their key and
count independently. A test caught it; a bug report would not have explained it.

## Where the plan stands

| # | Gap | Status |
| --- | --- | --- |
| 1, 2, 4 | password reset, password change, email verification | done — Phase 1 |
| 3 | admin user management | done — Phase 2 |
| 5, 6 | article tags, public taxonomy pages | not gaps — corrected in Phase 3 |
| 12 | dormant content fields | done — Phase 3 |
| 7, 8, 9, 10 | error pages, printable result, write throttle, account page | done — Phase 4 |
| 11 | account deletion | **deferred — awaiting your decision** |

**Two things remain open, and both are yours rather than mine:**

1. **Outbound mail.** Phase 1's code is complete and verified against the `log` mailer, which
   writes the message to a file and sends nothing while still answering 202. It is not real until
   `MAIL_MAILER` names a transport that delivers. See the readiness checklist.
2. **Account deletion (gap 11).** Whether Thai personal-data law obliges this product to offer
   erasure, and on what terms, is a legal question. Confirm the requirement, or add the governing
   text to `docs/tax-source/` and I will implement to it.
