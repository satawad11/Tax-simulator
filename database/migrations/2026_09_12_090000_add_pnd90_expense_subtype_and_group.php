<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.1 — represent the PND90 form's expense structure.
 *
 * ภ.ง.ด.90 states different expense treatments for subcategories *within* one Section 40
 * income type (for example ข้อ 5 gives การประกอบโรคศิลปะ 60% but อื่นๆ 30%), and it applies a
 * single shared expense deduction across *two* income types (ข้อ 1 combines 40(1) and 40(2)
 * before one 50% / 100,000 deduction).
 *
 * Neither distinction can be represented by the existing schema:
 *   - `conditions` JSON is rejected by ExpenseRuleResolver by design, and a subtype is a
 *     lookup key rather than a condition to evaluate — hiding it in JSON would make the rule
 *     neither indexable nor auditable;
 *   - there is no way to express that two income types share one deduction and one cap.
 *
 * Both columns are nullable, so every existing rule and saved income row keeps its current
 * meaning: a NULL subtype means "the rule covers the whole income type", and a NULL group
 * means "this income type is deducted on its own".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->string('income_subtype', 50)->nullable()->after('income_type_id');
            $table->string('expense_group', 50)->nullable()->after('income_subtype');
            // Non-null subtypes are unique per income type. MySQL and SQLite both treat NULL
            // as distinct, so the single-rule-per-type invariant stays enforced in
            // ExpenseRuleResolver, which fails closed on more than one match.
            $table->unique(['rule_version_id', 'income_type_id', 'income_subtype'], 'expense_rule_subtype_unique');
        });

        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->string('income_subtype', 50)->nullable()->after('income_type_id');
            $table->string('expense_method_selection', 20)->nullable()->after('income_subtype');
        });
    }

    public function down(): void
    {
        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->dropColumn(['income_subtype', 'expense_method_selection']);
        });

        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->dropUnique('expense_rule_subtype_unique');
            $table->dropColumn(['income_subtype', 'expense_group']);
        });
    }
};
