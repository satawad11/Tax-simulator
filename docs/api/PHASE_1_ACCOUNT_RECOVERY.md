# Phase 1 — account recovery API

2026-09-15. Closes gaps 1, 2 and 4 of
[ROLE_GAP_ANALYSIS.md](../planning/ROLE_GAP_ANALYSIS.md).

Before this, a member who forgot their password lost every saved return permanently: there was no
reset flow, no change-password endpoint, and support had no answer short of database access.

All five endpoints use the project's standard envelope, `{"success":…,"message":…,"data":…}`, and
the strict request rules every other endpoint uses — unknown fields are rejected, and a password
over 72 bytes is refused rather than silently truncated by bcrypt.

---

## `POST /api/v1/auth/password/forgot`

Public. Rate limited to **5 per minute**.

```json
{ "email": "member@example.com" }
```

Always **202**, always this body, whatever the address:

```json
{ "success": true, "message": "หากอีเมลนี้มีบัญชีอยู่ในระบบ เราได้ส่งลิงก์ตั้งรหัสผ่านใหม่ไปให้แล้ว", "data": null }
```

**The response never reveals whether an address is registered.** A registered address, an
unregistered one, and one asking again inside the broker's throttle window are answered
identically — same status, same body, no error. Anything else turns this form into a way to ask
which addresses hold accounts. The request validates the address's *format* only; deliberately no
`exists` rule, because a 422 would answer the question the 202 refuses to.

Only `422` on a malformed address is possible besides `202` and `429`.

## `POST /api/v1/auth/password/reset`

Public. Rate limited to **5 per minute**.

```json
{ "token": "<from the emailed link>", "email": "member@example.com",
  "password": "…", "password_confirmation": "…" }
```

**200** on success. **Every access token for the account is revoked** — a reset is the response to
a password that may be in someone else's hands, and a token issued under it must not outlive it.
The member signs in again on every device.

**422** on any failure, with one message on `token`:

> ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่อีกครั้ง

Wrong token, expired token, token belonging to another address, and already-used token are
indistinguishable on purpose: telling a caller that a guessed token merely *expired* confirms the
half of the guess that was right. Token lifetime is `auth.passwords.users.expire` (60 minutes).

The password must satisfy the same policy as registration — see `App\Support\PasswordPolicy`,
which is the single definition all three doors share.

## `PUT /api/v1/auth/password`

Authenticated. Rate limited to **5 per minute**.

```json
{ "current_password": "…", "password": "…", "password_confirmation": "…" }
```

**200** on success. **Other sessions are revoked; the calling session survives** — the member asked
for this from a tab they are using, and signing them out of it would be surprising, while leaving a
borrowed device signed in would not be safe.

| Status | Cause |
| --- | --- |
| 401 | not signed in, **or** `current_password` is wrong — a failed authentication, not a field typo |
| 422 | the new password fails the policy, or is the same as the current one |

## `POST /api/v1/auth/email/resend`

Authenticated. Rate limited to **5 per minute**. Empty body.

Always **202**. `data.verified` reports the state: `false` means a link was just sent, `true` means
the address was already verified and nothing was sent. Asking when already verified is not an
error. Authentication is required so the endpoint can only ever mail the caller's own address —
an unauthenticated version would both spam arbitrary addresses and confirm which exist.

## `GET /email/verify/{id}/{hash}` — web, not API

The far end of the verification link. A signed, expiring URL, because the link is clicked in a mail
client which sends a plain browser GET carrying no bearer token: the signature on the URL *is* the
authorisation. Renders a Thai result page.

The hash is of the address the link was issued for, so changing the address again invalidates every
link already sent for the old one — stale rather than hostile, and the page says so.

---

## Email verification is not a gate

`users.email_verified_at` has been cleared on an address change since M5, and nothing ever set it
again: a member who corrected a typo in their email was permanently unverified with no way back.
Harmless only while nothing consulted the column — and password recovery is exactly the kind of
thing that eventually would.

`User` now implements `MustVerifyEmail`, which gives the account a way to *become* verified.
**Nothing is gated on it.** No route carries the `verified` middleware, sign-in is unaffected, and
every account that existed before this date keeps working unchanged. Whether any feature should one
day require a verified address is a product decision;
`EmailVerificationTest::test_an_unverified_member_is_not_locked_out_of_anything` exists so it
cannot be made by accident.

A verification email that fails to send never costs a member their registration: the account is
already created and nothing depends on verification, so the failure is logged — with the user id,
never the address — and the member can ask again.

## Web pages

| Path | Purpose |
| --- | --- |
| `/password/forgot` | request a link |
| `/password/reset/{token}` | set the new password; the token comes from the path and the address from `?email=`, because that is how the emailed link carries them |
| `/account/password` | change a known password |

All three are `noindex`. The reset route constrains `{token}` to `[A-Za-z0-9]{1,255}`: the broker's
token is a SHA-256 HMAC in hex, so anything else could never verify, and refusing it at the route
means nothing attacker-shaped reaches a page that renders it into a value attribute.

`/account/password` is the one piece of account settings Phase 1 ships. The rest — name, email,
session management — is Phase 4's settings page; this one could not wait, because an endpoint
nobody can reach closes nothing.

## Deployment

Two prerequisites, both recorded in
[PRODUCTION_READINESS_CHECKLIST.md](../ops/PRODUCTION_READINESS_CHECKLIST.md): a real
`MAIL_MAILER` (the default `log` sends nothing while still answering 202), and the fact that no
queue worker exists, which is why both notifications send inline.

## Verification

| Check | Result |
| --- | --- |
| `PasswordRecoveryTest` | 18 passed |
| `EmailVerificationTest` | 13 passed |
| `PasswordPageTest` | 8 passed |
| SQLite full suite | 967 passed, 2 skipped, 5,406 assertions |
| MySQL full suite | 967 passed, 2 skipped, 5,406 assertions |
| Pint | 281 files, pass |
| `npm run build` | built |
| Live dev server | 202 from `/auth/password/forgot`; Thai email in the log with a working link; all three pages 200 |

The live check stopped short of completing a reset: that would have changed the development
admin password, which is set deliberately. No development account credential was altered, and the
`password_reset_tokens` rows the check created were removed afterwards.
