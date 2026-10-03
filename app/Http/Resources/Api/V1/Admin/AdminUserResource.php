<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\MetadataResource;
use Illuminate\Http\Request;

/**
 * Phase 2 — an account as an administrator needs to see it.
 *
 * This is the most sensitive collection the API returns, so what it leaves out is as deliberate
 * as what it includes. No `password`, no `remember_token`, no token value or plain-text token of
 * any kind — `$hidden` on the model covers the first two, and the rest are simply never selected.
 * Nothing here would let an administrator act *as* the member.
 *
 * It also carries **no tax figures and no longer even a count of them**. The count was dropped on
 * review: it served none of this page's three purposes — identify an account, manage its role,
 * respond to a compromise — and nothing here can act on it, because this product offers no way to
 * view, edit or delete a member's returns. It told an administrator something about a member's
 * private use of the product that they could do nothing with. If account deletion is ever built,
 * "does this account hold data?" becomes a question that decision needs, and it earns its way back.
 */
class AdminUserResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_admin' => $this->resource->isAdmin(),
            // Visible because an unverifiable address is why a reset link would never arrive.
            'email_verified' => $this->email_verified_at !== null,
            'registered_at' => $this->created_at?->toIso8601String(),
            'suspended' => $this->resource->isSuspended(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            // How many devices hold a live session — the number the revoke action acts on.
            'active_session_count' => $this->whenCounted('tokens'),
            /*
             * The most useful column on this page for the question it exists to answer: an
             * administrator account unused for eight months is exactly the signal an operator
             * wants before deciding whether it should still hold the role.
             */
            'last_active_at' => $this->when(array_key_exists('last_token_used_at', $this->resource->getAttributes()),
                fn (): ?string => $this->last_token_used_at ? (string) $this->last_token_used_at : null),
            // Whether *this* administrator may act on this row. The API re-decides on every
            // request; this only tells the console what is worth offering.
            'can_change_role' => $request->user()?->can('changeRole', $this->resource) ?? false,
            'can_revoke_sessions' => $request->user()?->can('revokeTokens', $this->resource) ?? false,
            'can_suspend' => $request->user()?->can('suspend', $this->resource) ?? false,
        ];
    }
}
