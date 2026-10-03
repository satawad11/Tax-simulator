<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentTag extends Model
{
    protected $table = 'content_tags';

    protected $fillable = ['name', 'slug', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function postMappings(): HasMany
    {
        return $this->hasMany(ContentPostTag::class, 'content_tag_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(ContentPost::class, 'content_post_tags', 'content_tag_id', 'content_post_id')->using(ContentPostTag::class)->withPivot('id')->withTimestamps();
    }
}
