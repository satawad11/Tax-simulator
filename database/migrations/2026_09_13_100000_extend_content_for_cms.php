<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 08 — the fields the public content API and the admin CMS need.
 *
 * The M2 schema already carries `content_posts`, `content_categories`, `content_tags` and the
 * pivot, with a `type` of article|news and a draft|published|archived status. M8 keeps all of
 * that and adds only what the CMS actually uses:
 *
 *   type gains GUIDE and FAQ, the two further kinds the public site shows. The vocabulary stays
 *     closed — M8 deliberately does not open an arbitrary content-type system;
 *   featured / sort_order   ordering for the featured strip and the FAQ list;
 *   tax_year_id             content written about one tax year, so the simulator can link to it;
 *   meta_title / meta_description   the SEO fields the public pages render;
 *   published_by            who performed the publish, kept apart from `author_id`;
 *   first_published_at      the slug becomes immutable from this moment, so a public URL that
 *                           has been shared cannot silently start pointing somewhere else.
 *
 * Every column is nullable or defaulted, so every row written before M8 keeps its meaning.
 */
return new class extends Migration
{
    private const TYPES = ['article', 'news', 'guide', 'faq'];

    private const PREVIOUS_TYPES = ['article', 'news'];

    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->enum('type', self::TYPES)->change();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->boolean('featured')->default(false)->after('status');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('featured');
            $table->foreignId('tax_year_id')->nullable()->after('author_id')->constrained('tax_years')->nullOnDelete();
            $table->string('meta_title', 255)->nullable()->after('published_at');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->foreignId('published_by')->nullable()->after('meta_description')->constrained('users')->nullOnDelete();
            $table->timestamp('first_published_at')->nullable()->after('published_by');
            $table->index(['featured', 'status', 'published_at']);
        });

        Schema::table('content_categories', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
        });

        Schema::table('content_tags', function (Blueprint $table): void {
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('content_tags', fn (Blueprint $table) => $table->dropColumn('active'));
        Schema::table('content_categories', fn (Blueprint $table) => $table->dropColumn(['sort_order', 'active']));

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropIndex(['featured', 'status', 'published_at']);
            $table->dropConstrainedForeignId('tax_year_id');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['featured', 'sort_order', 'meta_title', 'meta_description', 'first_published_at']);
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->enum('type', self::PREVIOUS_TYPES)->change();
        });
    }
};
