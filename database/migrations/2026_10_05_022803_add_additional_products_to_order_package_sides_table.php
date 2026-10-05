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
        Schema::table('order_package_sides', function (Blueprint $table) {
            $table->json('additional_products')->nullable()->after('side');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_package_sides', function (Blueprint $table) {
            $table->dropColumn('additional_products');
        });
    }
};
