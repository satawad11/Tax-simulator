# Rule version 2568.3 — ใบแนบ ข้อ 13, ข้อ 20 and ข้อ 22.1 become calculable

Three lines the product could previously only name are now calculated. Every number comes from
`docs/tax-source/PND90-2568-filing-instructions.pdf`, quoted in the seeder beside the rule it sets.
The reading of all six blocked lines, including the three that stay blocked and why, is in
[BLOCKED_ALLOWANCE_FEASIBILITY_2568.md](BLOCKED_ALLOWANCE_FEASIBILITY_2568.md).

## What the engine was missing

Not a rule — a **stage**. ใบแนบ ข้อ 13 (13.4) and ข้อ 20 (20.4) both say the amount comes off
เงินได้พึงประเมิน *after* มาตรา 42 ทวิ ถึง มาตรา 46, which is `เงินได้ที่ได้รับยกเว้น`, not ค่าลดหย่อน.
The engine went straight from expenses to allowances, so there was nowhere to put such a line and
these deductions were uncalculable for a structural reason rather than a legal one.

The distinction is not cosmetic. An exemption and an allowance reach the same net income on a
purely progressive return, but the ภ.ง.ด.90 minimum tax is charged on assessable income excluding
40 (1) rather than on net income, so which side of the line an amount falls on matters whenever
the minimum tax binds.

**The exemption does not reduce the minimum-tax base.** That base is defined by reference to the
form's own ข้อ 1–ข้อ 7 boxes, which are gross figures upstream of this stage — the same reason the
existing per-line exempt amount does not reduce it either. A test asserts it.

## The rules, and where each number comes from

| Code | ใบแนบ | Stage | Rule | Source |
| --- | --- | --- | --- | --- |
| `CCTV_SYSTEM` | ข้อ 13 | exemption | ร้อยละ 100 ของที่จ่ายจริง, no ceiling printed | 13.1–13.4 |
| `NEW_HOME_CONSTRUCTION` | ข้อ 20 | exemption | 10,000 per completed 1,000,000 paid, ≤ 100,000, one house | 20.1–20.6 |
| `DOMESTIC_TRAVEL_MAIN_CITY` | ข้อ 22.1 | allowance | actual ≤ 10,000 | 22.1 วรรคหนึ่ง |
| `DOMESTIC_TRAVEL_MAIN_CITY_ETAX` | ข้อ 22.1 | allowance | the part above 10,000, actual ≤ 10,000, e-Tax Invoice only | 22.1 วรรคสอง |

`ต่อทุกจำนวน` counts **completed** units — the booklet prints no proportion for a part-finished one,
so 2,900,000 baht completes two units and grants 20,000, not 29,000. `Money::wholeMultiplesOf()`
carries that rule with the reason attached.

### What stays out, and why

- **ข้อ 7.6** — prints both "ไม่เกิน 90,000" and "ไม่เกิน 200,000" without saying whether the first is
  inside the second.
- **ข้อ 12** — the ceiling is in another statute, not among this project's approved sources.
- **ข้อ 16** — the ceiling **is** printed (100,000), but "กรณีละ" leaves it unsettled whether two
  investments in one year share one ceiling or have two.
- **ข้อ 22 เมืองรอง** — the part above 10,000 is printed as 1.5× with no ceiling beside it.

None of these can be resolved from the approved sources, and supplying a number is the one thing
this project forbids.

## Entitlement is the filer's assertion, never the engine's

Each exemption line prints conditions no declared figure can establish — a geographic zone, a
contract date, a contractor's VAT registration. The API returns them with the rule, the wizard
shows every one, and the filer affirms them. A positive amount **without** the affirmation is
refused with 422 (`INCOME_EXEMPTION_DECLARATION_REQUIRED`); a zero never is, because a zero cannot
change the tax. This is the footing the product already uses for exempt income, dependants and
every allowance.

## Version 2568.3, and why not 2568.2

A rule change is a new version; `ProtectsPublishedRules` refuses to add, edit or remove a rule
belonging to a published one. `2568.2` was already taken by an unpublished draft (the
disabled-person relationship discriminator) that `FinalGapClosureTest` and three factories depend
on, so the successor is **2568.3**.

2568.1 keeps every rule it was published with and is **archived, not edited**. Archiving retires a
version from *new* calculations and destroys nothing: the 14 saved returns and 17 stored
calculations in the development database still point at the version they were calculated under and
still resolve it.

