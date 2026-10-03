# Milestone 09.1 mockup reconciliation

Visual acceptance reference: `docs/ui-reference/tax-simulator-mockup.png`.

| Page | Before | Reconciliation | Intentional deviation |
|---|---|---|---|
| Home | The hero had weak proportions and the page stopped before feature and content sections. | Added the blue hero band, clear CTA hierarchy, visual benefit panel, four feature cards, form comparison, featured articles, news, and disclaimer. | The reference illustration is represented with native UI shapes and icons so it stays sharp and accessible without introducing an unapproved image asset. |
| Form selection | PND90 and PND91 appeared as generic white boxes. | Added distinct rose/blue document icons, form badges, supported-income guidance, aligned actions, and interactive states. | Copy is based on the approved form mappings in the application. |
| PND91 | The functional wizard had limited visual hierarchy. | Standardized the stepper, numbered sections, field states, repeatable rows, navigation, review, and responsive overflow. | PND91 keeps the simpler one-income flow required by its approved mapping. |
| PND90 | Multiple income entry was visually dense. | Kept the shared wizard language while making income type and subtype choices explicit and grouping repeatable entries. | PND90 uses a rose cue while retaining the same component system as PND91. |
| Result | Results looked like another input card. | Added status banner, amount priority, summary metrics, breakdown table, trace disclosure, warning/guidance cards, and planning/save actions. | Every amount and difference remains server-derived; JavaScript only formats API responses. |
| Planning | Before/after values lacked comparison hierarchy. | Added a four-metric comparison, warning and recommendation regions, and source-return notice. | No formula or inferred saving is calculated in the browser. |
| Login / Register | Authentication used a compact generic card. | Added branded blue context panel, clear field requirements, password help, full-width primary action, and a route back to the simulator. | The context panel collapses on narrow screens to keep the form first. |
| Dashboard | Member pages read as a compact administration screen. | Added welcome and quick-action header, summary cards, card-based returns, filters, history, completed/read-only state, and scenario cards. | Data remains loaded from the existing Sanctum API after client authentication. |
| Knowledge / News | Content lists had weak metadata and empty states. | Added reusable article cards with type/category, excerpt, date, tags/tax year, search/filter presentation, and designed empty states. | Seeded copy avoids unapproved legal claims and numerical rules. |
| FAQ | The empty state was a plain white box and disclosures had little hierarchy. | Added an intentional empty state with a next action and retained keyboard-native `details` disclosures for populated FAQs. | Native disclosure controls provide reliable keyboard behavior without a custom widget. |

## Shared visual language

`resources/css/app.css` contains the small Tailwind component layer used by all pages: page and surface colors, text hierarchy, blue accent, status tones, radii, shadows, spacing, buttons, inputs, cards, stepper, results, tables, loading, and empty states. The header uses the approved white bar, brand lockup, active underline, desktop navigation, login CTA, and collapsible mobile navigation.

## Responsive and accessibility checks

The page shell, navigation, cards, forms, wizard, results, tables, dashboard, content lists, and FAQ were checked at 375px, 768px, and 1280px widths. Wide steppers and tables scroll inside their own region. Controls retain visible focus styles; inputs have labels; required/help states are written in text; status regions include words and icons; mobile navigation exposes `aria-expanded`; and the shell provides a skip link and ordered headings.

## Verified end state

Measured against the running development stack on `http://localhost:8088`, with the seeded
development database in place.

### Overflow

No page produces horizontal overflow. `document.documentElement.scrollWidth` equals the viewport
width at 375px, 768px and 1280px for `/`, `/tax-simulator`, `/tax-simulator/pnd90`,
`/tax-simulator/pnd91`, `/knowledge`, `/news`, `/faq`, `/login`, `/register` and
`/article/{slug}`. The one element wider than a phone screen — the wizard stepper — scrolls
inside its own `.ui-scroll-x` region (`scrollWidth > clientWidth` on the stepper, not on the
page), which is what keeps the page itself still.

