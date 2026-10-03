<?php

namespace App\Http\Requests\Api\V1;

use App\Support\PasswordPolicy;

class ChangePasswordRequest extends StrictApiRequest
{
    public function rules(): array
    {
        // `different` catches the submit that changes nothing before it revokes the member's other
        // sessions for no reason. It is a usability guard, not a history policy: this product
        // stores no previous hashes and makes no claim about reuse beyond the current password.
        return ['current_password' => ['required', 'string', 'max:72'],
            'password' => [...PasswordPolicy::rules(), 'different:current_password'],
            'password_confirmation' => PasswordPolicy::confirmationRules()];
    }
}
