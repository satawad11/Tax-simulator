<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เงินได้ที่ได้รับยกเว้น หลังหักค่าใช้จ่าย — a deduction the engine had no place to put.
 *
 * Several ใบแนบ lines say the same thing in the same words: the amount is taken from
 * เงินได้พึงประเมิน **after** expenses under มาตรา 42 ทวิ ถึง มาตรา 46, not from the allowance block.
 * ใบแนบ ข้อ 13 (13.4) and ข้อ 20 (20.4) both print it. The engine went straight from expenses to
 * allowances, so there was nowhere to record such a line and the four printed deductions stayed
 * uncalculable for a reason that was structural rather than legal.
 *
 * The distinction is not cosmetic. An exemption and an allowance land on the same net income for a
 * purely progressive return, but the ภ.ง.ด.90 minimum tax is charged on assessable income excluding
 * 40 (1) rather than on net income, so which side of that line an amount falls on changes the tax
 * whenever the minimum tax binds.
 *
 * Rules live in a rule version like every other rule, so a change of law is a new version and never
 * an edit to a published one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_exemption_rules', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_year_id')->constrained('tax_years')->restrictOnDelete();
            $table->unsignedBigInteger('rule_version_id');
            $table->foreign(['rule_version_id', 'tax_year_id'])
                ->references(['id', 'tax_year_id'])->on('tax_rule_versions')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 255);
            /*
             * `percentage`     — a share of the declared amount (ข้อ 13 prints ร้อยละหนึ่งร้อย).
             * `stepped_grant`  — a fixed grant for each whole step of declared spending, which is
             *                    how ข้อ 20 prints it: 10,000 บาท ต่อทุกจำนวน 1,000,000 บาท.
             * Branching is by method, never by code, so another verified line is a seeding change.
             */
            $table->string('method', 50);
            $table->decimal('percentage', 7, 4)->nullable();
            // The size of one whole step, and what each completed step grants.
            $table->decimal('step_amount', 15, 2)->nullable();
            $table->decimal('grant_per_step', 15, 2)->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->boolean('active')->default(true);
            // The declared facts a filer must affirm before the line may be claimed, and the
            // printed conditions behind them.
            $table->json('conditions')->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->unique(['rule_version_id', 'code']);
            $table->index(['rule_version_id', 'active']);
        });

        Schema::create('tax_return_income_exemptions', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('tax_return_id')->constrained('tax_returns')->cascadeOnDelete();
            $table->string('code', 100);
            // What the filer declared, and what the rule allowed of it. Both are kept: a filer who
            // paid 2,400,000 on ข้อ 20 is granted 20,000, and the response has to be able to show
            // the reader why rather than only the smaller number.
            $table->decimal('input_amount', 15, 2);
            $table->decimal('eligible_amount', 15, 2)->nullable();
            /*
             * The filer's affirmation of the printed conditions no declared figure can establish.
             * Stored rather than assumed: the API refuses a positive amount without it, so a saved
             * return that could not say whether it was given would be unable to recalculate.
             */
            $table->boolean('declarations_confirmed')->default(false);
            $table->json('metadata')->nullable();
            $table->unique(['tax_return_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_return_income_exemptions');
        Schema::dropIfExists('income_exemption_rules');
    }
};
