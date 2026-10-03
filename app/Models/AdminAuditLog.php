<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An administrative action, recorded for auditability.
 *
 * Milestone 08. This log covers content, tax sources and rule versions only. It never records
 * credentials, tokens or member tax payloads — a member's figures are their own and have no
 * business in an administrative trail.
 */
class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    public const UPDATED_AT = null;

    protected $fillable = ['actor_user_id', 'action', 'entity_type', 'entity_id',
        'summary', 'before_json', 'after_json'];

    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
