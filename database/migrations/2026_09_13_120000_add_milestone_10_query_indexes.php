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
        if (Schema::hasIndex('admin_audit_logs', ['actor_user_id', 'created_at'])) {
            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->index(['actor_user_id', 'created_at'], 'admin_audit_actor_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('admin_audit_logs', ['actor_user_id', 'created_at'])) {
            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('admin_audit_actor_created_index');
        });
    }
};
