<?php

namespace App\Services\Content;

use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Content authoring: create, update, delete, and the one query the public API reads through.
 *
 * Milestone 08. Workflow transitions live in ContentPublishingService; this class never sets
 * `status`, `published_at`, `author_id` or `published_by` from request data.
 */
class ContentService
{
    public function __construct(private AdminAuditService $audit) {}

    /** The single definition of what the public may see, reused by every public endpoint. */
    public function publicQuery(): Builder
    {
        return ContentPost::query()->publiclyVisible()
            ->with(['category', 'taxYear', 'tags' => fn ($query) => $query->where('active', true)]);
    }

    /** @param array<string, mixed> $data */
    public function create(User $author, array $data): ContentPost
    {
        return DB::transaction(function () use ($author, $data): ContentPost {
            $tagIds = $this->tagIds($data);
            $post = new ContentPost($this->attributes($data));
            $post->author_id = $author->id;
            $post->status = 'draft';
            $post->save();
            $post->tags()->sync($tagIds);
            $this->audit->record($author, AdminAuditService::CONTENT_CREATED, 'content_post', $post->id,
                'Created draft content "'.$post->title.'"', null, $post->only(['type', 'title', 'slug', 'status']));

            return $post->fresh(['category', 'tags', 'taxYear']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, ContentPost $post, array $data): ContentPost
    {
        return DB::transaction(function () use ($actor, $post, $data): ContentPost {
            // A slug that has been public once is frozen: a shared or indexed URL must keep
            // pointing at this post, and this project has no redirect layer to make it safe.
            if (array_key_exists('slug', $data) && $data['slug'] !== $post->slug && $post->slugIsLocked()) {
                throw ValidationException::withMessages([
                    'slug' => 'CONTENT_SLUG_IMMUTABLE: the slug is fixed once the content has been published.',
                ]);
            }
            $before = $post->only(['type', 'title', 'slug', 'status', 'featured']);
            $post->fill($this->attributes($data))->save();
            if (array_key_exists('tag_ids', $data)) {
                $post->tags()->sync($this->tagIds($data));
            }
            $this->audit->record($actor, AdminAuditService::CONTENT_UPDATED, 'content_post', $post->id,
                'Updated content "'.$post->title.'"', $before, $post->only(['type', 'title', 'slug', 'status', 'featured']));

            return $post->fresh(['category', 'tags', 'taxYear']);
        });
    }

    public function delete(User $actor, ContentPost $post): void
    {
        DB::transaction(function () use ($actor, $post): void {
            // Soft delete: the row stays for audit, and the public query never sees it because
            // SoftDeletes excludes it and its status is irrelevant once trashed.
            $post->delete();
            $this->audit->record($actor, AdminAuditService::CONTENT_DELETED, 'content_post', $post->id,
                'Soft-deleted content "'.$post->title.'"', $post->only(['type', 'title', 'slug', 'status']), null);
        });
    }

    /**
     * Only the fields an admin request may set. `status`, `published_at`, `author_id` and
     * `published_by` are absent by construction, not by the model's fillable list alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        // This list, not the model's `$fillable`, is what a write actually persists. A field
        // accepted by the request but missing here is dropped in silence — which is why
        // `source_name` and `source_url` had to be added in both places, not just one.
        $attributes = array_intersect_key($data, array_flip([
            'type', 'title', 'slug', 'excerpt', 'featured', 'sort_order', 'meta_title', 'meta_description',
            'source_name', 'source_url',
        ]));
        if (array_key_exists('body', $data)) {
            $attributes['content'] = $data['body'];
        }
        if (array_key_exists('category_id', $data)) {
            $attributes['content_category_id'] = $data['category_id'];
        }
        if (array_key_exists('tax_year_id', $data)) {
            $attributes['tax_year_id'] = $data['tax_year_id'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function tagIds(array $data): array
    {
        $ids = array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])));
        if ($ids !== [] && ContentTag::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['tag_ids' => 'One or more tags do not exist.']);
        }

        return $ids;
    }
}
