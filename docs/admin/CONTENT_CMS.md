# Content CMS — Milestone 08

The public knowledge / news / guide / FAQ system, and the admin console that feeds it.

## Content model

`content_posts`, extended from the M2 schema. M8 added `featured`, `sort_order`, `tax_year_id`,
`meta_title`, `meta_description`, `published_by` and `first_published_at`, and widened `type`.

| Field | Notes |
| --- | --- |
| `type` | `article`, `news`, `guide`, `faq` — a closed vocabulary. M8 does not open an arbitrary content-type system. |
| `status` | `draft`, `published`, `archived`. Only a workflow action changes it. |
| `published_at` | Set by the publish action. A future value keeps the post out of the public query until then, which is scheduling without a job runner. |
| `first_published_at` | Set once, at the first publish. This is what freezes the slug. |
| `content` | The body, stored as **structured plain text**. Exposed as `body` in the API. |
| `author_id` | The authenticated admin who created it. Never accepted from a request. |
| `published_by` | The admin who published it. Never accepted from a request. |

FAQs are `type = faq` rather than a separate table, which keeps one model, one workflow and one
set of guarantees.

## Body format, and why it is not HTML

**Decision: structured plain text. HTML is refused at the API.**

The milestone allows sanitized HTML *if an established sanitizer already exists in the stack*.
None does, and writing one out of regular expressions is what the prompt explicitly forbids — a
hand-rolled sanitizer is a standing XSS bug waiting for the next bypass.

So `App\Services\Content\ContentBodyFormat` does two things:

1. **`isSafe()`** rejects any body (or title, excerpt, or SEO field) containing an HTML tag, a
   `javascript:` URL, an event-handler attribute or a `data:text/html` payload. Markup never
   reaches storage.
2. **`blocks()`** parses the stored text into a small closed set of blocks, which
   `resources/views/components/content-body.blade.php` renders through Blade's escaping. The
   view emits its own markup; user text is never treated as markup.

Supported structure:

```
# Heading            → heading, level 1–3 by the number of #
- item               → unordered list
1. item              → ordered list
anything else        → paragraph; a blank line starts a new one
```

Prose that merely mentions `script`, `<`, or `&` is stored unchanged and displayed escaped — a
test asserts exactly that, so the rule is "no markup", not "no scary words".

## Slugs

Unique, URL-safe (`^[a-z0-9]+(?:-[a-z0-9]+)*$`), and **immutable from the first publish**.

Before the first publish an admin may rename freely. Afterwards the API refuses the change with
`CONTENT_SLUG_IMMUTABLE`, and the form disables the field. Unpublishing does not thaw it: the
URL has already been public, and this project has no redirect layer that would make a rename
safe for anyone holding that link.

## Public API

No authentication. Every query runs through `ContentService::publicQuery()` — the single
definition of public visibility: `status = published`, `published_at` not null, `published_at`
in the past. Nothing else can reach a reader.

| Route | Purpose |
| --- | --- |
| `GET /api/v1/content/articles` | Listing; filters `type`, `category`, `tag`, `tax_year`, `q`, `page`, `per_page` (default 12, max 100). Newest first. An unknown query parameter is a 422. |
| `GET /api/v1/content/articles/{slug}` | One item. |
| `GET /api/v1/content/featured` | Up to 6 editor-chosen items. No personalization. |
| `GET /api/v1/content/faqs` | Published FAQs in editor order; filters `category`, `tax_year`. |
| `GET /api/v1/content/categories`, `/categories/{slug}` | Active categories. |
| `GET /api/v1/content/tags`, `/tags/{slug}` | Active tags. |

Search covers `title` and `excerpt` only, with `%` and `_` escaped so a wildcard cannot be
smuggled through `q`. M8 builds no full-text search infrastructure.

The public payload carries no `id`, `status`, author identity, draft metadata or internal note.

## Admin API

All under `/api/v1/admin/content`, behind `auth:sanctum` + the `admin` middleware, and
additionally authorized by `ContentPolicy` at each action.

| Route | Purpose |
| --- | --- |
| `GET /` `POST /` | List (filters `status`, `type`), create — always as a draft |
| `GET /{id}` `PATCH /{id}` `DELETE /{id}` | Read, edit, soft delete |
| `POST /{id}/publish` | `status = published`, `published_at = now` or an explicit future moment |
| `POST /{id}/unpublish` | Back to draft, `published_at` cleared |
| `POST /{id}/archive` | Archived, `published_at` cleared |
| `/categories`, `/tags` | Taxonomy CRUD |

A request body may not carry `status`, `published_at`, `author_user_id`, `author_id`,
`published_by` or `first_published_at`. `StrictApiRequest` rejects them outright rather than
ignoring them, so a client that tries learns that it failed.

A category or tag still in use is **deactivated** rather than deleted, so published content
never loses the label it was filed under.

## Public pages

`/knowledge` (articles + guides), `/news`, `/faq`, `/article/{slug}`. Server-rendered Blade in
the approved visual direction, reading through the same `publicQuery()`. Each page sets a
`<title>`, a meta description, a canonical URL and basic Open Graph tags. The simulator UI is
untouched; the navigation's existing "ความรู้ภาษี" and "ข่าวสาร" entries now point at real pages.

## Audit

`CONTENT_CREATED`, `CONTENT_UPDATED`, `CONTENT_PUBLISHED`, `CONTENT_UNPUBLISHED`,
`CONTENT_ARCHIVED`, `CONTENT_DELETED` — see [the rule administration doc](TAX_RULE_ADMINISTRATION.md#audit-log).

## Tests

`tests/Feature/PublicContentApiTest.php`, `tests/Feature/AdminContentApiTest.php`.
