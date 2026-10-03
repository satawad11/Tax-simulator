# Phase 2 — account administration

2026-09-16. Closes gap 3 of [ROLE_GAP_ANALYSIS.md](../planning/ROLE_GAP_ANALYSIS.md).

Before this there was no `admin/users` route, no `UserPolicy`, and `users.role` is deliberately not
mass-assignable — set only by `DevelopmentAccountSeeder`. In production that meant:

- a second administrator could only be created by editing the database;
- an administrator who left could not be demoted through the product;
- nobody could respond to an abusive or compromised account.

`User.php` says M8 "deliberately does not build an RBAC platform", and that restraint was right.
But having *no* user administration is a different thing from having no RBAC, and two roles still
need an operator. This is the smallest thing that gives them one.

---

## What it is not

No new role. No permission matrix. No invitations. No account creation — registration is public.
**No password setting**: an administrator who could set someone's password could sign in as them,
so the recovery path is the member's own, through Phase 1. **No deletion** — a member's returns
hang off their account, and whether this product should offer erasure at all is an open question
in the gap analysis that only the operator can answer.

## Endpoints

All three sit inside the existing `auth:sanctum` + `admin` group **and** authorise through
`UserPolicy`, so a route added later without the group is still not reachable by a member.

### `GET /api/v1/admin/users`

Paginated. `search` matches name or email, `role` filters to one of the two roles, `per_page`
caps at 100. Administrators are ordered first — "who can administer this?" stays answerable
however many members register — then newest account first.

Each row carries `tax_return_count`, `active_session_count`, `email_verified`, `registered_at`,
`last_active_at`, and the two booleans `can_change_role` / `can_revoke_sessions`, which come from
the same policy that will judge the request, so the console never offers a button that would be
refused.

**What it deliberately omits.** No `password`, no `remember_token`, no token value of any kind.
No tax figures either: an administrator has a legitimate reason to know an account exists and how
much of the product it uses, but what is inside someone's return is theirs, and a count answers the
operational question without reading their income.
`AdminUserAdministrationTest::test_the_list_never_exposes_a_credential` and
`::test_the_list_carries_usage_counts_but_no_tax_figures` hold both lines.

### `PATCH /api/v1/admin/users/{user}/role`

Body `{"role": "admin"|"member"}` — any other value is a 422, never a silently stored string, and
no other field is accepted, so nothing can be smuggled alongside it into a model whose `role` is
not mass-assignable.

Two refusals, both about the administrator rather than the target, and both there to stop someone
locking every administrator out of the console:

| Refusal | Why |
| --- | --- |
| **You cannot change your own role** (403) | Demoting yourself removes your access to the page you are standing on, in one click, with no way back except the database. Promoting yourself is meaningless. Another administrator can always do it. |
| **The last administrator cannot be demoted** (422 `LAST_ADMINISTRATOR`) | Nothing in the product can create an administrator except an administrator. Removing the last one is not a mistake anyone could undo from inside the product. |

The last-administrator count runs **inside the transaction with `lockForUpdate`**, so two
administrators demoting each other at the same moment cannot both pass the check. Over HTTP the
two rules overlap — an actor who is an administrator implies at least two exist — so the service
guard is reached directly by
`::test_the_last_administrator_rule_is_enforced_by_the_service_not_only_the_policy`. A guard that
only another rule happens to shadow is a guard nobody would notice losing.

Setting the role an account already holds changes nothing and records nothing.

### `POST /api/v1/admin/users/{user}/revoke-sessions`

Deletes the account's access tokens, signing it out of every device, and answers with how many
sessions ended. **The password is untouched** — the member signs in again with what they already
have, or resets it themselves.

Refused on your own account (403): it would end your session mid-task with no explanation on
screen. Sign-out does that clearly, and `/auth/logout-all` already exists.

## Audit

Both actions are recorded through `AdminAuditService` — `USER_ROLE_CHANGED` and
`USER_TOKENS_REVOKED` — with actor, target and timestamp. Granting administrator rights is the
most consequential single action in the product, and ending someone's sessions is done in response
to a suspected compromise; both need to be answerable later.

The revocation record stores the **count**, never a token value.
`::test_the_audit_trail_records_no_token_value` asserts it.

## Console page

`/admin/users`, on the existing admin layout, listed under ระบบ in the sidebar. A data-free shell
like every other console page: it renders empty and asks the authorised API for each row. Both
actions confirm first, naming what will happen.

## Verification

| Check | Result |
| --- | --- |
| `AdminUserAdministrationTest` (new) | 23 passed, 77 assertions |
| SQLite full suite | 990 passed, 2 skipped, 5,483 assertions |
| MySQL full suite | 990 passed, 2 skipped, 5,483 assertions |
| Pint | 287 files, pass |
| `npm run build` | built |
| Live dev server | list returns 15 accounts, administrators first, the signed-in admin's own row correctly offers neither action; self-role-change 403, self-revoke 403, unknown role 422, anonymous 401, page 200 |

The live check was read-only by design: no role was changed and no session was revoked on the
development database, so it created no `USER_ROLE_CHANGED` or `USER_TOKENS_REVOKED` rows (verified:
zero). The token the check signed in with was revoked afterwards.

## A testing trap worth knowing

Laravel rebuilds the application between test *methods*, not between requests inside one, and the
auth guard caches the user it resolved. So the first authenticated request in a test fixes who
every later request is, whatever `Authorization` header it carries — which made a revoked token
appear to still work, the exact opposite of what was being tested. The fix is
`$this->app['auth']->forgetGuards()` between requests; see `forgetAuthenticatedUser()` in the test.
