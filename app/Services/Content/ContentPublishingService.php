<?php

namespace App\Services\Content;

use App\Models\ContentPost;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The content publication workflow: draft → published → unpublished / archived.
 *
 * Milestone 08. These transitions exist as their own actions precisely so that a generic
 * update request can never set `status` or `published_at`. Scheduling needs no job runner: a
 * future `published_at` simply keeps the row outside `publiclyVisible()` until that moment.
 */
class ContentPublishingService
{
    public function __construct(private AdminAuditService $audit) {}

    public function publish(User $actor, ContentPost $post, ?string $publishAt = null): ContentPost
    {
        return DB::transaction(function () use ($actor, $post, $publishAt): ContentPost {
            $when = $publishAt === null ? now() : Carbon::parse($publishAt);
            if ($post->status === 'archived') {
                throw ValidationException::withMessages([
                    'status' => 'CONTENT_ARCHIVED_NOT_PUBLISHABLE: restore the content to draft before publishing it.',
                ]);
            }
            $before = $post->only(['status', 'published_at']);
            $post->status = 'published';
            $post->published_at = $when;
            $post->published_by = $actor->id;
            // Set once, and never again: this is what freezes the slug.
            $post->first_published_at ??= $when;
            $post->save();
            $this->audit->record($actor, AdminAuditService::CONTENT_PUBLISHED, 'content_post', $post->id,
                'Published content "'.$post->title.'" at '.$when->toIso8601String(),
                $before, $post->only(['status', 'published_at']));

            return $post->fresh(['category', 'tags', 'taxYear']);
        });
    }

    public function unpublish(User $actor, ContentPost $post): ContentPost
    {
        return $this->transition($actor, $post, 'draft', AdminAuditService::CONTENT_UNPUBLISHED,
            'Unpublished content "'.$post->title.'"');
    }

    public function archive(User $actor, ContentPost $post): ContentPost
    {
        return $this->transition($actor, $post, 'archived', AdminAuditService::CONTENT_ARCHIVED,
            'Archived content "'.$post->title.'"');
    }

    private function transition(User $actor, ContentPost $post, string $status, string $action, string $summary): ContentPost
    {
        return DB::transaction(function () use ($actor, $post, $status, $action, $summary): ContentPost {
            $before = $post->only(['status', 'published_at']);
            $post->status = $status;
            // The publication date is cleared so the post leaves the public query immediately;
            // first_published_at is untouched, so the slug stays frozen.
            $post->published_at = null;
            $post->save();
            $this->audit->record($actor, $action, 'content_post', $post->id, $summary,
                $before, $post->only(['status', 'published_at']));

            return $post->fresh(['category', 'tags', 'taxYear']);
        });
    }
}
