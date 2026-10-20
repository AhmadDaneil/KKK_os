<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('wabot_channel', 32)->nullable()->after('source_reference');
            $table->string('wabot_idempotency_key', 160)->nullable()->unique()->after('wabot_channel');
            $table->string('wabot_package_code', 64)->nullable()->after('wabot_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['wabot_idempotency_key']);
            $table->dropColumn(['wabot_channel', 'wabot_idempotency_key', 'wabot_package_code']);
        });
    }
};
