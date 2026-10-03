<?php

namespace App\Policies;

use App\Models\TaxSource;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Milestone 08. Tax source administration is admin-only. */
class TaxSourcePolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny('Administrator access is required.');
    }

    public function view(User $user, TaxSource $source): Response
    {
        return $this->viewAny($user);
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TaxSource $source): Response
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, TaxSource $source): Response
    {
        return $this->viewAny($user);
    }
}
