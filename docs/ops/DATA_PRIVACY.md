# Data privacy behavior

Guest simulation inputs and results stay in browser session storage and are sent to the calculation API without being persisted by the application. They become Member data only after the user signs in and explicitly saves them.

Member storage includes account name and email, tax-return profile facts, spouse/dependent facts, income, allowance, donation and withholding entries, scenarios, calculation results, traces, warnings, rule-version references, and historical snapshots. Authentication tokens are stored hashed by Sanctum; the browser holds the issued token in session storage until logout or the browser session ends.

Content storage includes drafts and published posts, taxonomies, authorship, sources, rule citations, and administrative audit entries. Audit entries contain administrative action metadata and before/after admin entity data; they do not contain passwords, tokens, Authorization headers, full Member financial payloads, or sensitive Member PII.

No retention or deletion schedule is implemented or claimed. Backups contain the same personal and financial data as the database and require equivalent access control.
