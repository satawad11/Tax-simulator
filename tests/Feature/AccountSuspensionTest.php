<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\TaxReturn;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\UserAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Account suspension — the missing half of "respond to a compromised account".
 *
 * `POST /admin/users/{user}/revoke-sessions` ends every session and the member signs straight back
 * in with the password they already hold; `AdminUserAdministrationTest` asserts exactly that. The
 * behaviour is right — this product must never set someone else's password — but it left
 * revocation a speed bump rather than a stop.
 *
 * A suspension has to close **both** ways back in. Ending sessions without refusing sign-in is
 * theatre; refusing sign-in without refusing password reset just sends the member round the loop.
 * Most of this file is about those two doors staying shut, and about the account surviving intact
 * so that reopening it restores everything.
 */
class AccountSuspensionTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    // In-memory SQLite seeds once, at the first migration of the run. This class sorts early
    // enough to be that first migration, so without this the whole suite runs unseeded.
    protected $seed = true;

    private const PASSWORD = 'Old-Password-2024!';

    private function member(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }

    private function login(User $user): TestResponse
    {
        return $this->postJson('/api/v1/auth/login',
            ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'test']);
    }

    // ----------------------------------------------------- closing the doors

    public function test_a_suspended_account_cannot_sign_in(): void
    {
        $actor = $this->actingAsAdmin(['password' => self::PASSWORD]);
        $member = $this->member();
        $this->login($member)->assertOk();

        $this->actingAs($actor, 'sanctum');
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk()
            ->assertJsonPath('data.suspended', true);

        $this->forgetAuthenticatedUser();
        $this->login($member)->assertStatus(401);
    }

    public function test_the_refusal_names_the_real_reason(): void
    {
        /*
         * A suspended member told "invalid credentials" resets their password, finds it still does
         * not work, and resets it again. They need to be told to contact support.
         */
        $member = $this->member();
        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();

        $this->forgetAuthenticatedUser();
        $this->login($member)->assertStatus(401)
            ->assertJsonFragment(['message' => 'ACCOUNT_SUSPENDED: บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ']);
    }

    public function test_a_wrong_password_on_a_suspended_account_still_says_only_invalid(): void
    {
        // The credentials are checked first on purpose: answering "suspended" to anyone who types
        // an address would turn the sign-in form into a way to ask which accounts are suspended.
        $member = $this->member();
        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();

        $this->forgetAuthenticatedUser();
        $response = $this->postJson('/api/v1/auth/login',
            ['email' => $member->email, 'password' => 'Wrong-Password-1!', 'device_name' => 'test'])
            ->assertStatus(401);

        $this->assertStringNotContainsString('SUSPENDED', $response->content());
    }

    public function test_suspending_ends_every_live_session_immediately(): void
    {
        // A suspension that leaves a session open is a rule that takes effect whenever the
        // attacker happens to sign out.
        $member = $this->member();
        $member->createToken('phone');
        $member->createToken('laptop');

        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk()
            ->assertJsonPath('data.revoked_sessions', 2);

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_a_suspended_account_cannot_reset_its_password_back_in(): void
    {
        // Refusing sign-in without refusing reset would just send the member round the loop.
        Notification::fake();
        $member = $this->member();
        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();

        // Still the same 202 as every other address — saying "suspended" here would answer the
        // question that identical response exists to refuse.
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $member->email])->assertStatus(202);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    // ------------------------------------------------------------- reopening

    public function test_unsuspending_restores_everything(): void
    {
        $member = $this->member();
        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();
        $this->postJson("/api/v1/admin/users/{$member->id}/unsuspend")->assertOk()
            ->assertJsonPath('data.suspended', false);

        $this->forgetAuthenticatedUser();
        // The same password as before: nothing was reset, and nothing was deleted.
        $this->login($member)->assertOk();
        $this->assertNull($member->fresh()->suspended_at);
    }

    public function test_nothing_belonging_to_the_member_is_deleted(): void
    {
        // Reversibility is what makes this safe to reach for quickly, and what separates it from
        // the account-deletion question nobody has answered.
        $member = $this->member();
        TaxReturn::factory()->count(2)->create(['user_id' => $member->id]);

        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $member->id, 'email' => $member->email]);
        $this->assertSame(2, $member->taxReturns()->count());
    }

    public function test_suspending_an_already_suspended_account_changes_and_records_nothing(): void
    {
        $member = User::factory()->create();
        $this->actingAsAdmin();
        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();
        $before = AdminAuditLog::count();

        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();

        $this->assertSame($before, AdminAuditLog::count());
    }

    // ----------------------------------------------------------------- guards

    public function test_an_administrator_cannot_suspend_themselves(): void
    {
        // It would end their session in the same request and lock them out of the page that undoes it.
        $actor = $this->actingAsAdmin();

        $this->postJson("/api/v1/admin/users/{$actor->id}/suspend")->assertStatus(403);

        $this->assertNull($actor->fresh()->suspended_at);
    }

    public function test_the_last_usable_administrator_cannot_be_suspended(): void
    {
        $actor = $this->actingAsAdmin();
        $other = $this->admin();

        $this->postJson("/api/v1/admin/users/{$other->id}/suspend")->assertOk();
        // $actor is now the only unsuspended administrator; the service refuses, not just the policy.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/LAST_ADMINISTRATOR/');
        app(UserAdministrationService::class)->setSuspended($other->fresh(), $actor, true);
    }

    public function test_a_member_cannot_suspend_anyone(): void
    {
        $victim = User::factory()->create();
        $this->actingAsMember();

        $this->postJson("/api/v1/admin/users/{$victim->id}/suspend")->assertStatus(403);
        $this->postJson("/api/v1/admin/users/{$victim->id}/unsuspend")->assertStatus(403);

        $this->assertNull($victim->fresh()->suspended_at);
    }

    public function test_an_anonymous_caller_cannot_suspend_anyone(): void
    {
        $this->postJson('/api/v1/admin/users/1/suspend')->assertStatus(401);
    }

    public function test_suspension_is_not_mass_assignable(): void
    {
        // Set by one audited administrative action and nothing else, exactly like `role`.
        $this->assertNotContains('suspended_at', (new User)->getFillable());
    }

    // ------------------------------------------------------------------ audit

    public function test_both_directions_are_audited(): void
    {
        $actor = $this->actingAsAdmin();
        $member = User::factory()->create();
        $member->createToken('phone');

        $this->postJson("/api/v1/admin/users/{$member->id}/suspend")->assertOk();
        $this->postJson("/api/v1/admin/users/{$member->id}/unsuspend")->assertOk();

        foreach ([AdminAuditService::USER_SUSPENDED, AdminAuditService::USER_UNSUSPENDED] as $action) {
            $this->assertDatabaseHas('admin_audit_logs', ['action' => $action,
                'entity_type' => 'user', 'entity_id' => $member->id, 'actor_user_id' => $actor->id]);
        }
        $log = AdminAuditLog::where('action', AdminAuditService::USER_SUSPENDED)->sole();
        $this->assertSame(1, $log->after_json['revoked_sessions']);
    }

    // ------------------------------------------------------------------- list

    public function test_the_list_reports_and_filters_by_suspension(): void
    {
        $this->actingAsAdmin();
        $suspended = User::factory()->create(['name' => 'ถูกระงับ']);
        User::factory()->create(['name' => 'ปกติ']);
        $this->postJson("/api/v1/admin/users/{$suspended->id}/suspend")->assertOk();

        $row = collect($this->getJson('/api/v1/admin/users')->assertOk()->json('data'))
            ->firstWhere('id', $suspended->id);
        $this->assertTrue($row['suspended']);
        $this->assertNotNull($row['suspended_at']);

        $only = $this->getJson('/api/v1/admin/users?suspended=1')->assertOk()->json('data');
        $this->assertCount(1, $only);
        $this->assertSame($suspended->id, $only[0]['id']);

        $active = array_column($this->getJson('/api/v1/admin/users?suspended=0')->assertOk()->json('data'), 'id');
        $this->assertNotContains($suspended->id, $active);
    }

    public function test_the_return_count_is_gone_and_last_active_is_shown(): void
    {
        /*
         * The count served none of this page's three purposes and nothing here could act on it —
         * this product offers no way to view, edit or delete a member's returns — so it told an
         * administrator something private that they could do nothing with. `last_active_at` went
         * the other way: it was computed and shipped from the start and displayed nowhere.
         */
        $this->actingAsAdmin();
        $member = User::factory()->create();
        TaxReturn::factory()->create(['user_id' => $member->id]);

        $row = collect($this->getJson('/api/v1/admin/users')->assertOk()->json('data'))
            ->firstWhere('id', $member->id);

        $this->assertArrayNotHasKey('tax_return_count', $row);
        $this->assertArrayHasKey('last_active_at', $row);

        // Scoped to the accounts loader: the tax-years page has its own `tax_return_count`, which
        // counts the returns using a year — a different figure, and a legitimate one, since it is
        // what warns an administrator before retiring that year.
        $console = file_get_contents(resource_path('js/admin/console.js'));
        $loader = substr($console, strpos($console, 'async function loadUsers'));
        $loader = substr($loader, 0, strpos($loader, "\nasync function ", 10) ?: null);

        $this->assertStringContainsString('item.last_active_at', $loader);
        $this->assertStringNotContainsString('tax_return_count', $loader);
    }

    public function test_a_password_reset_notification_still_reaches_an_active_account(): void
    {
        // The suspension check in `forgot()` must not have broken the ordinary path.
        Notification::fake();
        $member = User::factory()->create();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $member->email])->assertStatus(202);

        Notification::assertSentTo($member, ResetPasswordNotification::class);
    }
}
