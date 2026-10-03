<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_returns', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->unsignedBigInteger('tax_form_id');
            $table->foreign(['tax_form_id', 'tax_year_id'])->references(['id', 'tax_year_id'])->on('tax_forms')->restrictOnDelete();
            $table->string('title', 255)->nullable();
            $table->enum('status', ['draft', 'completed', 'archived'])->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->unique(['id', 'rule_version_id']);
            $table->index(['user_id', 'status', 'updated_at']);
        });

        Schema::create('tax_return_profiles', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->json('details')->nullable();
            $table->unique(['tax_return_id']);
        });

        Schema::create('tax_return_spouses', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->boolean('has_income')->nullable();
            $table->json('details')->nullable();
            $table->unique(['tax_return_id']);
        });

        Schema::create('tax_return_dependents', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->enum('relationship', ['child', 'parent', 'disabled_person', 'other'])->nullable();
            $table->string('label', 255)->nullable();
            $table->date('birth_date')->nullable();
            $table->json('details')->nullable();
            $table->index(['tax_return_id', 'relationship']);
        });

        Schema::create('tax_return_incomes', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->foreignId('income_type_id')->constrained('income_types')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('exempt_amount', 15, 2)->nullable();
            $table->decimal('actual_expense', 15, 2)->nullable();
            $table->json('details')->nullable();
            $table->index(['tax_return_id', 'income_type_id']);
        });

        Schema::create('tax_return_allowances', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->json('details')->nullable();
            $table->index(['tax_return_id', 'allowance_type_id']);
        });

        Schema::create('tax_return_donations', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->enum('donation_type', ['special', 'general'])->nullable();
            $table->decimal('amount', 15, 2);
            $table->json('details')->nullable();
        });

        Schema::create('tax_return_withholdings', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->enum('credit_type', ['withholding', 'foreign_tax_credit', 'pnd93', 'pnd94', 'other_credit'])->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->index(['tax_return_id', 'credit_type']);
        });

        Schema::create('tax_calculations', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('tax_return_id');
            $table->foreignId('rule_version_id')->constrained('tax_rule_versions')->restrictOnDelete();
            $table->foreign(['tax_return_id', 'rule_version_id'])->references(['id', 'rule_version_id'])->on('tax_returns')->restrictOnDelete();
            $table->decimal('gross_income', 15, 2);
            $table->decimal('exempt_income', 15, 2);
            $table->decimal('total_expense', 15, 2);
            $table->decimal('income_after_expense', 15, 2);
            $table->decimal('total_allowance', 15, 2);
            $table->decimal('total_donation', 15, 2);
            $table->decimal('net_income', 15, 2);
            $table->decimal('calculated_tax', 15, 2);
            $table->decimal('credits', 15, 2);
            $table->decimal('withholding', 15, 2);
            $table->decimal('final_amount', 15, 2);
            $table->enum('result_status', ['PAYABLE', 'REFUND', 'ZERO']);
            $table->json('input_snapshot');
            $table->json('trace');
            $table->timestamp('calculated_at');
            $table->unique(['id', 'tax_return_id']);
            $table->index(['tax_return_id', 'calculated_at']);
        });

        Schema::create('tax_calculation_brackets', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_calculation_id')->constrained('tax_calculations')->cascadeOnDelete();
            $table->foreignId('tax_bracket_id')->constrained('tax_brackets')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('lower_bound', 15, 2);
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->decimal('rate', 7, 4);
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->unique(['tax_calculation_id', 'position']);
        });

        Schema::create('tax_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->restrictOnDelete();
            $table->unsignedBigInteger('base_calculation_id')->nullable();
            $table->foreign(['base_calculation_id', 'tax_return_id'])->references(['id', 'tax_return_id'])->on('tax_calculations')->restrictOnDelete();
            $table->string('name', 255);
            $table->json('input_overrides');
            $table->decimal('before_tax', 15, 2)->nullable();
            $table->decimal('after_tax', 15, 2)->nullable();
            $table->decimal('estimated_tax_saving', 15, 2)->nullable();
            $table->json('trace')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->index(['tax_return_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_scenarios');
        Schema::dropIfExists('tax_calculation_brackets');
        Schema::dropIfExists('tax_calculations');
        Schema::dropIfExists('tax_return_withholdings');
        Schema::dropIfExists('tax_return_donations');
        Schema::dropIfExists('tax_return_allowances');
        Schema::dropIfExists('tax_return_incomes');
        Schema::dropIfExists('tax_return_dependents');
        Schema::dropIfExists('tax_return_spouses');
        Schema::dropIfExists('tax_return_profiles');
        Schema::dropIfExists('tax_returns');
    }
};
