<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Phase 2. The two roles this product has, and nothing else — a value outside the pair is a 422,
 * not a silently stored string.
 */
class ChangeUserRoleRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['role' => ['required', 'string', Rule::in([User::ROLE_MEMBER, User::ROLE_ADMIN])]];
    }
}
