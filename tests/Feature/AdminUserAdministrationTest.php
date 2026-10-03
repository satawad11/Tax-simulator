<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\UserAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Phase 2 — account administration.
 *
 * Before this there was no `admin/users` route, no `UserPolicy`, and `users.role` is deliberately
 * not mass-assignable, so a second administrator could only be created by editing the database, an
 * administrator who left could not be demoted through the product, and nobody could respond to a
 * compromised account.
 *
 * The three properties worth more than the happy path:
 *
 *   a member cannot reach any of it;
 *   nothing here exposes a credential or lets an administrator act *as* a member; and
 *   nobody can lock every administrator out of the console, by demoting themselves or by demoting
 *     the last one left.
 */
class AdminUserAdministrationTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    // ------------------------------------------------------------------ access

    public function test_a_member_cannot_reach_account_administration(): void
    {
        $other = User::factory()->create();
        $this->actingAsMember();

        $this->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => 'admin'])->assertStatus(403);
        $this->postJson("/api/v1/admin/users/{$other->id}/revoke-sessions")->assertStatus(403);

        $this->assertSame(User::ROLE_MEMBER, $other->fresh()->role);
    }

    public function test_an_anonymous_caller_is_refused(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/admin/users')->assertStatus(401);
        $this->patchJson("/api/v1/admin/users/{$user->id}/role", ['role' => 'admin'])->assertStatus(401);
    }

    // -------------------------------------------------------------------- list

    public function test_the_list_never_exposes_a_credential(): void
    {
        $this->actingAsAdmin();
        User::factory()->create();

        $body = $this->getJson('/api/v1/admin/users')->assertOk()->content();

        // Nothing here may let an administrator sign in as a member.
        foreach (['password', 'remember_token', 'tokenable', 'plainTextToken'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_the_list_says_nothing_at_all_about_a_members_returns(): void
    {
        /*
         * This test used to assert a `tax_return_count`. On review the count was dropped: it
         * served none of this page's three purposes — identify an account, manage its role, respond
         * to a compromise — and nothing here could act on it, because the product offers no way to
         * view, edit or delete a member's returns. It told an administrator something private that
         * they could do nothing with.
         *
         * The session count stays, because it is the number the revoke action acts on and the
         * control is meaningless without it.
         */
        $this->actingAsAdmin();
        $member = User::factory()->create();
        TaxReturn::factory()->count(2)->create(['user_id' => $member->id]);
        $member->createToken('phone');

        $row = collect($this->getJson('/api/v1/admin/users')->assertOk()->json('data'))
            ->firstWhere('id', $member->id);

        $this->assertSame(1, $row['active_session_count']);
        foreach (['tax_return_count', 'gross_income', 'net_income', 'calculated_tax', 'incomes'] as $absent) {
            $this->assertArrayNotHasKey($absent, $row);
        }
    }

    public function test_administrators_are_listed_first(): void
    {
        $admin = $this->actingAsAdmin();
        User::factory()->count(3)->create();

        $ids = array_column($this->getJson('/api/v1/admin/users')->assertOk()->json('data'), 'id');

        // "Who can administer this?" stays answerable however many members register.
        $this->assertSame($admin->id, $ids[0]);
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'สมชาย ใจดี', 'email' => 'somchai@tax-simulator.local']);
        User::factory()->create(['name' => 'สมหญิง', 'email' => 'somying@tax-simulator.local']);

        $byName = $this->getJson('/api/v1/admin/users?search=ใจดี')->assertOk()->json('data');
        $this->assertCount(1, $byName);
        $this->assertSame('somchai@tax-simulator.local', $byName[0]['email']);

        $byEmail = $this->getJson('/api/v1/admin/users?search=somying')->assertOk()->json('data');
        $this->assertCount(1, $byEmail);

        $admins = $this->getJson('/api/v1/admin/users?role=admin')->assertOk()->json('data');
        $this->assertCount(1, $admins);
        $this->assertTrue($admins[0]['is_admin']);
    }

    public function test_a_search_string_is_bound_not_interpolated(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'ผู้ใช้ทดสอบ']);

        // The string arrives from a query string; if it were interpolated this would error or
        // return everything rather than nothing.
        $this->getJson('/api/v1/admin/users?search='.urlencode("' OR 1=1 --"))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(2, User::count());
    }

    // -------------------------------------------------------------------- role

    public function test_an_administrator_can_promote_a_member(): void
    {
        $actor = $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->patchJson("/api/v1/admin/users/{$member->id}/role", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.is_admin', true);

        $this->assertTrue($member->fresh()->isAdmin());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => AdminAuditService::USER_ROLE_CHANGED,
            'entity_type' => 'user', 'entity_id' => $member->id, 'actor_user_id' => $actor->id]);
    }

    public function test_an_administrator_can_demote_another_administrator(): void
    {
        $this->actingAsAdmin();
        $other = $this->admin();

        $this->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => 'member'])
            ->assertOk()->assertJsonPath('data.is_admin', false);

        $this->assertFalse($other->fresh()->isAdmin());
    }

    public function test_an_administrator_cannot_change_their_own_role(): void
    {
        // One click would remove their own access to the page they are standing on, with no way
        // back except the database. Another administrator can always do it.
        $actor = $this->actingAsAdmin();

        $this->patchJson("/api/v1/admin/users/{$actor->id}/role", ['role' => 'member'])->assertStatus(403);

        $this->assertTrue($actor->fresh()->isAdmin());
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        // Nothing in the product can create an administrator except an administrator, so removing
        // the last one is not a mistake anyone could undo from inside the product.
        $actor = $this->actingAsAdmin();
        $other = $this->admin();
        $this->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => 'member'])->assertOk();

        // $actor is now the only administrator left, and cannot self-demote either.
        $this->patchJson("/api/v1/admin/users/{$actor->id}/role", ['role' => 'member'])->assertStatus(403);

        $this->actingAs($other->fresh(), 'sanctum');
        $this->patchJson("/api/v1/admin/users/{$actor->id}/role", ['role' => 'member'])->assertStatus(403);

        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
    }

    public function test_the_last_administrator_rule_is_enforced_by_the_service_not_only_the_policy(): void
    {
        /*
         * Over HTTP the two rules overlap: an actor who is an administrator means there are at
         * least two, and the self-demotion rule covers the rest. The service guard exists for the
         * case HTTP cannot reach — two administrators demoting each other at the same moment,
         * which is why it counts inside the transaction with `lockForUpdate`.
         *
         * Reached directly here, because a guard that only the policy happens to shadow is a
         * guard nobody would notice losing.
         */
        $actor = $this->admin();
        $solitary = $this->admin();
        app(UserAdministrationService::class)->changeRole($actor, $actor, User::ROLE_MEMBER);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/LAST_ADMINISTRATOR/');

        app(UserAdministrationService::class)->changeRole($actor, $solitary, User::ROLE_MEMBER);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->patchJson("/api/v1/admin/users/{$member->id}/role", ['role' => 'superuser'])
            ->assertStatus(422)->assertJsonValidationErrorFor('role');

        $this->assertSame(User::ROLE_MEMBER, $member->fresh()->role);
    }

    public function test_the_request_accepts_no_other_field(): void
    {
        // `role` is not mass-assignable, and neither is anything else smuggled alongside it.
        $this->actingAsAdmin();
        $member = User::factory()->create(['email' => 'keep@tax-simulator.local']);

        $this->patchJson("/api/v1/admin/users/{$member->id}/role",
            ['role' => 'admin', 'email' => 'stolen@tax-simulator.local'])->assertStatus(422);

        $this->assertSame('keep@tax-simulator.local', $member->fresh()->email);
    }

    public function test_setting_the_role_a_user_already_has_changes_and_records_nothing(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->patchJson("/api/v1/admin/users/{$member->id}/role", ['role' => 'member'])->assertOk();

        $this->assertSame(0, AdminAuditLog::where('action', AdminAuditService::USER_ROLE_CHANGED)->count());
    }

    // ---------------------------------------------------------------- sessions

    public function test_revoking_sessions_signs_the_account_out_everywhere(): void
    {
        /*
         * Real bearer tokens on both sides, not `Sanctum::actingAs`: the fake guard would keep
         * resolving the same user for the rest of the test and the revoked token would appear to
         * still work. The whole point of this action is that it stops working.
         */
        $actor = $this->admin();
        $actorToken = $actor->createToken('console')->plainTextToken;
        $member = User::factory()->create();
        $memberToken = $member->createToken('phone')->plainTextToken;
        $member->createToken('laptop');

        $this->withHeader('Authorization', "Bearer $memberToken")->getJson('/api/v1/auth/me')->assertOk();

        $this->forgetAuthenticatedUser();
        $this->withHeader('Authorization', "Bearer $actorToken")
            ->postJson("/api/v1/admin/users/{$member->id}/revoke-sessions")
            ->assertOk()->assertJsonPath('data.revoked_sessions', 2);

        $this->assertSame(0, $member->tokens()->count());

        $this->forgetAuthenticatedUser();
        $this->withHeader('Authorization', "Bearer $memberToken")->getJson('/api/v1/auth/me')->assertStatus(401);

        // The administrator's own session is untouched.
        $this->forgetAuthenticatedUser();
        $this->withHeader('Authorization', "Bearer $actorToken")->getJson('/api/v1/auth/me')->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', ['action' => AdminAuditService::USER_TOKENS_REVOKED,
            'entity_type' => 'user', 'entity_id' => $member->id, 'actor_user_id' => $actor->id]);
    }

    public function test_revoking_sessions_leaves_the_password_alone(): void
    {
        // This product never sets someone else's password: an administrator who could would be
        // able to sign in as them. The member signs in again with the password they already have.
        $this->actingAsAdmin();
        $member = User::factory()->create(['password' => 'Old-Password-2024!']);
        $member->createToken('phone');

        $this->postJson("/api/v1/admin/users/{$member->id}/revoke-sessions")->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => $member->email,
            'password' => 'Old-Password-2024!', 'device_name' => 'test'])->assertOk();
    }

    public function test_an_administrator_cannot_revoke_their_own_sessions_here(): void
    {
        // It would end the session mid-task with no explanation on screen; sign-out does it
        // clearly, and `logout-all` already exists.
        $actor = $this->actingAsAdmin();
        $actor->createToken('console');

        $this->postJson("/api/v1/admin/users/{$actor->id}/revoke-sessions")->assertStatus(403);

        $this->assertSame(1, $actor->tokens()->count());
    }

    public function test_revoking_when_there_are_no_sessions_is_not_an_error(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->postJson("/api/v1/admin/users/{$member->id}/revoke-sessions")
            ->assertOk()->assertJsonPath('data.revoked_sessions', 0);
    }

    // ------------------------------------------------------------------- audit

    public function test_the_audit_trail_records_no_token_value(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();
        $member->createToken('phone');

        $this->postJson("/api/v1/admin/users/{$member->id}/revoke-sessions")->assertOk();

        $log = AdminAuditLog::where('action', AdminAuditService::USER_TOKENS_REVOKED)->sole();
        $this->assertSame(['revoked_sessions' => 1], $log->after_json);
        $this->assertStringNotContainsString('token', json_encode($log->after_json));
    }

    // ----------------------------------------------------------------- console

    public function test_the_console_offers_an_accounts_page(): void
    {
        $this->get('/admin/users')->assertOk()
            ->assertSee('data-admin-page="users"', false)
            ->assertSee('data-user-rows', false);
    }

    public function test_the_sidebar_links_to_it(): void
    {
        $this->get('/admin')->assertOk()->assertSee(route('admin.users'))->assertSee('บัญชีผู้ใช้');
    }

    public function test_the_console_page_carries_no_account_data_of_its_own(): void
    {
        // Every admin page is a data-free shell; the API authorises each request separately.
        $member = User::factory()->create(['email' => 'private@tax-simulator.local']);

        $this->get('/admin/users')->assertOk()->assertDontSee($member->email);
    }
}
