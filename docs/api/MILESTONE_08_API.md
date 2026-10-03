# Milestone 08 API — content, CMS and tax administration

API base: `http://localhost:8088/api/v1`. Send `Accept: application/json`.

M8 adds routes; it removes and renames none. Every M1–M7.5 endpoint behaves exactly as before,
and no tax result changed.

---

## Public content

No authentication. Nothing unpublished is reachable: every query runs through one definition of
public visibility — `status = published`, `published_at` not null and already past.

| Method | Route |
| --- | --- |
| GET | `/content/articles` |
| GET | `/content/articles/{slug}` |
| GET | `/content/featured` |
| GET | `/content/faqs` |
| GET | `/content/categories` · `/content/categories/{slug}` |
| GET | `/content/tags` · `/content/tags/{slug}` |

### Listing

`GET /content/articles?type=GUIDE&category=tax-knowledge&tag=pnd91&tax_year=2568&q=ลดหย่อน&page=1&per_page=12`

Ordered by `published_at` descending. `per_page` defaults to 12 and is capped at 100. `q`
searches `title` and `excerpt` only, with `%` and `_` escaped. An unknown query parameter is a
422 rather than being ignored.

### Detail

```json
{ "success": true, "message": null,
  "data": { "type": "GUIDE", "title": "…", "slug": "prepare-pnd91", "excerpt": "…",
            "body": "# เตรียมเอกสาร\n\n- …", "body_format": "STRUCTURED_TEXT",
            "featured": false,
            "category": { "name": "ความรู้ภาษี", "slug": "tax-knowledge" },
            "tags": [{ "name": "ภ.ง.ด.91", "slug": "pnd91" }],
            "tax_year": 2568, "meta_title": "…", "meta_description": "…",
            "published_at": "2026-09-12T00:00:00+00:00" } }
```

`body_format` is always `STRUCTURED_TEXT`. The body is never HTML — see
[CONTENT_CMS.md](../admin/CONTENT_CMS.md#body-format-and-why-it-is-not-html). No `id`, `status`,
author identity or draft metadata is exposed.

---

## Admin

`auth:sanctum` + the `admin` middleware. **401** unauthenticated, **403** for a member.

### CMS

| Method | Route |
| --- | --- |
| GET / POST | `/admin/content` |
| GET / PATCH / DELETE | `/admin/content/{id}` |
| POST | `/admin/content/{id}/publish` · `/unpublish` · `/archive` |
| GET / POST | `/admin/content/categories` · `/admin/content/tags` |
| PATCH / DELETE | `/admin/content/categories/{id}` · `/admin/content/tags/{id}` |

Create request:

```json
{ "type": "GUIDE", "title": "คู่มือทดลอง ภ.ง.ด.91", "slug": "pnd91-simulation-guide",
  "excerpt": "คำแนะนำก่อนเริ่มทดลองคำนวณ", "body": "# เตรียมข้อมูล\n\n- หนังสือรับรองเงินเดือน",
  "category_id": 2, "tag_ids": [1, 3], "tax_year_id": 1, "featured": true,
  "meta_title": "คู่มือ ภ.ง.ด.91", "meta_description": "…" }
```

Always created as a draft. `status`, `published_at`, `author_user_id`, `author_id`,
`published_by` and `first_published_at` are **rejected with 422** rather than ignored.

`POST /publish` accepts an optional `published_at` in the future, which schedules the post
without any job infrastructure.

### Tax sources

| Method | Route |
| --- | --- |
| GET / POST | `/admin/tax-sources` |
| GET / PATCH / DELETE | `/admin/tax-sources/{id}` |

`file_path` must be a repository path: a `..` segment, a scheme or a URL is a 422. DELETE
deactivates; a source cited by a published rule is refused with
`TAX_SOURCE_CITED_BY_PUBLISHED_RULE`.

### Rule versions

| Method | Route |
| --- | --- |
| GET / POST | `/admin/tax-rule-versions` |
| GET / PATCH | `/admin/tax-rule-versions/{id}` |
| POST | `/admin/tax-rule-versions/{id}/clone` · `/validate` · `/publish` · `/archive` |

### Draft rule editing

| Method | Route |
| --- | --- |
| GET / POST | `/admin/tax-rule-versions/{id}/{resource}` |
| PATCH / DELETE | `/admin/tax-rule-versions/{id}/{resource}/{ruleId}` |

`{resource}` ∈ `tax-brackets`, `expense-rules`, `allowance-rules`, `allowance-cap-groups`,
`donation-rules`, `recommendation-rules`. Anything else is a 404; it is never used as a table
name.

**Every write to a non-draft version is a 403.** See
[TAX_RULE_VERSION_LIFECYCLE.md](../admin/TAX_RULE_VERSION_LIFECYCLE.md).

### Dashboard and audit

| Method | Route |
| --- | --- |
| GET | `/admin` — content counts, published version, draft count, recent activity |
| GET | `/admin/audit-logs` — filters `entity_type`, `action` |

---

## Stable error codes added in M8

| Code | Meaning |
| --- | --- |
| `CONTENT_BODY_MARKUP_REJECTED` | HTML, a `javascript:` URL or an event handler in a text field |
| `CONTENT_SLUG_IMMUTABLE` | The slug is frozen from the first publish |
| `CONTENT_ARCHIVED_NOT_PUBLISHABLE` | Restore archived content to draft before publishing |
| `RULE_VERSION_NOT_DRAFT` | Only a draft version may be edited or published |
| `RULE_VERSION_DUPLICATE` | The tax year already has a version with that identifier |
| `RULE_VERSION_INVALID` | Publication refused; the message lists the validation codes |
| `RULE_NOT_IN_VERSION` | The rule belongs to another rule version |
| `TAX_SOURCE_CITED_BY_PUBLISHED_RULE` | Deactivate instead of deleting |

---

## Web pages

`/knowledge`, `/news`, `/faq`, `/article/{slug}` — public, server-rendered.
`/admin/login`, `/admin`, `/admin/content`, `/admin/content/create`,
`/admin/content/{id}/edit`, `/admin/tax-rule-versions`, `/admin/tax-rule-versions/{id}`,
`/admin/tax-sources` — the console shell; every write goes through the admin API above.

---

## Compatibility

No tax-engine semantics changed. The M7.5 baseline is intact: PND91 net 620,000 / tax 45,500,
the PND90 minimum tax, family derivation, combined caps, and every M7.5 runtime guard behave
identically. Rule version **2568.1 remains published and immutable**.
