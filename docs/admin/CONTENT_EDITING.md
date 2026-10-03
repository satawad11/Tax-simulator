# Phase 3 — completing the content editor

2026-09-16. Covers gaps 5, 6 and 12 of
[ROLE_GAP_ANALYSIS.md](../planning/ROLE_GAP_ANALYSIS.md) — two of which turned out to be wrong.

---

## First: two corrections to the gap analysis

I wrote the analysis by reading the code. On two items I read the wrong thing, and the honest
record matters more than a tidy phase.

### Gap 5 — "tags can be created but never attached" was **wrong**

Tag selection has worked since M9.1. I grepped `resources/views/admin/content-form.blade.php` for
`name="…"` and found no tag control, concluding the seam was missing. The tag checkboxes are
rendered by `console.js` into a `data-content-tags` fieldset, preselected from the post on edit,
and submitted as `tag_ids` — which the request has always accepted. The grep found static inputs
and could not see JavaScript-rendered ones. **A search that finds nothing is not evidence of
absence.**

### Gap 6 — "category and tag pages exist in the API, not on the web" was **overstated**

There is no dedicated `/category/{slug}` or `/tag/{slug}` route, which is what I checked. But
readers can already browse by both: `/knowledge` and `/news` carry category chips and a tag
dropdown, `ContentPageController::applyFilters()` handles `?category=`, `?tag=`, `?tax_year=` and
`?q=`, and every tag on an article page already links back to the filtered listing. A dedicated
route would be a prettier URL for a journey that works today — polish, not a gap, and not worth
building a second path to the same result.

### What that left

Gap 12 was real, and chasing it surfaced something worse.

---

## The defect underneath: editing any post failed

`AdminContentResource` emits `type` **upper-cased** for display, while `ContentPost::TYPES` — and
the form's options — are lower case. The console set the select from the API response:

```js
['type', 'title', …].forEach((name) => { form.elements[name].value = item[name] ?? ''; });
```

Assigning `'GUIDE'` to a select whose options are `'guide'` matches nothing, so the control went
**blank on every edit**, and submitting then failed with a 422 on `type` — a field the
administrator had never touched. Editing an existing post was effectively broken.

Confirmed on the live development server before the fix: `GET /admin/content/3` returns
`type='GUIDE'`; `PATCH` with that same value returned 422.

Fixed at both ends, because either alone leaves the trap for the next client:

- `WriteContentRequest::prepareForValidation()` lower-cases `type` on the way in, so a client can
  send back what the API gave it. An unknown type is still a 422 — normalising case is not
  accepting anything.
- The console lower-cases before selecting, and `type` no longer rides in the bulk field loop
  where the mismatch was invisible.

## Three controls the API always accepted

Each drives something a reader can already see, so without a control the feature was inert.

| Field | What it drives | Was |
| --- | --- | --- |
| `sort_order` | `/faq` and `GET /content/faqs` both `orderBy('sort_order')` | an administrator could publish FAQs they could not order |
| `tax_year_id` | the ปีภาษี filter on `/knowledge` and `/news` | a filter nothing could populate |
| `source_name`, `source_url` | attribution on the article page and in the public API | fillable on the model, absent from the request and every view |

**`source_url` is validated `url:http,https`, not plain `url`.** The value is rendered as an
`href`; plain `url` would accept `javascript:` and `data:`. The link carries
`rel="noopener noreferrer"` and says it opens in a new tab.

Attribution earns its place on this product in particular: it will not state a tax rule its own
sources do not print, so naming the announcement a news item came from lets a reader check it
against the original.

## `cover_image` is dropped rather than half-built

Removed from `ContentPost::$fillable`. It was reachable from nothing — absent from
`WriteContentRequest`, rendered by no view, and there is **no upload anywhere in this codebase**
(no `Storage::` call, no `UploadedFile` handling), so it could only ever have held a pasted URL.
Zero rows use it. A model that advertises a field the product cannot set or show is a promise it
does not keep.

The column stays; a migration to drop it would be risk for no gain. Nothing may write it until
there is an image story worth building.

## A seam worth knowing about

`ContentService::attributes()` keeps its **own** allow-list of what a write persists, independent
of `WriteContentRequest` and of `$fillable`. A field accepted by the request but missing there is
dropped in silence — no error, no stored value. `source_name` and `source_url` had to be added in
both places. The list now carries a comment saying so.

## Verification

| Check | Result |
| --- | --- |
| `ContentEditingCompletenessTest` (new) | 11 passed, 55 assertions |
| SQLite full suite | 1,001 passed, 2 skipped, 5,538 assertions |
| MySQL full suite | 1,001 passed, 2 skipped, 5,538 assertions |
| Pint | 288 files, pass |
| `npm run build` | built |
| Live dev server | the round-trip `PATCH` that returned 422 now returns 200; `javascript:` source URL 422; `cover_image` 422; edit page 200 |

The live check wrote `type: "GUIDE"` back to post 3, which already held `guide` — the value is
unchanged and only `updated_at` moved. No other development content was modified: no post gained a
source, and `cover_image` remains empty everywhere. The token the check signed in with was revoked
afterwards.
