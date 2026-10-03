<?php

namespace App\Policies;

use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Milestone 08. The admin middleware already gates every /admin route, but authorization is
 * kept explicit and testable here too, so a future route added without that middleware still
 * cannot be reached by a member.
 */
class ContentPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny('Administrator access is required.');
    }

    public function view(User $user, ContentPost $post): Response
    {
        return $this->viewAny($user);
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ContentPost $post): Response
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ContentPost $post): Response
    {
        return $this->viewAny($user);
    }

    public function publish(User $user, ContentPost $post): Response
    {
        return $this->viewAny($user);
    }
}
