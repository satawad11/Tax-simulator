<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.4 — the two facts ภ.ง.ด.90 ข้อ 7 needs before its expense rate is decidable,
 * and the tier structure one of those rates is printed in.
 *
 * The form prints ข้อ 7 item 1 and item 3 (2) with a blank percentage —
 * `หักค่าใช้จ่าย ☐ ร้อยละ ............ ☐ จริง` — because the rate is not a property of the
 * income category. It is a property of a further fact the taxpayer states:
 *
 *   expense_activity   ข้อ 7 item 1 — the rate comes from ตารางที่ 2 (page 17 of
 *                      วิธีการกรอกแบบ ภ.ง.ด.90), which lists 44 numbered activities. The form
 *                      leaves `(ระบุ)` for the taxpayer to name the activity.
 *   holding_years      ข้อ 7 item 3 (2) — the rate comes from the จำนวนปีที่ถือครอง table on
 *                      page 3 (92/84/77/71/65/60/55/50). The form prints a dedicated field,
 *                      `จำนวนปีที่ถือครอง ………. ปี`, for exactly this.
 *
 * Both are stored as a range on the rule so a single row can express "8 ปีขึ้นไป".
 *
 * expense_rule_tiers exists because ตารางที่ 2 row (1) is not one rate:
 *
 *   (1) การแสดงของนักแสดงละคร ภาพยนตร์ วิทยุหรือโทรทัศน์ นักร้อง นักดนตรี นักกีฬาอาชีพ …
 *       (ก) สำหรับเงินได้ส่วนที่ไม่เกิน 300,000 บาท            60
 *       (ข) สำหรับเงินได้ส่วนที่เกิน 300,000 บาท               40
 *       การหักค่าใช้จ่ายตาม (ก) และ (ข) รวมกันต้องไม่เกิน 600,000 บาท
 *
 * Flattening that to a single percentage would lose the band boundary, and flattening it to
 * the 600,000 ceiling alone would lose both rates, so the bands are stored as they are printed.
 *
 * Every column is nullable and every existing rule keeps its meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->string('expense_activity', 50)->nullable()->after('income_subtype');
            $table->unsignedSmallInteger('holding_years_min')->nullable()->after('expense_activity');
            $table->unsignedSmallInteger('holding_years_max')->nullable()->after('holding_years_min');
        });

        // M7.1 made (version, income type, subtype) unique because the subtype was the whole
        // key. It no longer is: ข้อ 7 item 1 has one rule per ตารางที่ 2 activity and item 3 (2)
        // one per holding-period band. The guarantee the index exists for — at most one rule
        // per resolvable combination — is preserved by widening it to the full key.
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->dropUnique('expense_rule_subtype_unique');
            $table->unique(['rule_version_id', 'income_type_id', 'income_subtype', 'expense_activity', 'holding_years_min'],
                'expense_rule_resolution_unique');
        });

        // The same two facts on a saved income line, so a member return states them exactly
        // once and the guest and member paths keep one payload shape.
        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->string('expense_activity', 50)->nullable()->after('income_subtype');
            $table->unsignedSmallInteger('holding_years')->nullable()->after('expense_activity');
        });

        Schema::create('expense_rule_tiers', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('expense_rule_id')->constrained('expense_rules')->cascadeOnDelete();
            $table->string('tier_code', 50);
            $table->unsignedSmallInteger('sort_order');
            // The upper bound of the band, measured on the income the rule applies to.
            // Null is the final, open-ended band.
            $table->decimal('threshold_amount', 15, 2)->nullable();
            $table->decimal('percentage', 8, 4);
            $table->string('source_reference', 500);
            $table->unique(['expense_rule_id', 'tier_code']);
            $table->unique(['expense_rule_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_rule_tiers');
        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->dropColumn(['expense_activity', 'holding_years']);
        });
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->dropUnique('expense_rule_resolution_unique');
            $table->unique(['rule_version_id', 'income_type_id', 'income_subtype'], 'expense_rule_subtype_unique');
            $table->dropColumn(['expense_activity', 'holding_years_min', 'holding_years_max']);
        });
    }
};
