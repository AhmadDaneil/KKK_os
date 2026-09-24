<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('payment_type', ['BOOKING_DEPOSIT', 'BALANCE', 'ARTWORK_CORRECTION'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('payment_transactions')->where('payment_type', 'ARTWORK_CORRECTION')->exists()) {
            throw new RuntimeException('Cannot remove correction payment type while correction receipts exist.');
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('payment_type', ['BOOKING_DEPOSIT', 'BALANCE'])->change();
        });
    }
};