Each step reserves a minimum width (`.ui-step`), because without it two adjacent Thai step labels
touch at 375px rather than the row simply becoming scrollable.

### Heading structure

Every page has exactly one `h1` and no skipped heading level. The two shared cards
(`x-article-card`, `x-form-choice-card`) take a `heading` prop for this reason: they render `h3`
beneath a section's `h2` on the home page, and `h2` where they sit directly beneath the page
`h1` on the listing and form-selection pages.

### Labels and state

No `input`, `select` or `textarea` on any server-rendered page is without a label, an
`aria-label`, or an associated `label[for]`. Required, optional, server-derived and unsupported
field states are written as words (`จำเป็น`, `ไม่บังคับ`, `เลือกไว้แล้ว`, `ยังไม่รองรับ`) beside
the label, never signalled by colour alone; the four message kinds (information, warning, action,
result) each carry an icon and a bold heading as well as a tint. The mobile navigation sheet is a
disclosure driven by `aria-expanded` with the panel's `hidden` attribute, so it leaves the
accessibility tree when closed and closes on Escape and on link activation.

### Intentional deviations from the mockup

| Deviation | Reason |
|---|---|
| The wizard presents more than the mockup's six steps. | The step list follows the topics the approved source form prints, which the form-fidelity work requires; the mockup's stepper *component* is used unchanged. |
| The hero illustration is composed from native UI elements rather than the mockup's bitmap. | No approved image asset exists in the repository, and a drawn panel stays sharp, themeable and readable to assistive technology. |
| The result page keeps the mockup's green "ผลการคำนวณเสร็จสิ้น" banner but renders the outcome amount inside it in the outcome's own colour. | The mockup shows the outcome only in the summary's final row; leading with it as well makes PAYABLE / REFUND / ZERO the first thing read, which the milestone requires of the result hierarchy. |
| The knowledge and news pages expose category as chips and keep tag and tax-year filters inside a disclosure. | The mockup shows only category chips; the other two filters exist in the API and are useful, but do not belong in the primary row. |

## Admin console

M9.1 originally left the console alone, which produced a second visual language inside the same
product: raw utilities repeated inline, inputs that did not match the public ones, and tables
with their own border rules. It is now expressed in the same tokens.

| Page | Before | Change | Remaining deviation |
|---|---|---|---|
| Shell | One-row header; active item a tinted pill; no skip link | Brand lockup, second-row navigation with the shared active underline, scrollable on narrow screens, skip link, "กลับไปหน้าเว็บ", guard notice as a warning note | Deliberately soberer than the public site: no hero, no marketing colour |
| Sign-in | Inline `rounded-lg border …` fields, bare submit | `ui-card`, shared field and button scale, live-region message | — |
| Overview | Three hand-built count boxes | `ui-card` tiles on the metric scale, quick-action row | — |
| Content list | Raw table classes; status as a bare lowercase word; blank `tbody` when empty | `ui-table`, status pills with Thai labels, designed empty row, quiet action buttons | — |
| Content editor | Every field styled inline; constraints undocumented | Shared field scale; body-format and slug-freeze rules stated in help text; link to taxonomy | Body stays a plain textarea — the format is structured text, not HTML, so a rich editor would be wrong |
| Rule versions | Raw table; status a lowercase word | `ui-table`, status pills, empty row, explanation of the clone→validate→publish path | — |
| Rule version detail | Lock and history warnings as grey/amber paragraphs | The two blocking conditions as `ui-note` warnings with icon and bold lead | — |
| Tax sources | All six columns identically styled | Code and path in mono, citation count in words, active/inactive as a pill, empty row | — |
| **หมวดหมู่และแท็ก (new)** | No page existed — the M8 taxonomy API had no interface, so labels could only be created by a seeder or a raw API call | New `/admin/taxonomy`: create, rename, delete/deactivate for both categories and tags, each row showing how many posts use it | — |

