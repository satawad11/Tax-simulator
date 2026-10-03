<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 08 — evidence tracking and administrative auditability.
 *
 * `tax_sources` is the registry of the repository-approved documents Milestones 4–7.5 read.
 * Until now those documents existed only as a path repeated inside each rule's
 * `source_reference` string; the registry makes them first-class so an admin can answer
 * "where did this rule come from?" without grepping.
 *
 * `tax_rule_sources` links one rule row of one rule version to one registered source, with the
 * page and section it was read from. It is a narrow mapping table rather than a general
 * polymorphic store: `rule_entity_type` is restricted to the rule tables the engine actually
 * has, and nothing outside that list can be referenced.
 *
 * `admin_audit_logs` records who changed what. It never stores credentials, tokens, or member
 * tax payloads — only administrative entities: content, tax sources and rule versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_sources', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('code', 100)->unique();
            $table->string('title', 255);
            // OFFICIAL_FORM | FILING_INSTRUCTIONS | ATTACHMENT | INTERNAL_APPROVED_REFERENCE
            $table->string('source_type', 50);
            // A path inside the repository. M8 never fetches an external URL.
            $table->string('file_path', 500)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('tax_year_id')->nullable()->constrained('tax_years')->nullOnDelete();
            $table->date('document_date')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['active', 'source_type']);
        });

        Schema::create('tax_rule_sources', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('rule_version_id')->constrained('tax_rule_versions')->cascadeOnDelete();
            $table->foreignId('tax_source_id')->constrained('tax_sources')->restrictOnDelete();
            // One of the engine's own rule tables; see TaxRuleSource::ENTITY_TYPES.
            $table->string('rule_entity_type', 50);
            $table->unsignedBigInteger('rule_entity_id');
            $table->string('page_reference', 100)->nullable();
            $table->string('section_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unique(['rule_entity_type', 'rule_entity_id', 'tax_source_id'], 'tax_rule_source_unique');
            $table->index(['rule_version_id', 'rule_entity_type']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('created_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('summary', 500);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('tax_rule_sources');
        Schema::dropIfExists('tax_sources');
    }
};
