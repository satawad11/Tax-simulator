<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ChangeUserRoleRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\UserAdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Phase 2 — account administration.
 *
 * Three actions, deliberately: see who holds an account, move an account between the two roles,
 * and end an account's sessions. No creation (registration is public), no deletion (see the open
 * question in the gap analysis), no password setting — an administrator who could set someone's
 * password could sign in as them.
 */
class AdminUserController extends Controller
{
    public function __construct(private UserAdministrationService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        /*
         * `taxReturns` is deliberately no longer counted.
         *
         * It served none of this page's three purposes — identify an account, manage its role,
         * respond to a compromise — and could not be acted on, because this product offers no way
         * to view, edit or delete a member's returns. It told an administrator something about a
         * member's private use of the product that they could do nothing with, on a product whose
         * stated position is that the figures are the member's. If account deletion is ever built,
         * "does this account hold data?" becomes a question that decision needs, and it comes back.
         */
        $query = User::query()
            ->withCount('tokens')
            // The most recent sign-in activity, as one value, without loading every token row.
            ->addSelect(['last_token_used_at' => PersonalAccessToken::selectRaw('MAX(last_used_at)')
                ->whereColumn('tokenable_id', 'users.id')->where('tokenable_type', User::class)]);

        if ($search = trim((string) $request->query('search'))) {
            // Bound parameters, never interpolation: this string comes from a query string.
            $query->where(fn ($group) => $group->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if (in_array($request->query('role'), [User::ROLE_MEMBER, User::ROLE_ADMIN], true)) {
            $query->where('role', $request->query('role'));
        }
        // Suspended accounts are what an operator comes back to find after acting on a report.
        if ($request->query('suspended') !== null) {
            filter_var($request->query('suspended'), FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('suspended_at')
                : $query->whereNull('suspended_at');
        }

        // Administrators first, so the answer to "who can administer this?" is above the fold
        // however many members exist, then newest account first.
        return AdminUserResource::collection(
            $query->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [User::ROLE_ADMIN])
                ->orderByDesc('id')
                ->paginate(min((int) $request->query('per_page', 25), 100))->withQueryString()
        )->additional(['success' => true, 'message' => null]);
    }

    public function updateRole(ChangeUserRoleRequest $request, User $user): AdminUserResource
    {
        $this->authorize('changeRole', $user);

        return new AdminUserResource(
            $this->users->changeRole($request->user(), $user, $request->validated('role'))
                ->loadCount(['taxReturns', 'tokens'])
        );
    }

    /**
     * Closes an account, or reopens it.
     *
     * Two endpoints rather than a `PATCH {suspended: bool}`, for the same reason content publishing
     * is its own endpoint: a generic update can never accidentally lock someone out.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', $user);
        $revoked = $this->users->setSuspended($request->user(), $user, true);

        return response()->json(['success' => true,
            'message' => "ระงับบัญชีนี้แล้ว และออกจากระบบให้ {$revoked} อุปกรณ์",
            'data' => ['suspended' => true, 'revoked_sessions' => $revoked]]);
    }

    public function unsuspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', $user);
        $this->users->setSuspended($request->user(), $user, false);

        return response()->json(['success' => true,
            'message' => 'คืนสิทธิ์การใช้งานบัญชีนี้แล้ว เจ้าของบัญชีเข้าสู่ระบบด้วยรหัสผ่านเดิมได้ตามปกติ',
            'data' => ['suspended' => false]]);
    }

    public function revokeSessions(Request $request, User $user): JsonResponse
    {
        $this->authorize('revokeTokens', $user);
        $revoked = $this->users->revokeTokens($request->user(), $user);

        return response()->json(['success' => true,
            'message' => "ออกจากระบบให้บัญชีนี้แล้ว {$revoked} อุปกรณ์",
            'data' => ['revoked_sessions' => $revoked]]);
    }
}
