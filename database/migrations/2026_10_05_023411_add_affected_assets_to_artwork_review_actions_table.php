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
        Schema::table('artwork_review_actions', function (Blueprint $table) {
            $table->json('affected_assets')->nullable()->after('customer_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artwork_review_actions', function (Blueprint $table) {
            $table->dropColumn('affected_assets');
        });
    }
};
