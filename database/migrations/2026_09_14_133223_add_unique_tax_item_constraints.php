<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tax_return_allowances', function (Blueprint $table): void {
            $table->unique(['tax_return_id', 'allowance_type_id'], 'tax_return_allowance_unique_item');
        });
        Schema::table('tax_return_donations', function (Blueprint $table): void {
            $table->unique(['tax_return_id', 'donation_code'], 'tax_return_donation_unique_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_return_allowances', function (Blueprint $table): void {
            $table->dropUnique('tax_return_allowance_unique_item');
        });
        Schema::table('tax_return_donations', function (Blueprint $table): void {
            $table->dropUnique('tax_return_donation_unique_item');
        });
    }
};
