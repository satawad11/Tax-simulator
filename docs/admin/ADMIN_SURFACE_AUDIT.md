# Is the admin console complete?

2026-09-16. An audit of what an administrator can and cannot do, checked endpoint by endpoint
rather than by impression — the Phase 3 lesson, where two of three claimed gaps turned out to be
features I had failed to find.

**Short answer: every capability the admin API offers is reachable from a page, with one deliberate
exception. What remains missing is not capability — it is editorial workflow.**

---

## Method

Enumerated all 43 admin API endpoints from `route:list`, then extracted every endpoint the console
actually calls from `console.js` and `rule-editor.js`, and compared the two sets. Each finding
below was then exercised against the running development server.

## Coverage

| Area | Page | Create | Read | Update | Delete / retire | Workflow |
| --- | --- | --- | --- | --- | --- | --- |
| Overview | `/admin` | — | ✓ | — | — | recent activity |
| Content | `/admin/content`, `.../create`, `.../edit` | ✓ | ✓ | ✓ | ✓ soft | publish, unpublish, archive |
| Categories & tags | `/admin/taxonomy` | ✓ | ✓ | ✓ | ✓ (deactivates when in use) | — |
| Tax years | `/admin/tax-years` | ✓ | ✓ | ✓ | ✓ retire | copy forms from a year |
| Rule versions | `/admin/tax-rule-versions`, `.../{id}` | clone | ✓ | ✓ | archive | validate, publish, archive |
| Rules (6 resources) | rule-version detail | ✓ | ✓ | ✓ | ✓ | — |
| Tax sources | `/admin/tax-sources` | ✓ | ✓ | ✓ | ✓ deactivate | — |
| Accounts | `/admin/users` | — (registration is public) | ✓ | role | — (see gap analysis 11) | revoke sessions |
| Audit trail | `/admin/audit-logs` | — | ✓ | — | — | filter by action and entity |

Every page has test coverage.

## The one endpoint with no page, and why that is right

`POST /admin/tax-rule-versions` creates a rule version **from scratch**. The console offers only
**clone**.

That is a good decision, not an oversight. An empty version has no brackets, no expense rules and
no allowance rules — the published 2568.1 baseline carries 8 brackets, 70 expense rules, 15
allowance rules, 2 donation rules and 4 recommendation rules. Creating one from scratch would ask
an administrator to hand-enter roughly ninety-five rules, each needing its own source citation,
with a blank version sitting in the list until they finished. Cloning last year's baseline and
editing what changed is both safer and the actual workflow, which is why
`docs/admin/NEXT_TAX_YEAR.md` describes it that way.

The endpoint should stay — an API client may have a use for it — but the console is right not to
offer the slower, more error-prone path as if it were equal.

---

## What is genuinely missing

None of these is a capability gap. All four are about the editor's own working loop.

### 1. A draft cannot be previewed — **DONE 2026-09-16**

`ContentService::publicQuery()` excludes drafts, so `/article/{slug}` returns **404** for anything
unpublished — verified against a real draft on the development server.

So the loop for writing an article is: write it, publish it, look at it, fix it. **Publishing is
how you see your own work.** On a product whose published content is the part readers are asked to
trust, that is the wrong way round. The structured-text body format makes it sharper still: an
author writing `#` headings and `-` lists has no way to check they parsed as intended.

*Proposed:* a signed, admin-only preview route rendering the same `content.show` view from the
post's id rather than through `publicQuery()`. No new storage, no draft-visibility flag on the
public query — the one definition of public visibility stays untouched, which is the property that
makes it trustworthy.

### 2. No way back from the console to the live page — **DONE 2026-09-16**

After publishing, nothing links from the content list to `/article/{slug}`. The administrator has
to know the URL shape and type it.

*Proposed:* a "ดูหน้าจริง" link on published rows, next to แก้ไข. Trivial, and it completes the
loop the preview starts.

### 3. The content list prints raw type codes — **DONE 2026-09-16**

The list renders `item.type` as it arrives — `ARTICLE`, `NEWS`, `FAQ` — while the edit form shows
บทความ, ข่าวสาร, คำถามที่พบบ่อย for the same values. Two vocabularies for one field, one of them not
in Thai, on the page an editor uses most.

*Proposed:* the same label map the form already defines.

### 4. The audit trail cannot be narrowed by time or by person

Filters are action and entity type. Sorted newest-first at 50 per page, so *recent* activity is
easy. But the questions an audit trail exists to answer — "what did this administrator change last
week?", "what happened around the time that rule was published?" — need an actor filter and a date
range, and the API supports neither.

*Proposed:* `actor_user_id` and `from` / `to` on `GET /admin/audit-logs`, with controls to match.
Now that `/admin/users` exists, the actor filter has a natural picker.

---

## Deliberately absent, and should stay that way

| Not offered | Why |
| --- | --- |
| Viewing or editing a member's tax return | The figures are the member's. Phase 2's account list carries a count and nothing else on purpose. |
| Setting another account's password | An administrator who could would be able to sign in as that member. Revoking sessions is the safe equivalent. |
| Deleting an account | Open question 11 — a legal question, awaiting your decision. |
| Editing a published rule version | `ProtectsPublishedRules` makes published versions immutable at the model. That is the guarantee the whole tax engine rests on. |
| Usage analytics | The dashboard counts content and rules. An analytics platform is a different product. |

## Delivered 2026-09-16

Items 1, 2 and 3 are done — see [CONTENT_PREVIEW.md](CONTENT_PREVIEW.md). Item 4, the audit
trail's missing actor and date filters, remains open; it needs an API change as well as a page.

## Recommendation

Items 1 and 2 belong together and are worth doing — they are one editorial loop, and the preview
is the only finding here that changes how the product is operated day to day. Items 3 and 4 are
small; 3 is minutes, 4 needs an API change as well as a page.

If none of this is built, the console is still complete in the sense that matters: there is no
administrative action this product supports that an administrator cannot perform.