### Why the usage count was added

`AdminContentTaxonomyController` deactivates a label that content is filed under and deletes one
that is unused. That is the right behaviour — a published post must not lose the category it was
published with — but it makes one button do two different things. The admin taxonomy resource
therefore now carries `content_count` (from `withCount('posts')`), the row shows it, and the
confirmation names the outcome that will actually occur.

### Not done in M9.1

User/role management and a searchable audit-log viewer. Both are new features rather than
reconciliation, and the permission model they imply belongs with Milestone 10's security work.
`admin_audit_logs` is still surfaced only as the overview's recent-activity list.

## Two layout defects found by measuring

Both were invisible in a static read of the markup and were found by driving the real result page
at 375px, where the document was 65px wider than the viewport.

**A responsive grid with no base column count.** `class="grid gap-5 lg:grid-cols-2"` declares its
columns only from `lg` up. Below that breakpoint the grid has one *implicit* column, which is
sized by content rather than by the container, so a long Thai label widened the card, the card
widened the track, and the track widened the page. Every responsive grid in `resources/views` and
`resources/js` now also declares `grid-cols-1`.

**A flex item that could not shrink.** A summary row is a label and an amount in a flex
container, and a flex item defaults to `min-width: auto` — it will not shrink below its content.
The pair therefore set a minimum width wider than a phone screen. `.ui-summary-label` now takes
the remaining space and may wrap (`min-w-0 flex-1`); `.ui-summary-value` never wraps
(`shrink-0 whitespace-nowrap`).

`ResponsiveLayoutInvariantTest` asserts both, because both are ordinary-looking markup that is
easy to reintroduce. After the fix, 17 routes × 3 widths (375 / 768 / 1280) show no horizontal
overflow, admin pages included.

## Test reconciliation

Twelve tests were failing when this milestone resumed. Two were regressions from the result-page
rewrite; the rest were tests written before the M9.2 input contract and never updated to it.

| Failure | Cause | Resolution |
|---|---|---|
| `Milestone092FormFidelityTest`, `Milestone0921FormRoleAuditTest` (minimum-tax guard) | The result rewrite showed the ภาษีขั้นต่ำ row whenever the field was present, dropping the ภ.ง.ด.90 guard | Guard restored: the row is printed only on ภ.ง.ด.90 |
| `Milestone0921FormRoleAuditTest` (coverage matrix ×2) | The matrix gained five columns; the audit read columns by position | The audit resolves its four columns from the header, so adding a column is no longer a false failure |
| `MemberTaxCalculationTest`, `MemberTaxReturnApiTest` | `eligible` is now a required dependent fact | Tests declare it, as the wizard already does |
| `Pnd90SourceExpenseRuleTest` ×2 | ข้อ 9 gift income must state its tax treatment | The fixture declares the ordinary (progressive) choice |
| `Pnd90RemainingRulesTest` | One donation line may no longer be declared twice | Split: the cap is still asserted from a single entry, and the duplicate is asserted to be rejected |
| `FilingInstructionReconciliationTest` ×3 | An under-specified child, or a spouse block without a married status, is now refused rather than partly computed | Rewritten to assert the refusal; the allowance amounts themselves are still asserted from complete data by the neighbouring cases |

No tax rule changed. `InputIntegrityValidator` states input-shape rules only and computes nothing,
and published version 2568.1 was neither edited nor republished. The warning codes the earlier
behaviour produced (`CHILD_TYPE_NOT_DECLARED`, `CHILD_BIRTH_ORDER_NOT_DECLARED`) remain live in
`ChildAllowanceStrategy` and unit-tested, because a saved return written before the requirement
can still reach the calculator.

## Admin console — capability coverage

The restyle above left a second, larger problem untouched: the console was a mostly read-only
view of a mostly writable API. Publishing a rule version was possible; changing a rule inside the
draft you were about to publish was not, which is the one thing a draft exists for.

