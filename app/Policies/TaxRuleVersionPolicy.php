<?php

namespace App\Policies;

use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Milestone 08. Administration of rule versions, layered over the model-level immutability
 * that refuses any write to a published version regardless of who is asking.
 */
class TaxRuleVersionPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny('Administrator access is required.');
    }

    public function view(User $user, TaxRuleVersion $version): Response
    {
        return $this->viewAny($user);
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    /** Draft-only; the service and the model both refuse a published version as well. */
    public function update(User $user, TaxRuleVersion $version): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Administrator access is required.');
        }

        return $version->status === 'draft'
            ? Response::allow()
            : Response::deny('Published and archived rule versions are immutable.');
    }

    public function publish(User $user, TaxRuleVersion $version): Response
    {
        return $this->update($user, $version);
    }

    public function archive(User $user, TaxRuleVersion $version): Response
    {
        return $this->viewAny($user);
    }
}
