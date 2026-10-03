<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account suspension — the missing half of "respond to a compromised account".
 *
 * `POST /admin/users/{user}/revoke-sessions` ended every session and the member signed straight
 * back in with the password they already held; the feature's own test asserts exactly that. That
 * behaviour is right — this product must never set someone else's password — but it left
 * revocation a speed bump rather than a stop, and the most urgent of Phase 2's three stated
 * purposes only half-served.
 *
 * A nullable timestamp, not a boolean: when an account was suspended is part of the answer an
 * operator or a member will eventually ask for, and null means "not suspended" without needing a
 * default. Nothing is deleted, so the action is reversible — which is what makes it safe to use
 * quickly, and what distinguishes it from the account-deletion question that remains open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('email_verified_at');
            // Suspended accounts are the ones an operator goes looking for, and they are a small
            // minority of rows; the index keeps that filter cheap as the table grows.
            $table->index('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['suspended_at']);
            $table->dropColumn('suspended_at');
        });
    }
};
