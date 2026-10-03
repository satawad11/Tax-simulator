# Closing the editorial loop

2026-09-16. Items 1–3 of [ADMIN_SURFACE_AUDIT.md](ADMIN_SURFACE_AUDIT.md).

Before this, the loop for writing an article was: write it, **publish it**, look at it, fix it.
`ContentService::publicQuery()` excludes drafts and nothing else could render an article, so
`/article/{slug}` returned 404 for anything unpublished. Publishing was how an author saw their own
work — on a product whose published content is the part readers are asked to trust.

The structured-text body made it sharper still: an author writing `#` headings and `-` lists had no
way to check they had parsed as intended until the piece was live.

---

## 1. Draft preview

### The shape of the problem

The console authenticates with a bearer token held in session storage. A plain `<a href>` carries
no headers, so it cannot link to a protected page. The preview therefore has to move its
authorisation **into the URL**.

- `GET /api/v1/admin/content/{content}/preview-url` — behind the admin middleware and
  `ContentPolicy`, mints a signed, expiring URL and returns it with its expiry.
- `GET /content-preview/{content}` — a web route behind Laravel's `signed` middleware, rendering
  the same `content.show` view a reader gets.

The same pattern as the Phase 1 email verification link, for the same reason: the request arrives
as a plain browser GET carrying nothing, so the signature is the authorisation.

### What it deliberately does not do

**It does not touch `publicQuery()`.** That method is the single definition of what the public can
see, and every listing, feed, home page and API response depends on it meaning exactly one thing.
A `withDrafts()` flag would move the burden of remembering onto every future caller — and the one
that forgot would leak a draft. The preview controller reads the post directly instead, so the
public query keeps its one meaning.

`ContentPreviewTest::test_a_draft_is_still_invisible_everywhere_a_reader_looks` checks five
surfaces — the article page, the knowledge listing, the home page, the single-article endpoint and
the article collection — and fails if the fix ever leaks.

### Thirty minutes, and the response says so

A signed URL is a bearer credential in a string: whoever holds it sees the draft until it expires.
That is useful — an editor pastes it to a colleague for a second opinion — and is exactly why it
must be short. Long enough to review a draft; short enough that a link left in a chat log stops
working. The API returns `expires_at` and `expires_in_minutes` so a client can say so too.

The signature covers the whole URL, so it cannot be moved to another post, and it is bound to the
host it was minted for — verified live: a link minted for `127.0.0.1` returns 403 on `localhost`.

### The banner is not decoration

A preview of a **published** post is pixel-identical to the live page. Without a banner an
administrator could believe an edit was live while it was still a draft. So the page states the
post's current status, says it is visible only to administrators, and warns that the link expires.
The `<title>` is prefixed `ตัวอย่าง —`, and the page is `noindex` — while the live article stays
indexable, which a test asserts in the same breath.

### Where the buttons are

| Place | Control |
| --- | --- |
| Content list, every row | **ดูตัวอย่าง** |
| Content list, published rows only | **ดูหน้าจริง** → `/article/{slug}` |
| Edit form, once the post is saved | **ดูตัวอย่าง (ตามที่บันทึกไว้ล่าสุด)** |

The edit-form label says *ตามที่บันทึกไว้ล่าสุด* because preview opens what is **stored**, not the
unsaved form. Saying so on the button is cheaper than letting an author wonder why their last
paragraph is missing.

`window.open('', '_blank')` is called **before** the fetch, not after: a window opened following an
async gap is blocked by every browser. The tab opens empty and is pointed at the URL when it
arrives — and if the browser blocked it anyway, the console says so rather than failing silently.

## 2. A way back to the live page

Nothing linked from the console to `/article/{slug}`; an administrator had to know the URL shape
and type it. Published rows now carry **ดูหน้าจริง**, opening in a new tab with `rel="noopener"`.
It appears only when the post is published, because before then there is no live page to look at.

## 3. Types named in Thai

The content list rendered `item.type` as it arrived — `ARTICLE`, `NEWS`, `FAQ` — while the edit
form showed บทความ, ข่าวสาร, คำถามที่พบบ่อย for the same values. Two vocabularies for one field, one
of them not in Thai, on the page an editor uses most. The list now uses the same labels the form
and the article page already use.

## Verification

| Check | Result |
| --- | --- |
| `ContentPreviewTest` (new) | 15 passed, 41 assertions |
| SQLite full suite | 1,042 passed, 2 skipped, 5,798 assertions |
| MySQL full suite | 1,042 passed, 2 skipped, 5,798 assertions |
| Pint | 292 files, pass |
| `npm run build` | built |

Live on the development server, against a real draft (post 17):

- minting returned a signed URL with a 30-minute expiry;
- the signed URL rendered **200** with the banner and `noindex`;
- unsigned → **403**; tampered signature → **403**; the same signature moved to another host →
  **403**, rendering the Thai 403 page from Phase 4;
- `/article/{slug}` for that draft still returns **404**;
- the draft's status is unchanged and no development content was modified.

The browser rendered the preview with its banner, the `ตัวอย่าง —` title prefix, and the draft's
structured body — headings and lists parsed — which is precisely what an author could not see
before.
