<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Milestone 08 — lets a controller call `$this->authorize()`. The admin routes are already
     * behind middleware; the policy call is a second, explicit check so authorization is stated
     * at the action rather than inferred from a route group.
     */
    use AuthorizesRequests;
}
