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
        Schema::table('tax_return_dependents', function (Blueprint $table): void {
            $table->string('disabled_person_relationship', 30)->nullable()->after('relation_type');
            $table->index(
                ['tax_return_id', 'relation_type', 'disabled_person_relationship'],
                'tax_return_disabled_relationship_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_return_dependents', function (Blueprint $table): void {
            $table->dropIndex('tax_return_disabled_relationship_index');
            $table->dropColumn('disabled_person_relationship');
        });
    }
};
