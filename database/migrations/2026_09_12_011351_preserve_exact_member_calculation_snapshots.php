<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = ['gross_income', 'exempt_income', 'total_expense', 'income_after_expense', 'total_allowance',
        'total_donation', 'net_income', 'calculated_tax', 'credits', 'withholding', 'final_amount', 'tax_credit', 'withholding_tax', 'prepaid_tax', 'final_tax'];

    public function up(): void
    {
        Schema::table('tax_calculations', function (Blueprint $table): void {
            $table->json('result_snapshot')->nullable();
            foreach ($this->columns as $column) {
                $table->decimal($column, 15, 2)->nullable()->change();
            }
        });
        Schema::table('tax_calculation_brackets', function (Blueprint $table): void {
            $table->json('result_snapshot')->nullable();
            $table->decimal('taxable_amount', 15, 2)->nullable()->change();
            $table->decimal('tax_amount', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse to discard exact results that cannot fit the old schema.
        foreach ($this->columns as $column) {
            if (DB::table('tax_calculations')->whereNull($column)->exists()) {
                throw new RuntimeException('Rollback would lose exact calculation values. Export/resolve these snapshots first.');
            }
        }
        if (DB::table('tax_calculation_brackets')->whereNull('taxable_amount')->orWhereNull('tax_amount')->exists()) {
            throw new RuntimeException('Rollback would lose exact bracket values. Export/resolve these snapshots first.');
        }
        Schema::table('tax_calculations', function (Blueprint $table): void {
            $table->dropColumn('result_snapshot');
            foreach ($this->columns as $column) {
                $definition = $table->decimal($column, 15, 2)->nullable(false);
                if (in_array($column, ['tax_credit', 'withholding_tax', 'prepaid_tax', 'final_tax'], true)) {
                    $definition->default('0.00');
                }
                $definition->change();
            }
        });
        Schema::table('tax_calculation_brackets', function (Blueprint $table): void {
            $table->dropColumn('result_snapshot');
            $table->decimal('taxable_amount', 15, 2)->nullable(false)->change();
            $table->decimal('tax_amount', 15, 2)->nullable(false)->change();
        });
    }
};
