# Account management — what to add, and what to take away

2026-09-16. A review of the Phase 2 feature against what it was built to do, checked against the
code rather than recalled. Two of the three findings are about my own work.

Phase 2 justified itself with three needs: **create a second administrator**, **demote one who has
left**, and **respond to an abusive or compromised account**. The first two are fully met. The
third is where the gap is.

---

## Add: an account cannot actually be stopped (the real one)

`POST /admin/users/{user}/revoke-sessions` ends every session. The member then signs straight back
in with the password they already have — asserted by the feature's own test:

```php
// AdminUserAdministrationTest::test_revoking_sessions_leaves_the_password_alone
$this->postJson("/api/v1/admin/users/{$member->id}/revoke-sessions")->assertOk();
$this->postJson('/api/v1/auth/login', [...])->assertOk();   // straight back in
```

That behaviour is correct on its own terms — this product must never set someone else's password,
because an administrator who could would be able to sign in as them. But it means **revocation is
a speed bump, not a stop.** Against a compromised account, it buys the seconds until the attacker
re-authenticates with the credentials they already stole. Against an abusive one it does nothing
at all.

So the third of Phase 2's three stated purposes is only half-served, and the half that is missing
is the urgent one.

### Proposed

A `suspended_at` column on `users`, and one endpoint pair —
`POST /admin/users/{user}/suspend` and `.../unsuspend` — with:

- **sign-in refused** while suspended, with a distinct reason code rather than "invalid
  credentials", so a suspended member is told to contact support instead of resetting their
  password in a loop;
- **existing tokens revoked** as part of suspending, so it takes effect immediately;
- **password reset refused** while suspended, or the member simply resets and returns;
- **nothing deleted** — a suspension is reversible, which is exactly what distinguishes it from
  the deletion question that remains open (gap 11) and what makes it safe to use quickly;
- **audited**, like every other account action.

The same two guards as role change apply: you cannot suspend yourself, and the last administrator
cannot be suspended.

This is a genuine schema change, so it is the one item here that deserves a decision rather than
just being built.

## Add: no way to ask what an administrator did

Granting administrator rights is the most consequential action in the product, and the audit trail
records every change — but there is no way to ask *"what has this account changed?"*. The audit
page filters by action and entity type only.

This is item 4 of the [surface audit](ADMIN_SURFACE_AUDIT.md), and the accounts page is what makes
it concrete: now that a user list exists, the natural place to start that question is a row in it.

*Proposed:* `actor_user_id` on `GET /admin/audit-logs`, plus a link from each administrator's row
into the filtered audit page. Small, once the filter exists.

---

## Remove: `last_active_at` is exposed and rendered nowhere

`AdminUserResource` computes the most recent `personal_access_tokens.last_used_at` with a
correlated subquery on every list request, and **no column displays it**. That is precisely the
pattern Phase 3 criticised in `cover_image`: a field the product advertises and cannot show.

I built it, so let me be plain about the choice rather than quietly patching it:

**Keep it and render it.** Unlike `cover_image` this one has an obvious use, and it is the single
most useful column on the page for the security question the page exists to answer — *"this
administrator account has not been used in eight months"* is exactly the signal an operator wants
before deciding whether it should still hold the role. It is dormant because I forgot the column,
not because it was unwanted.

Either way the current state — computed, shipped, invisible — is the one option that should not
stand.

## Consider removing: `tax_return_count`

The weakest column on the page, and worth stating the case against it honestly.

**For:** when responding to a report about an account, knowing whether it holds any data at all
changes what you do with it.

**Against:** it serves none of Phase 2's three purposes. It cannot be acted on — this product
deliberately offers no way to view, edit or delete a member's returns — so an administrator learns
something about a member's private use of the product and can do nothing with it. On a product
whose stated position is *"the figures are the member's"*, a usage counter sits awkwardly.

**Recommendation:** it earns its place only if account deletion (gap 11) is ever built, because
"does this account hold data?" is the question that decision needs. Until then it is ornament, and
I would drop it. This is your call rather than mine — it is a judgement about what an operator of
*your* service legitimately needs to see.

## Not candidates for removal

