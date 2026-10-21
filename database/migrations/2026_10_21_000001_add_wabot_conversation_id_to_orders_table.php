<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('wabot_conversation_id', 128)->nullable()->after('source_reference')->index();
            $table->string('wabot_active_booking_key', 64)->nullable()->unique()->after('wabot_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['wabot_active_booking_key']);
            $table->dropIndex(['wabot_conversation_id']);
            $table->dropColumn(['wabot_conversation_id', 'wabot_active_booking_key']);
        });
    }
};
