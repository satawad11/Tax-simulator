<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.4 — make two allowance semantics the schema could not previously express.
 *
 * 1. "percentage of what?"
 *    `allowance_rules.percentage` existed with no way to name its base, so every
 *    percentage rule the 2568 filing instructions print had to be left unseeded. The
 *    booklet always names the base in words, e.g. ใบแนบ item 10.4:
 *
 *      "ในอัตราไม่เกินร้อยละ 30 ของเงินได้พึงประเมินที่ได้รับซึ่งต้องเสียภาษีเงินได้ในปีภาษีนั้น"
 *
 *    A first-class column keeps that auditable; burying it in the conditions JSON would
 *    hide the single most important fact about the rule.
 *
 * 2. "capped together with what?"
 *    Several lines share one ceiling across allowance codes, e.g. ใบแนบ item 7.4:
 *
 *      "เบี้ยประกันสุขภาพ … ไม่เกิน 25,000 บาท ซึ่งเมื่อรวมกับค่าลดหย่อนตามมาตรา 47 (1) (ง)
 *       … ต้องไม่เกิน 100,000 บาท"
 *
 *    No single rule row can enforce a ceiling that spans rows, so the group is its own
 *    record with its own membership and its own source reference.
 *
 * Both additions are nullable/additive: every rule seeded before M7.4 keeps its meaning,
 * and a rule version with no cap groups behaves exactly as it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->string('percentage_base', 50)->nullable()->after('percentage');
        });

        Schema::create('allowance_cap_groups', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->foreignId('rule_version_id')->constrained('tax_rule_versions')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name', 255);
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('percentage_base', 50)->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_reference', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['rule_version_id', 'code']);
        });

        Schema::create('allowance_cap_group_members', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('allowance_cap_group_id')->constrained('allowance_cap_groups')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['allowance_cap_group_id', 'allowance_type_id'], 'allowance_cap_group_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowance_cap_group_members');
        Schema::dropIfExists('allowance_cap_groups');
        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->dropColumn('percentage_base');
        });
    }
};
