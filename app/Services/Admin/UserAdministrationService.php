<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 — the two administrative actions this product's two roles actually need.
 *
 * Deliberately not an RBAC platform: no new roles, no permission matrix, no invitations. A role
 * change and a sign-out-everywhere, both audited, because those are the two things that could
 * previously only be done by editing the database — creating a second administrator, removing one
 * who has left, and responding to an account that may be compromised.
 *
 * Accounts are never deleted here. A member's returns hang off their account, and whether this
 * product should offer erasure at all is an open question in
 * `docs/planning/ROLE_GAP_ANALYSIS.md` that only the operator can answer.
 */
class UserAdministrationService
{
    public function __construct(private AdminAuditService $audit) {}

    /**
     * @throws ValidationException when this would leave the product with no administrator
     */
    public function changeRole(User $actor, User $target, string $role): User
    {
        return DB::transaction(function () use ($actor, $target, $role): User {
            $before = $target->role;
            if ($before === $role) {
                return $target;
            }

            /*
             * The last administrator cannot be demoted.
             *
             * Nothing else in the product can create one: `users.role` is not mass-assignable, no
             * registration path grants it, and this endpoint requires an administrator to call it.
             * Removing the last one is therefore not a mistake anyone could undo from inside the
             * product — it would need database access, which is exactly the situation Phase 2
             * exists to end.
             *
             * Counted inside the transaction, and for update, so two administrators demoting each
             * other at the same moment cannot both pass the check.
             */
            if ($before === User::ROLE_ADMIN && $role !== User::ROLE_ADMIN
                && User::where('role', User::ROLE_ADMIN)->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'LAST_ADMINISTRATOR: ระบบต้องมีผู้ดูแลระบบอย่างน้อยหนึ่งบัญชี '
                        .'กรุณาแต่งตั้งผู้ดูแลระบบคนใหม่ก่อนถอดสิทธิ์บัญชีนี้',
                ]);
            }

            // `role` is not mass-assignable on purpose — no request may ever set it by accident.
            $target->forceFill(['role' => $role])->save();

            $this->audit->record($actor, AdminAuditService::USER_ROLE_CHANGED, 'user', $target->id,
                "Changed role of user #{$target->id} from {$before} to {$role}",
                ['role' => $before], ['role' => $role]);

            return $target->fresh();
        });
    }

    /**
     * Suspends or restores an account.
     *
     * Suspending revokes every token **in the same transaction**, because a suspension that leaves
     * a live session open is not a suspension — it is a rule that takes effect whenever the
     * attacker happens to sign out. Sign-in and password reset are refused while it lasts, so the
     * two ways back in are both closed.
     *
     * Nothing is deleted. The account, its returns and its password are untouched, so unsuspending
     * restores everything exactly — which is what makes this safe to reach for quickly, and what
     * separates it from the deletion question nobody has answered yet.
     *
     * @return int how many sessions the suspension ended
     *
     * @throws ValidationException when this would leave the product with no usable administrator
     */
    public function setSuspended(User $actor, User $target, bool $suspended): int
    {
        return DB::transaction(function () use ($actor, $target, $suspended): int {
            if ($target->isSuspended() === $suspended) {
                return 0;
            }

            // The same reasoning as demotion: nothing inside the product could undo suspending the
            // only administrator, because doing it would remove the account that would have to.
            if ($suspended && $target->isAdmin()
                && User::where('role', User::ROLE_ADMIN)->whereNull('suspended_at')->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages([
                    'suspended' => 'LAST_ADMINISTRATOR: ระบบต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อยหนึ่งบัญชี '
                        .'กรุณาแต่งตั้งผู้ดูแลระบบคนใหม่ก่อนระงับบัญชีนี้',
                ]);
            }

            $revoked = 0;
            if ($suspended) {
                $revoked = $target->tokens()->count();
                $target->tokens()->delete();
            }
            // Not mass-assignable, for the same reason `role` is not.
            $target->forceFill(['suspended_at' => $suspended ? now() : null])->save();

            $this->audit->record($actor,
                $suspended ? AdminAuditService::USER_SUSPENDED : AdminAuditService::USER_UNSUSPENDED,
                'user', $target->id,
                $suspended
                    ? "Suspended user #{$target->id}, ending {$revoked} session(s)"
                    : "Restored access for user #{$target->id}",
                ['suspended' => ! $suspended], ['suspended' => $suspended, 'revoked_sessions' => $revoked]);

            return $revoked;
        });
    }

    /**
     * Signs the account out of every device by deleting its access tokens.
     *
     * The password is untouched: this product never sets someone else's password, and an
     * administrator who could would be able to sign in as them. Revoking tokens ends the sessions
     * and leaves the member in control of their own credentials — they sign in again, or reset
     * their password themselves through the Phase 1 flow.
     *
     * @return int how many sessions were ended
     */
    public function revokeTokens(User $actor, User $target): int
    {
        return DB::transaction(function () use ($actor, $target): int {
            $revoked = $target->tokens()->count();
            $target->tokens()->delete();

            // The count, never a token value — see AdminAuditService::REDACTED.
            $this->audit->record($actor, AdminAuditService::USER_TOKENS_REVOKED, 'user', $target->id,
                "Revoked {$revoked} session(s) for user #{$target->id}", null, ['revoked_sessions' => $revoked]);

            return $revoked;
        });
    }
}
