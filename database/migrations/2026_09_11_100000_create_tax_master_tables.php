<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_years', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name', 255);
            $table->boolean('is_active')->default(false);
        });

        Schema::create('tax_forms', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['tax_year_id', 'code']);
            $table->unique(['id', 'tax_year_id']);
        });

        Schema::create('tax_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->string('version', 30);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->unique(['tax_year_id', 'version']);
            $table->unique(['id', 'tax_year_id']);
            $table->index(['tax_year_id', 'status']);
        });

        Schema::create('tax_brackets', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('lower_bound', 15, 2);
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->decimal('rate', 7, 4);
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'position']);
            $table->unique(['rule_version_id', 'lower_bound']);
        });

        Schema::create('income_types', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unique(['code']);
        });

        Schema::create('tax_form_income_types', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_form_id')->constrained('tax_forms')->restrictOnDelete();
            $table->foreignId('income_type_id')->constrained('income_types')->restrictOnDelete();
            $table->unique(['tax_form_id', 'income_type_id']);
        });

        Schema::create('income_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->foreignId('income_type_id')->constrained('income_types')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 255);
            $table->decimal('exempt_amount', 15, 2)->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
        });

        Schema::create('expense_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->foreignId('income_type_id')->constrained('income_types')->restrictOnDelete();
            $table->string('code', 100);
            $table->enum('method', ['fixed', 'percentage', 'percentage_limit', 'actual', 'percentage_or_actual', 'custom'])->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->decimal('percentage', 7, 4)->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
        });

        Schema::create('allowance_types', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('code', 60);
            $table->string('name', 255);
            $table->string('category', 60);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['code']);
        });

        Schema::create('allowance_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types')->restrictOnDelete();
            $table->string('code', 100);
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->decimal('percentage', 7, 4)->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
        });

        Schema::create('donation_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->string('code', 100);
            $table->enum('donation_type', ['special', 'general'])->nullable();
            $table->decimal('multiplier', 7, 4)->nullable();
            $table->decimal('cap_percentage', 7, 4)->nullable();
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
        });

        Schema::create('recommendation_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->string('code', 100);
            $table->enum('category', ['MISSING_INFORMATION', 'POTENTIAL_ALLOWANCE', 'TAX_PLANNING', 'PAYMENT', 'REFUND'])->nullable();
            $table->text('message')->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_rules');
        Schema::dropIfExists('donation_rules');
        Schema::dropIfExists('allowance_rules');
        Schema::dropIfExists('allowance_types');
        Schema::dropIfExists('expense_rules');
        Schema::dropIfExists('income_rules');
        Schema::dropIfExists('tax_form_income_types');
        Schema::dropIfExists('income_types');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('tax_rule_versions');
        Schema::dropIfExists('tax_forms');
        Schema::dropIfExists('tax_years');
    }
};