| Field | Why it stays |
| --- | --- |
| `email` | The identifier an administrator needs to match an account to a support request. Bulk PII, but no substitute exists. |
| `email_verified` | Explains why a reset link would never arrive — a real support answer. |
| `active_session_count` | The number the revoke action acts on; the control is meaningless without it. |
| `role` | The console uses `is_admin`, but `role` is the canonical value an API client wants. Duplication with a reason. |
| `can_change_role`, `can_revoke_sessions` | Come from `UserPolicy`, which is also what decides — so the console never offers a button that would be refused. |

## Still correctly absent

Viewing or editing a member's return; setting another account's password; deleting an account;
any second authentication factor (the product has no MFA at all, for members or administrators —
a separate question, and a larger one than this page).

---

## Summary

| | Item | Size |
| --- | --- | --- |
| **Add** | Account suspension — the missing half of "respond to a compromised account" | schema change; needs your decision |
| **Add** | Audit filter by actor, linked from the user row | small, after audit item 4 |
| **Fix** | `last_active_at` — render it or drop it; do not ship it invisible | minutes |
| **Reduce?** | `tax_return_count` — drop unless account deletion lands | minutes; your call |

Nothing here is a correctness or security defect in what was shipped. The suspension gap is a
capability the feature's own justification promised and does not deliver.

---

## Delivered 2026-09-16

All four items above are done.

### Suspension

`suspended_at` on `users` (nullable, indexed), `POST /admin/users/{user}/suspend` and
`/unsuspend`, and a control on each row. Two endpoints rather than one flag, so a generic update
can never lock somebody out.

A suspension closes **both** ways back in, which is the whole point — ending sessions without
refusing sign-in is theatre, and refusing sign-in without refusing password reset just sends the
member round the loop:

| Door | Behaviour |
| --- | --- |
| Live sessions | revoked in the same transaction as the suspension |
| Sign-in | refused, **after** the password is checked, naming the real reason |
| Password reset | no link sent — and the same 202 as every other address |
| The member's data | untouched; unsuspending restores everything |

**The credentials are verified first on purpose.** Answering "this account is suspended" to anyone
who types an address would turn the sign-in form into a way to ask which addresses hold suspended
accounts. Only someone who already proved they hold the password learns the real reason — and they
need it, because a suspended member told "invalid credentials" resets their password, finds it
still does not work, and resets it again.

The same two guards as a role change: you cannot suspend yourself, and the last **usable**
administrator cannot be suspended — counted inside the transaction with `lockForUpdate`, excluding
those already suspended.

#### A defect this uncovered

The API exception handler replaces the message on every error response with the generic status
text. That is right for authentication failures, and it silently swallowed the suspension reason —
the careful message reached nobody. Fixed with `AccountSuspendedException`, recognised by the
handler exactly as `TaxMetadataConflictException` is, so carrying a message through stays a
deliberate opt-in per exception rather than a hole in the rule.

### Audit trail: actor and date range

`actor_user_id`, `from` and `to` on `GET /admin/audit-logs`, with controls to match. `to` covers
the whole day named rather than the midnight that starts it, and an unparseable date narrows
nothing instead of emptying the page without saying why.

Each administrator's row links into their own filtered trail, and the filtered page names whose
trail it is with a way back to everything — a filter you cannot see is a filter you cannot clear.

### `last_active_at` is now rendered

Computed and shipped from the start, displayed nowhere. It is now a column, showing
*ยังไม่เคยใช้งาน* for an account that has never signed in.

### `tax_return_count` is gone

Dropped from the query, the resource, and the page. It served none of this page's three purposes
and nothing here could act on it. If account deletion is ever built, "does this account hold
data?" becomes a question that decision needs, and it earns its way back.

## Verification

| Check | Result |
| --- | --- |
| `AccountSuspensionTest` (new) | 17 passed |
| `AuditTrailFilterTest` (new) | 12 passed |
| SQLite full suite | 1,071 passed, 2 skipped, 5,884 assertions |
| MySQL full suite | 1,071 passed, 2 skipped, 5,884 assertions |
| Pint | 344 files, pass |
| `npm run build` | built |
| Migration on the development database | applied with `migrate`, never `migrate:fresh` |

Live, against a throwaway account created and then removed: sign-in before suspending **200** →
suspend revoked 2 sessions → sign-in **ACCOUNT_SUSPENDED** with the Thai reason → forgot-password
**202** with no token created → unsuspend → sign-in **200** again with the original password.
Self-suspension **403**. The audit filter by actor returned that administrator's 29 entries.

The probe account and the audit rows recording only its suspension were removed afterwards; the
development database is back to its 15 accounts with none suspended.
