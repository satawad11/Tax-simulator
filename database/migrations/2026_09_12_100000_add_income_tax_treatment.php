<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.2 — record which taxation route a gift-income line elects.
 *
 * ภ.ง.ด.90 prints the same 42(26)(27)(28) income twice, and the taxpayer picks one:
 *
 *   ข้อ 7 item 4 — โดยเลือกนำมารวมคำนวณภาษีกับเงินได้อื่น ๆ  (joins the progressive base)
 *   ข้อ 9        — โดยเลือกเสียภาษีในอัตราร้อยละ 5            (taxed separately, added at ข้อ 11 item 19)
 *
 * The election is the taxpayer's and cannot be inferred, so it is stored per income line.
 * Nullable, so every existing row keeps the progressive default it was calculated under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->string('tax_treatment', 30)->nullable()->after('expense_method_selection');
        });
    }

    public function down(): void
    {
        Schema::table('tax_return_incomes', function (Blueprint $table): void {
            $table->dropColumn('tax_treatment');
        });
    }
};
