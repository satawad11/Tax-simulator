<?php

namespace App\Models;

use App\Models\Concerns\HasLegacySchemaAliases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPost extends Model
{
    use HasFactory, SoftDeletes;
    use HasLegacySchemaAliases;

    protected $table = 'content_posts';

    /** The closed content vocabulary. M8 does not open an arbitrary content-type system. */
    public const TYPES = ['article', 'news', 'guide', 'faq'];

    /**
     * The Thai words for those codes, in one place.
     *
     * They were written out three times — `article-card.blade.php`, `content/show.blade.php` and
     * the console's content list — and the console's copy was the one Phase 3 found printing raw
     * `ARTICLE` codes while the other two showed บทความ. Three copies of four words is three
     * chances to disagree about what the product calls a thing.
     */
    public const TYPE_LABELS = [
        'article' => 'บทความ', 'guide' => 'คู่มือ', 'news' => 'ข่าวสาร', 'faq' => 'คำถามที่พบบ่อย',
    ];

    public const STATUS_LABELS = [
        'draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่แล้ว', 'archived' => 'จัดเก็บแล้ว',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[mb_strtolower((string) $type)] ?? (string) $type;
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[(string) $status] ?? (string) $status;
    }

    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * `status`, `published_at`, `author_id`, `published_by` and `first_published_at` are
     * deliberately absent: each is set by a workflow action or by the authenticated admin,
     * never by a request body.
     */
    /*
     * Phase 3 — `cover_image` is gone from this list.
     *
     * It was fillable but reachable from nothing: absent from `WriteContentRequest`, rendered by
     * no view, and there is no upload anywhere in the codebase — no `Storage::` call, no
     * `UploadedFile` handling — so it could only ever have held a pasted URL. A model that
     * advertises a field the product cannot set or show is a promise it does not keep. The column
     * stays (dropping it would be a migration for no gain) but nothing may write it until there
     * is an image story worth building. `source_name` and `source_url` earned their place
     * instead: they need no upload and they answer a real question on a news item.
     */
    protected $fillable = ['content_category_id', 'tax_year_id', 'type', 'title', 'slug', 'excerpt',
        'content', 'featured', 'sort_order', 'meta_title', 'meta_description', 'category_id',
        'source_name', 'source_url'];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'first_published_at' => 'immutable_datetime',
            'featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'content_category_id');
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class, 'tax_year_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function tagMappings(): HasMany
    {
        return $this->hasMany(ContentPostTag::class, 'content_post_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContentTag::class, 'content_post_tags', 'content_post_id', 'content_tag_id')->using(ContentPostTag::class)->withPivot('id')->withTimestamps();
    }

    /**
     * The one definition of "the public may see this".
     *
     * Draft and archived content is excluded, and so is a published row whose `published_at` is
     * still in the future — which is what makes an explicit future publish date behave as a
     * schedule without any job infrastructure.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** The slug is frozen from the first publish, so a shared URL keeps pointing at this post. */
    public function slugIsLocked(): bool
    {
        return $this->first_published_at !== null;
    }

    /** @return array<string, string> */
    protected function schemaAliases(): array
    {
        return ['category_id' => 'content_category_id'];
    }
}
