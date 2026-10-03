<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refuse ambiguous existing rules before MySQL's nontransactional DDL.
        foreach (['income_rules' => 'income_type_id', 'allowance_rules' => 'allowance_type_id'] as $table => $type) {
            if (DB::table($table)->select('rule_version_id', $type)->groupBy('rule_version_id', $type)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Reconciliation requires duplicate '.$table.' rows to be reviewed; no records were deleted.');
            }
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('member');
        });

        Schema::table('tax_years', function (Blueprint $table): void {
            $table->date('filing_start_date')->nullable();
            $table->date('filing_end_date')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('tax_forms', function (Blueprint $table): void {
            $table->boolean('active')->default(true);
        });

        Schema::table('tax_rule_versions', function (Blueprint $table): void {
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
        });

        Schema::table('tax_brackets', function (Blueprint $table): void {
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->unsignedInteger('sort_order')->nullable();
        });

        Schema::table('income_types', function (Blueprint $table): void {
            $table->string('section_code', 20)->nullable();
        });

        Schema::table('income_rules', function (Blueprint $table): void {
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
        });

        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->string('method', 50)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('donation_rules', function (Blueprint $table): void {
            $table->string('name', 255)->nullable();
            $table->decimal('max_percentage', 7, 4)->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('recommendation_rules', function (Blueprint $table): void {
            $table->string('type', 50)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('message_template')->nullable();
            $table->string('action_type', 50)->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::table('tax_returns', function (Blueprint $table): void {
            $table->string('name', 255)->nullable();
            $table->unsignedSmallInteger('current_step')->default(1);
        });

        Schema::table('tax_return_profiles', function (Blueprint $table): void {
            $table->string('filing_status', 50)->nullable();
            $table->json('extra_data')->nullable();
        });

        Schema::table('tax_return_spouses', function (Blueprint $table): void {
            $table->string('filing_status', 50)->nullable();
            $table->json('extra_data')->nullable();
        });

        Schema::table('tax_return_dependents', function (Blueprint $table): void {
            $table->string('relation_type', 50)->nullable();
            $table->boolean('eligible')->default(false);
            $table->decimal('allowance_amount', 15, 2)->default('0.00');
            $table->json('metadata')->nullable();
        });

        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->string('description', 255)->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->string('expense_method', 50)->nullable();
            $table->decimal('calculated_expense', 15, 2)->default('0.00');
            $table->decimal('net_amount', 15, 2)->default('0.00');
            $table->json('metadata')->nullable();
        });

        Schema::table('tax_return_allowances', function (Blueprint $table): void {
            $table->decimal('input_amount', 15, 2)->default('0.00');
            $table->decimal('eligible_amount', 15, 2)->default('0.00');
            $table->json('metadata')->nullable();
        });

        Schema::table('tax_return_donations', function (Blueprint $table): void {
            $table->string('donation_code', 50)->nullable();
            $table->decimal('input_amount', 15, 2)->default('0.00');
            $table->decimal('eligible_amount', 15, 2)->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('tax_return_withholdings', function (Blueprint $table): void {
            $table->string('type', 50)->nullable();
            $table->string('payer_name', 255)->nullable();
            $table->string('payer_tax_id', 30)->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('tax_calculations', function (Blueprint $table): void {
            $table->decimal('tax_credit', 15, 2)->default('0.00');
            $table->decimal('withholding_tax', 15, 2)->default('0.00');
            $table->decimal('prepaid_tax', 15, 2)->default('0.00');
            $table->decimal('final_tax', 15, 2)->default('0.00');
            $table->json('calculation_trace')->nullable();
        });

        Schema::table('tax_calculation_brackets', function (Blueprint $table): void {
            $table->decimal('from_amount', 15, 2)->nullable();
            $table->decimal('to_amount', 15, 2)->nullable();
            $table->unsignedInteger('sort_order')->nullable();
        });

        Schema::table('tax_scenarios', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_tax_return_id')->nullable()->constrained('tax_returns')->restrictOnDelete();
            $table->foreignId('tax_year_id')->nullable()->constrained('tax_years')->restrictOnDelete();
            $table->foreignId('rule_version_id')->nullable()->constrained('tax_rule_versions')->restrictOnDelete();
            $table->json('payload')->nullable();
            $table->json('calculation_result')->nullable();
        });

        Schema::table('content_posts', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->string('cover_image', 500)->nullable();
            $table->string('source_name', 255)->nullable();
            $table->string('source_url', 1000)->nullable();
        });

        Schema::table('tax_rule_versions', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
            $table->index('status', 'reconciled_rule_status');
        });
        Schema::table('recommendation_rules', function (Blueprint $table): void {
            $table->string('priority', 20)->default('low')->change();
        });
        Schema::table('allowance_types', function (Blueprint $table): void {
            $table->string('category', 100)->change();
        });
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->string('slug', 255)->change();
        });
        Schema::table('tax_brackets', function (Blueprint $table): void {
            $table->index(['rule_version_id', 'sort_order'], 'reconciled_bracket_order');
        });
        Schema::table('income_rules', function (Blueprint $table): void {
            $table->unique(['rule_version_id', 'income_type_id'], 'reconciled_income_rule_type');
        });
        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->unique(['rule_version_id', 'allowance_type_id'], 'reconciled_allowance_rule_type');
        });
        Schema::table('tax_returns', function (Blueprint $table): void {
            $table->index(['user_id', 'tax_year_id'], 'reconciled_return_user_year');
            $table->index(['tax_year_id', 'tax_form_id'], 'reconciled_return_year_form');
        });

        // Copy representations only; do not recalculate or update original rule values/timestamps.
        DB::table('tax_years')->update(['active' => DB::raw('is_active')]);
        DB::table('tax_forms')->update(['active' => DB::raw('is_active')]);
        DB::table('tax_brackets')->update(['min_amount' => DB::raw('lower_bound'), 'max_amount' => DB::raw('upper_bound'), 'sort_order' => DB::raw('position')]);
        DB::table('income_rules')->update(['metadata' => DB::raw('conditions')]);
        DB::table('expense_rules')->update(['maximum_amount' => DB::raw('limit_amount')]);
        DB::table('allowance_rules')->update(['maximum_amount' => DB::raw('limit_amount')]);
        DB::table('donation_rules')->update(['max_percentage' => DB::raw('cap_percentage')]);
        DB::table('recommendation_rules')->update(['type' => DB::raw('category'), 'message_template' => DB::raw('message')]);
        DB::table('tax_returns')->update(['name' => DB::raw('title')]);
        DB::table('tax_return_profiles')->update(['extra_data' => DB::raw('details')]);
        DB::table('tax_return_spouses')->update(['extra_data' => DB::raw('details')]);
        DB::table('tax_return_dependents')->update(['metadata' => DB::raw('details')]);
        DB::table('tax_return_incomes')->update(['gross_amount' => DB::raw('amount'), 'metadata' => DB::raw('details')]);
        DB::table('tax_return_allowances')->update(['input_amount' => DB::raw('amount'), 'metadata' => DB::raw('details')]);
        DB::table('tax_return_donations')->update(['input_amount' => DB::raw('amount'), 'metadata' => DB::raw('details')]);
        DB::table('tax_return_withholdings')->update(['type' => DB::raw('credit_type')]);
        DB::table('tax_calculations')->update(['tax_credit' => DB::raw('credits'), 'withholding_tax' => DB::raw('withholding'), 'final_tax' => DB::raw('final_amount'), 'calculation_trace' => DB::raw('trace')]);
        DB::table('tax_calculation_brackets')->update(['from_amount' => DB::raw('lower_bound'), 'to_amount' => DB::raw('upper_bound'), 'sort_order' => DB::raw('position')]);
        DB::table('tax_scenarios')->update(['source_tax_return_id' => DB::raw('tax_return_id'), 'payload' => DB::raw('input_overrides')]);
        DB::table('content_posts')->update(['category_id' => DB::raw('content_category_id')]);
        foreach (DB::table('income_types')->select('id', 'code')->get() as $type) {
            if (preg_match('/^SECTION_(\\d+)_(\\d+)$/', $type->code, $parts)) {
                DB::table('income_types')->where('id', $type->id)->update(['section_code' => $parts[1].'('.$parts[2].')']);
            }
        }
        DB::table('tax_return_dependents')->whereIn('relationship', ['child', 'disabled_person'])
            ->update(['relation_type' => DB::raw('relationship')]);
        foreach (DB::table('tax_scenarios')->select('id', 'tax_return_id')->get() as $scenario) {
            $source = DB::table('tax_returns')->where('id', $scenario->tax_return_id)->first();
            DB::table('tax_scenarios')->where('id', $scenario->id)->update([
                'user_id' => $source->user_id, 'tax_year_id' => $source->tax_year_id, 'rule_version_id' => $source->rule_version_id,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('tax_rule_versions')->whereNotIn('status', ['draft', 'published'])->exists()
            || DB::table('recommendation_rules')->exists()
            || DB::table('allowance_types')->whereRaw('LENGTH(category) > 60')->exists()
            || DB::table('content_posts')->whereRaw('LENGTH(slug) > 191')->exists()) {
            throw new RuntimeException('Rollback would narrow status/priority values. Review existing data first.');
        }
        Schema::table('tax_returns', function (Blueprint $table): void {
            $table->dropIndex('reconciled_return_user_year');
            $table->dropIndex('reconciled_return_year_form');
        });
        Schema::table('income_rules', fn (Blueprint $table) => $table->dropUnique('reconciled_income_rule_type'));
        Schema::table('allowance_rules', fn (Blueprint $table) => $table->dropUnique('reconciled_allowance_rule_type'));
        Schema::table('tax_brackets', fn (Blueprint $table) => $table->dropIndex('reconciled_bracket_order'));
        Schema::table('tax_rule_versions', function (Blueprint $table): void {
            $table->dropIndex('reconciled_rule_status');
            $table->enum('status', ['draft', 'published'])->default('draft')->change();
        });
        Schema::table('recommendation_rules', fn (Blueprint $table) => $table->unsignedSmallInteger('priority')->default(0)->change());
        Schema::table('allowance_types', fn (Blueprint $table) => $table->string('category', 60)->change());
        Schema::table('content_posts', fn (Blueprint $table) => $table->string('slug', 191)->change());
        Schema::table('content_posts', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'cover_image', 'source_name', 'source_url']);
        });
        Schema::table('tax_scenarios', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['source_tax_return_id']);
            $table->dropForeign(['tax_year_id']);
            $table->dropForeign(['rule_version_id']);
            $table->dropColumn(['user_id', 'source_tax_return_id', 'tax_year_id', 'rule_version_id', 'payload', 'calculation_result']);
        });
        Schema::table('tax_calculation_brackets', function (Blueprint $table): void {

            $table->dropColumn(['from_amount', 'to_amount', 'sort_order']);
        });
        Schema::table('tax_calculations', function (Blueprint $table): void {

            $table->dropColumn(['tax_credit', 'withholding_tax', 'prepaid_tax', 'final_tax', 'calculation_trace']);
        });
        Schema::table('tax_return_withholdings', function (Blueprint $table): void {

            $table->dropColumn(['type', 'payer_name', 'payer_tax_id', 'metadata']);
        });
        Schema::table('tax_return_donations', function (Blueprint $table): void {

            $table->dropColumn(['donation_code', 'input_amount', 'eligible_amount', 'metadata']);
        });
        Schema::table('tax_return_allowances', function (Blueprint $table): void {

            $table->dropColumn(['input_amount', 'eligible_amount', 'metadata']);
        });
        Schema::table('tax_return_incomes', function (Blueprint $table): void {

            $table->dropColumn(['description', 'gross_amount', 'expense_method', 'calculated_expense', 'net_amount', 'metadata']);
        });
        Schema::table('tax_return_dependents', function (Blueprint $table): void {

            $table->dropColumn(['relation_type', 'eligible', 'allowance_amount', 'metadata']);
        });
        Schema::table('tax_return_spouses', function (Blueprint $table): void {

            $table->dropColumn(['filing_status', 'extra_data']);
        });
        Schema::table('tax_return_profiles', function (Blueprint $table): void {

            $table->dropColumn(['filing_status', 'extra_data']);
        });
        Schema::table('tax_returns', function (Blueprint $table): void {

            $table->dropColumn(['name', 'current_step']);
        });
        Schema::table('recommendation_rules', function (Blueprint $table): void {

            $table->dropColumn(['type', 'title', 'message_template', 'action_type', 'active']);
        });
        Schema::table('donation_rules', function (Blueprint $table): void {

            $table->dropColumn(['name', 'max_percentage', 'active']);
        });
        Schema::table('allowance_rules', function (Blueprint $table): void {

            $table->dropColumn(['method', 'fixed_amount', 'maximum_amount', 'minimum_amount', 'active']);
        });
        Schema::table('expense_rules', function (Blueprint $table): void {

            $table->dropColumn(['maximum_amount', 'minimum_amount', 'active']);
        });
        Schema::table('income_rules', function (Blueprint $table): void {

            $table->dropColumn(['active', 'metadata']);
        });
        Schema::table('income_types', function (Blueprint $table): void {

            $table->dropColumn(['section_code']);
        });
        Schema::table('tax_brackets', function (Blueprint $table): void {

            $table->dropColumn(['min_amount', 'max_amount', 'sort_order']);
        });
        Schema::table('tax_rule_versions', function (Blueprint $table): void {

            $table->dropColumn(['effective_from', 'effective_to']);
        });
        Schema::table('tax_forms', function (Blueprint $table): void {

            $table->dropColumn(['active']);
        });
        Schema::table('tax_years', function (Blueprint $table): void {

            $table->dropColumn(['filing_start_date', 'filing_end_date', 'active']);
        });
        Schema::table('users', function (Blueprint $table): void {

            $table->dropColumn(['role']);
        });
    }
};
