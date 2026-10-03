# Form fidelity guidelines

## Source term and explanation

Show the legal/form term first, followed by a short Thai explanation. A primary label must never be only an internal code. Example: **เงินได้ตามมาตรา 40(1) - เงินเดือน ค่าจ้าง โบนัส และเงินได้จากการจ้างแรงงาน**. Source locations are shown as form names, page/section labels, or instruction table names; repository paths are not displayed to users.

## Progressive disclosure

The selected form limits available income types. Selecting an income type reveals only its source-defined subtypes. Selecting a subtype reveals activity, holding period, expense election, actual-expense amount, or tax treatment only where the published metadata requires it. Family child fields appear only for a child relationship.

## Unsupported presentation

A source item that is guarded by the current baseline remains visible in an informational card using: **รายการนี้มีในแบบ แต่ระบบจำลองรุ่นปัจจุบันยังไม่รองรับการคำนวณอัตโนมัติ**. It has no enabled amount input. Unsupported credit options are disabled and labelled as unsupported.

## Source references and examples

Guidance cites user-facing locations such as **ภ.ง.ด.90 ข้อ 7** or **ตารางที่ 2 หน้า 17**. Examples describe categories only; they never predict tax or implement an amount formula in JavaScript.

## Validation mapping

API paths are translated to Thai field names and one-based row numbers. The UI focuses and scrolls to the first mapped field when possible. Raw paths such as `incomes.2.actual_expense` must not be shown.

## Review

Review follows form logic: form/year, family, income and expense by legal section, allowances, donations, then withholding/prepayments. Values shown are user input or API output. Calculated values remain server-derived.

## Persistence

Guest state is browser-session state only. Member income rows round-trip subtype, activity, holding years, expense method, actual expense, and tax treatment through the same REST API fields available to a future mobile client.

