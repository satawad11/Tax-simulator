<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ContentPostTag extends Pivot
{
    protected $table = 'content_post_tags';

    public $incrementing = true;

    protected $fillable = ['content_post_id', 'content_tag_id'];

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }

    public function contentTag(): BelongsTo
    {
        return $this->belongsTo(ContentTag::class, 'content_tag_id');
    }
}
