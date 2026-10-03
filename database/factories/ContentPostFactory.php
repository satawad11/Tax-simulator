<?php

namespace Database\Factories;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentPost> */
class ContentPostFactory extends Factory
{
    protected $model = ContentPost::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'content_category_id' => fn () => ContentCategory::create(['name' => 'Test category', 'slug' => fake()->unique()->slug()])->id,
            'title' => 'Synthetic article',
            'slug' => fake()->unique()->slug(),
            'content' => 'Synthetic test content. No tax advice.',
            'type' => 'article',
            'status' => 'draft',
        ];
    }
}
