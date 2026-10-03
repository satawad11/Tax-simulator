# The ใบแนบ family lines are claimed from declared facts

Milestone 09.1. This is the record required by the project rule that **a changed tax result must
be justified, never updated casually**. Every expected figure that moved is listed below with the
reason it moved.

---

## 1. The defect

`FamilyAllowanceResolver` was written to a deliberate contract: items 1–5 of ใบแนบ print a fixed
amount per person, so the amount is derived from profile / spouse / dependants and a
client-supplied number is discarded — but the declared entry still decided **whether** the line was
claimed. The comment said so plainly: *"the taxpayer must ask for it"*.

Nothing in the product ever asked. The allowance step tells the reader these lines are computed
for them — *ระบบคำนวณให้อัตโนมัติ* — and offers no control to claim them with, and neither the Guest
wizard nor the Member return ever put a family code in `allowances[]`. So the two halves each
behaved exactly as designed and the seam between them dropped every family deduction on the floor.

A married filer with one child, salary 720,000, withholding 25,000:

| | Before | After |
| --- | --- | --- |
| Allowances eligible | 0.00 | 150,000.00 |
| Net income | 620,000.00 | 470,000.00 |
| Calculated tax | 45,500.00 | 24,500.00 |
| Result | PAYABLE 20,500.00 | PAYABLE 0.00 |

They were told to pay **21,000 baht more than the form gives them**, with no warning of any kind.
This is the failure mode the project cares most about: not a refused answer, but a confident wrong
one.

## 2. What changed

`FamilyAllowanceResolver::claimableCodes(FamilyFacts)` returns the ใบแนบ lines the declared facts
entitle the filer to, in printed order:

| Code | ใบแนบ item | Claimed when |
| --- | --- | --- |
| `PERSONAL` | 1 ผู้มีเงินได้ | a profile is declared |
| `SPOUSE` | 2 คู่สมรส | a spouse is declared |
| `CHILD` | 3 บุตร | a `child` dependant is declared |
| `PARENT` | 4 บิดามารดา | a `father` / `mother` / `spouse_father` / `spouse_mother` dependant is declared |
| `DISABLED_PERSON` | 5 คนพิการฯ | a `disabled_person` dependant is declared |

`AllowanceCalculator::withFamilyLinesTheFactsEntitle()` appends any of those not already declared,
marked `claimed_automatically`, before the existing loop runs.

**Item 1 carries no condition beyond being a filer**, so the personal line follows the profile
rather than being unconditional. That is what the simulator requires before it calculates anything
real, and it preserves the one payload shape that deliberately declares no family at all — the
frozen M4 regression baseline. Making it unconditional instead broke 120 tests, almost all of them
mechanics tests that had no opinion about families; gating it on a declared profile left 28, every
one of them a real figure this document accounts for.

### What did **not** change

- No amount, cap, rate, bracket or ordering. Each line is still derived by the strategy that owns
  it, from the same printed instruction, against the same published `2568.1` rule version.
- A caller-supplied amount is still discarded, and still warns `FAMILY_ALLOWANCE_DERIVED`.
- A filer who does not qualify still receives the strategy's zero and its warning — a declared
  spouse with income of their own gets `SPOUSE` at 0.00, not a removed line.
- An explicitly declared entry is untouched, so a caller that names a code behaves as it did, and
  the order the caller chose is preserved.
- No published rule version, seeder, or source document was modified.

The one warning suppressed is `FAMILY_ALLOWANCE_DERIVED` on an automatically claimed line. It
exists to tell a caller their number was ignored; a line the server claimed carries no number to
ignore, so emitting it would be noise about something the caller never did.

### Where it lives, and why there

In the calculator, not the browser. Guest, Member and any future client each build their own
payload, so a fix in the wizard would have left the Member path — and every later client — wrong.
`FamilyAllowanceClaimingTest::test_a_member_simulation_claims_the_same_lines_as_a_guest` is the
case that would have caught a browser-only fix.

## 3. Every changed expectation

The canonical single-filer example used across the API docs and most tests — profile declared,
`SECTION_40_1` gross 720,000, expense ceiling 100,000, withholding 25,000 — now claims `PERSONAL`
60,000.

| Figure | Before | After | Why |
| --- | --- | --- | --- |
| `allowances.total_eligible` | 0.00 | 60,000.00 | ใบแนบ item 1, derived, now claimed |
| `net_income` | 620,000.00 | 560,000.00 | 620,000 − 60,000 |
| `progressive_tax.total` | 45,500.00 | 36,500.00 | 560,000 in the published 2568.1 brackets: 150,000 exempt; 150,000 @ 5% = 7,500; 200,000 @ 10% = 20,000; 60,000 @ 15% = 9,000 |
| `analysis.effective_tax_rate` | 6.319444 | 5.069444 | 36,500 / 720,000 × 100 |
| Result @ withholding 25,000 | PAYABLE 20,500.00 | PAYABLE 11,500.00 | 36,500 − 25,000 |
| Result @ withholding 36,500 | — | ZERO 0.00 | the new break-even; the old one was 45,500 |
| Result @ withholding 50,000 | REFUND 4,500.00 | REFUND 13,500.00 | 50,000 − 36,500 |

Files carrying these:

- `tests/Feature/TaxCalculationApiTest.php` — the figures above, and the payments provider
  re-centred on the new break-even.
- `tests/Feature/TaxPlanningApiTest.php` — before/after both 36,500; the scenario still saves
  0.00 because it changes no family fact.
- `tests/Feature/FilingInstructionReconciliationTest.php` — the parent case now asserts the
  `PARENT` **line item** rather than the grand total, because the total now also contains
  `PERSONAL`; the assertion is more precise than the one it replaces.

Tests whose subject is arithmetic mechanics rather than a filer — bracket boundaries, the
zero-income exemption path, sub-satang precision, the unverified-allowance warning set — were moved
onto a new `payloadWithoutFamily()` helper that omits `profile`. They keep their original figures
because they keep their original intent: none of them was ever about a family.

`TaxEngineBaselineClosureTest::test_pnd91_no_family_baseline_is_unchanged` is untouched and still
green at net 620,000 / tax 45,500.

## 4. Verification

- `tests/Feature/FamilyAllowanceClaimingTest.php` — 8 tests locking the new behaviour: which facts
  claim which line, that no facts claim nothing, that a non-qualifying filer still gets the
  strategy's zero, that an automatic claim does not warn, that a declared amount is still discarded
  and still warns, that a declared line is not claimed twice, and that Member matches Guest.
- Full suite green on SQLite and MySQL; see the M9.1 verification log.

## 5. Still source-blocked

This change does not touch the allowances that remain unsupported. `PENSION_INSURANCE` (ใบแนบ 7.6),
`SOCIAL_SECURITY` (ใบแนบ 12) and the annual tax measures (ใบแนบ 13, 16, 20, 22) are blocked because
the repository's approved sources do not print the figure or condition needed, not because of
technique. See [PND91_PRODUCTION_BASELINE_2568.md](PND91_PRODUCTION_BASELINE_2568.md) §3.
