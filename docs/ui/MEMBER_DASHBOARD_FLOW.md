# Member Dashboard Flow

The browser signs in through the existing M5 token API and stores the token in `sessionStorage`. It is cleared when the tab session ends or the user signs out. Tokens never appear in rendered HTML.

`/dashboard` shows recent records and counts from the Member TaxReturn API. `/dashboard/tax-returns` supports status, year, form, and name filters. A return detail can calculate, complete, duplicate, open stored calculation history, or open planning scenarios. Completed records show a read-only notice and can be duplicated into a new draft.

History reads persisted snapshots through `/calculations`; it never reruns current rules. Scenario actions use the existing scenario CRUD and calculate endpoints, and the UI explicitly states that a scenario does not mutate its source return.