### Two defects found while doing this

**`TaxBracketSeeder` republished a retired baseline.** Its guard read `if (status === 'published')
return`, so once 2568.1 was correctly archived the next reseed found it "not published", rebuilt
its brackets and published it a second time — leaving the year with two published versions and
every calculation failing the conflict `PublishedTaxRuleResolver` raises. It now returns unless the
version is a draft. Seven rule seeders that hard-required `status === 'published'` now accept a
retired baseline too, since they are building that version's historical content.

**`TaxRuleVersionCloneService` did not copy `income_exemption_rules`,** which would have silently
dropped these rules from any future clone — including carrying 2568 forward into 2569. Added to
`VERSIONED_TABLES`.

**The readiness command named a fixed baseline.** `ops:production-readiness` checked for "2568.1
published" and so reported `FAIL` the moment that version was correctly retired. It now checks the
invariant that actually matters — exactly one published version — and names whichever it is.

## The development database

The state found before this change was not what it appeared to be:

| version | status | what it was |
| --- | --- | --- |
| 2568.1 | archived | the original baseline |
| 2568.2 | draft | the disabled-person discriminator, never published |
| **2569** | **published** | `"Cloned from 2568.1"` — a clone named for the *next tax year* but attached to 2568 |

The rules the product was actually using were in a version misleadingly named `2569`. Its content
was identical to 2568.1, so no figure was ever wrong, but the version an auditor would read was.
Publishing 2568.3 retired it, as it retires every other published version of the year.

**Nothing was deleted.** 15 users, 17 content posts, 14 tax returns, 17 calculations and 2
scenarios are all present and unchanged.

Tax year 2569 still has no rule versions of its own. That is unchanged by this work and remains
open — opening the simulator on 2569 would raise the metadata conflict
`PublishedTaxRuleResolver` throws, which is why `TaxMetadataService::years()` lists only years that
have a published version.

Cloning 2568.3 into 2569 through the admin console is the supported way to start it, and now
carries the exemption rules too.

## A member's saved return

A draft carries its exemption lines like any other input, through
`/api/v1/tax-returns/{id}/income-exemptions`, and `tax_return_income_exemptions` stores the
affirmation beside the amount.

Storing the affirmation is the part that mattered. The guard refuses a positive exemption without
it, so a draft that saved only the amount would recalculate into a 422 the member never caused —
and the obvious "fix", defaulting it to true, would have the product assert an entitlement on their
behalf. Saving an unaffirmed line is allowed (a member may fill the amount first and tick the
conditions later, and `PATCH` on the saved line does exactly that), but calculating it is not.

The code is validated against the return's **own** rule version, not a table-wide list: a return
calculated under an earlier version must not be able to save a line only a later version carries,
or it could no longer recalculate itself.

### One more defect this surfaced

`TaxRuleVersion2568_3Seeder` returned early whenever 2568.3 already existed as published. A version
whose rules had been lost — to a rolled-back migration, a partial restore — therefore stayed
published and **empty**, with no command able to put them back. It now carries forward and publishes
once, but seeds this version's own rules on every run; the rule seeders are idempotent and refuse to
overwrite a differing value, so running them always is safe and repairing. Reproduced and fixed
against the development database.

## Verification

| Check | Result |
| --- | --- |
| `IncomeExemptionCalculatorTest` (new) | 11 passed |
| `IncomeExemptionApiTest` (new) | 13 passed |
| `MemberIncomeExemptionPersistenceTest` (new) | 10 passed |
| SQLite full suite | 1,151 passed, 2 skipped, 6,325 assertions |
| MySQL full suite | 1,153 passed, 6,781 assertions |
| Pint | 380 files, pass |
| `npm run build` | pass |
| `ops:production-readiness` | all checks pass; one published version, 2568.3 |
| Live API, guest | 2,400,000 declared → 20,000 granted; version reported 2568.3 |
| Live API, member | draft saved, recalculated (4,900,000 → 20,000 → 4,880,000), reloaded with the affirmation intact |
| Live wizard, ภ.ง.ด.90 | both lines render with their conditions; the result shows the stage |

The frozen M4 regression figure — no family, net 620,000, tax 45,500 — is asserted against the new
version and unchanged. No existing calculation moves: every rule carried forward is byte-identical,
and a return claiming no exemption keeps the sixteen-step trace it always had.
