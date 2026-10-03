<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Phase 2 — who may administer accounts.
 *
 * Until now nothing could: there was no route, no policy, and `users.role` is deliberately not
 * mass-assignable, so a second administrator could only be created by editing the database. M8
 * was right not to build an RBAC platform — but having *no* user administration is a different
 * thing from having no RBAC, and two roles still need an operator.
 *
 * Two rules here are about the administrator rather than the target, and both exist to stop
 * someone locking themselves out of the console with a single click.
 */
class UserPolicy
{
    public function viewAny(User $actor): Response
    {
        return $actor->isAdmin() ? Response::allow() : Response::deny('Administrator access is required.');
    }

    public function view(User $actor, User $target): Response
    {
        return $this->viewAny($actor);
    }

    /**
     * An administrator never changes their own role.
     *
     * Demoting yourself removes your own access to the page you are standing on, in one click,
     * with no way back except the database. Promoting yourself is meaningless — you are already
     * an administrator. Another administrator can always do it, which is the check that matters.
     */
    public function changeRole(User $actor, User $target): Response
    {
        if (! $actor->isAdmin()) {
            return Response::deny('Administrator access is required.');
        }

        return $actor->is($target)
            ? Response::deny('คุณเปลี่ยนบทบาทของบัญชีตนเองไม่ได้ ให้ผู้ดูแลระบบคนอื่นเป็นผู้ดำเนินการ')
            : Response::allow();
    }

    /**
     * Suspending closes an account until an administrator reopens it.
     *
     * Not your own, for the same reason as a role change and more sharply: suspending yourself
     * ends your session in the same request and locks you out of the page that would undo it.
     */
    public function suspend(User $actor, User $target): Response
    {
        if (! $actor->isAdmin()) {
            return Response::deny('Administrator access is required.');
        }

        return $actor->is($target)
            ? Response::deny('คุณระงับบัญชีของตนเองไม่ได้ ให้ผู้ดูแลระบบคนอื่นเป็นผู้ดำเนินการ')
            : Response::allow();
    }

    /**
     * Revoking tokens signs the account out of every device.
     *
     * Aimed at someone else's compromised or shared account. Aimed at your own it is simply
     * signing out, which the console's own sign-out already does more clearly — and doing it from
     * this screen would end the session mid-task with no explanation on screen.
     */
    public function revokeTokens(User $actor, User $target): Response
    {
        if (! $actor->isAdmin()) {
            return Response::deny('Administrator access is required.');
        }

        return $actor->is($target)
            ? Response::deny('ใช้ปุ่มออกจากระบบสำหรับบัญชีของคุณเอง')
            : Response::allow();
    }
}
