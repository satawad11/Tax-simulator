<?php

namespace App\Policies;

use App\Models\TaxReturn;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaxReturnPolicy
{
    public function view(User $user, TaxReturn $taxReturn): Response
    {
        return $user->id === $taxReturn->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, TaxReturn $taxReturn): Response
    {
        return $this->view($user, $taxReturn);
    }

    public function delete(User $user, TaxReturn $taxReturn): Response
    {
        return $this->view($user, $taxReturn);
    }

    public function calculate(User $user, TaxReturn $taxReturn): Response
    {
        return $this->view($user, $taxReturn);
    }
}