### What was missing, and what it now has

| Capability | API since | Console before | Console now |
|---|---|---|---|
| Draft rule rows — add / edit / delete, all six entities | M8 | read-only table | full editing, only while the version is a draft |
| Source registry — register, edit, retire | M8 | read-only table | form plus per-row controls |
| Audit trail with action / entity filters | M8 | last few lines on the overview | its own page, filtered and paged |
| Rule version — edit description, archive | M8 | absent | draft-only controls |
| Content — delete, unpublish from the list | M8 | absent | per-row controls |

The menu is now grouped — ภาพรวม · เนื้อหา · กฎภาษี · ระบบ — because a flat list of five gave no
sense of which pages belong together. Group names are screen-reader only; sighted separation is
the divider.

### Two real defects found while doing it

**Content could not be saved from the console at all.** The form offered its type values in upper
case (`ARTICLE`) while `ContentPost::TYPES` is lower case, so every create and every edit failed
validation on `type`, and an existing post's type never preselected. Fixed, with a test asserting
the form offers exactly the vocabulary the API validates against and that each offered value can
actually be created.

**The source control promised an outcome the API never produces.** It offered "ลบ" for an uncited
document, but `TaxSourceService::delete()` deactivates in every case and refuses outright while a
published rule cites the document. The control now says ปิดใช้งาน and warns about the refusal.

### What keeps it from drifting again

`AdminConsoleCoverageTest` walks the registered admin API routes and fails if a write route has no
control in the console, with a short allow-list for the one route deliberately not offered
(creating an empty rule version — versions are created by cloning, so the rules carry forward).

`AdminDraftRuleFieldParityTest` compares the console's field spec against `DraftRuleRegistry`
field by field, so the form can never offer a field the API rejects nor omit one it requires.

### Deliberately still absent

User and role management, and editing the audit log. The first is a new feature with a permission
model behind it and belongs with Milestone 10's security work; the second would make the audit
trail worthless as evidence.

### The console had no entrance

The menu above was complete and correct, and still unreachable. Nothing on the public site or in
the member area linked to `/admin`, so an administrator who signed in normally saw no
administrative menu anywhere and had to know the path by heart. That is what "I don't see those
menus" meant: the pages existed, the way in did not.

`/auth/me` now answers `is_admin`, and the shells carry a link that `auth.js` reveals when the
answer is true — in the desktop header, the mobile sheet and the member dashboard. A boolean
rather than the role string, because that is the entire question the browser has. Revealing the
link authorises nothing: every console page is a data-free shell and every admin endpoint checks
the caller itself, which `AdminConsoleEntryPointTest` asserts by signing in as a member and
watching the API refuse.

**A leak found while verifying it.** The link was first hidden with `class="hidden lg:inline-flex"`,
and `lg:inline-flex` wins over `hidden` at desktop width — so ordinary members saw the admin link
on a wide screen. Anything toggled by script now uses the `hidden` *attribute*, with
`[hidden] { display: none !important }` in the base layer so no breakpoint can override it. The
test pins both halves: the attribute is present, and the class is not.

## Role-aware presentation

Each audience is now shown the navigation its role can actually use, and the whole product shares
one session.

### One mechanism instead of three

Visibility used to be decided in three places with three ad-hoc attributes — `data-guest-navigation`,
`data-member-navigation`, `data-admin-navigation` — each deciding it slightly differently. That is
how the admin link came to be shown to ordinary members at desktop width.

There is now one attribute and one decision point. `session.js` resolves the viewer once from
`/auth/me` and applies `data-visible-to`:

```html
<a data-visible-to="guest"        hidden href="/login">เข้าสู่ระบบ</a>
<a data-visible-to="admin"        hidden href="/admin">ผู้ดูแลระบบ</a>
<button data-visible-to="member admin" hidden data-member-logout>ออกจากระบบ</button>
```

