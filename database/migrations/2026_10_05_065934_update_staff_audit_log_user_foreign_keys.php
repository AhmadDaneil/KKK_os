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
        Schema::table('staff_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->dropForeign(['target_user_id']);
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->dropForeign(['target_user_id']);
            $table->foreign('actor_user_id')->references('id')->on('users');
            $table->foreign('target_user_id')->references('id')->on('users');
        });
    }
};
