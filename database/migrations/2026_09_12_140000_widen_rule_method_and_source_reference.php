<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 07.4 — room for the rule shapes and citations M7.4 introduces.
 *
 * `method` gains `tiered_or_actual`, the shape ตารางที่ 2 row (1) is printed in: two rate bands
 * plus the form's `จริง` checkbox. Nothing else in the vocabulary changes, so every rule seeded
 * before M7.4 keeps its method and its meaning.
 *
 * `source_reference` moves from 255 to 500 characters. The M7.1/M7.2 citations quoted a form
 * line; an M7.4 citation quotes a sentence of the filing instructions, which is longer, and a
 * truncated citation is a citation that cannot be checked.
 */
return new class extends Migration
{
    private const METHODS = ['fixed', 'percentage', 'percentage_limit', 'actual', 'percentage_or_actual', 'tiered_or_actual', 'custom'];

    private const PREVIOUS_METHODS = ['fixed', 'percentage', 'percentage_limit', 'actual', 'percentage_or_actual', 'custom'];

    public function up(): void
    {
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->enum('method', self::METHODS)->nullable()->change();
            $table->string('source_reference', 500)->nullable()->change();
        });
        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->string('source_reference', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('allowance_rules', function (Blueprint $table): void {
            $table->string('source_reference', 255)->nullable()->change();
        });
        Schema::table('expense_rules', function (Blueprint $table): void {
            $table->enum('method', self::PREVIOUS_METHODS)->nullable()->change();
            $table->string('source_reference', 255)->nullable()->change();
        });
    }
};
