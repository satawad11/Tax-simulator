# Entering income sources — choosing the category, and how many sources a return may carry

Two changes to the ภ.ง.ด.90/91 wizard's income step. Neither changes a tax figure: the engine,
the rules and the published baseline are untouched, and every calculation gives the same answer it
gave before.

## The question that started this

> "แหล่งเงินได้ทั้ง 2 ทำไมสามารถเลือกประเภทเงินได้เหมือนกัน หากผู้ใช้หลงกดแต่ใส่ยอดเงินไม่เท่ากันจะเป็นยังไง"

Repeating a category is **correct and necessary**. A source is a payer, not a category: two
employers both pay เงินได้ตามมาตรา 40(1), and the return has to be able to say so, because the
withholding certificates are issued per payer. Blocking a repeat would make the product unable to
represent the most ordinary situation there is. The "ผู้จ่ายเงินได้ / แหล่งเงินได้" field exists to
tell the repeats apart.

And the arithmetic was already right. [`IncomeCalculator`](../../app/Services/Tax/IncomeCalculator.php)
groups lines by `income_type|income_subtype|expense_activity|holding_years` **before** any rule is
applied, and [`ExpenseCalculator`](../../app/Services/Tax/ExpenseCalculator.php) deducts once from
the group's aggregate — so a printed ceiling is never multiplied by the number of rows.

| Entered | Expense deducted | Net income |
| --- | --- | --- |
| 40(1) × 1 row of 10,000,000 | 100,000 | 9,900,000 |
| 40(1) × 100 rows of 100,000 | 100,000 | 9,900,000 |

Two rows of one category electing different expense methods are refused with **422** on
`incomes.N.expense_method_selection`, not a 500.

So the merging was never the defect. Two quieter things were.

## 1. A new row no longer chooses a category for the reader

A new income row used to arrive with **40(1) already selected**. A reader adding a row for rent or
a professional fee, who went straight to the amount, had it taxed under a rule they never picked —
and nothing said so.

The size of that silence, measured on the live API:

| Entered as | Expense on 500,000 | Tax |
| --- | --- | --- |
| 40(2) | 100,000 (50%, capped) | 17,500 |
| 40(7) | 300,000 (60%, uncapped) | 2,500 |

A row now starts on `— เลือกประเภทเงินได้ —`, an empty `required` option. The reader answers the
question; the product does not answer it on their behalf.

Three consequences, all handled:

- **The subtype slot.** An unchosen row used to fall through to the rule lookup, find nothing, and
  report *"ยังไม่รองรับการคำนวณอัตโนมัติ"* — naming a limitation that does not exist and hiding the
  one action the reader has to take. It now shows a neutral prompt instead.
- **"ถัดไป".** An unchosen category is caught on its own step, beside the field, rather than by
  `reportValidity()` seven steps later at submit.
- **Saved returns.** A stored row carries its category, so the placeholder is not selected and the
  restored value shows exactly as before.

The API still decides: an empty `income_type` is refused with 422 regardless of the browser.

## 2. The add button stops at the limit the API enforces

`CalculateTaxRequest` accepts at most **100** income lines. The button had no limit, so a reader
could add a 101st row, fill in the whole return, and learn only at submit that it was refused.

`MAX_INCOME_ROWS` in the wizard mirrors that rule; a test asserts the two stay equal, so moving the
API limit without moving the constant fails. At 100 rows the button is disabled and a note in an
`aria-live="polite"` region gives the reason **and the way forward** — combine sources of the same
category, since the result is identical either way. Removing a row re-enables it.

## Verification

| Check | Result |
| --- | --- |
| `IncomeSourceEntryTest` (new) | 9 passed, 32 assertions |
| SQLite full suite | 1,117 passed, 2 skipped, 6,194 assertions |
| MySQL full suite | 1,119 passed, 6,624 assertions |
| Pint | 364 files, pass |
| `npm run build` | pass |
| Live wizard — new row | value `""`, label `— เลือกประเภทเงินได้ —`, `checkValidity()` false |
| Live wizard — "ถัดไป" unchosen | stays on step 3, states the reason |
| Live wizard — 100 rows | button disabled, note shown; remove one → re-enabled; add again → disabled |

No rule version, seeder or source document was modified, and the frozen M4 regression figure (no
family, net 620,000, tax 45,500) is unchanged.

## Still open

The per-category expense breakdown on the result page. The result shows only
`หัก ค่าใช้จ่าย` as a single total, so a reader who entered 500,000 + 500,000 of 40(2) and expected
200,000 sees 100,000 with no explanation. The API already returns `expenses.items` with `basis`,
`percentage`, `maximum_amount` and `eligible_amount` per group — it is not yet rendered.
