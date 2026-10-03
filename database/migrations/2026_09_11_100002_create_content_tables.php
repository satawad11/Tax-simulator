<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('name', 255);
            $table->string('slug', 191);
            $table->text('description')->nullable();
            $table->unique(['slug']);
        });

        Schema::create('content_tags', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('name', 255);
            $table->string('slug', 191);
            $table->unique(['slug']);
        });

        Schema::create('content_posts', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('content_category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['article', 'news']);
            $table->string('title', 255);
            $table->string('slug', 191);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unique(['slug']);
            $table->index(['status', 'type', 'published_at']);
        });

        Schema::create('content_post_tags', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('content_post_id')->constrained('content_posts')->cascadeOnDelete();
            $table->foreignId('content_tag_id')->constrained('content_tags')->cascadeOnDelete();
            $table->unique(['content_post_id', 'content_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_post_tags');
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_tags');
        Schema::dropIfExists('content_categories');
    }
};
