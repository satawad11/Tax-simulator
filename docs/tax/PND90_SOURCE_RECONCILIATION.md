# PND90 source reconciliation — Milestone 07.1

> **Milestone 7.x is CLOSED.** This file records how a rule was reconciled, milestone by
> milestone, and its earlier sections are historical: a status there may have been superseded
> further down the same file. The authoritative final status of every rule is
> [TAX_ENGINE_BASELINE_2568.md](TAX_ENGINE_BASELINE_2568.md), with per-form detail in
> [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) and
> [PND90_PRODUCTION_BASELINE_2568.md](PND90_PRODUCTION_BASELINE_2568.md), and a source map in
> [SOURCE_COVERAGE_2568.md](SOURCE_COVERAGE_2568.md).

> **M7.3 source correction (2026-09-12).** The earlier audit banner on this file was written
> against `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` / `…91.pdf`, which were the blank
> return forms renamed — their SHA-256 hashes matched `ภงด.90.pdf` / `ภงด.91.pdf` exactly.
> The genuine filing-instruction booklets are now in the repository:
> `docs/tax-source/PND90-2568-filing-instructions.pdf` (18 pages) and
> `docs/tax-source/PND91-2568-filing-instructions.pdf` (15 pages). Its conclusion that
> "M7.3 adds no verified numeric rule" is therefore **withdrawn**: M7.3 resolves ใบแนบ
> items 1–5, five allowance ceilings and the 0.5% minimum tax from those booklets. See
> [family allowance model](FAMILY_ALLOWANCE_MODEL_2568.md) and
> [minimum-tax reconciliation](MINIMUM_TAX_RECONCILIATION_2568.md).
> Sections below dated to earlier milestones remain historical records of what was known then.


Record of what was read, what it supports, and what it does not.

## Source files inspected

| Path | Type | Pages | Used for |
|---|---|---|---|
| `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.90.pdf` | PDF, A4, Adobe PDF Library 17.0 | 5 | Every expense rule reconciled here |
| `docs/tax-source/วิธีการกรอกแบบ ภ.ง.ด.91.pdf` | PDF, A4, Adobe PDF Library 17.0 | 3 | 40(1) cross-check; confirming PND91 has no minimum-tax line |
| `docs/tax-source/1788851201423.jpg` | JPEG, 359×198 | 1 | Progressive bracket table cross-check |

No document was assumed to exist; no page was cited without being read.

### How they were read

The host has no PDF tooling, so `poppler-utils` was installed in the `app` container and the
files were read two ways for cross-checking:

- `pdftotext -layout`, then again per column (`-x/-y/-W/-H`) because the form is two-column and
  a full-page extraction interleaves the columns and would misattribute percentages;
- `pdftoppm -png -r 160` and visual reading of the rendered pages.

Every percentage recorded in the matrix was confirmed in both the column-isolated text and the
page image.

## Pages and sections inspected