An element without the attribute is untouched, because most of the page is for everyone and this
must not become something that has to be applied to every node.

### What each audience sees

| | Guest | Member | Administrator |
|---|---|---|---|
| Public pages | ✓ | ✓ | ✓ |
| เข้าสู่ระบบ | ✓ | — | — |
| แดชบอร์ด · แบบภาษีของฉัน | — | ✓ | — |
| ผู้ดูแลระบบ | — | — | ✓ |
| ออกจากระบบ | — | ✓ | ✓ |
| Console menu (6 items) | — | — | ✓ |

An administrator is not shown the member affordances in the header: their home is the console.
Their own simulations remain reachable at `/dashboard`, which is a page, not a missing menu.

### One sign-in

The console kept its token under `tax-simulator.admin-token` while the site used
`tax-simulator.member-token` — the same storage with the same lifetime, so the separation bought
nothing and cost an administrator a second password entry at `/admin`. Both now read
`tax-simulator.session-token`, and the two old keys are migrated on first read so a session open
across the change survives. Signing in also lands by role: an administrator arrives at the
console, a member at their dashboard, and an explicit destination still wins over both.

### The console says why it is empty

Three states are now told apart rather than collapsed into "ไม่สามารถโหลดข้อมูลได้": nobody is
signed in; somebody is but their account has no admin rights (named, with a way to their own
dashboard); or the page loads. The console header also shows who is signed in.

### This is presentation, never permission

`RoleAwareNavigationTest` asserts both halves. Every audience-specific control starts hidden by the
`hidden` *attribute* — never the bare `hidden` class, which a later display utility overrides — so
a visitor without JavaScript is never shown a control meant for someone else. And a member signed
in on the public site is refused every administrative endpoint regardless of what their browser
chose to display.

## Admin console visual pass

The sidebar worked but did not yet look like a finished console. Three things carried most of the
ugliness, and all three were content problems rather than styling ones.

**The activity feed was a dump of an enum.** It read
`CONTENT_UPDATED · Deleted unused category tax-knowledge-m8 · ผู้ดูแลระบบทดสอบ` — the constant is
for us, and the English summary was written for a log file. Each action now has a Thai phrase, a
kind, and a tinted icon, so the feed reads as a sentence: *แก้ไขเนื้อหา · หมวดหมู่*, then the
actor and a full timestamp. The stored record is untouched — it is evidence — so the original
English summary still appears as the detail column; only the framing is written for a reader.

**The metric tiles were mostly empty space.** A small grey word above a large figure, with nothing
to anchor either. They now carry an icon tile, the figure, and a line saying what the figure
means (`มองเห็นได้บนหน้าเว็บ`, `ยังไม่เผยแพร่`, `เก็บไว้ ไม่แสดงผล`).

**The sidebar had no weight.** Icons now sit muted until hovered, the current item is a filled
pill with a small bar on its inner edge — which is what makes the current page findable in a
collapsed rail, where the label is gone and the fill alone has to carry it — and group labels have
real spacing. The signed-in identity moved out of the top bar and into the foot of the rail as an
initial, a name and a role, which is where a console usually puts it. The top bar in turn became a
breadcrumb instead of a copy of the page's own heading, and the content area now uses the width a
console needs for its tables.

### A note on measuring

Several rounds of this pass were spent chasing a collapse that appeared not to work: the rail
measured 256px however it was styled. It was the measurement. The browser pane returns stale
layout while its window is hidden — it reported 256px even for an inline `width: 80px !important`,
which is not possible — and screenshots time out for the same reason. A screenshot taken once the
window was visible showed the rail collapsing correctly all along. Two of the fixes made during
that stretch were nonetheless real and kept: a flex item needs `min-w-0` or its automatic minimum
size inflates it back to its content's width, and the rail width now has a single author.

---

## Family allowances are claimed from declared facts (2026-09-15)

