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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_reference', 32)->nullable()->unique()->after('order_id');
            $table->string('source', 32)->default('DIRECT')->after('customer_reference');
            $table->string('source_reference', 128)->nullable()->after('source');
            $table->timestamp('dashboard_activated_at')->nullable()->after('details_confirmed_at');

            $table->unique(['source', 'source_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_reference']);
            $table->dropUnique(['customer_reference']);
            $table->dropColumn([
                'customer_reference',
                'source',
                'source_reference',
                'dashboard_activated_at',
            ]);
        });
    }
};
