<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Phase 1 — `MustVerifyEmail` closes a trap rather than adding a gate.
 *
 * `AuthService::update()` has cleared `email_verified_at` on an address change since M5, and
 * nothing in the codebase ever set it again: a member who corrected a typo in their email was
 * permanently unverified, with no way back. That was harmless only while nothing consulted the
 * column — and password recovery is exactly the kind of thing that eventually would.
 *
 * Implementing the contract gives the account a way to become verified again. **Nothing is gated
 * on it.** No route carries the `verified` middleware, sign-in is unaffected, and every existing
 * account keeps working exactly as before. Whether any feature should one day require a verified
 * address is a product decision, and making it here by accident is what this comment exists to
 * prevent.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** The two roles this project has. M8 deliberately does not build an RBAC platform. */
    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    /**
     * Milestone 08 — `users.role` exists since M2 and defaults to member. It is deliberately
     * absent from the fillable list, so no registration or profile request can grant it.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * A suspended account cannot sign in, cannot reset its password, and holds no live session.
     *
     * Deliberately not mass-assignable either: suspension is set by one audited administrative
     * action and by nothing else, for the same reason `role` is not.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Both notifications are this product's own Thai wording, not the framework's English. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function taxReturns(): HasMany
    {
        return $this->hasMany(TaxReturn::class, 'user_id');
    }

    public function contentPosts(): HasMany
    {
        return $this->hasMany(ContentPost::class, 'author_id');
    }

    public function taxScenarios(): HasMany
    {
        return $this->hasMany(TaxScenario::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
