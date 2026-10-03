<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class UserResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            /*
             * M9.1 — whether to offer the link to the admin console.
             *
             * A boolean rather than the raw `role` string, because that is the whole question the
             * browser has: an administrator signing in normally had no way to reach /admin and
             * had to know the path by heart. Showing the link grants nothing — every console page
             * is a data-free shell and every admin endpoint authorises the request on its own.
             */
            'is_admin' => $this->resource->isAdmin(),
            /*
             * Phase 4 — the account page tells the member whether their address is confirmed and
             * offers the link again if it is not. A boolean rather than the timestamp: when it
             * happened is of no use to the reader, and nothing in the product is gated on it.
             */
            'email_verified' => $this->email_verified_at !== null,
        ];
    }
}