| Page | Sections | Content |
|---|---|---|
| ภงด.90 p.1 | header | Taxpayer/spouse identity, filing status. Nothing rule-bearing for expenses. |
| ภงด.90 p.2 | ข้อ 1, ข้อ 2, ข้อ 3, ข้อ 4 (start) | 40(1)+40(2) combined deduction; 40(3) subcategories; 40(4) (no expense line); 40(5) rent subcategories |
| ภงด.90 p.3 | ข้อ 4 (end), ข้อ 5, ข้อ 6, ข้อ 7, actual-expense schedule | 40(5) hire-purchase; 40(6); 40(7); 40(8) subcategories; the 40(3)(5)(6)(7)(8) actual-expense list |
| ภงด.90 p.4 | ข้อ 8, ข้อ 9, ข้อ 10, ข้อ 11 | Separate-taxation elections; the full tax computation including the minimum-tax rule |
| ภงด.90 p.5 | ใบแนบ (attachment) | Allowance/exemption schedule — inspected, not reconciled (out of this milestone's scope) |
| ภงด.91 p.1–3 | header, การคำ�นวณภาษี, รายการเงินได้ที่ได้รับยกเว้น | 40(1) 50% deduction; no minimum-tax line |

## Rules verified and implemented

Thirteen new rows, plus a grouping change to the existing 40(1) row. Values, wording and page
citations are in `docs/tax/PND90_RULE_MATRIX.md`.

| Income type / subtype | Method | Value |
|---|---|---|
| 40(1) + 40(2) (shared group) | `percentage_limit` | 50% capped 100,000, applied once to the combined base |
| 40(3) `ANNUITY_FROM_WILL_OR_JUDGMENT` | `fixed` | 0.00 (no expense line printed) |
| 40(3) `COPYRIGHT_GOODWILL_OTHER_RIGHTS` | `percentage_or_actual` | 50% capped 100,000, or actual |
| 40(4) | `fixed` | 0.00 (no expense line printed) |
| 40(5) `RENT_BUILDING_OR_RAFT` | `percentage_or_actual` | 30%, or actual |
| 40(5) `HIRE_PURCHASE_BREACH` | `percentage` | 20% |
| 40(6) `MEDICAL_PRACTICE` | `percentage_or_actual` | 60%, or actual |
| 40(6) `FINE_ARTS` | `percentage_or_actual` | 60%, or actual |
| 40(6) `OTHER_LIBERAL_PROFESSION` | `percentage_or_actual` | 30%, or actual |
| 40(7) | `percentage_or_actual` | 60%, or actual |
| 40(8) `MUTUAL_FUND_PROFIT_SHARE` | `fixed` | 0.00 (no expense line printed) |
| 40(8) `IMMOVABLE_PROPERTY_INHERITED_OR_GIFTED` | `percentage` | 50% |
| 40(8) `GIFT_OR_SUPPORT_RECEIVED` | `fixed` | 0.00 (no expense line printed) |

### The 40(1) + 40(2) interaction

ข้อ 1 is one block: item 1 is 40(1), item 2 subtracts the exempt provident-fund items, item 3
adds 40(2), item 4 is the running total, and **item 5 takes one `ร้อยละ 50 แต่ไม่เกิน 100,000 บาท`
deduction from item 4**. The cap is therefore shared, not applied per income type. This is
modelled with an `expense_group` on the two rules and is covered by boundary tests
(`Pnd90SourceExpenseRuleTest::sharedCapBoundaries`), including the case that distinguishes the
two readings: 150,000 + 150,000 deducts 100,000 under the shared cap and would deduct 150,000
under independent caps.

### "No expense line" as a verified rule

Four categories print no `หักค่าใช้จ่าย` line at all and carry their amount straight to
`คงเหลือ`/`รวม`: 40(4) as a whole, 40(3) item 1, 40(8) item 2 and 40(8) item 4. That is the form
stating no deduction is taken, so they are seeded as `fixed` with `fixed_amount = 0.00` — exact,
auditable, and distinguishable from "we do not know the rule", which is what an absent row means.

## Rules partial

| Income type | Why |
|---|---|
| SECTION_40_5 | `RENT_OTHER` (ข้อ 4 item 1 (2)–(4)) prints `ร้อยละ ............` — a blank the taxpayer fills per asset. The rate is not in this document. The other two subcategories are verified and calculate. |
| SECTION_40_8 | `BUSINESS_COMMERCE_OTHER` (item 1) prints a blank percentage; `IMMOVABLE_PROPERTY_NON_TRADE` (item 3 (2)) prints a blank percentage **and** a holding-period field, and has a separate-taxation variant in ข้อ 8. The other three subcategories are verified and calculate. |

## Rules unverified

| Category | Missing evidence |
|---|---|
| 40(5) `RENT_OTHER` | The percentage for non-building rental assets. The form defers it to the taxpayer. |
| 40(8) `BUSINESS_COMMERCE_OTHER` | The percentage per business activity. The form defers it; it is set by a separate instrument not in this repository. |
| 40(8) `IMMOVABLE_PROPERTY_NON_TRADE` | The percentage, the meaning of `จำ�นวนปีที่ถือครอง` in the computation, and the relationship to the ข้อ 8 separate-taxation route. |

## Schema gaps found and closed

Two distinctions could not be represented before this milestone.

1. **Subcategories within one income type.** ภ.ง.ด.90 gives 40(3), 40(5), 40(6) and 40(8)
   several lines with different rates. Collapsing them into one percentage would invent a rate
   nobody stated. `conditions` JSON was rejected as the carrier: a subtype is a *lookup key*,
   not a condition to evaluate, the resolver deliberately fails closed on any rule carrying
   `conditions`, and a JSON blob is neither indexable nor readable next to the form.
   → `expense_rules.income_subtype`, `tax_return_incomes.income_subtype`.
2. **One deduction shared across two income types** (ข้อ 1). Nothing could express it.
   → `expense_rules.expense_group`.

A third column follows from the form's two-checkbox election:
→ `tax_return_incomes.expense_method_selection`, mirrored by the request field of the same name.

Both new rule columns are nullable, so every pre-existing row keeps its meaning: a NULL subtype
covers the whole income type, and a NULL group means the type is deducted on its own.

## Implementation changes

- `ExpenseRuleResolver` resolves by income type **and** subtype, accepts `percentage_or_actual`,
  and verifies that rules sharing a group agree.
- `IncomeCalculator` groups by income type and subtype and carries the election.
- `ExpenseCalculator` deducts per **expense group**, so a group spanning income types is
  deducted once against the combined basis.
- `TaxCalculationInputGuard` gained subtype, election and actual-expense checks, shared by
  Guest calculate, Guest planning and Member saved returns.
- `IncomeTypeResource` exposes per-subcategory rule readiness so a client can tell what it may
  offer before attempting a calculation; the existing `expense_rule` field is unchanged.
- `TaxCalculationService` emits `PND90_MINIMUM_TAX_NOT_APPLIED` where ข้อ 11 items 9–10 could
  bite (see the matrix).

`ProgressiveTaxCalculator` was not touched: combined net income still runs through it once.

## Rule-version decision — 2568.1 extended (Case A)

The rules were added to the published `2568.1` rather than creating a new version, because this
is completion of the dataset that version always intended, and it **changes no previously
calculable outcome**:

- 40(1) keeps `percentage_limit` 50% / 100,000 with identical mechanics; only `expense_group`
  was added, and a return containing 40(1) alone has a group basis equal to its own basis;
- 40(2)–40(8) could not be calculated at all before (they returned 422), so no saved return,
  draft, completed return, scenario or calculation snapshot can contain them;
- PND91 maps 40(1) only and is therefore untouched, which is asserted by regression tests.

Had any rule changed an outcome that was previously calculable, a new version would have been
required instead. No saved return was migrated, and every existing return keeps its own
`rule_version`.

## Known unresolved items

1. Minimum tax, ข้อ 11 items 9–10 — PARTIAL; base defined by form line numbers the schema does
   not record. Flagged at runtime by `PND90_MINIMUM_TAX_NOT_APPLIED`.
2. Donation rules, ข้อ 11 items 4 and 6 — now source-stated, still unimplemented and unseeded.
3. Foreign tax credit limit, ข้อ 11 item 13 — now source-stated, still unimplemented.
4. ภ.ง.ด.94 prepayment, ข้อ 11 item 15 — printed on the form, not in the credit enum.
5. Separate-taxation elections: ข้อ 8, ข้อ 9, and the 15% / 10% elections in ข้อ 3.
6. Allowance rules — the ใบแนบ schedule on page 5 was not reconciled; out of scope here.
7. Legal final rounding — unchanged, `ROUNDING_RULE_PENDING`.
8. The three unverified subcategories listed above.

---

# Milestone 07.2 addendum — remaining rules

Same three source files, re-read for the eight blockers this document left open. Full per-rule
detail is in `docs/tax/PND90_REMAINING_RULES_2568.md`; allowances are in
`docs/tax/ALLOWANCE_RULE_MATRIX_2568.md`.

## Additional pages and sections inspected

| Page | Section | Content |
|---|---|---|
| ภงด.90 p.4 | ข้อ 8, ข้อ 9, ข้อ 11 items 3–7, 9–10, 13, 15, 19 | Separate-taxation elections; donation arithmetic; minimum tax; foreign tax credit; the three prepayment checkboxes |
| ภงด.90 p.5 | ใบแนบ items 1–24 | The allowance attachment, read line by line |
| ภงด.91 p.2 | การคำนวณภาษี items 7–15 | Donation and credit wording cross-check; confirms PND91 has no minimum-tax line |
| ภงด.91 p.3 | ใบแนบ | Byte-identical to ภงด.90 p.5 |
| both, all pages | every `ร้อยละ` and `บาท` occurrence | Exhaustive sweep for any percentage or amount stated elsewhere |

The exhaustive sweep is what establishes the negative results: the three blank-percentage
subcategories, and the thirteen allowance lines with no printed amount, have no value anywhere
in these documents.

## Rules verified and implemented in M7.2

| Rule | Source | Value |
|---|---|---|
| `SPECIAL_DONATION` | p.4 ข้อ 11 item 4 | 2x actual, capped at 10% of item 3 |
| `GENERAL_DONATION` | p.4 ข้อ 11 item 6 | actual, capped at 10% of item 5 |
| `PROVIDENT_FUND` allowance | p.5 ใบแนบ item 8 | min(declared, 10,000) |
| `pnd93` / `pnd94` prepayments | p.4 ข้อ 11 item 15 | deducted in full |
| ข้อ 9 separate rate | p.4 ข้อ 9 and ข้อ 11 item 19 | 5% on declared non-exempt gift income, added after credits |

## Rules left PARTIAL or UNVERIFIED in M7.2

| Rule | Status | Missing evidence |
|---|---|---|
| Minimum tax, ข้อ 11 items 9–10 | PARTIAL | What `ข้อ 1 ถึง ข้อ 7 1. ถึง 4.` scopes. Everything else is printed. |
| `PERSONAL` allowance | PARTIAL | Which case gives 60,000 and which 120,000 (`แล้วแต่กรณี`) |
| `SPOUSE` allowance | PARTIAL | The combined-filing definition, deferred since M5; no spouse block in the payload |
| `CHILD` allowance | PARTIAL | Birth-order rule, eligibility, count limit; no dependents block in the payload |
| Foreign tax credit, ข้อ 11 item 13 | PARTIAL | Which ceiling `ไม่เกินภาษีที่ต้องเสียตามกฎหมายประเทศไทย` means |
| ข้อ 8 property election | UNVERIFIED | No rate, no holding-year schedule |
| ข้อ 3 15% / 10% elections | UNVERIFIED | The form only says what is excluded from the return |
| 13 allowance lines | UNVERIFIED | No amount printed |
| 3 expense subcategories | UNVERIFIED | Percentages deferred by the form |
| Legal rounding | UNVERIFIED | Nothing stated |

## Schema change

One nullable column: `tax_return_incomes.tax_treatment`. ภ.ง.ด.90 prints the same
42(26)(27)(28) income on two mutually exclusive routes — ข้อ 7 item 4 (progressive) and ข้อ 9
(5% separate) — and the choice is the taxpayer's, so it cannot be inferred and had to be stored
per income line. Donations and allowances needed no schema change: `donation_rules` and
`allowance_rules` already carried every field their printed rules require.

## Rule-version decision — 2568.1 extended again

Of the five implemented changes, four apply to input that was previously impossible or inert:
donation codes were previously unknown (422), `pnd94` was rejected by validation,
`tax_treatment` did not exist, and `PROVIDENT_FUND` was accepted but always granted `0.00` with
no saved return declaring it.

One change does alter an outcome for previously accepted input: **`pnd93` now reduces the
balance**, where it previously warned and granted nothing. A new rule version would not have
contained that change, because credit handling lives in `TaxCreditCalculator` rather than in
versioned rule data. Historical snapshots stay immutable, no saved return carries a `pnd93`
entry, and the change makes the engine agree with ข้อ 11 item 15, which has always deducted it.
Recorded here rather than applied silently.

## Known unresolved items after M7.2

1. Minimum-tax base scope — the only gap that can make a returned tax figure wrong.
2. `PERSONAL`, `SPOUSE`, `CHILD` allowance discriminators, plus a spouse and dependents block in
   the calculation payload for the latter two.
3. Thirteen allowance ceilings the attachment does not print.
4. Foreign tax credit ceiling and eligibility.
5. ข้อ 8 property-sale election rate and schedule.
6. Three blank-percentage expense subcategories.
7. Legal final rounding.

Page 5 carries QR codes to **วิธีการกรอกแบบ ภ.ง.ด.90** and **วิธีการกรอกแบบ ภ.ง.ด.91**, the filing
instruction booklets. They are not in the repository and would most plausibly resolve items 2,
3 and possibly 1.

## M7.3 audit outcome

The current filenames listed at the top are verified against all PDF pages. SHA-256 comparison
with the previous app-container files confirms exact byte equality, recorded in
[FAMILY_ALLOWANCE_MODEL_2568.md](FAMILY_ALLOWANCE_MODEL_2568.md). This supersedes any inference
that the new filenames supplied new filing instructions. The PDFs were not renamed or modified.

No production numeric rule, source-reference database value, seeder, migration, model,
request, DTO or calculator was changed. Existing seed references retain the historical
filenames; this documentation supplies the auditable mapping to the current files.

PERSONAL/SPOUSE/CHILD remain PARTIAL. PARENT/DISABLED_PERSON remain UNVERIFIED.
Minimum tax remains PARTIAL in warning-mode, not a complete calculation.
The current family API gap is documented, not filled with speculative fields.
No new legal-branch unit test is appropriate until a legal branch is verified.
New safeguard feature tests cover family input authority, no fabricated planning savings,
warning propagation, Guest/Member outcomes and preserved completed snapshots.

## M7.3 verification results (2026-09-12)

- SQLite full suite: 562 passed, 0 failed, 2 skipped, 2887 assertions.
- MySQL full suite: 564 passed, 0 failed, 0 skipped, 3243 assertions.
- New safeguard file: 8 passed, 63 assertions.
- Two pre-existing scenario assertions failed on MySQL JSON object-key ordering. They now
  compare the complete JSON value, preserving all keys, values and array order. No API
  behavior or tax expectation changed. Targeted scenario suite: 31 passed, 258 assertions.
- Pint passed for both changed test files; PHP lint passed. The requested --dirty attempt
  cannot run without Git, so explicit file paths were used. Compose config and npm build passed.
- Live Guest checks: single/married profiles with declared PERSONAL/SPOUSE/CHILD grant zero
  with warnings; unsupported spouse/dependents objects return 422; family planning saves zero.
- Live PND90 high-gross/low-net case retains PND90_MINIMUM_TAX_NOT_APPLIED even with zero
  normal tax. This is an incomplete-result warning, not a verified minimum-tax answer.
- Live synthetic member return 10: Guest/Member tax both 45500.00; family eligible zero;
  completed snapshot unchanged after recommendations/history reads; cross-user history 404.
  Two synthetic accounts and that return remain; all verification tokens were revoked.
- Home and health endpoints: HTTP 200. No migration, production seeding or database reset ran.
- New family/minimum-tax legal branches remain source-blocked. Milestone 08 was not started.
---

## Milestone 07.3 — filing-instruction booklets

| File | Size | SHA-256 | Pages | What it is |
| --- | --- | --- | --- | --- |
| `docs/tax-source/PND90-2568-filing-instructions.pdf` | 797,008 B | `ece283c0fdd14becf9412da48852578f6ed3ba72aa39c378be80161cc79e5f4e` | 18 | วิธีการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568 — text-readable on every page, 7,280–21,799 characters per page, no AcroForm |
| `docs/tax-source/PND91-2568-filing-instructions.pdf` | 809,127 B | `9ae4936ef71c6f33806e07911c5620ec9c0cfb3346e3fe514c8aa185f3f02529` | 15 | วิธีการกรอกแบบ ภ.ง.ด.91 ปีภาษี 2568 — same |
| `docs/tax-source/PND90-2568-form.pdf.pdf` | 1,629,183 B | `e47796b2b5a5e253407dc6e1d0accebb0ebcf18456ff5ee08396c487786e84e5` | 5 | blank ภ.ง.ด.90 return plus the ใบแนบ attachment on page 5 |
| `docs/tax-source/PND91-2568-form.pdf.pdf` | 1,463,353 B | `8cb80f3b2392be6462c3b73cb31bd7b560578e81a84625b3938bb02d258e206b` | 3 | blank ภ.ง.ด.91 return plus its ใบแนบ |
| `docs/tax-source/tax-rates-2568.jpg` | 59,446 B | `44183978969f322a2fc2105c355aeb3be2ad46463426dedfeee7b4cbeefcebef` | — | bracket table image |

The two form files carry a doubled `.pdf.pdf` extension. This is cosmetic, is reported here
rather than fixed, and no file was renamed or deleted.

### How the booklets were read

They are printed in two columns. Extracting a whole page interleaves the columns and produces
sentences that appear in neither, which is how an earlier pass came to report several allowance
keywords as absent. Every value cited in M7.3 documentation was therefore read with a
column-isolated extraction:

```
pdftotext -layout -f N -l N -x 0   -y 0 -W 300 -H 842 <booklet> -   # left column
pdftotext -layout -f N -l N -x 295 -y 0 -W 300 -H 842 <booklet> -   # right column
```

and cross-checked against `pdftoppm -png -r 170` renders of the same pages. poppler-utils runs
inside the application container; the host has no PDF tooling.

### What M7.3 took from them

- **Minimum tax** (ภ.ง.ด.90 page 6) — base, threshold, rate, de-minimis floor and the
  pay-the-greater rule, all VERIFIED. See
  [minimum-tax reconciliation](MINIMUM_TAX_RECONCILIATION_2568.md).
- **ใบแนบ items 1–5** (pages 7–9) — the family allowance amounts and their conditions. See
  [family allowance model](FAMILY_ALLOWANCE_MODEL_2568.md).
- **Five allowance ceilings** (pages 9–16) — items 6, 11, 14, 15 and 21. See
  [allowance rule matrix](ALLOWANCE_RULE_MATRIX_2568.md).

### What they do not settle

- The eligibility tests in ใบแนบ items 3, 4 and 5 depend on a dependant's own income and study
  status, which no repository source carries and which the taxpayer therefore declares.
- The percentage, shared-basket and tiered ceilings listed in the allowance matrix remain
  UNVERIFIED, because expressing them correctly needs rule shapes this schema does not have.
- ใบแนบ item 5's "บุคคลอื่น … ได้ไม่เกิน 1 คน" cannot be applied, because the schema does not
  distinguish a family member from a บุคคลอื่น. It is reported on every affected calculation.

---

## Milestone 07.4 — pages read, and what they settled

The two form files no longer carry the doubled `.pdf.pdf` extension reported in the M7.3
section; the bytes are unchanged and the SHA-256 hashes recorded there still match
`PND90-2568-form.pdf` and `PND91-2568-form.pdf`.

### Pages read

| Page | Section | What it settled |
| --- | --- | --- |
| p.3 | ข้อ 4 การหักค่าใช้จ่าย (1) วิธีที่ 2 | the five rent classes and their rates, 30/20/15/30/10 |
| p.3 | ข้อ 7 item 3 (2) | the eight จำนวนปีที่ถือครอง bands, 92/84/77/71/65/60/55/50, and the ten-year counting ceiling |
| p.10 | ใบแนบ items 7.4, 7.6, 9 | the 100,000 life/health shared ceiling, the ambiguous 90,000 + 15%/200,000 pension pair, and the NSF 500,000 with its basket |
| p.11 | ใบแนบ item 10.4 | RMF at 30% of "เงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้", cap 500,000, basket 500,000 |
| p.13 | ใบแนบ item 17 | Easy E-Receipt 50,000 overall, split 30,000 + 20,000 |
| p.14 | ใบแนบ items 18.1, 19.1 | Thai ESG 30%/300,000 for the 2567–2569 window, Thai ESG X 30%/300,000 |
| p.15 | ใบแนบ item 19.2 | the LTF-switch value, 300,000 for tax year 2568 |
| p.16 | ใบแนบ items 20, 22 | the two lines that remain unimplementable, and why |
| p.17 | ตารางที่ 2 | the 44 มาตรา 40 (8) activities, 42 at 60%, row (1) banded 60/40 capped 600,000, row (44) actual only |

Form pages 2, 3 and 5 were re-read alongside, to confirm the printed line structure each rule
attaches to — in particular that ข้อ 4 (2)–(4), ข้อ 7 item 1 and ข้อ 7 item 3 (2) all print a
`☐ จริง` checkbox beside their blank percentage, and that ใบแนบ prints items 17 and 19 as
several boxes rather than one.

### How they were read

Unchanged from M7.3: column-isolated `pdftotext -layout -f N -l N -x 0/-x 295 -W 300 -H 842`,
cross-checked against `pdftoppm -png -r 170` (whole page) and `-r 200` with `-x/-y/-W/-H`
crops for the three rate tables. Page 17's ตารางที่ 2 is a two-column table, so it was read
from the rendered image as well as the text layer; all 44 rows and both of row (1)'s band rates
agree between the two.

### What these pages do not settle

- **ใบแนบ item 7.6** prints two ceilings for one allowance without saying whether they nest.
  `PENSION_INSURANCE` therefore stays PARTIAL despite its rate being fully legible.
- **ใบแนบ item 12** refers the numeric social-security limit to another act. Still UNVERIFIED.
- **ใบแนบ item 22** prints a second เมืองรอง band with a 1.5× multiplier and no upper limit.
- **กบข.** and **กองทุนสงเคราะห์ครูโรงเรียนเอกชน** are named as members of the 500,000
  retirement basket but have no master allowance code in this project, so the basket is
  narrower than the source's.
- **Legal final rounding** is stated nowhere in any repository source.