The allowance step says these lines are computed for the reader and gives them no control to claim
them with, but the calculator only applied a family line when the payload named its code — and no
client ever did. A married filer with one child was quoted 21,000 baht more than the form gives
them, with no warning. Claiming now follows the declared facts, in `AllowanceCalculator`, so Guest
and Member share one behaviour.

Full justification for every changed tax figure, and the list of what deliberately did not change,
is in [FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md](../tax/FAMILY_ALLOWANCE_AUTOMATIC_CLAIM.md).

### Verification

| Check | Result |
| --- | --- |
| SQLite full suite | 920 passed, 2 skipped, 5,199 assertions |
| MySQL full suite (`--env=mysql_testing`) | 920 passed, 2 skipped, 5,199 assertions |
| `FamilyAllowanceClaimingTest` (new) | 8 passed, 32 assertions |
| Pint (`app/Services/Tax`, the four touched test files) | 45 files, pass |
| `npm run build` | built, 18 modules |

The frozen M4 no-family regression figure (net 620,000 / tax 45,500) is untouched and still green.
No published rule version, seeder or source document was modified; `2568.1` remains the only
published baseline.

---

## Blocked allowances explain themselves (2026-09-15)

`AllowanceCoverageCatalogue` has always held a specific, source-cited reason for each code it
blocks, but that reason only reached a caller who submitted a positive amount and was refused with
a 422. A client deciding what to render had nothing to go on but the absence of a rule, so every
blocked code looked identical and the allowance step explained all five with one sentence.

Two changes, both driven by what the source documents actually say:

**1. Coverage is metadata.** `AllowanceTypeResource` now returns
`coverage: {status, reason_code, reason}` on `GET /tax-years/{year}/allowances` and on the
single-code endpoint. `status` is `SUPPORTED`, `PARTIAL_BLOCKED` or `UNSUPPORTED`, straight from
the catalogue, so a client never has to infer coverage from a missing rule — which would also have
mislabelled the five family-derived codes, none of which carries a rule row.

**2. The list under "รายการที่มีในแบบ" holds only lines ใบแนบ prints.** It was built from "has no
rule", which swept in the three umbrella master categories — `INSURANCE`, `OTHER`,
`ANNUAL_TAX_MEASURES` — that the attachment never prints as a line. Telling a reader those appear
on their form and are merely not supported yet sent them looking for something that does not
exist. The section is now built from `PARTIAL_BLOCKED` alone, and is omitted entirely when nothing
qualifies.

| Code | Status | What the reader now sees |
| --- | --- | --- |
| `PENSION_INSURANCE` | PARTIAL_BLOCKED | ใบแนบ ข้อ 7.6 prints two ceilings without saying whether one includes the other |
| `SOCIAL_SECURITY` | PARTIAL_BLOCKED | ใบแนบ ข้อ 12 defers the ceiling to a law outside the project's sources |
| `INSURANCE`, `OTHER`, `ANNUAL_TAX_MEASURES` | UNSUPPORTED | not listed — ใบแนบ prints no such line |

No rule, amount, cap or validation changed: a positive amount on any of these five is still the
same 422 with the same stable error code. Only what the reader is told changed.

`Milestone0921FormRoleAuditTest::test_unsupported_items_have_no_enabled_numeric_entry` used the old
generic sentence as its proxy for "blocked items get no input field". It now asserts the card's
shape — a reason paragraph and no input — so the guard holds while the wording stays free to
improve.

### Verification

| Check | Result |
| --- | --- |
| SQLite full suite | 928 passed, 2 skipped, 5,294 assertions |
| MySQL full suite | 928 passed, 2 skipped, 5,294 assertions |
| `AllowanceCoverageMetadataTest` (new) | 8 passed, 94 assertions |
| Pint | pass |
| `npm run build` | built |
| Live dev API | all five blocked codes return their own reason; no other code is non-SUPPORTED |
