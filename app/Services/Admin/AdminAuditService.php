<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;

/**
 * Records administrative actions.
 *
 * Milestone 08. The log answers "who changed this, and to what" for content, tax sources and
 * rule versions. It records nothing else: no credentials, no tokens, and no member tax figures,
 * which belong to the member and have no place in an administrative trail.
 */
class AdminAuditService
{
    public const CONTENT_CREATED = 'CONTENT_CREATED';

    public const CONTENT_UPDATED = 'CONTENT_UPDATED';

    public const CONTENT_PUBLISHED = 'CONTENT_PUBLISHED';

    public const CONTENT_UNPUBLISHED = 'CONTENT_UNPUBLISHED';

    public const CONTENT_ARCHIVED = 'CONTENT_ARCHIVED';

    public const CONTENT_DELETED = 'CONTENT_DELETED';

    public const RULE_VERSION_CREATED = 'RULE_VERSION_CREATED';

    public const RULE_VERSION_CLONED = 'RULE_VERSION_CLONED';

    public const RULE_VERSION_VALIDATED = 'RULE_VERSION_VALIDATED';

    public const RULE_VERSION_PUBLISHED = 'RULE_VERSION_PUBLISHED';

    public const RULE_VERSION_ARCHIVED = 'RULE_VERSION_ARCHIVED';

    public const TAX_RULE_CREATED = 'TAX_RULE_CREATED';

    public const TAX_RULE_UPDATED = 'TAX_RULE_UPDATED';

    public const TAX_RULE_DELETED = 'TAX_RULE_DELETED';

    public const TAX_SOURCE_CREATED = 'TAX_SOURCE_CREATED';

    public const TAX_SOURCE_UPDATED = 'TAX_SOURCE_UPDATED';

    public const TAX_SOURCE_ARCHIVED = 'TAX_SOURCE_ARCHIVED';

    /**
     * Milestone 09.1 — opening a tax year is an administrative act in its own right.
     *
     * A year is the container every rule version, form and saved return hangs from, so creating
     * or retiring one is at least as consequential as editing a rule, and is recorded the same way.
     */
    public const TAX_YEAR_CREATED = 'TAX_YEAR_CREATED';

    public const TAX_YEAR_UPDATED = 'TAX_YEAR_UPDATED';

    public const TAX_YEAR_DEACTIVATED = 'TAX_YEAR_DEACTIVATED';

    /**
     * Phase 2 — account administration.
     *
     * Granting administrator rights is the most consequential single action in the product, and
     * ending someone's sessions is done in response to a suspected compromise. Both need to be
     * answerable later: who did it, to whom, and when.
     */
    public const USER_ROLE_CHANGED = 'USER_ROLE_CHANGED';

    public const USER_TOKENS_REVOKED = 'USER_TOKENS_REVOKED';

    /** Suspension is reversible, so both directions are recorded. */
    public const USER_SUSPENDED = 'USER_SUSPENDED';

    public const USER_UNSUSPENDED = 'USER_UNSUSPENDED';

    /** Never written to the log, whatever an entity happens to carry. */
    private const REDACTED = ['password', 'password_confirmation', 'remember_token', 'token',
        'api_token', 'input_snapshot', 'result_snapshot', 'calculation_trace'];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?User $actor, string $action, string $entityType, ?int $entityId,
        string $summary, ?array $before = null, ?array $after = null): AdminAuditLog
    {
        return AdminAuditLog::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => mb_substr($summary, 0, 500),
            'before_json' => $before === null ? null : $this->scrub($before),
            'after_json' => $after === null ? null : $this->scrub($after),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function scrub(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::REDACTED));
    }
}
